<?php

declare(strict_types=1);

namespace App\Service\Documents;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/** Compression sans perte, apres validation et deplacement du fichier recu. */
final class OptimiseurDocuments
{
    private const MAX_OCTETS = 64 * 1024 * 1024;
    private const MAX_DECOMPRESSE = 32 * 1024 * 1024;
    private const FORMATS_ZIP = ['docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp'];

    public function __construct(private LoggerInterface $logger) {}

    /** @return array{avant: int, apres: int, gain: int, statut: string} */
    public function optimiser(string $chemin): array
    {
        clearstatcache(true, $chemin);
        $avant = is_file($chemin) ? (int) filesize($chemin) : 0;
        $extension = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
        $statut = 'format conserve';
        $temp = null;
        try {
            if ($avant < 1024 || $avant > self::MAX_OCTETS || !is_writable($chemin)) {
                $statut = 'taille ou droits incompatibles';
            } elseif ($extension === 'pdf' || in_array($extension, self::FORMATS_ZIP, true)) {
                // Meme dossier et extension : remplacement atomique et aucun nom fourni par le client.
                $temp = dirname($chemin) . '/.optim-' . bin2hex(random_bytes(8)) . '.' . $extension;
                $statut = $extension === 'pdf' ? $this->pdf($chemin, $temp) : $this->bureautique($chemin, $temp);
                clearstatcache(true, $temp);
                if ($statut === 'valide' && is_file($temp) && filesize($temp) > 0 && filesize($temp) < $avant) {
                    @chmod($temp, fileperms($chemin) & 0777);
                    if (!@rename($temp, $chemin)) {
                        throw new \RuntimeException('Remplacement du document optimise impossible.');
                    }
                    $statut = 'compresse';
                } elseif ($statut === 'valide') {
                    $statut = 'deja optimise';
                }
            }
        } catch (\Throwable $e) {
            // Un outil indisponible ou un fichier non optimisable ne doit jamais perdre l'original.
            $statut = 'original conserve';
            $this->logger->warning('Compression document impossible : original conserve.', ['erreur' => $e->getMessage()]);
        } finally {
            if ($temp !== null && is_file($temp)) {
                @unlink($temp);
            }
        }
        clearstatcache(true, $chemin);
        $apres = is_file($chemin) ? (int) filesize($chemin) : $avant;
        $resultat = ['avant' => $avant, 'apres' => $apres, 'gain' => max(0, $avant - $apres), 'statut' => $statut];
        // Ni chemin serveur ni contenu des documents dans le journal.
        $this->logger->info('Optimisation piece jointe.', $resultat + ['extension' => $extension]);
        return $resultat;
    }

    private function pdf(string $source, string $cible): string
    {
        if (!class_exists(Process::class)) {
            return 'PDF conserve : symfony/process indisponible';
        }
        $version = $this->processus(['qpdf', '--version']);
        if (!$version->isSuccessful()) {
            return 'PDF conserve : qpdf indisponible';
        }
        $chiffrement = $this->processus(['qpdf', '--is-encrypted', $source]);
        if ($chiffrement->getExitCode() !== 2) {
            return 'PDF chiffre ou illisible : conserve';
        }
        // JSON de structure : detecte les signatures meme dans les objets compresses.
        $structure = $this->processus(['qpdf', '--json=1', '--json-key=objects', $source]);
        if (!$structure->isSuccessful()) {
            return 'PDF conserve : structure non verifiable';
        }
        if (str_contains($structure->getOutput(), '"/Sig"') || str_contains($structure->getOutput(), '"/ByteRange"')) {
            return 'PDF signe : conserve';
        }
        $compression = $this->processus(['qpdf', '--stream-data=compress', '--object-streams=generate', '--recompress-flate', '--compression-level=9', $source, $cible]);
        if (!$compression->isSuccessful()) {
            return 'PDF conserve : optimisation indisponible';
        }
        $controle = $this->processus(['qpdf', '--check', $cible]);
        $pagesAvant = $this->processus(['qpdf', '--show-npages', $source]);
        $pagesApres = $this->processus(['qpdf', '--show-npages', $cible]);
        if (!$controle->isSuccessful() || !$pagesAvant->isSuccessful() || !$pagesApres->isSuccessful()
            || trim($pagesAvant->getOutput()) !== trim($pagesApres->getOutput())) {
            return 'PDF conserve : verification echouee';
        }
        return 'valide';
    }

