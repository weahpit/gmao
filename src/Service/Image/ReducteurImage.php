<?php

namespace App\Service\Image;

use Psr\Log\LoggerInterface;

/**
 * Ramene une photo (JPEG, PNG, WEBP) a 200 Ko maximum.
 *
 * Methode : on reduit d'abord la qualite JPEG, puis les dimensions, jusqu'a
 * passer sous la limite. L'orientation EXIF des photos de telephone est
 * appliquee avant re-encodage (sinon la photo apparaitrait couchee).
 * Un PNG a transparence reste un PNG tant que c'est possible.
 *
 * Necessite l'extension PHP GD ; sans elle, le fichier est laisse tel quel.
 */
final class ReducteurImage
{
    public const TAILLE_MAX = 204800; // 200 Ko

    private const COTES = [1600, 1280, 1024, 800, 640, 480];
    private const QUALITES = [85, 75, 65, 55, 45, 35];
    private const MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /** Ce fichier est-il une image que l'on sait reduire ? */
    public function estReductible(string $chemin): bool
    {
        return in_array($this->mime($chemin), self::MIMES, true);
    }

    /**
     * @return array{chemin: string, mime: string, extension: string}|null
     *         null = rien a faire (deja assez petit) ou reduction impossible
     */
    public function reduire(string $chemin, int $maxOctets = self::TAILLE_MAX): ?array
    {
        if (!extension_loaded('gd') || !is_file($chemin)) {
            return null;
        }

        clearstatcache(true, $chemin);
        if ((int) filesize($chemin) <= $maxOctets) {
            return null;
        }

        $mime = $this->mime($chemin);
        if (!in_array($mime, self::MIMES, true)) {
            return null;
        }

        try {
            if ((string) ini_get('memory_limit') !== '-1') {
                @ini_set('memory_limit', '512M');
            }

            $source = @imagecreatefromstring((string) file_get_contents($chemin));
            if ($source === false) {
                return null;
            }

            if ($mime === 'image/jpeg') {
                $source = $this->orienter($source, $chemin);
            }

            $avecAlpha = $mime === 'image/png' && $this->pngAvecTransparence($chemin);
            $resultat = $this->comprimer($source, $maxOctets, $avecAlpha);
            imagedestroy($source);

            $tmp = tempnam(sys_get_temp_dir(), 'red');
            if ($tmp === false || file_put_contents($tmp, $resultat['donnees']) === false) {
                return null;
            }
            register_shutdown_function(static function () use ($tmp): void {
                if (is_file($tmp)) {
                    @unlink($tmp);
                }
            });

            return ['chemin' => $tmp, 'mime' => $resultat['mime'], 'extension' => $resultat['extension']];
        } catch (\Throwable $e) {
            $this->logger->warning('Reduction de photo impossible : ' . $e->getMessage());

            return null;
        }
    }

    /** @return array{donnees: string, mime: string, extension: string} */
    private function comprimer(\GdImage $source, int $max, bool $avecAlpha): array
    {
        $largeur = imagesx($source);
        $hauteur = imagesy($source);
        $plusGrand = max($largeur, $hauteur);

        $cotes = array_values(array_filter(self::COTES, static fn (int $c) => $c <= $plusGrand));
        if ($cotes === [] || $plusGrand <= self::COTES[0]) {
            array_unshift($cotes, $plusGrand);
            $cotes = array_values(array_unique($cotes));
        }

        $meilleur = null;

        foreach ($cotes as $cote) {
            $image = $this->redimensionner($source, $cote, $plusGrand, $avecAlpha);

            if ($avecAlpha) {
                ob_start();
                imagepng($image, null, 9);
                $donnees = (string) ob_get_clean();
                if (strlen($donnees) <= $max) {
                    if ($image !== $source) {
                        imagedestroy($image);
                    }

                    return ['donnees' => $donnees, 'mime' => 'image/png', 'extension' => 'png'];
                }
            }

            $plat = $this->aplatir($image);
            imageinterlace($plat, true);

            foreach (self::QUALITES as $qualite) {
                ob_start();
                imagejpeg($plat, null, $qualite);
                $donnees = (string) ob_get_clean();

                if ($meilleur === null || strlen($donnees) < strlen($meilleur)) {
                    $meilleur = $donnees;
                }
                if (strlen($donnees) <= $max) {
                    imagedestroy($plat);
                    if ($image !== $source) {
                        imagedestroy($image);
                    }

                    return ['donnees' => $donnees, 'mime' => 'image/jpeg', 'extension' => 'jpg'];
                }
            }

            imagedestroy($plat);
            if ($image !== $source) {
                imagedestroy($image);
            }
        }

        // Dernier recours : la plus petite version obtenue.
        return ['donnees' => (string) $meilleur, 'mime' => 'image/jpeg', 'extension' => 'jpg'];
    }

    private function redimensionner(\GdImage $source, int $cote, int $plusGrand, bool $alpha): \GdImage
    {
        if ($cote >= $plusGrand) {
            return $source;
        }

        $ratio = $cote / $plusGrand;
        $l = max(1, (int) round(imagesx($source) * $ratio));
        $h = max(1, (int) round(imagesy($source) * $ratio));

        $cible = imagecreatetruecolor($l, $h);
        if ($alpha) {
            imagealphablending($cible, false);
            imagesavealpha($cible, true);
            imagefill($cible, 0, 0, imagecolorallocatealpha($cible, 0, 0, 0, 127));
        }
        imagecopyresampled($cible, $source, 0, 0, 0, 0, $l, $h, imagesx($source), imagesy($source));

        return $cible;
    }

    /** Fond blanc derriere les zones transparentes (le JPEG n'a pas de transparence). */
    private function aplatir(\GdImage $image): \GdImage
    {
        $fond = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($fond, 0, 0, imagecolorallocate($fond, 255, 255, 255));
        imagecopy($fond, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $fond;
    }

    private function orienter(\GdImage $image, string $chemin): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($chemin);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $tourne = imagerotate($image, $angle, 0);

        return $tourne instanceof \GdImage ? $tourne : $image;
    }

    private function pngAvecTransparence(string $chemin): bool
    {
        $entete = (string) @file_get_contents($chemin, false, null, 0, 33);
        if (strlen($entete) < 26) {
            return false;
        }

        // Octet 25 : type de couleur (4 = gris+alpha, 6 = RVBA).
        return in_array(ord($entete[25]), [4, 6], true);
    }

    private function mime(string $chemin): string
    {
        if (!is_file($chemin)) {
            return '';
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        return (string) $finfo->file($chemin);
    }
}
