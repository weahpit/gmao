<?php

namespace App\Repository\Stock;

use App\Entity\Stock\Unite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Unite>
 */
class UniteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Unite::class);
    }

    /**
     * Libelles des unites actives, triees par position puis par libelle.
     *
     * @return string[]
     */
    public function libelles(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.libelle')
            ->andWhere('u.active = true')
            ->orderBy('u.position', 'ASC')
            ->addOrderBy('u.libelle', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_map(static fn ($row) => $row['libelle'], $rows));
    }

    /** Libelle de l'unite marquee par defaut (null si aucune). */
    public function libelleParDefaut(): ?string
    {
        $unite = $this->createQueryBuilder('u')
            ->andWhere('u.active = true')
            ->andWhere('u.parDefaut = true')
            ->orderBy('u.position', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $unite?->getLibelle();
    }
}
