<?php

namespace App\Controller\Stock;

use App\Entity\Production\Lot;
use App\Repository\Production\LotRepository;
use App\Repository\Production\ProduitRepository;
use App\Repository\Stock\LotDechargementRepository;
use App\Service\Production\NatureLot;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * ORIGINE DU PRODUIT.
 *
 * Objectif : repondre a « d'ou vient ce produit fini ? » a partir d'une
 * selection de lots. L'operateur filtre les produits (masse, beurre, tourteau,
 * poudre), les coche, lance la recherche et obtient la liste des CAMIONS
 * DISTINCTS qui ont servi a fabriquer l'ensemble de la selection.
 *
 * Chaine exploitee : lot fini -> lots de feves CITRAC -> camions de
 * dechargement. Les camions sont fusionnes : un camion qui a alimente plusieurs
 * lots de feves n'apparait qu'une fois, avec le poids cumule et la liste des
 * lots concernes.
 */
#[Route('/Stock/OrigineProduit')]
#[IsGranted('ROLE_USER')]
final class OrigineProduitController extends AbstractController
{
    private const CANDIDATS_MAX = 200;

    private const MOTIFS_FINIS = ['%MASSE%', '%BEURRE%', '%TOURTEAU%', '%POUDRE%'];

    public function __construct(
        private readonly LotRepository $lots,
        private readonly ProduitRepository $produits,
        private readonly LotDechargementRepository $liens,
        private readonly NatureLot $nature,
    ) {
    }

    #[Route('', name: 'stock_origine_produit', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // ---------------- Filtres ----------------
        $terme = trim((string) $request->query->get('q', ''));
        $produitId = (string) $request->query->get('produit', '');
        $du = trim((string) $request->query->get('du', ''));
        $au = trim((string) $request->query->get('au', ''));
        $statut = (string) $request->query->get('statut', 'tous');
        if (!in_array($statut, ['tous', 'avec_feve', 'sans_feve'], true)) {
            $statut = 'tous';
        }

        $produit = null;
        if (Uuid::isValid($produitId)) {
            $produit = $this->produits->find($produitId);
        }

