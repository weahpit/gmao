<?php

namespace App\Repository\Rh;

use App\Entity\Rh\AccidentTravail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccidentTravail>
 */
class AccidentTravailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccidentTravail::class);
    }

    public function compterAnnee(int $annee): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.date >= :debut')
            ->andWhere('a.date <= :fin')
            ->setParameter('debut', new \DateTimeImmutable($annee . '-01-01'))
            ->setParameter('fin', new \DateTimeImmutable($annee . '-12-31'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findRecents(int $limite = 20): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.dossier', 'd')->addSelect('d')
            ->leftJoin('d.employe', 'e')->addSelect('e')
            ->orderBy('a.date', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    public function compterJoursArretAnnee(int $annee): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COALESCE(SUM(a.joursArret), 0)')
            ->andWhere('a.date >= :debut')
            ->andWhere('a.date <= :fin')
            ->setParameter('debut', new \DateTimeImmutable($annee . '-01-01'))
            ->setParameter('fin', new \DateTimeImmutable($annee . '-12-31'))
            ->getQuery()
            ->getSingleScalarResult();
    }
}
