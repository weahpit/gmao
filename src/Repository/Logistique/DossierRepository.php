<?php

namespace App\Repository\Logistique;

use App\Entity\Logistique\Dossier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dossier>
 */
class DossierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dossier::class);
    }

    /**
     * Compteurs du bandeau des embarquements en UNE seule requete.
     *
     * Avant, chaque compteur (total, clotures, a planifier, du mois) lancait
     * sa propre requete : quatre allers-retours en base a chaque affichage de
     * la liste. Les sommes conditionnelles ci-dessous donnent le meme resultat
     * avec une seule requete, donc moins de memoire et de latence.
     *
     * @return array{total:int, en_cours:int, clotures:int, a_planifier:int, ce_mois:int}
     */
    public function compterParStatut(\DateTimeInterface $debutMois, \DateTimeInterface $finMois): array
    {
        $ligne = $this->createQueryBuilder('d')
            ->select('COUNT(d.id) AS total')
            ->addSelect('SUM(CASE WHEN d.cloture = true THEN 1 ELSE 0 END) AS clotures')
            ->addSelect('SUM(CASE WHEN d.date_embarquement IS NULL THEN 1 ELSE 0 END) AS a_planifier')
            ->addSelect('SUM(CASE WHEN d.date_embarquement BETWEEN :debut AND :fin THEN 1 ELSE 0 END) AS ce_mois')
            ->setParameter('debut', $debutMois)
            ->setParameter('fin', $finMois)
            ->getQuery()
            ->getSingleResult();

        $total     = (int) ($ligne['total'] ?? 0);
        $clotures  = (int) ($ligne['clotures'] ?? 0);

        return [
            'total'       => $total,
            'en_cours'    => max(0, $total - $clotures),
            'clotures'    => $clotures,
            'a_planifier' => (int) ($ligne['a_planifier'] ?? 0),
            'ce_mois'     => (int) ($ligne['ce_mois'] ?? 0),
        ];
    }

    /** Vrai si un numero d'OT existe deja (comparaison insensible aux espaces). */
    public function numeroDejaUtilise(string $numeroOt): bool
    {
        return null !== $this->createQueryBuilder('d')
            ->select('d.id')
            ->andWhere('UPPER(TRIM(d.numero_ot)) = :num')
            ->setParameter('num', strtoupper(trim($numeroOt)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
