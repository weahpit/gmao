<?php

namespace App\Repository\Rh;

use App\Entity\Rh\ArretTravail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ArretTravail> */
class ArretTravailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArretTravail::class);
    }

    /** @return ArretTravail[] */
    public function findRecents(int $limite = 60): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('d', 'e')
            ->leftJoin('a.dossier', 'd')
            ->leftJoin('d.employe', 'e')
            ->orderBy('a.dateDebut', 'DESC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /** @return ArretTravail[] */
    public function findParDossier(string $dossierId, int $limite = 30): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.dossier = :dossier')
            ->setParameter('dossier', $dossierId)
            ->orderBy('a.dateDebut', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    public function compterOuverts(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.statut IN (:statuts)')
            ->setParameter('statuts', [ArretTravail::DEMANDE, ArretTravail::VALIDE])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function compterEnAttente(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.statut = :statut')
            ->setParameter('statut', ArretTravail::DEMANDE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Jours d'arret par type sur les douze derniers mois. */
    public function joursParType(int $mois = 12): array
    {
        $debut = new \DateTimeImmutable('first day of -' . ($mois - 1) . ' months 00:00:00');

        $lignes = $this->createQueryBuilder('a')
            ->select('a.type AS type', 'SUM(a.jours) AS jours')
            ->andWhere('a.dateDebut >= :debut')
            ->andWhere('a.statut <> :refuse')
            ->setParameter('debut', $debut)
            ->setParameter('refuse', ArretTravail::REFUSE)
            ->groupBy('a.type')
            ->orderBy('jours', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($lignes as $ligne) {
            $code = (string) $ligne['type'];
            $libelle = ArretTravail::TYPES[$code] ?? $code;
            $out[] = ['libelle' => $libelle, 'valeur' => (int) $ligne['jours']];
        }

        return ['lignes' => $out];
    }
}
