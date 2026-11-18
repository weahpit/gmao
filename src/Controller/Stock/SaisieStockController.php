<?php

namespace App\Controller\Stock;

use App\Entity\Services;
use App\Entity\Stock\SaisieStock;
use App\Entity\Stock\SortieStock;
use App\Repository\Stock\SaisieStockRepository;
use App\Repository\Stock\SortieStockRepository;
use App\Service\Stock\ReferentielStock;
use App\Service\Stock\StockInitialService;
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
        private StockInitialService $stockInitial,
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
            // Detail par nature du produit : [nature => ligneVide()]
            'parNature'    => [],
        ];
    }

    /** Ajoute une quantite a une ligne ($sens = 'entree' ou 'sortie'). */
    private function ajouter(array &$ligne, string $sens, ?string $unite, float $quantite): void
    {
        $ligne[$sens . ($unite === 'Litres' ? 'Litres' : 'Kg')] += $quantite;
        $ligne['nb' . ($sens === 'entree' ? 'Entrees' : 'Sorties')]++;
    }

    /**
     * Ajoute un mouvement au detail par nature de la rubrique, avec la meme
     * regle de sens (entree / sortie) et d'unite (Kg / Litres) que la ligne
     * principale : la somme des natures egale donc le total de la rubrique.
     */
    private function ajouterNature(array &$ligne, ?string $nature, string $sens, ?string $unite, float $quantite): void
    {
        $cle = trim((string) $nature);
        if ($cle === '') {
            $cle = 'Nat. non precisee';
        }
        $ligne['parNature'][$cle] ??= $this->ligneVide();
        $this->ajouter($ligne['parNature'][$cle], $sens, $unite, $quantite);
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
    private function statistiques(array $saisies, array $sorties, string $rubriqueFiltre, array $stockInitial = []): array
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
            $this->ajouterNature($parRubrique[$r], $s->getNature(), 'entree', $s->getUnite(), (float) $s->getQuantite());
        }
        foreach ($sorties as $s) {
            /** @var SortieStock $s */
            $r = $s->getRubrique() ?? 'Inconnu';
            $parRubrique[$r] ??= $this->ligneVide();
            $this->ajouter($parRubrique[$r], 'sortie', $s->getUnite(), (float) $s->getQuantite());
            $this->ajouterNature($parRubrique[$r], $s->getNature(), 'sortie', $s->getUnite(), (float) $s->getQuantite());
        }

        $totaux = $this->ligneVide();
        foreach ($parRubrique as $r => &$ligne) {
            $ligne['soldeKg']     = round($ligne['entreeKg'] - $ligne['sortieKg'], 3);
            $ligne['soldeLitres'] = round($ligne['entreeLitres'] - $ligne['sortieLitres'], 3);
            foreach ($totaux as $cle => $valeur) {
                if ($cle === 'parNature') {
                    continue;
                }
                $totaux[$cle] += $ligne[$cle];
            }

            // Detail par nature : solde, quantite a afficher et unite.
            // Les mouvements filtres (periode, service, agent...) et le detail
            // de l'inventaire sont fusionnes nature par nature : le cumul de la
            // periode et le stock initial/final figurent ainsi sur la meme
            // ligne. Les natures vides sont ignorees, puis la liste est classee
            // de la plus grosse quantite a la plus petite.
            $natures = [];
            $liste = $ligne['parNature'];
            if (isset($stockInitial['parRubrique'][$r]) && is_array($stockInitial['parRubrique'][$r]['parNature'] ?? null)) {
                $liste = $this->fusionnerNatures($ligne['parNature'], $stockInitial['parRubrique'][$r]['parNature']);
            }
            foreach ($liste as $nomNature => $n) {
                // Une nature sans aucun mouvement (periode filtree) est ignoree :
                // elle n'apporte pas de cumul a afficher.
                if ($n['nbEntrees'] === 0 && $n['nbSorties'] === 0 && $n['entreeKg'] == 0 && $n['sortieKg'] == 0
                    && $n['entreeLitres'] == 0 && $n['sortieLitres'] == 0
                    && ($n['stockInitialKg'] ?? 0) == 0 && ($n['stockInitialLitres'] ?? 0) == 0) {
                    continue;
                }
                $n['soldeKg']     = round($n['entreeKg'] - $n['sortieKg'], 3);
                $n['soldeLitres'] = round($n['entreeLitres'] - $n['sortieLitres'], 3);
                $estLitres        = $n['entreeLitres'] > 0 || $n['sortieLitres'] > 0;
                $n['unite']       = $estLitres ? 'Litres' : 'Kg';
                $n['stockInitial'] = $estLitres ? ($n['stockInitialLitres'] ?? 0.0) : ($n['stockInitialKg'] ?? 0.0);
                $n['stockFinal']   = $estLitres ? ($n['stockFinalLitres'] ?? 0.0) : ($n['stockFinalKg'] ?? 0.0);
                $n['entrees']     = $estLitres ? $n['entreeLitres'] : $n['entreeKg'];
                $n['sorties']     = $estLitres ? $n['sortieLitres'] : $n['sortieKg'];
                $n['solde']       = $estLitres ? $n['soldeLitres'] : $n['soldeKg'];
                $n['nom']         = $nomNature;
                $natures[]        = $n;
            }
            usort($natures, static fn (array $a, array $b): int => [$b['entrees'], $b['sorties']] <=> [$a['entrees'], $a['sorties']]);
            $ligne['natures']   = $natures;
            $ligne['nbNatures'] = count($natures);
            $ligne['parNature'] = [];
        }
        unset($ligne);

        $avecPhoto = static fn (SaisieStock|SortieStock $m): bool => $m->getPhoto() !== null;

        return [
            'parRubrique' => $parRubrique,
            'totaux'      => $totaux,
            'nbPhotos'    => count(array_filter($saisies, $avecPhoto)) + count(array_filter($sorties, $avecPhoto)),
        ];
    }

    /**
     * Fusionne le detail par nature des mouvements filtres avec celui de
     * l'inventaire (stock initial + mouvements posterieurs).
     *
     * Les quantites de mouvement de l'inventaire ne sont reprises que pour les
     * natures absentes des mouvements filtres : sinon elles seraient comptees
     * deux fois. Les stocks initial/final viennent toujours de l'inventaire.
     *
     * @param array<string, array<string, float|int>> $mouvements
     * @param array<string, array<string, float|int>> $inventaire
     *
     * @return array<string, array<string, float|int>>
     */
    private function fusionnerNatures(array $mouvements, array $inventaire): array
    {
        $cles = ['entreeKg', 'entreeLitres', 'sortieKg', 'sortieLitres'];
        $out  = [];

        foreach ($inventaire as $nom => $n) {
            $m = $mouvements[$nom] ?? null;
            $ligne = [
                'entreeKg' => 0.0, 'entreeLitres' => 0.0,
                'sortieKg' => 0.0, 'sortieLitres' => 0.0,
                'nbEntrees' => 0, 'nbSorties' => 0,
                'stockInitialKg' => (float) ($n['stockInitialKg'] ?? 0),
                'stockInitialLitres' => (float) ($n['stockInitialLitres'] ?? 0),
                'stockFinalKg' => (float) ($n['stockFinalKg'] ?? 0),
                'stockFinalLitres' => (float) ($n['stockFinalLitres'] ?? 0),
            ];
            foreach ($cles as $c) {
                $ligne[$c] = $m !== null ? (float) ($m[$c] ?? 0) : (float) ($n[$c] ?? 0);
            }
            if ($m !== null) {
                $ligne['nbEntrees'] = (int) ($m['nbEntrees'] ?? 0);
                $ligne['nbSorties'] = (int) ($m['nbSorties'] ?? 0);
            } else {
                $ligne['nbEntrees'] = (int) ($n['nbEntrees'] ?? 0);
                $ligne['nbSorties'] = (int) ($n['nbSorties'] ?? 0);
            }
            $out[$nom] = $ligne;
        }

        // Natures presentes uniquement dans les mouvements filtres.
        foreach ($mouvements as $nom => $m) {
            if (isset($out[$nom])) {
                continue;
            }
            $out[$nom] = [
                'entreeKg' => (float) ($m['entreeKg'] ?? 0), 'entreeLitres' => (float) ($m['entreeLitres'] ?? 0),
                'sortieKg' => (float) ($m['sortieKg'] ?? 0), 'sortieLitres' => (float) ($m['sortieLitres'] ?? 0),
                'nbEntrees' => (int) ($m['nbEntrees'] ?? 0), 'nbSorties' => (int) ($m['nbSorties'] ?? 0),
                'stockInitialKg' => 0.0, 'stockInitialLitres' => 0.0,
                'stockFinalKg' => 0.0, 'stockFinalLitres' => 0.0,
            ];
        }

        return $out;
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

        // Stock initial (inventaire physique) et stock final :
        // stock final = quantite inventoriee + mouvements posterieurs a l'inventaire.
        $stockInitial = $this->stockInitial->apercu(null, $filtres['rubrique'] !== '' ? $filtres['rubrique'] : null);

        return $this->render('saisie_stock/index.html.twig', [
            'stockInitial' => $stockInitial,
            'saisies'  => $saisies,
            'sorties'  => $sorties,
            'filtres'  => $filtres,
            'stats'    => $this->statistiques($saisies, $sorties, $filtres['rubrique'], $stockInitial),
            'rubriques' => $this->referentiel->rubriques(),
            'natures'   => $this->referentiel->natures(),
            'unites'   => $this->referentiel->unites(),
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


    /**
     * Lignes du tableau "Situation par type de stock" pour une rubrique :
     * une ligne en Kg, une en Litres, selon les mouvements et l'inventaire.
     *
     * @param array<string, mixed>      $ligne
     * @param array<string, mixed>|null $detail
     *
     * @return list<list<string>>
     */
    private function lignesSituation(string $rubrique, array $ligne, ?array $detail, bool $initialise): array
    {
        $num = static fn (float $v): string => number_format($v, 2, ',', ' ');

        $unites = [
            ['Kg', 'entreeKg', 'sortieKg', 'stockInitialKg', 'stockFinalKg'],
            ['Litres', 'entreeLitres', 'sortieLitres', 'stockInitialLitres', 'stockFinalLitres'],
        ];

        $lignes = [];
        foreach ($unites as [$unite, $cleEntree, $cleSortie, $cleInitial, $cleFinal]) {
            $entree = (float) ($ligne[$cleEntree] ?? 0);
            $sortie = (float) ($ligne[$cleSortie] ?? 0);
            $initial = (float) ($detail[$cleInitial] ?? 0);
            $final = (float) ($detail[$cleFinal] ?? 0);

            $mouvement = abs($entree) > 0.000001 || abs($sortie) > 0.000001;
            $stock = $initialise && (abs($initial) > 0.000001 || abs($final) > 0.000001);
            if (!$mouvement && !$stock) {
                continue;
            }

            $row = [
                $rubrique,
                $num($entree) . ' ' . $unite,
                $num($sortie) . ' ' . $unite,
                $unite,
            ];
            if ($initialise) {
                $row[] = $num($initial);
                $row[] = $num($final);
            }
            $lignes[] = $row;
        }

        return $lignes;
    }

    /**
     * Export PDF de la consultation : criteres de filtre, recapitulatif de la
     * periode (tableau 1) puis detail des saisies (tableau 2).
     */
    #[Route('/saisies/export.pdf', name: 'app_saisies_stock_pdf', methods: ['GET'])]
    public function exportPdf(
        Request $request,
        pdfService $pdfService,
        SaisieStockRepository $repo,
        SortieStockRepository $sortieRepo
    ): Response {
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

        $stats = $this->statistiques($saisies, $sorties, $filtres['rubrique']);

        // ---------- 1. Criteres de filtre ----------
        $periode = 'Toutes les periodes';
        if ($filtres['du'] !== '' || $filtres['au'] !== '') {
            $du      = $filtres['du'] !== '' ? $this->dateFr($filtres['du']) : '...';
            $au      = $filtres['au'] !== '' ? $this->dateFr($filtres['au']) : '...';
            $periode = 'du ' . $du . ' au ' . $au;
        }

        $criteres = [
            ['Periode', $periode],
            ['Rubrique', $filtres['rubrique'] !== '' ? $filtres['rubrique'] : 'Toutes les rubriques'],
            ['Nature du produit', $filtres['nature'] !== '' ? $filtres['nature'] : 'Toutes les natures'],
            ['Service', $this->libelleService($filtres['service'])],
            ['Agent', $filtres['agent'] !== '' ? $filtres['agent'] : 'Tous les agents'],
            ['Recherche libre', $filtres['q'] !== '' ? $filtres['q'] : '-'],
            ['Volume', count($saisies) . ' saisie(s) et ' . count($sorties) . ' sortie(s)'],
        ];

        // ---------- 2. Situation par type de stock (comme a l'ecran) ----------
        // Memes colonnes que le tableau de consultation : Type Stock, Entrees,
        // Sorties, Unite, puis Stock initial / Stock final quand l'inventaire
        // physique est renseigne.
        $stockInitial = $this->stockInitial->apercu(null, $filtres['rubrique'] !== '' ? $filtres['rubrique'] : null);
        $initialise = !empty($stockInitial['initialise']);

        $num = static fn (float $v): string => number_format($v, 2, ',', ' ');

        $entetesRecap = ['Type Stock', 'Entrees', 'Sorties', 'Unite'];
        $poidsRecap = [40, 22, 22, 16];
        $alignRecap = ['L', 'R', 'R', 'C'];
        if ($initialise) {
            $entetesRecap[] = 'Stock initial';
            $entetesRecap[] = 'Stock final';
            $poidsRecap = [32, 17, 17, 12, 11, 11];
            $alignRecap = ['L', 'R', 'R', 'C', 'R', 'R'];
        }

        $lignesRecap = [];
        foreach ($stats['parRubrique'] as $rubrique => $ligne) {
            $detail = $stockInitial['parRubrique'][$rubrique] ?? null;
            foreach ($this->lignesSituation((string) $rubrique, $ligne, is_array($detail) ? $detail : null, $initialise) as $l) {
                $lignesRecap[] = $l;
            }
        }

        // Totaux : une ligne par unite, comme le pied du tableau a l'ecran.
        $totaux = $stats['totaux'];
        if ($initialise) {
            $siTotaux = $stockInitial['totaux'] ?? [];
            $lignesRecap[] = [
                'TOTAL',
                $num((float) $totaux['entreeKg']) . ' Kg',
                $num((float) $totaux['sortieKg']) . ' Kg',
                'Kg',
                $num((float) ($siTotaux['stockInitialKg'] ?? 0)),
                $num((float) ($siTotaux['stockFinalKg'] ?? 0)),
            ];
            if (abs((float) $totaux['entreeLitres']) > 0.000001 || abs((float) $totaux['sortieLitres']) > 0.000001) {
                $lignesRecap[] = [
                    '',
                    $num((float) $totaux['entreeLitres']) . ' Litres',
                    $num((float) $totaux['sortieLitres']) . ' Litres',
                    'Litres',
                    $num((float) ($siTotaux['stockInitialLitres'] ?? 0)),
                    $num((float) ($siTotaux['stockFinalLitres'] ?? 0)),
                ];
            }
        } else {
            $lignesRecap[] = [
                'TOTAL',
                $num((float) $totaux['entreeKg']) . ' Kg',
                $num((float) $totaux['sortieKg']) . ' Kg',
                'Kg',
            ];
            if (abs((float) $totaux['entreeLitres']) > 0.000001 || abs((float) $totaux['sortieLitres']) > 0.000001) {
                $lignesRecap[] = [
                    '',
                    $num((float) $totaux['entreeLitres']) . ' Litres',
                    $num((float) $totaux['sortieLitres']) . ' Litres',
                    'Litres',
                ];
            }
        }

        // ---------- 3. Detail des entrees (saisies) ----------
        $lignesDetail = [];
        foreach ($saisies as $s) {
            /** @var SaisieStock $s */
            $lignesDetail[] = [
                $s->getDateSaisie()?->format('d/m/Y H:i') ?? '-',
                $s->getRubrique() ?? '-',
                $s->getNature() ?? '-',
                $s->getServiceLibelle() ?? '-',
                $s->getAgentLibelle() ?? '-',
                number_format((float) $s->getQuantite(), 2, ',', ' ') . ' ' . ($s->getUnite() ?? ''),
                $s->getPhoto() ? 'Oui' : '-',
                $s->getCreatedAt()?->format('d/m/Y H:i') ?? '-',
            ];
        }

        // ---------- 4. Detail des sorties ----------
        $lignesSorties = [];
        foreach ($sorties as $o) {
            /** @var SortieStock $o */
            $lignesSorties[] = [
                $o->getDateSortie()?->format('d/m/Y H:i') ?? '-',
                $o->getRubrique() ?? '-',
                $o->getNature() ?? '-',
                $o->getMotif() ?? '-',
                $o->getServiceLibelle() ?? '-',
                $o->getAgentLibelle() ?? '-',
                number_format((float) $o->getQuantite(), 2, ',', ' ') . ' ' . ($o->getUnite() ?? ''),
                $o->getPhoto() ? 'Oui' : '-',
            ];
        }

        $contenu = $pdfService->genererRapport(
            'Consultation des stocks',
            'Periode : ' . $periode,
            $criteres,
            [
                [
                    'titre'   => 'Situation par type de stock',
                    'entetes' => $entetesRecap,
                    'poids'   => $poidsRecap,
                    'lignes'  => $lignesRecap,
                    'align'   => $alignRecap,
                ],
                [
                    'titre'   => 'Entrees de stock (saisies)',
                    'entetes' => ['Date saisie', 'Type Stock', 'Nature', 'Service', 'Agent', 'Quantite', 'Photo', 'Recu le'],
                    'poids'   => [16, 15, 18, 15, 15, 11, 6, 14],
                    'lignes'  => $lignesDetail,
                    'align'   => ['L', 'L', 'L', 'L', 'L', 'R', 'C', 'L'],
                ],
                [
                    'titre'   => 'Sorties de stock',
                    'entetes' => ['Date sortie', 'Type Stock', 'Nature', 'Motif', 'Service', 'Agent', 'Quantite', 'Photo'],
                    'poids'   => [16, 15, 16, 16, 14, 14, 12, 7],
                    'lignes'  => $lignesSorties,
                    'align'   => ['L', 'L', 'L', 'L', 'L', 'L', 'R', 'C'],
                ],
            ],
            'P'
        );

        return new Response($contenu, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="consultation-stocks.pdf"',
        ]);
    }

    /** Date AAAA-MM-JJ -> JJ/MM/AAAA (pour l'affichage PDF). */
    private function dateFr(string $date): string
    {
        try {
            return (new \DateTimeImmutable($date))->format('d/m/Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    /** Libelle du service filtre (le filtre contient son identifiant). */
    private function libelleService(string $serviceId): string
    {
        if ($serviceId === '') {
            return 'Tous les services';
        }

        foreach ($this->services() as $service) {
            if ((string) $service->getId() === $serviceId) {
                return (string) $service->getLibelle();
            }
        }

        return $serviceId;
    }
}
