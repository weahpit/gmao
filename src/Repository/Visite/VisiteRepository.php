<?php

namespace App\Repository\Visite;

use App\Entity\Services;
use App\Entity\Visite\Visite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Visite>
 */
class VisiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Visite::class);
    }

    /**
     * Requete filtree : periode, nom (visiteur ou personne visitee), service,
     * statut. Utilisee a la fois par l'ecran et par l'etat PDF.
     *
     * @param array{du?: string, au?: string, q?: string, service?: ?Services, statut?: string} $filtres
     */
    public function construireRequete(array $filtres): QueryBuilder
    {
        $qb = $this->createQueryBuilder('v')
            ->addSelect('visiteur', 'service', 'direction')
            ->leftJoin('v.visiteur', 'visiteur')
            ->leftJoin('v.service', 'service')
            ->leftJoin('service.code_direction', 'direction')
            ->orderBy('v.heureEntree', 'DESC');

        $du = trim((string) ($filtres['du'] ?? ''));
        if ($du !== '') {
            $qb->andWhere('v.heureEntree >= :du')
               ->setParameter('du', new \DateTimeImmutable($du . ' 00:00:00'));
        }

        $au = trim((string) ($filtres['au'] ?? ''));
        if ($au !== '') {
            $qb->andWhere('v.heureEntree <= :au')
               ->setParameter('au', new \DateTimeImmutable($au . ' 23:59:59'));
        }

        $q = trim((string) ($filtres['q'] ?? ''));
        if ($q !== '') {
            $motif = '%' . mb_strtolower($q) . '%';
            $qb->andWhere('LOWER(visiteur.nom) LIKE :motif OR LOWER(visiteur.prenoms) LIKE :motif'
                . ' OR LOWER(visiteur.societe) LIKE :motif OR LOWER(visiteur.numeroPiece) LIKE :motif'
                . ' OR LOWER(v.personneVisitee) LIKE :motif OR LOWER(v.reference) LIKE :motif')
               ->setParameter('motif', $motif);
        }

        $service = $filtres['service'] ?? null;
        if ($service instanceof Services) {
            $qb->andWhere('v.service = :service')->setParameter('service', $service);
        }

        $statut = trim((string) ($filtres['statut'] ?? ''));
        if ($statut !== '' && isset(Visite::STATUTS[$statut])) {
            $qb->andWhere('v.statut = :statut')->setParameter('statut', $statut);
        }

        return $qb;
    }

    /**
     * @param array<string, mixed> $filtres
     *
     * @return list<Visite>
     */
    public function rechercher(array $filtres, ?int $limite = null): array
    {
        $qb = $this->construireRequete($filtres);
        if ($limite !== null) {
            $qb->setMaxResults($limite);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Visiteurs encore presents (piece d'identite non remise).
     *
     * @return list<Visite>
     */
    public function presents(): array
    {
        return $this->createQueryBuilder('v')
            ->addSelect('visiteur', 'service')
            ->leftJoin('v.visiteur', 'visiteur')
            ->leftJoin('v.service', 'service')
            ->where('v.statut = :statut')
            ->setParameter('statut', Visite::EN_COURS)
            ->orderBy('v.heureEntree', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Visite> */
    public function duJour(\DateTimeInterface $jour): array
    {
        $debut = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $jour->format('Y-m-d') . ' 00:00:00');
        $fin   = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $jour->format('Y-m-d') . ' 23:59:59');

        return $this->createQueryBuilder('v')
            ->addSelect('visiteur', 'service')
            ->leftJoin('v.visiteur', 'visiteur')
            ->leftJoin('v.service', 'service')
            ->where('v.heureEntree BETWEEN :debut AND :fin')
            ->setParameter('debut', $debut ?: new \DateTimeImmutable('today 00:00:00'))
            ->setParameter('fin', $fin ?: new \DateTimeImmutable('today 23:59:59'))
            ->orderBy('v.heureEntree', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function compterStatut(string $statut): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.statut = :statut')
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function compterPeriode(\DateTimeInterface $du, \DateTimeInterface $au): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.heureEntree BETWEEN :du AND :au')
            ->setParameter('du', $du)
            ->setParameter('au', $au)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Nombre de visites du jour, sert a numeroter les passages (VIS-AAAAMMJJ-001). */
    public function compterJour(\DateTimeInterface $jour): int
    {
        $debut = new \DateTimeImmutable($jour->format('Y-m-d') . ' 00:00:00');
        $fin   = new \DateTimeImmutable($jour->format('Y-m-d') . ' 23:59:59');

        return $this->compterPeriode($debut, $fin);
    }

    /** Genere une reference unique pour le jour (VIS-20261120-001, 002, ...). */
    public function genererReference(\DateTimeInterface $jour): string
    {
        $base = 'VIS-' . $jour->format('Ymd') . '-';
        $numero = $this->compterJour($jour) + 1;

        do {
            $reference = $base . str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
            $existe = (int) $this->createQueryBuilder('v')
                ->select('COUNT(v.id)')
                ->where('v.reference = :reference')
                ->setParameter('reference', $reference)
                ->getQuery()
                ->getSingleScalarResult() > 0;
            $numero++;
        } while ($existe && $numero < 9999);

        return $reference;
    }

    /**
     * Derniers passages d'un visiteur (fiche visiteur).
     *
     * @return list<Visite>
     */
    public function pourVisiteur(\App\Entity\Visite\Visiteur $visiteur, int $limite = 50): array
    {
        return $this->createQueryBuilder('v')
            ->addSelect('service')
            ->leftJoin('v.service', 'service')
            ->where('v.visiteur = :visiteur')
            ->setParameter('visiteur', $visiteur)
            ->orderBy('v.heureEntree', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
