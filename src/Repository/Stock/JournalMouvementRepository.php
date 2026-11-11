<?php

namespace App\Repository\Stock;

use App\Entity\Stock\JournalMouvement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JournalMouvement>
 */
class JournalMouvementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalMouvement::class);
    }

    /** Dernieres entrees du journal, de la plus recente a la plus ancienne. */
    public function findRecents(int $limite = 500): array
    {
        return $this->createQueryBuilder('j')
            ->orderBy('j.dateAction', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
