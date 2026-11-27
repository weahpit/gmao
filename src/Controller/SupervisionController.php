<?php

namespace App\Controller;

use App\Entity\Courrier\Courrier;
use App\Entity\DemandeAchat;
use App\Entity\Equipement;
use App\Entity\Logistique\Dossier;
use App\Entity\Maintenance\FicheIntervention;
use App\Entity\Stock\SaisieStock;
use App\Entity\Stock\SortieStock;
use App\Service\Stock\StockCacaoJourService;
use App\Service\Stock\StockInitialService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tableau de bord PUBLIC de supervision (page « mur »).
 *
 * Aucune connexion n'est demandee : l'acces est protege par un CODE PIN
 * fourni par l'administrateur, saisi une fois par poste (session) et
 * revalide a chaque appel de l'API de donnees.
 *
 * Le PIN accepte est, dans l'ordre :
 *   1. le parametre « supervision_pin » (config/services.yaml) ;
 *   2. la variable d'environnement CITRAC_SUPERVISION_PIN ;
 *   3. a defaut, le PIN administrateur de l'application (profil PIN).
 */
#[Route('/supervision')]
final class SupervisionController extends AbstractController
{
    private const SESSION_CLE = 'supervision_pin_ok';
    private const PIN_DEFAUT  = '1234';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StockInitialService $stockInitial,
        private readonly StockCacaoJourService $stockCacaoJour,
    ) {}

    #[Route('', name: 'app_supervision', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $session = $request->getSession();
        $erreur = null;

        if ($request->isMethod('POST')) {
            $code = trim((string) $request->request->get('pin', ''));
            if ($code !== '' && $this->pinValide($code)) {
                $session->set(self::SESSION_CLE, true);

                return $this->redirectToRoute('app_supervision');
            }
            $erreur = 'Code PIN incorrect.';
        }

        if ($session->get(self::SESSION_CLE) !== true) {
            return $this->render('supervision/pin.html.twig', ['erreur' => $erreur]);
        }

        return $this->render('supervision/index.html.twig', [
            'donnees' => $this->collecter($request),
        ]);
    }

    /** Donnees brutes (rafraichies toutes les 60 s par la page). */
    #[Route('/donnees', name: 'app_supervision_donnees', methods: ['GET'])]
    public function donnees(Request $request): Response
    {
        if ($request->getSession()->get(self::SESSION_CLE) !== true) {
            return $this->json(['code' => 'error', 'msg' => 'PIN requis'], 403);
        }

        return $this->json(['code' => 'success', 'data' => $this->collecter($request)]);
    }

    #[Route('/quitter', name: 'app_supervision_quitter', methods: ['GET', 'POST'])]
    public function quitter(Request $request): Response
    {
        $request->getSession()->remove(self::SESSION_CLE);

        return $this->redirectToRoute('app_supervision');
    }

    // ------------------------------------------------------------------
    //  Collecte des indicateurs (chaque module est isole : une erreur ne
    //  doit jamais empecher l'affichage des autres modules).
    // ------------------------------------------------------------------
    private function collecter(?Request $request = null): array
    {
        // Periode : 7, 14, 30 ou 60 jours (toute autre valeur retombe sur 7).
        $jours = (int) ($request?->query->get('j', 7));
        if (!in_array($jours, [7, 14, 30, 60], true)) { $jours = 7; }
        // Mode : MOVEMENTES (types qui bougent), CACAO (types cacao) ou TOUTES.
        $mode = strtoupper(trim((string) $request?->query->get('mode', 'TOUTES')));
        if (!in_array($mode, ['MOVEMENTES', 'CACAO', 'TOUTES'], true)) { $mode = 'TOUTES'; }

        return [
            'genere'    => (new \DateTimeImmutable())->format('d/m/Y H:i'),
            'gmao'      => $this->gmao(),
            'stock'     => $this->stock(),
            'cacao'     => $this->cacao(),
            'cacaoJour' => $this->cacaoJour($jours, $mode),
            'courrier'  => $this->courrier(),
            'achats'    => $this->achats(),
            'embarqu'   => $this->embarquement(),
            'visitesLots' => $this->visitesLots($jours),
        ];
    }

    private function gmao(): array
    {
        return $this->auCasOu(function (): array {
            $equipements = $this->em->getRepository(Equipement::class);
            $total = (int) $equipements->count([]);
            $enRupture = (int) $equipements->count(['qte' => 0]);
            $fiches = $this->em->getRepository(FicheIntervention::class);

            $enCours = 0;
            $prev = 0;
            $corr = 0;
            try {
                $enCours = (int) $fiches->createQueryBuilder('f')
                    ->select('count(f.id)')
                    ->where('f.date_fin_reel IS NULL')
                    ->getQuery()->getSingleScalarResult();
            } catch (\Throwable) {
                $enCours = (int) $fiches->count([]);
            }
            foreach (['PREVENTIVE' => 'prev', 'CORRECTIVE' => 'corr'] as $lib => $cle) {
                try {
                    $n = (int) $fiches->createQueryBuilder('f')
                        ->select('count(f.id)')
                        ->join('f.type_intervention', 't')
                        ->where('UPPER(t.libelle) = :l')
                        ->setParameter('l', $lib)
                        ->getQuery()->getSingleScalarResult();
                    if ($cle === 'prev') { $prev = $n; } else { $corr = $n; }
                } catch (\Throwable) {
                }
            }

            $alertesOuvertes = 0;
            try {
                $alertesOuvertes = (int) $alertes->count([]);
            } catch (\Throwable) {
            }

            return [
                'equipements' => $total,
                'rupture'     => $enRupture,
                'interventions' => $prev + $corr,
                'interventionsEnCours' => $enCours,
                'preventives' => $prev,
                'correctives' => $corr,
                'alertes'     => $alertesOuvertes,
            ];
        }, [
            'equipements' => 0, 'rupture' => 0, 'interventions' => 0,
            'interventionsEnCours' => 0, 'preventives' => 0, 'correctives' => 0, 'alertes' => 0,
        ]);
    }

    private function stock(): array
    {
        return $this->auCasOu(function (): array {
            $saisies = $this->em->getRepository(SaisieStock::class);
            $sorties = $this->em->getRepository(SortieStock::class);

            $entrees = (float) $saisies->createQueryBuilder('s')
                ->select('coalesce(sum(s.quantite), 0)')->getQuery()->getSingleScalarResult();
            $sortieQte = (float) $sorties->createQueryBuilder('s')
                ->select('coalesce(sum(s.quantite), 0)')
                ->where('s.statut <> :a')->setParameter('a', SortieStock::STATUT_ANNULE)
                ->getQuery()->getSingleScalarResult();
            $enAttente = (int) $sorties->count(['statut' => SortieStock::STATUT_EN_ATTENTE]);

            // Cinq rubriques les plus mouvementees (entrees).
            $parRubrique = [];
            try {
                $rows = $saisies->createQueryBuilder('s')
                    ->select('s.rubrique AS rubrique', 'sum(s.quantite) AS qte')
                    ->groupBy('s.rubrique')
                    ->orderBy('qte', 'DESC')
                    ->setMaxResults(6)
                    ->getQuery()->getArrayResult();
                foreach ($rows as $r) {
                    $parRubrique[mb_strtoupper((string) $r['rubrique'])] = round((float) $r['qte'], 2);
                }
            } catch (\Throwable) {
            }

            return [
                'entrees'     => round($entrees, 2),
                'sorties'     => round($sortieQte, 2),
                'enAttente'   => $enAttente,
                'parRubrique' => $parRubrique,
            ];
        }, ['entrees' => 0, 'sorties' => 0, 'enAttente' => 0, 'parRubrique' => []]);
    }

    /** Stocks cacao : stock final par rubrique (initialisation + mouvements). */
    private function cacao(): array
    {
        return $this->auCasOu(function (): array {
            $lignes = $this->stockInitial->stockFinal();
            $out = [];
            foreach ($lignes as $l) {
                $out[] = [
                    'rubrique' => (string) $l['rubrique'],
                    'kg'       => (float) ($l['stockFinal']['kg'] ?? 0),
                    'litres'   => (float) ($l['stockFinal']['litres'] ?? 0),
                ];
            }
            $sortiesCacao = 0;
            try {
                $sortiesCacao = (int) $this->em->getRepository(SortieStock::class)
                    ->count(['statut' => SortieStock::STATUT_EN_ATTENTE]);
            } catch (\Throwable) {
            }

            return ['lignes' => $out, 'sortiesEnAttente' => $sortiesCacao];
        }, ['lignes' => [], 'sortiesEnAttente' => 0]);
    }

    /**
     * Point du stock cacao par jour, par type (rubrique) et nature de produit.
     * Le calcul vit dans StockCacaoJourService (memes regles que la consultation).
     */
    private function cacaoJour(int $jours, ?string $mode): array
    {
        return $this->auCasOu(function () use ($jours, $mode): array {
            $data = $this->stockCacaoJour->pointDuJour($jours, $mode);

            // Types dont le point bouge : pour la synthese en haut du panneau.
            $variation = 0.0;
            foreach ($data['rubriques'] as $r) {
                $variation += (float) $r['variation'];
            }
            $data['variationPeriode'] = round($variation, 3);

            return $data;
        }, [
            'jours' => $jours, 'mode' => $mode ?: 'MOVEMENTES', 'dateDebut' => '', 'dateFin' => '',
            'duJour' => ['entrees' => 0.0, 'sorties' => 0.0, 'variation' => 0.0, 'stock' => 0.0],
            'periode' => ['entrees' => 0.0, 'sorties' => 0.0],
            'variationPeriode' => 0.0,
            'series' => [], 'rubriques' => [], 'natures' => [], 'litres' => 0.0, 'unite' => 'Kg',
        ]);
    }

    private function courrier(): array
    {
        return $this->auCasOu(function (): array {
            $repo = $this->em->getRepository(Courrier::class);
            $total = (int) $repo->count([]);
            $par = [];
            foreach (Courrier::STATUTS as $code => $libelle) {
                $par[$libelle] = (int) $repo->count(['statut' => $code]);
            }
            $entrants = (int) $repo->count(['sens' => Courrier::SENS_ENTRANT]);
            $sortants = (int) $repo->count(['sens' => Courrier::SENS_SORTANT]);

            return [
                'total'    => $total,
                'entrants' => $entrants,
                'sortants' => $sortants,
                'parStatut' => $par,
            ];
        }, ['total' => 0, 'entrants' => 0, 'sortants' => 0, 'parStatut' => []]);
    }

    private function achats(): array
    {
        return $this->auCasOu(function (): array {
            $repo = $this->em->getRepository(DemandeAchat::class);
            $par = [];
            $total = 0;
            foreach (DemandeAchat::LIBELLES as $code => $libelle) {
                $n = (int) $repo->count(['statut' => $code]);
                if ($n > 0) { $par[$libelle] = $n; }
                $total += $n;
            }

            return ['total' => $total, 'parStatut' => $par];
        }, ['total' => 0, 'parStatut' => []]);
    }

    private function embarquement(): array
    {
        return $this->auCasOu(function (): array {
            $repo = $this->em->getRepository(Dossier::class);
            $total = (int) $repo->count([]);
            $clotures = (int) $repo->count(['cloture' => true]);

            $tonnage = 0.0;
            try {
                $tonnage = (float) $repo->createQueryBuilder('d')
                    ->select('coalesce(sum(d.poids_sigam), 0)')
                    ->getQuery()->getSingleScalarResult();
            } catch (\Throwable) {
                try {
                    $tonnage = (float) $repo->createQueryBuilder('d')
                        ->select('coalesce(sum(d.poids_vgm), 0)')
                        ->getQuery()->getSingleScalarResult();
                } catch (\Throwable) {
                }
            }

            return [
                'dossiers'  => $total,
                'clotures'  => $clotures,
                'enCours'   => max(0, $total - $clotures),
                'tonnage'   => round($tonnage, 2),
            ];
        }, ['dossiers' => 0, 'clotures' => 0, 'enCours' => 0, 'tonnage' => 0.0]);
    }

    /** Execute une lecture de donnees sans jamais faire echouer la page. */
    private function auCasOu(callable $fn, array $defaut): array
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            $defaut['erreur'] = $e->getMessage();
            return $defaut;
        }
    }

    /** Verifie le code PIN de supervision. */
    private function pinValide(string $code): bool
    {
        $candidats = [];

        try {
            $candidats[] = (string) $this->getParameter('supervision_pin');
        } catch (\Throwable) {
        }
        $env = (string) ($_ENV['CITRAC_SUPERVISION_PIN'] ?? $_SERVER['CITRAC_SUPERVISION_PIN'] ?? '');
        if ($env !== '') { $candidats[] = $env; }

        foreach ($candidats as $attendu) {
            $attendu = trim($attendu);
            if ($attendu !== '' && hash_equals($attendu, $code)) {
                return true;
            }
        }

        // Repli : PIN administrateur de l'application s'il existe.
        try {
            $pinApp = $this->getParameter('pin_admin');
            if (is_string($pinApp) && $pinApp !== '' && hash_equals($pinApp, $code)) {
                return true;
            }
        } catch (\Throwable) {
        }

        // Dernier repli : code par defaut, a changer en production.
        return hash_equals(self::PIN_DEFAUT, $code) && $candidats === [];
    }

    private function visitesLots(int $jours): array {
        return $this->auCasOu(function() use($jours): array {
            $debut=new \DateTimeImmutable('today -'.($jours-1).' days');$fin=new \DateTimeImmutable('tomorrow');$out=[];
            for($j=$debut;$j<$fin;$j=$j->modify('+1 day'))$out[$j->format('Y-m-d')]=['jour'=>$j->format('d/m'),'visites'=>0,'lots'=>0];
            foreach([\App\Entity\Visite\Visite::class=>['heureEntree','visites'],\App\Entity\Production\Lot::class=>['date_prod','lots']] as $classe=>[$field,$cle]){
                $m=$this->em->getClassMetadata($classe);$p=$this->em->getConnection()->getDatabasePlatform();$q=static fn($v)=>$p->quoteIdentifier($v);$t=$m->getSchemaName()&&!str_contains($m->getTableName(),'.')?$m->getSchemaName().'.'.$m->getTableName():$m->getTableName();$date=$q($m->getColumnName($field));
                foreach($this->em->getConnection()->fetchAllAssociative('SELECT CAST('.$date.' AS DATE) AS jour,COUNT(*) AS n FROM '.$q($t).' WHERE '.$date.' >= :du AND '.$date.' < :au GROUP BY CAST('.$date.' AS DATE)',['du'=>$debut->format('Y-m-d'),'au'=>$fin->format('Y-m-d')]) as $row)if(isset($out[$row['jour']]))$out[$row['jour']][$cle]=(int)$row['n'];
            }return ['series'=>array_values($out)];
        },['series'=>[]]);
    }

}
