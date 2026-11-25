<?php

namespace App\Repository\Production;

use App\Entity\Production\LigneOrdreProduction;
use App\Entity\Production\Lot;
use App\Entity\Production\OrdreProduction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LigneOrdreProduction>
 */
class LigneOrdreProductionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LigneOrdreProduction::class);
    }

    /** Un couple produit fini x feve existe-t-il deja (hors ordre en cours) ? */
    public function coupleExiste(Lot $fini, Lot $feve, ?OrdreProduction $sauf = null): bool
    {
        $qb = $this->createQueryBuilder('lg')
            ->select('COUNT(lg.id)')
            ->andWhere('lg.lotFini = :fini')
            ->andWhere('lg.lotFeve = :feve')
            ->setParameter('fini', $fini)
            ->setParameter('feve', $feve);

        if (null !== $sauf) {
            $qb->andWhere('lg.ordre <> :sauf')->setParameter('sauf', $sauf);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * @return array{lignes: int, finis: int, feves: int}
     */
    public function totaux(): array
    {
        $row = $this->createQueryBuilder('lg')
            ->select('COUNT(lg.id) AS lignes, COUNT(DISTINCT IDENTITY(lg.lotFini)) AS finis, COUNT(DISTINCT IDENTITY(lg.lotFeve)) AS feves')
            ->getQuery()
            ->getSingleResult();

        return [
            'lignes' => (int) ($row['lignes'] ?? 0),
            'finis' => (int) ($row['finis'] ?? 0),
            'feves' => (int) ($row['feves'] ?? 0),
        ];
    }
}
