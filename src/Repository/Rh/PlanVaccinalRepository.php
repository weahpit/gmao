<?php

namespace App\Repository\Rh;

use App\Entity\Rh\PlanVaccinal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PlanVaccinal> */
class PlanVaccinalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanVaccinal::class);
    }

    /** @return PlanVaccinal[] */
    public function findTous(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.obligatoire', 'DESC')
            ->addOrderBy('p.vaccin', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function trouverParVaccin(string $vaccin): ?PlanVaccinal
    {
        return $this->createQueryBuilder('p')
            ->andWhere('LOWER(p.vaccin) = :vaccin')
            ->setParameter('vaccin', mb_strtolower(trim($vaccin)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function compter(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