        // ---------------- Selection ----------------
        $ids = [];
        foreach ((array) $request->query->all('ids') as $id) {
            if (is_string($id) && Uuid::isValid($id)) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));

        // ---------------- Liste des produits proposes ----------------
        $qb = $this->lots->createQueryBuilder('l')
            ->leftJoin('l.code_produit', 'p')->addSelect('p')
            ->leftJoin('l.code_magasin', 'm')->addSelect('m');

        if ($produit instanceof \App\Entity\Production\Produit) {
            $qb->andWhere('p = :produit')->setParameter('produit', $produit);
        } else {
            $conditions = [];
            foreach (self::MOTIFS_FINIS as $i => $motif) {
                $conditions[] = 'p.libelle LIKE :fini' . $i;
                $qb->setParameter('fini' . $i, $motif);
            }
            $qb->andWhere('(' . implode(' OR ', $conditions) . ')');
        }

        if ($terme !== '') {
            $qb->andWhere('UPPER(l.numero_lot) LIKE :terme OR UPPER(p.libelle) LIKE :terme OR UPPER(m.libelle) LIKE :terme')
               ->setParameter('terme', '%' . mb_strtoupper($terme) . '%');
        }
        if ('' !== $du) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $du);
            if ($date instanceof \DateTimeImmutable) {
                $qb->andWhere('l.date_prod >= :du')->setParameter('du', $date->setTime(0, 0));
            }
        }
        if ('' !== $au) {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $au);
            if ($date instanceof \DateTimeImmutable) {
                $qb->andWhere('l.date_prod <= :au')->setParameter('au', $date->setTime(23, 59, 59));
            }
        }
        if ('avec_feve' === $statut) {
            $qb->andWhere('SIZE(l.lotsOrigine) > 0');
        } elseif ('sans_feve' === $statut) {
            $qb->andWhere('SIZE(l.lotsOrigine) = 0');
        }

        $candidats = $qb->orderBy('l.date_prod', 'DESC')
            ->addOrderBy('l.numero_lot', 'DESC')
            ->setMaxResults(self::CANDIDATS_MAX)
            ->getQuery()
            ->getResult();

        $proposes = [];
        $affiches = [];
        foreach ($candidats as $lot) {
            $affiches[] = (string) $lot->getId();
            $proposes[] = [
                'lot' => $lot,
                'feves' => $lot->getLotsOrigine()->count(),
                'qualite' => $this->nature->libelleLot($lot),
                'estNc' => 'NC' === mb_strtoupper((string) $lot->getQualite()),
            ];
        }

        // ---------------- Calcul de l'origine ----------------
        $selection = [];
        $sansFeve = [];
        $feves = [];
        $camions = [];

        foreach ($ids as $id) {
            $lot = $this->lots->find($id);
            if (!$lot instanceof Lot || !$this->nature->estFini($lot)) {
                continue;
            }
            $selection[(string) $lot->getId()] = $lot;

            if ($lot->getLotsOrigine()->isEmpty()) {
                $sansFeve[] = $lot;

                continue;
            }

            foreach ($lot->getLotsOrigine() as $feve) {
                $feves[(string) $feve->getId()] = $feve;

                foreach ($this->liens->parLot($feve) as $lien) {
                    $camion = $lien->getDechargement();
                    if (null === $camion) {
                        continue;
                    }

                    $cle = (string) $camion->getId();
                    if (!isset($camions[$cle])) {
                        $camions[$cle] = [
                            'camion' => $camion,
                            'poids' => 0.0,
                            'sacs' => 0,
                            'feves' => [],
                            'produits' => [],
                        ];
                    }

                    $camions[$cle]['poids'] += (float) ($lien->getPoidsAffecte() ?? $camion->getPoidsNet() ?? 0);
                    $camions[$cle]['sacs'] += (int) ($lien->getNbreSacs() ?? 0);
                    $camions[$cle]['feves'][(string) $feve->getId()] = (string) $feve->getNumeroLot();
                    $camions[$cle]['produits'][(string) $lot->getId()] = (string) $lot->getNumeroLot();
                }
            }
        }

        $camions = array_values($camions);
        usort($camions, static function (array $a, array $b): int {
            $da = $a['camion']->getDateDechargement();
            $db = $b['camion']->getDateDechargement();
            if ($da === $db) {
                return strcmp((string) $a['camion']->getCode(), (string) $b['camion']->getCode());
            }
            if (null === $da) {
                return 1;
            }
            if (null === $db) {
                return -1;
            }

            return $db <=> $da;
        });

        $poidsTotal = 0.0;
        $sacsTotal = 0;
        foreach ($camions as $ligne) {
            $poidsTotal += $ligne['poids'];
            $sacsTotal += $ligne['sacs'];
        }

        // Une selection peut depasser la liste affichee (filtres, limite de
        // 200 lignes) : on conserve les identifiants non visibles en champs
        // caches pour ne rien perdre au prochain envoi.
        $idsCaches = array_values(array_diff($ids, $affiches));

        return $this->render('stock/origine_produit.html.twig', [
            'proposes' => $proposes,
            'idsCaches' => $idsCaches,
            'selection' => array_values($selection),
            'ids' => $ids,
            'sansFeve' => $sansFeve,
            'feves' => array_values($feves),
            'camions' => $camions,
            'poidsTotal' => $poidsTotal,
            'sacsTotal' => $sacsTotal,
            'terme' => $terme,
            'produitChoisi' => $produit,
            'produits' => $this->produits->findBy([], ['libelle' => 'ASC']),
            'du' => $du,
            'au' => $au,
            'statut' => $statut,
            'rechercheFaite' => $ids !== [],
        ]);
    }
}
