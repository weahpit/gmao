<?php

namespace App\Repository\Maintenance;

use App\Entity\Maintenance\PlanMaintenance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanMaintenance>
 */
class PlanMaintenanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanMaintenance::class);
    }

    /** @return PlanMaintenance[] Plans actifs */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.actif = true')
            ->orderBy('p.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
