<?php

namespace App\Repository\Rh;

use App\Entity\Rh\OffreEmploi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OffreEmploi>
 */
class OffreEmploiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OffreEmploi::class);
    }

    public function findToutes(): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.service', 's')->addSelect('s')
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPubliees(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.statut = :statut')
            ->setParameter('statut', OffreEmploi::PUBLIEE)
            ->orderBy('o.dateCloture', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAValider(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.statut = :statut')
            ->setParameter('statut', OffreEmploi::SOUMISE)
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function prochaineReference(): string
    {
        $annee = date('Y');
        $nombre = (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.reference LIKE :prefixe')
            ->setParameter('prefixe', 'OFF-' . $annee . '-%')
            ->getQuery()
            ->getSingleScalarResult();

        return 'OFF-' . $annee . '-' . str_pad((string) ($nombre + 1), 3, '0', STR_PAD_LEFT);
    }
}
