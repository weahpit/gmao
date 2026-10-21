<?php

namespace App\Repository\Maintenance;

use App\Entity\Maintenance\PlanMaintenanceOccurrence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanMaintenanceOccurrence>
 */
class PlanMaintenanceOccurrenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanMaintenanceOccurrence::class);
    }

    /**
     * Occurrences "a faire" dont la date prevue est <= date donnee (echeances dues).
     * @return PlanMaintenanceOccurrence[]
     */
    public function findDues(\DateTime $jusquAu): array
    {
        return $this->createQueryBuilder('o')
            ->join('o.plan', 'p')
            ->andWhere('o.statut = :s')->setParameter('s', PlanMaintenanceOccurrence::A_FAIRE)
            ->andWhere('o.date_prevue <= :d')->setParameter('d', $jusquAu)
            ->andWhere('p.actif = true')
            ->orderBy('o.date_prevue', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
