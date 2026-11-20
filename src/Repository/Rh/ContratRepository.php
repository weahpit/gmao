<?php

namespace App\Repository\Rh;

use App\Entity\Rh\Contrat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contrat>
 */
class ContratRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contrat::class);
    }

    /** @return Contrat[] */
    public function findRecents(int $limite = 50): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.employe', 'e')->addSelect('e')
            ->orderBy('c.dateDebut', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /** Contrats actifs dont la date de fin tombe dans les N prochains jours. */
    /** @return Contrat[] */
    public function findEcheances(int $jours = 60): array
    {
        $aujourdhui = new \DateTimeImmutable('today');
        $limite = $aujourdhui->modify('+' . $jours . ' days');

        return $this->createQueryBuilder('c')
            ->leftJoin('c.employe', 'e')->addSelect('e')
            ->andWhere('c.dateFin IS NOT NULL')
            ->andWhere('c.dateFin >= :aujourdhui')
            ->andWhere('c.dateFin <= :limite')
            ->andWhere('c.statut = :statut')
            ->setParameter('aujourdhui', $aujourdhui)
            ->setParameter('limite', $limite)
            ->setParameter('statut', Contrat::EN_COURS)
            ->orderBy('c.dateFin', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Contrat[] */
    public function findExpiresARegulariser(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.employe', 'e')->addSelect('e')
            ->andWhere('c.dateFin IS NOT NULL')
            ->andWhere('c.dateFin < :aujourdhui')
            ->andWhere('c.statut = :statut')
            ->setParameter('aujourdhui', new \DateTimeImmutable('today'))
            ->setParameter('statut', Contrat::EN_COURS)
            ->orderBy('c.dateFin', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function masseSalarialeMensuelle(): float
    {
        $total = $this->createQueryBuilder('c')
            ->select('SUM(c.salaire)')
            ->andWhere('c.statut = :statut')
            ->setParameter('statut', Contrat::EN_COURS)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($total ?? 0);
    }

    public function compterParType(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.type AS type', 'COUNT(c.id) AS total')
            ->andWhere('c.statut = :statut')
            ->setParameter('statut', Contrat::EN_COURS)
            ->groupBy('c.type')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['type']] = (int) $row['total'];
        }

        return $result;
    }

    public function prochaineReference(): string
    {
        $annee = date('Y');
        $nombre = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.reference LIKE :prefixe')
            ->setParameter('prefixe', 'CTR-' . $annee . '-%')
            ->getQuery()
            ->getSingleScalarResult();

        return 'CTR-' . $annee . '-' . str_pad((string) ($nombre + 1), 4, '0', STR_PAD_LEFT);
    }
}
