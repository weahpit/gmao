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
     * @param string|null $q         recherche libre (rubrique / motif / emplacement / service / agent)
     *
     * @return SortieStock[]
     */
    public function findFiltre(
        ?string $du = null,
        ?string $au = null,
        ?string $rubrique = null,
        ?string $serviceId = null,
        ?string $agent = null,
        ?string $q = null
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.service', 'svc')->addSelect('svc')
            ->leftJoin('s.emplacement', 'emp')->addSelect('emp')
            ->leftJoin('s.agent', 'ag')->addSelect('ag')
            ->orderBy('s.dateSortie', 'DESC');

        if ($rubrique !== null && $rubrique !== '') {
            $qb->andWhere('s.rubrique = :rubrique')->setParameter('rubrique', $rubrique);
        }
        if ($serviceId !== null && $serviceId !== '') {
            $qb->andWhere('svc.id = :serviceId')->setParameter('serviceId', $serviceId);
        }
        if ($agent !== null && $agent !== '') {
            $qb->andWhere('LOWER(s.agentLibelle) LIKE :agent')
               ->setParameter('agent', '%' . mb_strtolower($agent) . '%');
        }
        if ($q !== null && $q !== '') {
            $qb->andWhere('(LOWER(s.rubrique) LIKE :q OR LOWER(s.motif) LIKE :q OR LOWER(s.emplacementLibelle) LIKE :q OR LOWER(s.serviceLibelle) LIKE :q OR LOWER(s.agentLibelle) LIKE :q)')
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
}