    /** Pas de shell : les arguments et les chemins ne sont jamais interpretes comme commandes. */
    private function processus(array $arguments): Process
    {
        $p = new Process($arguments);
        $p->setTimeout(20);
        $p->run();
        return $p;
    }

    private function bureautique(string $source, string $cible): string
    {
        if (!class_exists(\ZipArchive::class)) {
            return 'Document conserve : extension PHP zip indisponible';
        }
        $entree = new \ZipArchive();
        $sortie = new \ZipArchive();
        $verification = new \ZipArchive();
        $entreeOuverte = false;
        $sortieOuverte = false;
        $verificationOuverte = false;
        try {
            if ($entree->open($source, \ZipArchive::CHECKCONS) !== true) return 'Document chiffre ou archive invalide : conserve';
            $entreeOuverte = true;
            $total = 0;
            if ($entree->numFiles > 4000) return 'Document trop complexe : conserve';
            for ($i = 0; $i < $entree->numFiles; ++$i) {
                $stat = $entree->statIndex($i);
                if ($stat === false) return 'Archive invalide : conserve';
                $total += $stat['size'];
                // Ne jamais modifier les signatures, macros ou documents chiffres.
                if ($total > self::MAX_DECOMPRESSE || $stat['size'] > 8 * 1024 * 1024
                    || ($stat['encryption_method'] ?? 0) !== 0
                    || preg_match('~(?:_xmlsignatures/|documentsignatures|macrosignatures|vbaproject)~i', $stat['name'])) {
                    return 'Document signe, protege ou volumineux : conserve';
                }
            }
            if ($sortie->open($cible, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) throw new \RuntimeException('Creation archive temporaire impossible.');
            $sortieOuverte = true;
            $sortie->setArchiveComment($entree->getArchiveComment());
            $empreintes = [];
            for ($i = 0; $i < $entree->numFiles; ++$i) {
                $stat = $entree->statIndex($i);
                $nom = $stat['name'];
                if (isset($empreintes[$nom])) return 'Archive avec entrees dupliquees : conserve';
                $octets = $entree->getFromIndex($i);
                if ($octets === false) throw new \RuntimeException('Lecture du document impossible.');
                $empreintes[$nom] = hash('sha256', $octets);
                if (str_ends_with($nom, '/')) {
                    if (!$sortie->addEmptyDir($nom)) throw new \RuntimeException('Recompression impossible.');
                } else {
                    if (!$sortie->addFromString($nom, $octets)) throw new \RuntimeException('Recompression impossible.');
                    // mimetype des fichiers ODF doit rester la premiere entree non compressee.
                    if (!$sortie->setCompressionName($nom, $nom === 'mimetype' ? \ZipArchive::CM_STORE : \ZipArchive::CM_DEFLATE, 9)) throw new \RuntimeException('Compression ZIP indisponible.');
                }
                if (method_exists($sortie, 'setMtimeName')) $sortie->setMtimeName($nom, $stat['mtime']);
                $sortie->setCommentName($nom, $entree->getCommentIndex($i));
                $opsys = 0; $attributs = 0;
                if ($entree->getExternalAttributesIndex($i, $opsys, $attributs)) $sortie->setExternalAttributesName($nom, $opsys, $attributs);
            }
            if (!$sortie->close()) throw new \RuntimeException('Fermeture archive impossible.');
            $sortieOuverte = false;
            if ($verification->open($cible, \ZipArchive::CHECKCONS) !== true) throw new \RuntimeException('Verification archive impossible.');
            $verificationOuverte = true;
            if ($verification->numFiles !== count($empreintes)) throw new \RuntimeException('Entree manquante apres compression.');
            foreach ($empreintes as $nom => $hash) {
                $octets = $verification->getFromName($nom);
                if ($octets === false || hash('sha256', $octets) !== $hash) throw new \RuntimeException('Contenu different apres compression.');
            }
            return 'valide';
        } finally {
            if ($verificationOuverte) $verification->close();
            if ($sortieOuverte) $sortie->close();
            if ($entreeOuverte) $entree->close();
        }
    }
}
