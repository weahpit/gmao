<?php

namespace App\Service\Rh;

use App\Entity\Rh\DocumentRh;
use App\Entity\Rh\Employe;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stockage des documents RH et des photos des employes.
 *
 * Les fichiers sont ranges sous public/data/rh/{RH|Medical|Recrutement}/AAAA/MM
 * et public/data/rh/photos/. Le nom sur disque est toujours genere par
 * l'application ; le nom d'origine n'est conserve que pour l'affichage.
 *
 * Attention : le dossier public/ est servi par le serveur web. Les documents
 * medicaux ne doivent etre proposes que par un controleur qui verifie les
 * droits (RhAcces::peutVoirMedical) ; en production, on peut egalement
 * interdire l'acces direct au sous-dossier Medical cote serveur web.
 */
final class StockageDocumentRh
{
    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods'];

    public const TAILLE_MAX = 10 * 1024 * 1024; // 10 Mo

    public const TAILLE_PHOTO_MAX = 4 * 1024 * 1024; // 4 Mo

    /** Documents pedagogiques d'une formation (support, liste de presences). */
    public const EXTENSIONS_FORMATION = [
        'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt',
        'xls', 'xlsx', 'ods', 'csv',
        'ppt', 'pptx', 'odp',
        'jpg', 'jpeg', 'png', 'webp',
    ];

    public const TAILLE_FORMATION_MAX = 25 * 1024 * 1024; // 25 Mo

    private const DOSSIER = 'data/rh/';

    private const SOUS_DOSSIERS = [
        DocumentRh::CATEGORIE_RH => 'RH',
        DocumentRh::CATEGORIE_MEDICAL => 'Medical',
        DocumentRh::CATEGORIE_RECRUTEMENT => 'Recrutement',
    ];

    private const MIMES = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg', 'image/pjpeg', 'image/jpg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg', 'image/jpg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'doc' => ['application/msword', 'application/vnd.ms-office'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'odt' => ['application/vnd.oasis.opendocument.text'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private \App\Service\Documents\OptimiseurDocuments $optimiseurDocuments,
    ) {}

    public function dossierAbsolu(string $categorie = DocumentRh::CATEGORIE_RH, ?\DateTimeInterface $date = null): string
    {
        $date ??= new \DateTimeImmutable();
        $sousDossier = self::SOUS_DOSSIERS[$categorie] ?? 'RH';

        return rtrim($this->projectDir, '/') . '/public/' . self::DOSSIER . $sousDossier
            . '/' . $date->format('Y') . '/' . $date->format('m') . '/';
    }

    /**
     * Verifie que le dossier peut recevoir un fichier et renvoie un message
     * explicite en cas de probleme (droits, dossier temporaire, espace).
     */
    public function preparerDossier(string $categorie = DocumentRh::CATEGORIE_RH): ?string
    {
        $racine = rtrim($this->projectDir, '/') . '/public/' . self::DOSSIER;
        $dossier = $this->dossierAbsolu($categorie);

        foreach ([$racine, $dossier] as $chemin) {
            if (!is_dir($chemin) && !@mkdir($chemin, 0775, true) && !is_dir($chemin)) {
                return 'Le dossier de stockage des documents RH n\'a pas pu etre cree (' . $chemin . ').';
            }
            if (!is_writable($chemin)) {
                @chmod($chemin, 0775);
            }
        }

        $temoin = $dossier . '.temoin-' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temoin, 'ok') === false) {
            return 'Le serveur n\'a pas le droit d\'ecrire dans public/' . self::DOSSIER
                . (self::SOUS_DOSSIERS[$categorie] ?? 'RH') . '/ : accordez le droit d\'ecriture a ce dossier.';
        }
        @unlink($temoin);

        foreach ([$dossier . '.htaccess' => "<IfModule mod_mime.c>\n    RemoveHandler .php .phar .phtml\n</IfModule>\nOptions -Indexes\n", $dossier . 'index.html' => ''] as $fichier => $contenu) {
            if (!is_file($fichier)) {
                @file_put_contents($fichier, $contenu);
            }
        }

