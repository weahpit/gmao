<?php

namespace App\Repository\Reunion;

use App\Entity\Reunion\Reunion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reunion>
 */
class ReunionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reunion::class);
    }

    /** Toutes les reunions, la plus recente d'abord. */
    public function findToutes(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Reunion retrouvee par le jeton de son lien public. */
    public function findParJeton(string $jeton): ?Reunion
    {
        return $this->findOneBy(['jeton' => $jeton]);
    }

    /** Reunion actuellement ouverte aux inscriptions (la plus recente). */
    public function findActive(): ?Reunion
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.statut = :statut')
            ->setParameter('statut', Reunion::STATUT_OUVERTE)
            ->orderBy('r.dateDebut', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
