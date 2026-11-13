<?php

namespace App\Repository\Stock;

use App\Entity\Stock\InitialisationStock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InitialisationStock>
 */
class InitialisationStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InitialisationStock::class);
    }

    /**
     * Derniere initialisation ACTIVE (non remplacee), la plus recente d'abord.
     *
     * @return InitialisationStock[]
     */
    public function actives(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.desactive = false')
            ->orderBy('i.dateInventaire', 'DESC')
            ->addOrderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Initialisation active la plus recente pour une rubrique (et une nature
     * donnee si precisee).
     */
    public function plusRecentePour(?string $rubrique, ?string $nature = null): ?InitialisationStock
    {
        if ($rubrique === null || $rubrique === '') {
            return null;
        }

        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.desactive = false')
            ->andWhere('i.rubrique = :rubrique')
            ->setParameter('rubrique', $rubrique)
            ->orderBy('i.dateInventaire', 'DESC')
            ->addOrderBy('i.createdAt', 'DESC')
            ->setMaxResults(1);

        if ($nature !== null && $nature !== '') {
            $qb->andWhere('i.nature = :nature')->setParameter('nature', $nature);
        } else {
            $qb->andWhere('i.nature IS NULL');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Initialisations actives, indexees par « rubrique|nature ».
     * En cas de doublon, la plus recente gagne.
     *
     * @return array<string, InitialisationStock>
     */
    public function indexees(): array
    {
        $out = [];
        foreach ($this->actives() as $init) {
            $cle = $init->cle();
            if (!isset($out[$cle])) {
                $out[$cle] = $init;
            }
        }

        return $out;
    }

    /** Historique complet, du plus recent au plus ancien. */
    public function historique(int $limite = 200): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.dateInventaire', 'DESC')
            ->addOrderBy('i.createdAt', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
