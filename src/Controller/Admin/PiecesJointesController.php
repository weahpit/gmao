<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Export des pieces jointes stockees sur le serveur.
 *
 * Reserve aux administrateurs : la page liste les dossiers de public/data
 * (courriers, RH, visites, photos...) avec le nombre de fichiers et leur
 * poids, puis permet de telecharger une archive ZIP d'un dossier ou de tout.
 */
#[Route('/admin/pieces-jointes', name: 'admin_pieces_jointes')]
#[IsGranted('ROLE_ADMIN')]
final class PiecesJointesController extends AbstractController
{
    /** Dossiers jamais exportes : fichiers techniques du serveur web. */
    private const EXCLUS = ['.htaccess', 'index.html', '.DS_Store', 'Thumbs.db'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {}

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        $dossiers = [];
        foreach ($this->dossiers() as $nom => $chemin) {
            $fichiers = $this->listerFichiers($chemin);
            $poids = 0;
            foreach ($fichiers as $fichier) {
                $poids += (int) @filesize($fichier);
            }
            $dossiers[$nom] = [
                'nom' => $nom,
                'nombre' => count($fichiers),
                'poids' => $poids,
                'poidsLisible' => $this->poidsLisible($poids),
                'existe' => is_dir($chemin),
            ];
        }

        $total = array_sum(array_column($dossiers, 'nombre'));
        $poidsTotal = array_sum(array_column($dossiers, 'poids'));

        return $this->render('admin/pieces_jointes/index.html.twig', [
            'dossiers' => $dossiers,
            'total' => $total,
            'poidsTotal' => $this->poidsLisible($poidsTotal),
            'zipDisponible' => class_exists(\ZipArchive::class),
            'racine' => 'public/data',
        ]);
    }

    #[Route('/export/{dossier}', name: '_export', methods: ['GET'], requirements: ['dossier' => '[a-zA-Z0-9_-]+'])]
    public function export(string $dossier): Response
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->addFlash('danger', "L'extension zip de PHP n'est pas activee sur ce serveur : impossible de creer l'archive. Demandez a l'hebergeur d'activer l'extension zip.");

            return $this->redirectToRoute('admin_pieces_jointes');
        }

        $dossiers = $this->dossiers();
        if ($dossier !== 'tout' && !isset($dossiers[$dossier])) {
            $this->addFlash('danger', 'Dossier inconnu : "' . $dossier . '".');

            return $this->redirectToRoute('admin_pieces_jointes');
        }

        $aExporter = $dossier === 'tout' ? $dossiers : [$dossier => $dossiers[$dossier]];

        $archive = tempnam(sys_get_temp_dir(), 'pj_');
        if ($archive === false) {
            $this->addFlash('danger', 'Le dossier temporaire du serveur est inaccessible : archive impossible a preparer.');

            return $this->redirectToRoute('admin_pieces_jointes');
        }

        $zip = new \ZipArchive();
        if ($zip->open($archive, \ZipArchive::OVERWRITE) !== true) {
            @unlink($archive);
            $this->addFlash('danger', "L'archive n'a pas pu etre creee dans le dossier temporaire du serveur.");

            return $this->redirectToRoute('admin_pieces_jointes');
        }

        $ajoutes = 0;
        $manquants = [];
        $inventaire = "Dossier;Fichier;Taille (octets);Date de modification\n";

        foreach ($aExporter as $nom => $chemin) {
            foreach ($this->listerFichiers($chemin) as $fichier) {
                $relatif = $nom . '/' . ltrim(str_replace($chemin, '', $fichier), '/');
                if ($zip->addFile($fichier, $relatif)) {
                    ++$ajoutes;
                } else {
                    $manquants[] = $relatif;
                    continue;
                }
                $inventaire .= $relatif . ';' . (int) @filesize($fichier) . ';'
                    . date('d/m/Y H:i', (int) @filemtime($fichier)) . "\n";
            }
        }

        $zip->addFromString('inventaire.csv', "\xEF\xBB\xBF" . $inventaire);
        $zip->close();

        if ($ajoutes === 0) {
            @unlink($archive);
            $this->addFlash('warning', 'Aucun fichier a exporter dans "' . ($dossier === 'tout' ? 'public/data' : 'public/data/' . $dossier) . '".');

            return $this->redirectToRoute('admin_pieces_jointes');
        }

        $nomArchive = 'pieces-jointes-' . ($dossier === 'tout' ? 'complet' : $dossier) . '-' . date('Ymd-Hi') . '.zip';

        $reponse = new BinaryFileResponse($archive);
        $reponse->setContentDisposition('attachment', $nomArchive);
        $reponse->headers->set('Content-Type', 'application/zip');
        $reponse->deleteFileAfterSend(true);

        return $reponse;
    }

    /**
     * Dossiers de stockage presents sous public/data.
     *
     * @return array<string, string> nom du dossier => chemin absolu
     */
    private function dossiers(): array
    {
        $racine = rtrim($this->projectDir, '/') . '/public/data';
        $dossiers = [];

        foreach (['courriers', 'rh', 'visites', 'users', 'equipements', 'stock', 'reunions'] as $connu) {
            $chemin = $racine . '/' . $connu;
            if (is_dir($chemin)) {
                $dossiers[$connu] = $chemin;
            }
        }

        if (is_dir($racine)) {
            foreach (scandir($racine) ?: [] as $entree) {
                if ($entree === '.' || $entree === '..') {
                    continue;
                }
                $chemin = $racine . '/' . $entree;
                if (is_dir($chemin) && !isset($dossiers[$entree]) && !str_starts_with($entree, '.')) {
                    $dossiers[$entree] = $chemin;
                }
            }
        }

        ksort($dossiers);

        return $dossiers;
    }

    /** @return list<string> chemins absolus des fichiers, dossiers techniques exclus */
    private function listerFichiers(string $dossier): array
    {
        if (!is_dir($dossier)) {
            return [];
        }

        $fichiers = [];
        $parcours = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dossier, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($parcours as $fichier) {
            if (!$fichier instanceof \SplFileInfo || !$fichier->isFile()) {
                continue;
            }
            if (in_array($fichier->getFilename(), self::EXCLUS, true)) {
                continue;
            }
            $fichiers[] = $fichier->getPathname();
        }

        sort($fichiers);

        return $fichiers;
    }

    private function poidsLisible(int $octets): string
    {
        if ($octets >= 1073741824) {
            return number_format($octets / 1073741824, 2, ',', ' ') . ' Go';
        }
        if ($octets >= 1048576) {
            return number_format($octets / 1048576, 1, ',', ' ') . ' Mo';
        }
        if ($octets >= 1024) {
            return number_format($octets / 1024, 0, ',', ' ') . ' Ko';
        }

        return $octets . ' octets';
    }
}
