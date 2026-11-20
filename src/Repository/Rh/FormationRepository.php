<?php

namespace App\Repository\Rh;

use App\Entity\Rh\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Formation>
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    public function findToutes(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAVenir(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.dateDebut >= :aujourdhui')
            ->setParameter('aujourdhui', new \DateTimeImmutable('today'))
            ->andWhere('f.statut != :annulee')
            ->setParameter('annulee', Formation::ANNULEE)
            ->orderBy('f.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function coutTotalAnnee(int $annee): float
    {
        $total = $this->createQueryBuilder('f')
            ->select('SUM(f.cout)')
            ->andWhere('f.dateDebut >= :debut')
            ->andWhere('f.dateDebut <= :fin')
            ->andWhere('f.statut != :annulee')
            ->setParameter('debut', new \DateTimeImmutable($annee . '-01-01'))
            ->setParameter('fin', new \DateTimeImmutable($annee . '-12-31'))
            ->setParameter('annulee', Formation::ANNULEE)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($total ?? 0);
    }
}
