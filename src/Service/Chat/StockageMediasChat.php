<?php

namespace App\Service\Chat;

use App\Entity\Chat\MessageChat;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Enregistrement des pieces jointes de la messagerie (photos et vocaux).
 *
 * Les fichiers sont ranges dans public/uploads/chat/AAAA/MM/. Le nom sur
 * disque est genere : le nom fourni par l'appareil n'est jamais reutilise
 * (pas de collision, pas de traversee de chemin).
 *
 * Les types acceptes sont volontairement limites : une photo et un vocal,
 * rien d'executable. Le type reel est verifie avec finfo quand l'extension
 * PHP est disponible, sans bloquer le depot si elle ne l'est pas.
 */
final class StockageMediasChat
{
    /** Poids maximal d'une photo, en octets. */
    public const TAILLE_IMAGE_MAX = 8 * 1024 * 1024;

    /** Poids maximal d'un vocal, en octets. */
    public const TAILLE_AUDIO_MAX = 15 * 1024 * 1024;

    private const EXTENSIONS_IMAGE = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const EXTENSIONS_AUDIO = ['webm', 'ogg', 'oga', 'opus', 'm4a', 'mp4', 'mp3', 'wav', 'aac', 'amr'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {}

    public function dossierAbsolu(\DateTimeInterface $date): string
    {
        return rtrim($this->projectDir, '/') . '/public/uploads/chat/'
            . $date->format('Y') . '/' . $date->format('m') . '/';
    }

    /**
     * Enregistre le fichier et remplit le message.
     *
     * @param string $type MessageChat::TYPE_IMAGE ou MessageChat::TYPE_AUDIO
     *
     * @throws \InvalidArgumentException message lisible en cas de refus
     */
    public function enregistrer(UploadedFile $fichier, MessageChat $message, string $type): void
    {
        if (!$fichier->isValid()) {
            throw new \InvalidArgumentException($this->expliquerErreurUpload($fichier));
        }

        $estImage = $type === MessageChat::TYPE_IMAGE;
        if (!$estImage && $type !== MessageChat::TYPE_AUDIO) {
            throw new \InvalidArgumentException('Type de piece jointe inconnu : ' . $type . '.');
        }

        $nomOriginal = (string) $fichier->getClientOriginalName();
        $extension = strtolower((string) pathinfo($nomOriginal, PATHINFO_EXTENSION));
        $extension = (string) preg_replace('/[^a-z0-9]/', '', $extension);

        // Certains navigateurs mobiles n'envoient pas d'extension (ou un nom
        // generique) pour un enregistrement audio : on deduit alors depuis le
        // type MIME declare.
        if ($extension === '') {
            $extension = $estImage ? 'jpg' : $this->extensionDepuisMime((string) $fichier->getClientMimeType());
        }

        $autorisees = $estImage ? self::EXTENSIONS_IMAGE : self::EXTENSIONS_AUDIO;
        if (!in_array($extension, $autorisees, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Format .%s non accepté pour %s. Formats autorisés : %s.',
                $extension !== '' ? $extension : '(inconnu)',
                $estImage ? 'une photo' : 'un message vocal',
                implode(', ', $autorisees)
            ));
        }

        $taille = (int) ($fichier->getSize() ?: 0);
        $max = $estImage ? self::TAILLE_IMAGE_MAX : self::TAILLE_AUDIO_MAX;
        if ($taille > $max) {
            throw new \InvalidArgumentException(sprintf(
                'Fichier trop volumineux : %s Mo. Maximum autorisé : %s Mo.',
                number_format($taille / 1048576, 1, ',', ' '),
                number_format($max / 1048576, 0, ',', ' ')
            ));
        }

        if ($taille === 0) {
            throw new \InvalidArgumentException(
                'Le fichier reçu est vide (0 octet). Vérifiez la limite d\'envoi du serveur (upload_max_filesize / post_max_size).'
            );
        }

        $dossier = $this->dossierAbsolu(new \DateTimeImmutable());
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            throw new \RuntimeException('Le dossier de stockage des médias (' . $dossier . ') n\'a pas pu être créé. Vérifiez les droits du dossier public.');
        }
        if (!is_writable($dossier)) {
            @chmod($dossier, 0775);
        }

        $nom = 'chat_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;

        try {
            $fichier->move($dossier, $nom);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Enregistrement du fichier impossible dans ' . $dossier . ' : ' . $e->getMessage());
        }

        if (!is_file($dossier . $nom)) {
            throw new \RuntimeException('Le fichier n\'a pas été retrouvé après transfert (' . $dossier . $nom . ').');
        }

        $message->setType($type)
            ->setFichier(date('Y') . '/' . date('m') . '/' . $nom)
            ->setFichierNom($this->nettoyerNom($nomOriginal, $extension))
            ->setFichierMime((string) ($this->typeMime($dossier . $nom) ?: ($estImage ? 'image/' . $extension : 'audio/' . $extension)));
    }

    /** Supprime un fichier (message retire). */
    public function supprimer(?string $cheminRelatif): void
    {
        if (!$cheminRelatif) {
            return;
        }
        $chemin = rtrim($this->projectDir, '/') . '/public/uploads/chat/' . ltrim($cheminRelatif, '/');
        if (is_file($chemin)) {
            @unlink($chemin);
        }
    }

    private function typeMime(string $chemin): ?string
    {
        if (!class_exists(\finfo::class)) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = @$finfo->file($chemin);

        return is_string($mime) && $mime !== '' ? $mime : null;
    }

    private function extensionDepuisMime(string $mime): string
    {
        $mime = strtolower(trim(explode(';', $mime)[0]));

        return match ($mime) {
            'image/png'             => 'png',
            'image/webp'            => 'webp',
            'image/gif'             => 'gif',
            'audio/ogg', 'audio/opus' => 'ogg',
            'audio/mp4', 'audio/aac', 'audio/x-m4a' => 'm4a',
            'audio/mpeg'            => 'mp3',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/webm'            => 'webm',
            default                 => 'jpg',
        };
    }

    private function nettoyerNom(string $nom, string $extension): string
    {
        $base = pathinfo($nom, PATHINFO_FILENAME);
        $base = (string) preg_replace('/[^\p{L}\p{N}\-_\. ]+/u', '', $base);
        $base = trim(mb_substr($base, 0, 80));

        return ($base !== '' ? $base : 'piece-jointe') . '.' . $extension;
    }

    private function expliquerErreurUpload(UploadedFile $fichier): string
    {
        $raisons = [
            UPLOAD_ERR_INI_SIZE   => 'le fichier dépasse la limite du serveur (upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE  => 'le fichier dépasse la limite du formulaire',
            UPLOAD_ERR_PARTIAL    => 'le transfert a été interrompu avant la fin',
            UPLOAD_ERR_NO_FILE    => 'aucun fichier n\'a été reçu',
            UPLOAD_ERR_NO_TMP_DIR => 'le dossier temporaire du serveur est introuvable',
            UPLOAD_ERR_CANT_WRITE => 'le serveur n\'a pas pu écrire le fichier sur le disque',
            UPLOAD_ERR_EXTENSION  => 'une extension PHP a bloqué le transfert',
        ];

        return 'Envoi impossible : ' . ($raisons[$fichier->getError()] ?? 'erreur inconnue (' . $fichier->getError() . ')') . '.';
    }
}
