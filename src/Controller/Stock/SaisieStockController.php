<?php

namespace App\Controller\Stock;

use App\Entity\Services;
use App\Entity\Stock\SaisieStock;
use App\Repository\Stock\SaisieStockRepository;
use App\Services\pdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Ecran web de consultation des saisies de stock + export CSV / PDF.
 *
 * Filtres : rubrique, service, agent, periode (du / au), texte libre.
 * Vue : templates/saisie_stock/index.html.twig
 */
#[Route('/stock')]
final class SaisieStockController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Lit les filtres de la requete et renvoie les saisies correspondantes.
     *
     * @return array{0: SaisieStock[], 1: array<string,mixed>}
     */
    private function appliquerFiltres(Request $request, SaisieStockRepository $repo): array
    {
        $rubrique  = trim((string) $request->query->get('rubrique', ''));
        $serviceId = trim((string) $request->query->get('service', ''));
        $agent     = trim((string) $request->query->get('agent', ''));
        $q         = trim((string) $request->query->get('q', ''));
        $du        = trim((string) $request->query->get('du', ''));
        $au        = trim((string) $request->query->get('au', ''));

        $saisies = $repo->findFiltre($du, $au, $rubrique, $serviceId, $agent, $q);

        $filtres = [
            'rubrique' => $rubrique,
            'service'  => $serviceId,
            'agent'    => $agent,
            'q'        => $q,
            'du'       => $du,
            'au'       => $au,
        ];

        return [$saisies, $filtres];
    }

    /** Statistiques agregees (par rubrique) sur le jeu filtre. */
    private function statistiques(array $saisies): array
    {
        $parRubrique = [];
        $totalKg = 0.0;
        $totalLitres = 0.0;

        foreach ($saisies as $s) {
            /** @var SaisieStock $s */
            $r = $s->getRubrique() ?? 'Inconnu';
            if (!isset($parRubrique[$r])) {
                $parRubrique[$r] = ['nb' => 0, 'kg' => 0.0, 'litres' => 0.0];
            }
            $parRubrique[$r]['nb']++;
            if ($s->getUnite() === 'Litres') {
                $parRubrique[$r]['litres'] += (float) $s->getQuantite();
                $totalLitres += (float) $s->getQuantite();
            } else {
                $parRubrique[$r]['kg'] += (float) $s->getQuantite();
                $totalKg += (float) $s->getQuantite();
            }
        }

        return [
            'parRubrique'  => $parRubrique,
            'totalKg'      => $totalKg,
            'totalLitres'  => $totalLitres,
            'nbTotal'      => count($saisies),
            'nbPhotos'     => count(array_filter($saisies, fn (SaisieStock $s) => $s->getPhoto() !== null)),
        ];
    }

    private function services(): array
    {
        return $this->em->getRepository(Services::class)->findBy([], ['libelle' => 'ASC']);
    }

    #[Route('/saisies', name: 'app_saisies_stock', methods: ['GET'])]
    public function index(Request $request, SaisieStockRepository $repo): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        $this->denyAccessUnlessGranted('ROLE_PDR');

        [$saisies, $filtres] = $this->appliquerFiltres($request, $repo);

        return $this->render('saisie_stock/index.html.twig', [
            'saisies'  => $saisies,
            'filtres'  => $filtres,
            'stats'    => $this->statistiques($saisies),
            'rubriques' => SaisieStock::RUBRIQUES,
            'unites'   => SaisieStock::UNITES,
            'services' => $this->services(),
            'dossierPhotos' => $this->getParameter('photo_saisie_stock'),
        ]);
    }

    /** Export CSV (Excel) du jeu filtre. */
    #[Route('/saisies/export.csv', name: 'app_saisies_stock_csv', methods: ['GET'])]
    public function exportCsv(Request $request, SaisieStockRepository $repo): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        $this->denyAccessUnlessGranted('ROLE_PDR');

        [$saisies] = $this->appliquerFiltres($request, $repo);

        $fh = fopen('php://temp', 'r+');
        // BOM UTF-8 pour Excel (accents corrects)
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['Date saisie', 'Rubrique', 'Service', 'Agent', 'Quantite', 'Unite', 'Photo', 'Recu le'], ';');
        foreach ($saisies as $s) {
            /** @var SaisieStock $s */
            fputcsv($fh, [
                $s->getDateSaisie()?->format('d/m/Y H:i'),
                $s->getRubrique(),
                $s->getServiceLibelle(),
                $s->getAgentLibelle(),
                number_format((float) $s->getQuantite(), 2, ',', ''),
                $s->getUnite(),
                $s->getPhoto() ? 'oui' : 'non',
                $s->getCreatedAt()?->format('d/m/Y H:i'),
            ], ';');
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        $nom = 'saisies-stock-' . date('Ymd-His') . '.csv';

        return new Response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $nom . '"',
        ]);
    }

    /** Export PDF (tableau) du jeu filtre. */
    #[Route('/saisies/export.pdf', name: 'app_saisies_stock_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, pdfService $pdfService, SaisieStockRepository $repo): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        $this->denyAccessUnlessGranted('ROLE_PDR');

        [$saisies, $filtres] = $this->appliquerFiltres($request, $repo);

        $entetes = ['Date saisie', 'Rubrique', 'Service', 'Agent', 'Quantite', 'Unite'];
        $largeurs = [26, 50, 40, 40, 20, 18];
        $lignes = [];
        $totalKg = 0.0;
        $totalLitres = 0.0;
        foreach ($saisies as $s) {
            /** @var SaisieStock $s */
            $lignes[] = [
                $s->getDateSaisie()?->format('d/m/Y H:i'),
                $s->getRubrique(),
                $s->getServiceLibelle() ?? '-',
                $s->getAgentLibelle() ?? '-',
                number_format((float) $s->getQuantite(), 2, ',', ' '),
                $s->getUnite(),
            ];
            if ($s->getUnite() === 'Litres') {
                $totalLitres += (float) $s->getQuantite();
            } else {
                $totalKg += (float) $s->getQuantite();
            }
        }
        $lignes[] = ['', '', '', 'TOTAL', number_format($totalKg, 2, ',', ' ') . ' Kg', ''];
        $lignes[] = ['', '', '', 'TOTAL', number_format($totalLitres, 2, ',', ' ') . ' L', ''];

        $sousTitre = 'Saisies de stock';
        if ($filtres['du'] || $filtres['au']) {
            $sousTitre .= ' - periode du ' . ($filtres['du'] ?: '...') . ' au ' . ($filtres['au'] ?: '...');
        }
        if ($filtres['rubrique']) {
            $sousTitre .= ' - ' . $filtres['rubrique'];
        }

        $contenu = $pdfService->genererTableau(
            'Saisies de stock',
            $entetes,
            $largeurs,
            $lignes,
            'L',
            $sousTitre
        );

        return new Response($contenu, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="saisies-stock.pdf"',
        ]);
    }
}
