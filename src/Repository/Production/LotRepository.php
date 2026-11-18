<?php

namespace App\Repository\Production;

use App\Entity\Logistique\Magasin;
use App\Entity\Logistique\Proprietaire;
use App\Entity\Production\Lot;
use App\Entity\Production\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Lot>
 */
class LotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lot::class);
    }

    /**
     * Lots filtres pour l'ecran de production, avec produit / proprietaire /
     * magasin deja charges (une seule requete, pas de N+1).
     *
     * @param array{q?: string, proprietaire?: Proprietaire|null, produit?: Produit|null, magasin?: Magasin|null, situation?: string} $filtres
     *
     * @return Lot[]
     */
    public function rechercher(array $filtres, int $limite = 25, int $decalage = 0): array
    {
        return $this->qbFiltre($filtres)
            ->addSelect('p', 'pr', 'm')
            ->orderBy('l.numero_lot', 'DESC')
            ->setFirstResult(max(0, $decalage))
            ->setMaxResults(max(1, $limite))
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{q?: string, proprietaire?: Proprietaire|null, produit?: Produit|null, magasin?: Magasin|null, situation?: string} $filtres
     */
    public function compterFiltres(array $filtres): int
    {
        return (int) $this->qbFiltre($filtres)
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Cumuls de la selection courante : nombre de lots, poids net theorique,
     * lots non conformes, lots issus d'une scission.
     *
     * @param array{q?: string, proprietaire?: Proprietaire|null, produit?: Produit|null, magasin?: Magasin|null, situation?: string} $filtres
     *
     * @return array{nb: int, pn: float, nc: int, scindes: int}
     */
    public function cumuls(array $filtres): array
    {
        $nb = $this->compterFiltres($filtres);

        $pn = (float) $this->qbFiltre($filtres)
            ->select('COALESCE(SUM(l.pn_th), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        $nc = (int) $this->qbFiltre($filtres)
            ->select('COUNT(l.id)')
            ->andWhere("UPPER(l.qualite) = 'NC'")
            ->getQuery()
            ->getSingleScalarResult();

        $scindes = (int) $this->qbFiltre($filtres)
            ->select('COUNT(l.id)')
            ->andWhere('l.scission = true')
            ->getQuery()
            ->getSingleScalarResult();

        return ['nb' => $nb, 'pn' => $pn, 'nc' => $nc, 'scindes' => $scindes];
    }

    /**
     * Totaux portant sur l'ensemble du referentiel des lots.
     *
     * @return array{nb: int, pn: float, nc: int}
     */
    public function totauxGlobaux(): array
    {
        $row = $this->createQueryBuilder('l')
            ->select("COUNT(l.id) AS nb, COALESCE(SUM(l.pn_th), 0) AS pn, SUM(CASE WHEN UPPER(l.qualite) = 'NC' THEN 1 ELSE 0 END) AS nc")
            ->getQuery()
            ->getArrayResult()[0] ?? [];

        return [
            'nb' => (int) ($row['nb'] ?? 0),
            'pn' => (float) ($row['pn'] ?? 0),
            'nc' => (int) ($row['nc'] ?? 0),
        ];
    }

    /**
     * Un numero de lot est-il deja pris ? (comparaison insensible a la casse)
     */
    public function numeroDejaUtilise(string $numero, ?Uuid $sauf = null): bool
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('UPPER(l.numero_lot) = :numero')
            ->setParameter('numero', mb_strtoupper(trim($numero)));

        if (null !== $sauf) {
            $qb->andWhere('l.id <> :sauf')->setParameter('sauf', (string) $sauf, 'uuid');
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Suffixes numeriques deja utilises pour une base de numero (BASE-1, BASE-2...).
     *
     * @return int[]
     */
    public function suffixesUtilises(string $base): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.numero_lot')
            ->andWhere('UPPER(l.numero_lot) LIKE :prefixe')
            ->setParameter('prefixe', mb_strtoupper($base) . '-%')
            ->getQuery()
            ->getArrayResult();

        $suffixes = [];
        foreach ($rows as $row) {
            if (preg_match('/-(\d+)$/', (string) ($row['numero_lot'] ?? ''), $m)) {
                $suffixes[] = (int) $m[1];
            }
        }

        return $suffixes;
    }

    /**
     * @return Lot[]
     */
    public function pourSelection(?string $id): array
    {
        if (null === $id || !Uuid::isValid($id)) {
            return [];
        }

        return $this->createQueryBuilder('l')
            ->addSelect('p', 'pr', 'm')
            ->leftJoin('l.code_produit', 'p')
            ->leftJoin('l.code_proprio', 'pr')
            ->leftJoin('l.code_magasin', 'm')
            ->andWhere('l.id = :id')
            ->setParameter('id', Uuid::fromString($id), 'uuid')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array{q?: string, proprietaire?: Proprietaire|null, produit?: Produit|null, magasin?: Magasin|null, situation?: string} $filtres
     */
    private function qbFiltre(array $filtres): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.code_produit', 'p')
            ->leftJoin('l.code_proprio', 'pr')
            ->leftJoin('l.code_magasin', 'm');

        $terme = trim((string) ($filtres['q'] ?? ''));
        if ('' !== $terme) {
            $qb->andWhere('UPPER(l.numero_lot) LIKE :terme OR UPPER(l.qualite) LIKE :terme OR UPPER(p.libelle) LIKE :terme OR UPPER(pr.denomination) LIKE :terme')
                ->setParameter('terme', '%' . mb_strtoupper($terme) . '%');
        }

        if (($filtres['proprietaire'] ?? null) instanceof Proprietaire) {
            $qb->andWhere('pr = :proprietaire')->setParameter('proprietaire', $filtres['proprietaire']);
        }
        if (($filtres['produit'] ?? null) instanceof Produit) {
            $qb->andWhere('p = :produit')->setParameter('produit', $filtres['produit']);
        }
        if (($filtres['magasin'] ?? null) instanceof Magasin) {
            $qb->andWhere('m = :magasin')->setParameter('magasin', $filtres['magasin']);
        }

        switch ((string) ($filtres['situation'] ?? 'tous')) {
            case 'avec_camion':
                $qb->andWhere('EXISTS (SELECT ld1.id FROM App\\Entity\\Stock\\LotDechargement ld1 WHERE ld1.lot = l)');
                break;
            case 'sans_camion':
                $qb->andWhere('NOT EXISTS (SELECT ld2.id FROM App\\Entity\\Stock\\LotDechargement ld2 WHERE ld2.lot = l)');
                break;
            case 'nc':
                $qb->andWhere("UPPER(l.qualite) = 'NC'");
                break;
            case 'conforme':
                $qb->andWhere("UPPER(l.qualite) = 'C'");
                break;
            case 'scinde':
                $qb->andWhere('l.scission = true');
                break;
        }

        return $qb;
    }
}