        return null;
    }

    /**
     * Enregistre le fichier depose et renseigne le document.
     *
     * @throws \RuntimeException avec un message directement affichable
     */
    public function stocker(UploadedFile $fichier, DocumentRh $document): void
    {
        $nomOriginal = (string) $fichier->getClientOriginalName();
        $extension = strtolower((string) pathinfo($nomOriginal, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: '';

        if (!in_array($extension, self::EXTENSIONS, true)) {
            throw new \RuntimeException(
                'Format de fichier non accepte (.' . $extension . '). Formats autorises : '
                . implode(', ', self::EXTENSIONS) . '.'
            );
        }

        $taille = (int) ($fichier->getSize() ?: 0);
        if ($taille > self::TAILLE_MAX) {
            throw new \RuntimeException(
                'Le document fait ' . number_format($taille / 1048576, 1, ',', ' ')
                . ' Mo : la limite est de 10 Mo. Compressez ou scindez le fichier.'
            );
        }

        if (!$fichier->isValid()) {
            throw new \RuntimeException(
                'Le fichier n\'a pas pu etre transfere (' . $fichier->getErrorMessage() . ').'
                . ' Verifiez la limite upload_max_filesize du serveur.'
            );
        }

        $mime = $this->typeMime($fichier, $extension);
        $mimesAcceptes = [];
        foreach (self::MIMES as $ext => $liste) {
            $mimesAcceptes[$ext] = $liste;
        }

        $extensionConnue = null;
        foreach ($mimesAcceptes as $ext => $liste) {
            if (in_array($mime, $liste, true)) {
                $extensionConnue = $ext;
                break;
            }
        }

        // Certains serveurs ne reconnaissent pas les formats Office : on se fie
        // alors a l'extension, deja validee dans la liste blanche.
        if ($extensionConnue === null && $mime === 'application/octet-stream') {
            $extensionConnue = $extension;
        }

        if ($extensionConnue === null) {
            throw new \RuntimeException(
                'Le fichier ne correspond pas a un format autorise (type detecte : ' . $mime . ').'
                . ' Formats autorises : ' . implode(', ', self::EXTENSIONS) . '.'
            );
        }

        $erreur = $this->preparerDossier($document->getCategorie());
        if ($erreur !== null) {
            throw new \RuntimeException($erreur);
        }

        $dossier = $this->dossierAbsolu($document->getCategorie());
        $nom = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $extensionConnue;

        try {
            $fichier->move($dossier, $nom);
            $this->optimiseurDocuments->optimiser($dossier . $nom);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Enregistrement impossible dans ' . $dossier . ' : ' . $e->getMessage(), 0, $e);
        }

        if (!is_file($dossier . $nom)) {
            throw new \RuntimeException('Le fichier n\'a pas ete retrouve apres transfert dans ' . $dossier . '.');
        }

        $sousDossier = self::SOUS_DOSSIERS[$document->getCategorie()] ?? 'RH';

        $document
            ->setChemin($sousDossier . '/' . date('Y') . '/' . date('m') . '/' . $nom)
            ->setNomOriginal($this->nettoyerNom($nomOriginal))
            ->setMime($mime)
            ->setTaille((int) (filesize($dossier . $nom) ?: $taille));
    }

    /** Chemin absolu d'un document deja stocke (null si absent ou chemin suspect). */
    public function chemin(DocumentRh $document): ?string
    {
        $relatif = (string) $document->getChemin();
        if ($relatif === '' || str_contains($relatif, '..')) {
            return null;
        }

        $chemin = rtrim($this->projectDir, '/') . '/public/' . self::DOSSIER . $relatif;

        return is_file($chemin) ? $chemin : null;
    }

    public function supprimer(DocumentRh $document): void
    {
        $chemin = $this->chemin($document);
        if ($chemin !== null) {
            @unlink($chemin);
        }
    }

    /**
     * Enregistre la photo de l'employe (JPG, PNG ou WEBP, 4 Mo maxi).
     *
     * @return string chemin relatif (dossier photos/) a stocker sur l'employe
     */
    public function stockerPhoto(UploadedFile $fichier, Employe $employe): string
    {
        $dossier = rtrim($this->projectDir, '/') . '/public/' . self::DOSSIER . 'photos/';
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            throw new \RuntimeException('Le dossier des photos n\'a pas pu etre cree (' . $dossier . ').');
        }

        $taille = (int) ($fichier->getSize() ?: 0);
        if ($taille > self::TAILLE_PHOTO_MAX) {
            throw new \RuntimeException(
                'La photo fait ' . number_format($taille / 1048576, 1, ',', ' ')
                . ' Mo : la limite est de 4 Mo.'
            );
        }

        $extension = strtolower((string) pathinfo((string) $fichier->getClientOriginalName(), PATHINFO_EXTENSION));
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        if (!in_array($extension, ['jpg', 'png', 'webp'], true)) {
            throw new \RuntimeException('La photo doit etre au format JPG, PNG ou WEBP (recu : .' . $extension . ').');
        }

        $nom = 'photo_' . ($employe->getMatricule() ?: 'employe') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

        try {
            $fichier->move($dossier, $nom);
            $this->optimiseurDocuments->optimiser($dossier . $nom);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Enregistrement de la photo impossible : ' . $e->getMessage(), 0, $e);
        }

        return 'photos/' . $nom;
    }

    public function supprimerPhoto(?string $cheminRelatif): void
    {
        if ($cheminRelatif === null || $cheminRelatif === '' || str_contains($cheminRelatif, '..')) {
            return;
        }

        $chemin = rtrim($this->projectDir, '/') . '/public/' . self::DOSSIER . $cheminRelatif;
        if (is_file($chemin)) {
            @unlink($chemin);
        }
    }

    /**
     * Enregistre un document de formation (support de presentation ou liste
     * de presences) sous public/data/rh/Formations/AAAA/MM/.
     *
     * @return array{chemin: string, nom: string} chemin relatif a data/rh/ et nom d'origine
     *
     * @throws \RuntimeException message directement affichable a l'utilisateur
     */
    public function stockerDocumentFormation(UploadedFile $fichier, string $prefixe = 'document'): array
    {
        $nomOriginal = (string) $fichier->getClientOriginalName();
        $extension = strtolower((string) pathinfo($nomOriginal, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: '';

        if (!in_array($extension, self::EXTENSIONS_FORMATION, true)) {
            throw new \RuntimeException(
                'Format de fichier non accepte (.' . $extension . '). Formats autorises : '
                . implode(', ', self::EXTENSIONS_FORMATION) . '.'
            );
        }

        if (!$fichier->isValid()) {
            throw new \RuntimeException(
                'Le fichier "' . $nomOriginal . '" n\'a pas pu etre transfere (' . $fichier->getErrorMessage() . ').'
                . ' Verifiez la limite upload_max_filesize du serveur.'
            );
        }

        $taille = (int) ($fichier->getSize() ?: 0);
        if ($taille > self::TAILLE_FORMATION_MAX) {
            throw new \RuntimeException(
                'Le fichier "' . $nomOriginal . '" fait ' . number_format($taille / 1048576, 1, ',', ' ')
                . ' Mo : la limite est de 25 Mo.'
            );
        }

        $dossierRelatif = 'Formations/' . date('Y') . '/' . date('m') . '/';
        $dossier = rtrim($this->projectDir, '/') . '/public/' . self::DOSSIER . $dossierRelatif;

        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            throw new \RuntimeException('Le dossier des documents de formation n\'a pas pu etre cree (' . $dossier . ').');
        }
        if (!is_writable($dossier)) {
            @chmod($dossier, 0775);
        }
        if (!is_writable($dossier)) {
            throw new \RuntimeException(
                'Le serveur n\'a pas le droit d\'ecrire dans public/' . self::DOSSIER . $dossierRelatif
                . ' : accordez le droit d\'ecriture a ce dossier (chmod 775).'
            );
        }

        $nom = $prefixe . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

        try {
            $fichier->move($dossier, $nom);
            $this->optimiseurDocuments->optimiser($dossier . $nom);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Enregistrement du fichier impossible : ' . $e->getMessage(), 0, $e);
        }

        if (!is_file($dossier . $nom)) {
            throw new \RuntimeException(
                'Le fichier "' . $nomOriginal . '" n\'a pas ete retrouve apres transfert dans ' . $dossier . '.'
                . ' Le dossier temporaire du serveur est peut-etre plein.'
            );
        }

        foreach ([$dossier . '.htaccess' => "<IfModule mod_mime.c>\n    RemoveHandler .php .phar .phtml\n</IfModule>\nOptions -Indexes\n", $dossier . 'index.html' => ''] as $fichierTemoin => $contenu) {
            if (!is_file($fichierTemoin)) {
                @file_put_contents($fichierTemoin, $contenu);
            }
        }

        return [
            'chemin' => $dossierRelatif . $nom,
            'nom' => $this->nettoyerNom($nomOriginal),
        ];
    }

    /** Chemin absolu d'un document de formation (null si absent ou chemin suspect). */
    public function cheminFormation(?string $relatif): ?string
    {
        if ($relatif === null || $relatif === '' || str_contains($relatif, '..')) {
            return null;
        }

        $chemin = rtrim($this->projectDir, '/') . '/public/' . self::DOSSIER . $relatif;

        return is_file($chemin) ? $chemin : null;
    }

    public function supprimerDocumentFormation(?string $relatif): void
    {
        $chemin = $this->cheminFormation($relatif);
        if ($chemin !== null) {
            @unlink($chemin);
        }
    }

    private function typeMime(UploadedFile $fichier, string $extension): string
    {
        $aliases = [
            'image/jpg' => 'image/jpeg',
            'image/pjpeg' => 'image/jpeg',
            'application/x-pdf' => 'application/pdf',
            'application/zip' => 'application/octet-stream',
        ];

        try {
            $mime = $fichier->getMimeType();
            if (is_string($mime) && $mime !== '') {
                return $aliases[$mime] ?? $mime;
            }
        } catch (\Throwable) {
            // finfo indisponible : on retombe sur l'extension.
        }

        return self::MIMES[$extension][0] ?? 'application/octet-stream';
    }

    private function nettoyerNom(string $nom): string
    {
        $nom = str_replace(['\\', '/'], '-', $nom);
        $nom = preg_replace('/[\x00-\x1F\x7F]/u', '', $nom) ?? $nom;

        return mb_substr(trim($nom) !== '' ? trim($nom) : 'document', 0, 200);
    }
}
