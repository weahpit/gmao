<?php

namespace App\Repository\Stock;

use App\Entity\Stock\SortieStock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SortieStock>
 */
class SortieStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SortieStock::class);
    }

    /** Recherche une sortie par son identifiant client (idempotence mobile). */
    public function findOneByClientUuid(?string $clientUuid): ?SortieStock
    {
        if (!$clientUuid) {
            return null;
        }

        return $this->findOneBy(['clientUuid' => $clientUuid]);
    }

    /** Recherche une sortie/bon par sa référence lisible (BS-CAC-...). */
    public function findOneByReference(string $reference): ?SortieStock
    {
        return $this->findOneBy(['reference' => $reference]);
    }

    /** Recherche un bon de sortie cacao par son jeton de signature. */
    public function findOneByToken(string $token): ?SortieStock
    {
        if (trim($token) === '') {
            return null;
        }

        return $this->findOneBy(['token' => $token]);
    }

    /**
     * Bons de sortie (toutes rubriques) en attente de la signature du Directeur Général.
     *
     * @param \DateTimeInterface|null $du borne basse (date de sortie)
     * @param \DateTimeInterface|null $au borne haute (date de sortie)
     * @return SortieStock[]
     */
    public function findBonsEnAttente(?\DateTimeInterface $du = null, ?\DateTimeInterface $au = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.statut = :statut')
            ->setParameter('statut', SortieStock::STATUT_EN_ATTENTE)
            ->orderBy('s.dateSortie', 'DESC');

        if ($du !== null) {
            $qb->andWhere('s.dateSortie >= :du')->setParameter('du', $du);
        }
        if ($au !== null) {
            $qb->andWhere('s.dateSortie <= :au')->setParameter('au', $au);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Bons de sortie (toutes rubriques) déjà signés (VALIDÉ) par le Directeur Général.
     *
     * @param \DateTimeInterface|null $du borne basse (date de signature)
     * @param \DateTimeInterface|null $au borne haute (date de signature)
     * @return SortieStock[]
     */
    public function findBonsSignes(?\DateTimeInterface $du = null, ?\DateTimeInterface $au = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.statut = :statut')
            ->setParameter('statut', SortieStock::STATUT_VALIDE)
            ->orderBy('s.signeAt', 'DESC');

        if ($du !== null) {
            $qb->andWhere('s.signeAt >= :du')->setParameter('du', $du);
        }
        if ($au !== null) {
            $qb->andWhere('s.signeAt <= :au')->setParameter('au', $au);
        }

        return $qb->getQuery()->getResult();
    }

    /** Dernieres sorties d'un agent, les plus recentes d'abord. */
    public function findRecentesParAgent($agent, int $limite = 50): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.agent = :agent')
            ->setParameter('agent', $agent)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Sorties filtrées (même logique que SaisieStockRepository::findFiltre,
     * pour que les deux jeux de données se comparent sur les mêmes critères).
     *
     * Tous les critères sont optionnels : un critère vide est ignoré.
     *
     * @param string|null $du        date de début (Y-m-d)
     * @param string|null $au        date de fin (Y-m-d)
     * @param string|null $rubrique  libellé exact de la rubrique
     * @param string|null $serviceId identifiant (uuid) du service
     * @param string|null $agent     fragment de nom d'agent (insensible à la casse)
     * @param string|null $q         recherche libre (rubrique / motif / emplacement / service / agent / nature)
     * @param string|null $nature    nature exacte du produit
     *
     * @return SortieStock[]
     */
    public function findFiltre(
        ?string $du = null,
        ?string $au = null,
        ?string $rubrique = null,
        ?string $serviceId = null,
        ?string $agent = null,
        ?string $q = null,
        ?string $nature = null
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.service', 'svc')->addSelect('svc')
            ->leftJoin('s.emplacement', 'emp')->addSelect('emp')
            ->leftJoin('s.agent', 'ag')->addSelect('ag')
            ->orderBy('s.dateSortie', 'DESC');

        if ($rubrique !== null && $rubrique !== '') {
            $qb->andWhere('s.rubrique = :rubrique')->setParameter('rubrique', $rubrique);
        }
        if ($nature !== null && $nature !== '') {
            $qb->andWhere('s.nature = :nature')->setParameter('nature', $nature);
        }
        if ($serviceId !== null && $serviceId !== '') {
            $qb->andWhere('svc.id = :serviceId')->setParameter('serviceId', $serviceId);
        }
        if ($agent !== null && $agent !== '') {
            $qb->andWhere('LOWER(s.agentLibelle) LIKE :agent')
               ->setParameter('agent', '%' . mb_strtolower($agent) . '%');
        }
        if ($q !== null && $q !== '') {
            $qb->andWhere('(LOWER(s.rubrique) LIKE :q OR LOWER(s.motif) LIKE :q OR LOWER(s.emplacementLibelle) LIKE :q OR LOWER(s.serviceLibelle) LIKE :q OR LOWER(s.agentLibelle) LIKE :q OR LOWER(s.nature) LIKE :q)')
               ->setParameter('q', '%' . mb_strtolower($q) . '%');
        }
        try {
            if ($du !== null && $du !== '') {
                $qb->andWhere('s.dateSortie >= :du')->setParameter('du', new \DateTime($du . ' 00:00:00'));
            }
            if ($au !== null && $au !== '') {
                $qb->andWhere('s.dateSortie <= :au')->setParameter('au', new \DateTime($au . ' 23:59:59'));
            }
        } catch (\Throwable) {
            // dates invalides : on les ignore silencieusement
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Sorties d'une rubrique, eventuellement posterieures a une date (exclue).
     * Seules les sorties VALIDES (non annulees) sont comptees.
     *
     * @return SortieStock[]
     */
    public function findByRubrique(string $rubrique, ?\DateTimeInterface $apres = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.rubrique = :rubrique')
            ->setParameter('rubrique', $rubrique);

        if ($apres !== null) {
            $qb->andWhere('s.dateSortie > :apres')->setParameter('apres', $apres);
        }

        // Un bon annule ne diminue pas le stock.
        $qb->andWhere('s.statut IS NULL OR s.statut != :annule')
           ->setParameter('annule', 'ANNULE');

        return $qb->getQuery()->getResult();
    }

    /**
     * Somme des sorties posterieures a une date, par unite.
     *
     * @return array<string, float>
     */
    public function sommeParUniteApres(string $rubrique, ?\DateTimeInterface $apres = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.unite AS unite', 'SUM(s.quantite) AS total')
            ->andWhere('s.rubrique = :rubrique')
            ->setParameter('rubrique', $rubrique)
            ->groupBy('s.unite');

        if ($apres !== null) {
            $qb->andWhere('s.dateSortie > :apres')->setParameter('apres', $apres);
        }

        $qb->andWhere('s.statut IS NULL OR s.statut != :annule')
           ->setParameter('annule', 'ANNULE');

        $out = [];
        foreach ($qb->getQuery()->getArrayResult() as $r) {
            $out[(string) $r['unite']] = (float) $r['total'];
        }

        return $out;
    }
}
