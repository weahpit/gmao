<?php

namespace App\Repository\Rh;

use App\Entity\Rh\HorairesPersonnalises;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HorairesPersonnalises>
 */
class HorairesPersonnalisesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HorairesPersonnalises::class);
    }
}
