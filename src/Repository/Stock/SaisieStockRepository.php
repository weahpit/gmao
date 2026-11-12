<?php

namespace App\Repository\Stock;

use App\Entity\Stock\SaisieStock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SaisieStock>
 */
class SaisieStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SaisieStock::class);
    }

    /** Recherche une saisie par son identifiant client (idempotence). */
    public function findOneByClientUuid(?string $clientUuid): ?SaisieStock
    {
        if (!$clientUuid) {
            return null;
        }

        return $this->findOneBy(['clientUuid' => $clientUuid]);
    }

    /** Dernieres saisies d'un agent, les plus recentes d'abord. */
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
     * Saisies filtrees (ecran web de consultation).
     *
     * Tous les criteres sont optionnels : un critere vide est ignore.
     *
     * @param string|null $du        date de debut (Y-m-d)
     * @param string|null $au        date de fin (Y-m-d)
     * @param string|null $rubrique  libelle exact de la rubrique
     * @param string|null $serviceId identifiant (uuid) du service
     * @param string|null $agent     fragment de nom d'agent (recherche insensible a la casse)
     * @param string|null $q         recherche libre (rubrique / service / agent)
     *
     * @return SaisieStock[]
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
            ->leftJoin('s.agent', 'ag')->addSelect('ag')
            ->orderBy('s.dateSaisie', 'DESC');

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
            $qb->andWhere('(LOWER(s.rubrique) LIKE :q OR LOWER(s.serviceLibelle) LIKE :q OR LOWER(s.agentLibelle) LIKE :q OR LOWER(s.nature) LIKE :q)')
               ->setParameter('q', '%' . mb_strtolower($q) . '%');
        }
        try {
            if ($du !== null && $du !== '') {
                $qb->andWhere('s.dateSaisie >= :du')->setParameter('du', new \DateTime($du . ' 00:00:00'));
            }
            if ($au !== null && $au !== '') {
                $qb->andWhere('s.dateSaisie <= :au')->setParameter('au', new \DateTime($au . ' 23:59:59'));
            }
        } catch (\Throwable) {
            // dates invalides : on les ignore silencieusement
        }

        return $qb->getQuery()->getResult();
    }
}
