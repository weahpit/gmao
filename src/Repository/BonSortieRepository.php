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

    /**
     * Bons signés par le Directeur Général (statut VALIDE ou CLOTURE),
     * triés du plus récemment signé au plus ancien.
     *
     * @param \DateTimeInterface|null $du    borne basse sur la date de signature
     * @param \DateTimeInterface|null $au    borne haute sur la date de signature
     * @return BonSortie[]
     */
    public function findSignes(?\DateTimeInterface $du = null, ?\DateTimeInterface $au = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.statut IN (:statuts)')
            ->setParameter('statuts', [BonSortie::STATUT_VALIDE, BonSortie::STATUT_CLOTURE])
            ->orderBy('b.signe_at', 'DESC');

        if ($du !== null) {
            $qb->andWhere('b.signe_at >= :du')->setParameter('du', $du);
        }
        if ($au !== null) {
            $qb->andWhere('b.signe_at <= :au')->setParameter('au', $au);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Bons en attente de signature du Directeur Général (statut EN_ATTENTE),
     * triés du plus récemment créé au plus ancien.
     *
     * @param \DateTimeInterface|null $du    borne basse sur la date de création
     * @param \DateTimeInterface|null $au    borne haute sur la date de création
     * @return BonSortie[]
     */
    public function findEnAttente(?\DateTimeInterface $du = null, ?\DateTimeInterface $au = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.statut = :statut')
            ->setParameter('statut', BonSortie::STATUT_EN_ATTENTE)
            ->orderBy('b.created_at', 'DESC');

        if ($du !== null) {
            $qb->andWhere('b.created_at >= :du')->setParameter('du', $du);
        }
        if ($au !== null) {
            $qb->andWhere('b.created_at <= :au')->setParameter('au', $au);
        }

        return $qb->getQuery()->getResult();
    }
}
