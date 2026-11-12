<?php

namespace App\Controller\Stock;

use App\Entity\Services;
use App\Entity\Stock\SaisieStock;
use App\Entity\Stock\SortieStock;
use App\Repository\Stock\SaisieStockRepository;
use App\Repository\Stock\SortieStockRepository;
use App\Service\Stock\ReferentielStock;
use App\Services\pdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Ecran web de consultation des stocks (saisies = entrees, sorties, solde)
 * + export CSV / PDF des saisies.
 *
 * Filtres : rubrique, nature du produit, service, agent, periode (du / au), texte libre.
 * Par defaut, la periode affichee est celle du jour.
 * Vue : templates/saisie_stock/index.html.twig
 */
#[Route('/stock')]
final class SaisieStockController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReferentielStock $referentiel,
    ) {}

    /**
     * Lit les filtres de la requete.
     *
     * Par defaut (aucun parametre du / au dans l'URL), la periode est le jour
     * courant. Une periode volontairement videe (?du=&au=) affiche tout.
     *
     * @return array<string,string>
     */
    private function lireFiltres(Request $request): array
    {
        $du = trim((string) $request->query->get('du', ''));
        $au = trim((string) $request->query->get('au', ''));

        if (!$request->query->has('du') && !$request->query->has('au')) {
            $du = $au = (new \DateTimeImmutable('today'))->format('Y-m-d');
        }

        return [
            'rubrique' => trim((string) $request->query->get('rubrique', '')),
            'nature'   => trim((string) $request->query->get('nature', '')),
            'service'  => trim((string) $request->query->get('service', '')),
            'agent'    => trim((string) $request->query->get('agent', '')),
            'q'        => trim((string) $request->query->get('q', '')),
            'du'       => $du,
            'au'       => $au,
        ];
    }

    /**
     * Renvoie les saisies correspondant aux filtres de la requete.
     *
     * @return array{0: SaisieStock[], 1: array<string,string>}
     */
    private function appliquerFiltres(Request $request, SaisieStockRepository $repo): array
    {
        $f = $this->lireFiltres($request);

        return [
            $repo->findFiltre($f['du'], $f['au'], $f['rubrique'], $f['service'], $f['agent'], $f['q'], $f['nature']),
            $f,
        ];
    }

    /** Ligne de recapitulatif a zero. */
    private function ligneVide(): array
    {
        return [
            'nbEntrees'    => 0,
            'nbSorties'    => 0,
            'entreeKg'     => 0.0,
            'entreeLitres' => 0.0,
            'sortieKg'     => 0.0,
            'sortieLitres' => 0.0,
            'soldeKg'      => 0.0,
            'soldeLitres'  => 0.0,
        ];
    }

    /** Ajoute une quantite a une ligne ($sens = 'entree' ou 'sortie'). */
    private function ajouter(array &$ligne, string $sens, ?string $unite, float $quantite): void
    {
        $ligne[$sens . ($unite === 'Litres' ? 'Litres' : 'Kg')] += $quantite;
        $ligne['nb' . ($sens === 'entree' ? 'Entrees' : 'Sorties')]++;
    }

    /**
     * Statistiques agregees par rubrique sur le jeu filtre.
     *
     * TOUTES les rubriques predefinies figurent au recapitulatif, meme sans
     * mouvement (quantites a zero). Seule exception : quand une rubrique est
     * choisie dans le filtre, on n'affiche que celle-ci.
     *
     * @param SaisieStock[] $saisies
     * @param SortieStock[] $sorties
     */
    private function statistiques(array $saisies, array $sorties, string $rubriqueFiltre): array
    {
        $rubriques = in_array($rubriqueFiltre, $this->referentiel->rubriques(), true)
            ? [$rubriqueFiltre]
            : $this->referentiel->rubriques();

        $parRubrique = [];
        foreach ($rubriques as $r) {
            $parRubrique[$r] = $this->ligneVide();
        }

        foreach ($saisies as $s) {
            /** @var SaisieStock $s */
            $r = $s->getRubrique() ?? 'Inconnu';
            $parRubrique[$r] ??= $this->ligneVide();
            $this->ajouter($parRubrique[$r], 'entree', $s->getUnite(), (float) $s->getQuantite());
        }
        foreach ($sorties as $s) {
            /** @var SortieStock $s */
            $r = $s->getRubrique() ?? 'Inconnu';
            $parRubrique[$r] ??= $this->ligneVide();
            $this->ajouter($parRubrique[$r], 'sortie', $s->getUnite(), (float) $s->getQuantite());
        }

        $totaux = $this->ligneVide();
        foreach ($parRubrique as &$ligne) {
            $ligne['soldeKg']     = round($ligne['entreeKg'] - $ligne['sortieKg'], 3);
            $ligne['soldeLitres'] = round($ligne['entreeLitres'] - $ligne['sortieLitres'], 3);
            foreach ($totaux as $cle => $valeur) {
                $totaux[$cle] += $ligne[$cle];
            }
        }
        unset($ligne);

        $avecPhoto = static fn (SaisieStock|SortieStock $m): bool => $m->getPhoto() !== null;

        return [
            'parRubrique' => $parRubrique,
            'totaux'      => $totaux,
            'nbPhotos'    => count(array_filter($saisies, $avecPhoto)) + count(array_filter($sorties, $avecPhoto)),
        ];
    }

    private function services(): array
    {
        return $this->em->getRepository(Services::class)->findBy([], ['libelle' => 'ASC']);
    }

    #[Route('/saisies', name: 'app_saisies_stock', methods: ['GET'])]
    public function index(Request $request, SaisieStockRepository $repo, SortieStockRepository $sortieRepo): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        $this->denyAccessUnlessGranted('ROLE_USER');

        [$saisies, $filtres] = $this->appliquerFiltres($request, $repo);
        $sorties = $sortieRepo->findFiltre(
            $filtres['du'],
            $filtres['au'],
            $filtres['rubrique'],
            $filtres['service'],
            $filtres['agent'],
            $filtres['q'],
            $filtres['nature']
        );

        return $this->render('saisie_stock/index.html.twig', [
            'saisies'  => $saisies,
            'sorties'  => $sorties,
            'filtres'  => $filtres,
            'stats'    => $this->statistiques($saisies, $sorties, $filtres['rubrique']),
            'rubriques' => $this->referentiel->rubriques(),
            'natures'   => $this->referentiel->natures(),
            'unites'   => SaisieStock::UNITES,
            'services' => $this->services(),
            'dossierPhotos' => $this->getParameter('photo_saisie_stock'),
            'dossierPhotosSorties' => $this->getParameter('photo_sortie_stock'),
        ]);
    }

    /** Export CSV (Excel) du jeu filtre. */
    #[Route('/saisies/export.csv', name: 'app_saisies_stock_csv', methods: ['GET'])]
    public function exportCsv(Request $request, SaisieStockRepository $repo): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        $this->denyAccessUnlessGranted('ROLE_USER');

        [$saisies] = $this->appliquerFiltres($request, $repo);

        $fh = fopen('php://temp', 'r+');
        // BOM UTF-8 pour Excel (accents corrects)
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['Date saisie', 'Rubrique', 'Nature', 'Service', 'Agent', 'Quantite', 'Unite', 'Photo', 'Recu le'], ';');
        foreach ($saisies as $s) {
            /** @var SaisieStock $s */
            fputcsv($fh, [
                $s->getDateSaisie()?->format('d/m/Y H:i'),
                $s->getRubrique(),
                $s->getNature() ?? '',
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
        $this->denyAccessUnlessGranted('ROLE_USER');

        [$saisies, $filtres] = $this->appliquerFiltres($request, $repo);

        $entetes = ['Date saisie', 'Rubrique', 'Nature', 'Service', 'Agent', 'Quantite', 'Unite'];
        $largeurs = [26, 44, 34, 38, 38, 20, 18];
        $lignes = [];
        $totalKg = 0.0;
        $totalLitres = 0.0;
        foreach ($saisies as $s) {
            /** @var SaisieStock $s */
            $lignes[] = [
                $s->getDateSaisie()?->format('d/m/Y H:i'),
                $s->getRubrique(),
                $s->getNature() ?? '-',
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
        $lignes[] = ['', '', '', '', 'TOTAL', number_format($totalKg, 2, ',', ' ') . ' Kg', ''];
        $lignes[] = ['', '', '', '', 'TOTAL', number_format($totalLitres, 2, ',', ' ') . ' L', ''];

        $sousTitre = 'Saisies de stock';
        if ($filtres['du'] || $filtres['au']) {
            $sousTitre .= ' - periode du ' . ($filtres['du'] ?: '...') . ' au ' . ($filtres['au'] ?: '...');
        }
        if ($filtres['rubrique']) {
            $sousTitre .= ' - ' . $filtres['rubrique'];
        }
        if (!empty($filtres['nature'])) {
            $sousTitre .= ' - nature : ' . $filtres['nature'];
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
