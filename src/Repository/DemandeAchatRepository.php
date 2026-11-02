<?php

namespace App\Repository;

use App\Entity\DemandeAchat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DemandeAchat>
 */
class DemandeAchatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeAchat::class);
    }

    public function save(DemandeAchat $da, bool $flush = true): void
    {
        $this->getEntityManager()->persist($da);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** Demandes créées par un utilisateur (suivi côté demandeur). */
    public function findForDemandeur(string $username): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.created_by = :u')
            ->setParameter('u', $username)
            ->orderBy('d.created_at', 'DESC')
            ->getQuery()->getResult();
    }

    /** Suivi par jeton de tracking. */
    public function findOneByToken(string $token): ?DemandeAchat
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * File du magasinier : nouvelles demandes à enregistrer/soumettre +
     * demandes validées à servir ou à commander.
     */
    public function findPourMagasin(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.statut IN (:st)')
            ->setParameter('st', [
                DemandeAchat::STATUT_NOUVELLE,
                DemandeAchat::STATUT_VALIDEE,
                DemandeAchat::STATUT_EN_COMMANDE,
            ])
            ->orderBy('d.created_at', 'DESC')
            ->getQuery()->getResult();
    }

    /** Historique complet côté magasin (toutes demandes). */
    public function findAllForMagasin(): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.created_at', 'DESC')
            ->getQuery()->getResult();
    }

    /** File de la direction : demandes soumises à valider. */
    public function findPourDG(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.statut = :st')
            ->setParameter('st', DemandeAchat::STATUT_SOUMISE)
            ->orderBy('d.soumise_at', 'ASC')
            ->getQuery()->getResult();
    }

    /** Historique complet côté direction. */
    public function findAllForDG(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.statut != :nouv')
            ->setParameter('nouv', DemandeAchat::STATUT_NOUVELLE)
            ->orderBy('d.created_at', 'DESC')
            ->getQuery()->getResult();
    }

    /** Compteurs par statut pour les badges de navigation. */
    public function counts(): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('d.statut AS statut, COUNT(d.id) AS nb')
            ->groupBy('d.statut')
            ->getQuery()->getScalarResult();

        $out = [];
        foreach ($rows as $r) {
            $out[$r['statut']] = (int) $r['nb'];
        }
        return $out;
    }

    /** Génère un numéro unique DA-AAAAMMJJ-XXXXXX. */
    public function genererNumero(): string
    {
        $prefix = 'DA-' . date('Ymd') . '-';
        $last = $this->createQueryBuilder('d')
            ->andWhere('d.numero LIKE :p')
            ->setParameter('p', $prefix . '%')
            ->orderBy('d.numero', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();

        $seq = 1;
        if ($last !== null) {
            $seq = ((int) substr($last->getNumero(), -6)) + 1;
        }
        return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }
}
