<?php

namespace App\Service\Visite;

use App\Entity\Visite\Visite;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stockage des pieces d'identite scannees a l'accueil.
 *
 * Les fichiers sont ranges dans public/data/visites/AAAA/MM/. Le nom sur
 * disque est toujours genere par l'application : le nom fourni par le
 * navigateur n'est conserve que pour l'affichage et le telechargement.
 */
final class StockagePieceIdentite
{
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    public const TAILLE_MAX = 8 * 1024 * 1024; // 8 Mo

    private const DOSSIER = 'data/visites/';

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private \App\Service\Documents\OptimiseurDocuments $optimiseurDocuments,
    ) {}

    /** Dossier absolu de stockage pour une date donnee. */
    public function dossierAbsolu(?\DateTimeInterface $date = null): string
    {
        $date ??= new \DateTimeImmutable();

        return rtrim($this->projectDir, '/') . '/public/' . self::DOSSIER
            . $date->format('Y') . '/' . $date->format('m') . '/';
    }

    /**
     * Verifie que le dossier peut recevoir un fichier et renvoie un message
     * explicite en cas de probleme (droits, dossier temporaire, taille).
     */
    public function preparerDossier(): ?string
    {
        $racine = rtrim($this->projectDir, '/') . '/public/' . self::DOSSIER;
        foreach ([$racine, $this->dossierAbsolu()] as $dossier) {
            if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
                return 'Le dossier de stockage des pieces d\'identite n\'a pas pu etre cree (' . $dossier . ').';
            }
            if (!is_writable($dossier)) {
                @chmod($dossier, 0775);
            }
        }

        $dossier = $this->dossierAbsolu();
        $temoin = $dossier . '.temoin-' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temoin, 'ok') === false) {
            return 'Le serveur n\'a pas le droit d\'ecrire dans public/' . self::DOSSIER
                . date('Y') . '/' . date('m') . '/ : accordez le droit d\'ecriture a ce dossier.';
        }
        @unlink($temoin);

        // Petites protections pour Apache / Nginx / IIS.
        foreach ([$dossier . '.htaccess' => "<IfModule mod_mime.c>\n    RemoveHandler .php .phar .phtml\n</IfModule>\nOptions -Indexes\n", $dossier . 'index.html' => ''] as $fichier => $contenu) {
            if (!is_file($fichier)) {
                @file_put_contents($fichier, $contenu);
            }
        }

        return null;
    }

    /**
     * Enregistre le fichier depose et renseigne la visite.
     *
     * @throws \RuntimeException avec un message lisible par l'agent d'accueil
     */
    public function stocker(UploadedFile $fichier, Visite $visite): void
    {
        $nomOriginal = (string) $fichier->getClientOriginalName();
        $extension = strtolower((string) pathinfo($nomOriginal, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: '';

        if (!in_array($extension, self::EXTENSIONS, true)) {
            throw new \RuntimeException(
                'Format de piece non accepte (.' . $extension . '). Formats autorises : '
                . implode(', ', self::EXTENSIONS) . '.'
            );
        }

        $taille = (int) ($fichier->getSize() ?: 0);
        if ($taille > self::TAILLE_MAX) {
            throw new \RuntimeException(
                'La piece jointe fait ' . number_format($taille / 1048576, 1, ',', ' ')
                . ' Mo : la limite est de 8 Mo. Scannez le document en qualite moindre.'
            );
        }

        if (!$fichier->isValid()) {
            throw new \RuntimeException(
                'Le fichier n\'a pas pu etre transfere (' . $fichier->getErrorMessage() . ').'
                . ' Verifiez la limite upload_max_filesize du serveur.'
            );
        }

        $mime = $this->typeMime($fichier, $extension);
        $mimesAcceptes = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
            'application/pdf' => ['pdf'],
        ];

        if (!isset($mimesAcceptes[$mime])) {
            throw new \RuntimeException(
                'Le fichier n\'est pas une image JPG, PNG ou WEBP ni un PDF (type detecte : ' . $mime . ').'
                . ' Verifiez que vous joignez bien le scan de la piece et non un autre document.'
            );
        }

        $extension = $mimesAcceptes[$mime][0];

        $erreur = $this->preparerDossier();
        if ($erreur !== null) {
            throw new \RuntimeException($erreur);
        }

        $dossier = $this->dossierAbsolu();
        $nom = 'piece_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;

        try {
            $fichier->move($dossier, $nom);
            $this->optimiseurDocuments->optimiser($dossier . $nom);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Enregistrement impossible dans ' . $dossier . ' : ' . $e->getMessage(), 0, $e);
        }

        if (!is_file($dossier . $nom)) {
            throw new \RuntimeException('Le fichier n\'a pas ete retrouve apres transfert dans ' . $dossier . '.');
        }

        $visite->setPieceIdentite(date('Y') . '/' . date('m') . '/' . $nom)
            ->setPieceNomOriginal($this->nettoyerNom($nomOriginal))
            ->setPieceTypeMime($mime)
            ->setPieceTaille((int) (filesize($dossier . $nom) ?: $taille));
    }

    /** Chemin absolu d'une piece deja stockee (null si absente ou chemin suspect). */
    public function chemin(Visite $visite): ?string
    {
        $relatif = (string) $visite->getPieceIdentite();
        if ($relatif === '' || str_contains($relatif, '..')) {
            return null;
        }

        $chemin = rtrim($this->projectDir, '/') . '/public/' . self::DOSSIER . $relatif;

        return is_file($chemin) ? $chemin : null;
    }

    public function supprimer(Visite $visite): void
    {
        $chemin = $this->chemin($visite);
        if ($chemin !== null) {
            @unlink($chemin);
        }
    }

    private function typeMime(UploadedFile $fichier, string $extension): string
    {
        $connus = [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
        ];

        $aliases = [
            'image/jpg' => 'image/jpeg',
            'image/pjpeg' => 'image/jpeg',
            'application/x-pdf' => 'application/pdf',
        ];

        try {
            $mime = $fichier->getMimeType();
            if (is_string($mime) && $mime !== '' && $mime !== 'application/octet-stream') {
                return $aliases[$mime] ?? $mime;
            }
        } catch (\Throwable) {
            // finfo indisponible : on retombe sur l'extension.
        }

        return $connus[$extension] ?? 'application/octet-stream';
    }

    private function nettoyerNom(string $nom): string
    {
        $nom = str_replace(['\\', '/'], '-', $nom);
        $nom = preg_replace('/[\x00-\x1F\x7F]/u', '', $nom) ?? $nom;

        return mb_substr(trim($nom) !== '' ? trim($nom) : 'piece-identite', 0, 200);
    }
}
