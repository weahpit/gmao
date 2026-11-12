<?php

namespace App\Service\Reunion;

/**
 * Generation du QR code (image PNG en base64) du lien public d'une reunion.
 *
 * S'appuie sur la librairie deja presente dans le projet : endroid/qr-code.
 * Si elle n'est pas installee, la methode renvoie null et les pages
 * affichent simplement le lien en clair (aucune erreur bloquante).
 *
 * Mise en cache en memoire : le QR code d'un meme lien n'est genere qu'une
 * fois par requete (la page d'accueil et les cartes admin le demandent
 * parfois plusieurs fois).
 */
final class QrCodeReunion
{
    /** @var array<string, string|null> */
    private array $cache = [];

    /**
     * Retourne "data:image/png;base64,..." ou null si la librairie est absente.
     */
    public function dataUri(string $contenu, int $taille = 620): ?string
    {
        if ($contenu === '') {
            return null;
        }

        $cle = $taille . '|' . $contenu;
        if (array_key_exists($cle, $this->cache)) {
            return $this->cache[$cle];
        }

        $image = null;

        try {
            if (class_exists('Endroid\\QrCode\\QrCode')
                && class_exists('Endroid\\QrCode\\Writer\\PngWriter')) {

                $qrClass     = 'Endroid\\QrCode\\QrCode';
                $writerClass = 'Endroid\\QrCode\\Writer\\PngWriter';

                /** @var object $qr */
                $qr = $qrClass::create($contenu)->setSize($taille)->setMargin(16);

                /** @var object $writer */
                $writer = new $writerClass();
                $png    = $this->convertirEnPng($writer, $qr);

                if ($png !== null && $png !== '') {
                    $image = 'data:image/png;base64,' . base64_encode($png);
                }
            }
        } catch (\Throwable) {
            // Librairie absente ou API differente : on affichera le lien seul.
            $image = null;
        }

        $this->cache[$cle] = $image;

        return $image;
    }

    /**
     * Selon la version d'endroid/qr-code, la sortie PNG se recupere
     * soit par ->write($qr)->getString(), soit par ->writeString($qr).
     * On essaie dans cet ordre, sans jamais faire planter la page.
     */
    private function convertirEnPng(object $writer, object $qr): ?string
    {
        try {
            /** @var mixed $resultat */
            $resultat = $writer->write($qr);
            if (is_object($resultat) && method_exists($resultat, 'getString')) {
                return (string) $resultat->getString();
            }
        } catch (\Throwable) {
            // API differente : on tente l'autre methode.
        }

        if (method_exists($writer, 'writeString')) {
            try {
                /** @var mixed $sortie */
                $sortie = $writer->writeString($qr);

                return is_string($sortie) && $sortie !== '' ? $sortie : null;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
