<?php

namespace App\Repository\Rh;

use App\Entity\Rh\Employe;
use App\Entity\Rh\Pointage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pointage>
 */
class PointageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pointage::class);
    }

    public function findDuJour(\DateTimeInterface $jour): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.employe', 'e')->addSelect('e')
            ->andWhere('p.jour = :jour')
            ->setParameter('jour', $jour)
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPourPeriode(\DateTimeInterface $debut, \DateTimeInterface $fin): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.employe', 'e')->addSelect('e')
            ->andWhere('p.jour BETWEEN :debut AND :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('p.jour', 'DESC')
            ->addOrderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findUnPourEmployeEtJour(Employe $employe, \DateTimeInterface $jour): ?Pointage
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.employe = :employe')
            ->andWhere('p.jour = :jour')
            ->setParameter('employe', $employe)
            ->setParameter('jour', $jour)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function compterPresents(\DateTimeInterface $jour): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.jour = :jour')
            ->andWhere('p.heureArrivee IS NOT NULL')
            ->setParameter('jour', $jour)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function totalRetardsMois(\DateTimeInterface $reference): array
    {
        return $this->createQueryBuilder('p')
            ->select('SUM(p.retardMinutes) AS minutes', 'SUM(p.heuresSupplementaires) AS heures')
            ->andWhere('p.jour BETWEEN :debut AND :fin')
            ->setParameter('debut', new \DateTimeImmutable($reference->format('Y-m-01')))
            ->setParameter('fin', new \DateTimeImmutable($reference->format('Y-m-t')))
            ->getQuery()
            ->getOneOrNullResult() ?? ['minutes' => 0, 'heures' => 0];
    }
}
