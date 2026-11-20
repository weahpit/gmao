<?php

namespace App\Repository\Rh;

use App\Entity\Rh\Absence;
use App\Entity\Rh\Employe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Absence>
 */
class AbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Absence::class);
    }

    public function findRecentes(int $limite = 100): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.employe', 'e')->addSelect('e')
            ->orderBy('a.dateDebut', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    public function findEnAttente(): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.employe', 'e')->addSelect('e')
            ->andWhere('a.statut IN (:statuts)')
            ->setParameter('statuts', [Absence::SOUMIS, Absence::VALIDE_CHEF])
            ->orderBy('a.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function compterEnAttente(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.statut IN (:statuts)')
            ->setParameter('statuts', [Absence::SOUMIS, Absence::VALIDE_CHEF])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Deteche un chevauchement de dates pour un employe.
     * $exclureId permet de modifier une demande existante sans se bloquer soi-meme.
     */
    public function findChevauchement(Employe $employe, \DateTimeInterface $debut, \DateTimeInterface $fin, ?string $exclureId = null): ?Absence
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.employe = :employe')
            ->andWhere('a.statut != :refuse')
            ->andWhere('a.dateDebut <= :fin')
            ->andWhere('a.dateFin >= :debut')
            ->setParameter('employe', $employe)
            ->setParameter('refuse', Absence::REFUSE)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->setMaxResults(1);

        if ($exclureId !== null) {
            $qb->andWhere('a.id != :id')->setParameter('id', $exclureId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /** Jours d'absence valides par type sur une annee. */
    public function joursParType(int $annee): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.type AS type', 'SUM(a.nombreJours) AS jours')
            ->andWhere('a.statut = :statut')
            ->andWhere('a.dateDebut >= :debut')
            ->andWhere('a.dateDebut <= :fin')
            ->setParameter('statut', Absence::VALIDE_RH)
            ->setParameter('debut', new \DateTimeImmutable($annee . '-01-01'))
            ->setParameter('fin', new \DateTimeImmutable($annee . '-12-31'))
            ->groupBy('a.type')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['type']] = (int) $row['jours'];
        }

        return $result;
    }

    public function totalJoursValides(\DateTimeInterface $debut, \DateTimeInterface $fin): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COALESCE(SUM(a.nombreJours), 0)')
            ->andWhere('a.statut = :statut')
            ->andWhere('a.dateDebut >= :debut')
            ->andWhere('a.dateDebut <= :fin')
            ->setParameter('statut', Absence::VALIDE_RH)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function soldeCongesAnnuel(Employe $employe, int $annee): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COALESCE(SUM(a.nombreJours), 0)')
            ->andWhere('a.employe = :employe')
            ->andWhere('a.type = :type')
            ->andWhere('a.statut = :statut')
            ->andWhere('a.dateDebut >= :debut')
            ->andWhere('a.dateDebut <= :fin')
            ->setParameter('employe', $employe)
            ->setParameter('type', Absence::CONGE_ANNUEL)
            ->setParameter('statut', Absence::VALIDE_RH)
            ->setParameter('debut', new \DateTimeImmutable($annee . '-01-01'))
            ->setParameter('fin', new \DateTimeImmutable($annee . '-12-31'))
            ->getQuery()
            ->getSingleScalarResult();
    }
}
