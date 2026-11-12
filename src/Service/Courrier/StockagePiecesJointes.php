<?php

namespace App\Service\Courrier;

use App\Entity\Courrier\PieceJointeCourrier;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Depot, lecture et suppression des fichiers joints aux courriers.
 *
 * Les fichiers sont ranges dans public/data/courriers/AAAA/MM/ afin que
 * chaque annee reste facile a archiver. Le nom sur disque est genere
 * (jamais le nom fourni par l'utilisateur) : pas de collision, pas de
 * chemin traverse, pas de caractere problematique.
 */
final class StockagePiecesJointes
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        #[Autowire('%courrier_pieces_jointes%')] private string $dossierRelatif = 'data/courriers/',
    ) {}

    /** Dossier absolu de stockage. */
    public function dossierAbsolu(?\DateTimeInterface $date = null): string
    {
        $date ??= new \DateTimeImmutable();

        return rtrim($this->projectDir, '/') . '/public/' . $this->dossierRelatif
            . $date->format('Y') . '/' . $date->format('m') . '/';
    }

    /**
     * Enregistre un fichier depose et remplit la fiche de la piece jointe.
     *
     * @throws \RuntimeException si le fichier ne peut pas etre deplace
     */
    public function stocker(UploadedFile $fichier, PieceJointeCourrier $piece): void
    {
        $dossier = $this->dossierAbsolu();
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            throw new \RuntimeException('Impossible de creer le dossier de stockage des courriers (' . $dossier . ').');
        }
        if (!is_writable($dossier)) {
            @chmod($dossier, 0775);
        }

        // L'extension vient du NOM D'ORIGINE fourni par le navigateur. On ne
        // passe pas par getClientOriginalExtension(), qui exige une extension
        // que Symfony connait (via la finfo du systeme) : absent de certains
        // serveurs, il leve une exception avant meme le premier enregistrement.
        $nomOriginal = $fichier->getClientOriginalName();
        $extension = strtolower((string) pathinfo($nomOriginal, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
        $extension = mb_substr($extension, 0, 12);

        $nom = 'courrier_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;

        try {
            $fichier->move($dossier, $nom);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Deplacement du fichier impossible vers ' . $dossier . ' : ' . $e->getMessage()
                . ' (droits d\'ecriture a verifier sur ce dossier).',
                0,
                $e
            );
        }

        if (!is_file($dossier . $nom)) {
            throw new \RuntimeException('Le fichier n\'a pas ete retrouve apres transfert dans ' . $dossier . '.');
        }

        $piece->setFichier(date('Y') . '/' . date('m') . '/' . $nom)
            ->setNomOriginal($this->nettoyerNom($nomOriginal))
            ->setTypeMime($this->typeMime($fichier, $extension))
            ->setTaille((int) (filesize($dossier . $nom) ?: 0));
    }

    /**
     * Dossier public/data/... verifie et pret a recevoir un fichier.
     *
     * Un dossier present sur le disque ne suffit pas : il doit etre
     * accessible en ecriture par le compte PHP (www-data, www, apache,
     * ou votre compte sous symfony server:start). Le test reel d'ecriture
     * evite le message generique « n'a pas pu etre enregistre » sans
     * explication.
     *
     * @return string|null message d'erreur comprehensible, null si tout va bien
     */
    public function preparerDossier(): ?string
    {
        $dossier = $this->dossierAbsolu();

        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            return 'Le dossier de stockage des courriers n\'a pas pu etre cree ('
                . $this->dossierRelatif . '). Verifiez les droits du dossier public du site.';
        }

        if (!is_writable($dossier)) {
            @chmod($dossier, 0775);
        }

        // Regles Apache pour le dossier des pieces jointes : les telechargements
        // sont servis par l'application, on interdit l'execution de scripts et
        // l'affichage du contenu du dossier.
        $regles = $dossier . '.htaccess';
        if (!is_file($regles)) {
            $contenu = '<IfModule mod_mime.c>' . PHP_EOL
                . '    RemoveHandler .php .phar .phtml' . PHP_EOL
                . '    RemoveType .php .phar .phtml' . PHP_EOL
                . '</IfModule>' . PHP_EOL . PHP_EOL
                . '<IfModule mod_php.c>' . PHP_EOL
                . '    php_flag engine off' . PHP_EOL
                . '</IfModule>' . PHP_EOL . PHP_EOL
                . 'Options -Indexes' . PHP_EOL;
            @file_put_contents($regles, $contenu);
        }

        // Petits fichiers de protection pour les serveurs Nginx / IIS.
        if (!is_file($dossier . 'index.html')) {
            @file_put_contents($dossier . 'index.html', '');
        }

        // La racine de stockage doit exister avant le dossier annee/mois.
        $racine = rtrim($this->projectDir, '/') . '/public/' . $this->dossierRelatif;
        if (!is_dir($racine) && !@mkdir($racine, 0775, true) && !is_dir($racine)) {
            return 'Le dossier ' . $this->dossierRelatif . ' n\'a pas pu etre cree dans public/.';
        }

        $temoin = $dossier . '.temoin-' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temoin, 'ok') === false) {
            return 'Le serveur n\'a pas le droit d\'ecrire dans '
                . $this->dossierRelatif . '.' . date('Y') . '/' . date('m')
                . '/ alors qu\'il en a besoin pour enregistrer les pieces jointes : '
                . 'accordez le droit d\'ecriture a ce dossier (utilisateur du serveur web).';
        }
        @unlink($temoin);

        // Le depose final passe par le dossier temporaire de PHP.
        $temporaire = (string) ini_get('upload_tmp_dir');
        if ($temporaire !== '' && is_dir($temporaire) && !is_writable($temporaire)) {
            return 'Le dossier temporaire du serveur (' . $temporaire . ') est en lecture seule : '
                . 'impossible de recevoir un fichier.';
        }

        try {
            $this->verifierExtensionFichier();
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * Symfony refuse par defaut les extensions qu'il ne connait pas. On
     * etend la liste aux formats utilises par le courrier, sinon l'envoi
     * echoue avec « the file was not uploaded because of a PHP extension »
     * ou une erreur de transfert sans rapport apparent.
     */
    public function verifierExtensionFichier(): void
    {
        $config = (string) ini_get('file_uploads');
        if ($config === '0' || strtolower($config) === 'off') {
            throw new \RuntimeException('Les envois de fichiers sont desactives sur ce serveur (file_uploads = Off).');
        }
    }

    /** Chemin absolu d'un fichier deja stocke. */
    public function chemin(PieceJointeCourrier $piece): ?string
    {
        $relatif = (string) $piece->getFichier();
        if ($relatif === '' || str_contains($relatif, '..')) {
            return null;
        }

        $chemin = rtrim($this->projectDir, '/') . '/public/' . $this->dossierRelatif . $relatif;

        return is_file($chemin) ? $chemin : null;
    }

    /** Supprime le fichier physique (silencieusement s'il est deja absent). */
    public function supprimer(PieceJointeCourrier $piece): void
    {
        $chemin = $this->chemin($piece);
        if ($chemin !== null) {
            @unlink($chemin);
        }
    }

    /**
     * Type MIME du fichier.
     *
     * getMimeType() (finfo) peut renvoyer null sur certains hebergeurs :
     * on retombe alors sur le type du navigateur, puis sur une table simple
     * par extension. Une piece jointe n'est jamais refusee pour cela.
     */
    private function typeMime(UploadedFile $fichier, string $extension): string
    {
        try {
            $mime = $fichier->getMimeType();
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        } catch (\Throwable) {
            // finfo indisponible : on continue avec les valeurs par defaut.
        }

        try {
            $mime = $fichier->getClientMimeType();
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        } catch (\Throwable) {
            // idem
        }

        $connus = [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'zip' => 'application/zip',
        ];

        return $connus[$extension] ?? 'application/octet-stream';
    }

    /** Nom d'origine nettoye, borne a 200 caracteres. */
    private function nettoyerNom(string $nom): string
    {
        $nom = str_replace(['\\', '/'], '-', $nom);
        $nom = preg_replace('/[\x00-\x1F\x7F]/u', '', $nom) ?? $nom;

        return mb_substr(trim($nom) !== '' ? trim($nom) : 'piece-jointe', 0, 200);
    }
}
