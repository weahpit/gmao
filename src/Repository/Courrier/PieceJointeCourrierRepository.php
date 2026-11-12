<?php

namespace App\Repository\Courrier;

use App\Entity\Courrier\PieceJointeCourrier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PieceJointeCourrier>
 */
class PieceJointeCourrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PieceJointeCourrier::class);
    }
}
