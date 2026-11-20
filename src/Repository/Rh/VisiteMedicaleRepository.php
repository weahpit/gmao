<?php

namespace App\Repository\Rh;

use App\Entity\Rh\VisiteMedicale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VisiteMedicale>
 */
class VisiteMedicaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VisiteMedicale::class);
    }

    /** @return VisiteMedicale[] */
    public function findRenouvellements(int $jours = 60): array
    {
        $limite = (new \DateTimeImmutable('today'))->modify('+' . $jours . ' days');

        return $this->createQueryBuilder('v')
            ->leftJoin('v.dossier', 'd')->addSelect('d')
            ->leftJoin('d.employe', 'e')->addSelect('e')
            ->andWhere('v.dateProchaine IS NOT NULL')
            ->andWhere('v.dateProchaine <= :limite')
            ->setParameter('limite', $limite)
            ->orderBy('v.dateProchaine', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findDernieres(int $limite = 30): array
    {
        return $this->createQueryBuilder('v')
            ->leftJoin('v.dossier', 'd')->addSelect('d')
            ->leftJoin('d.employe', 'e')->addSelect('e')
            ->orderBy('v.date', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    public function compterInaptitudes(): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.aptitude IN (:inaptes)')
            ->setParameter('inaptes', [VisiteMedicale::INAPTE_TEMPORAIRE, VisiteMedicale::INAPTE_DEFINITIF])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
