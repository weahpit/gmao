<?php

namespace App\Repository;

use App\Entity\BonSortie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BonSortie>
 */
class BonSortieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BonSortie::class);
    }

    public function findOneByReference(string $reference): ?BonSortie
    {
        return $this->findOneBy(['reference' => $reference]);
    }

    public function findOneByToken(string $token): ?BonSortie
    {
        return $this->findOneBy(['token' => $token]);
    }
}
