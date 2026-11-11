<?php

namespace App\Repository\Stock;

use App\Entity\Stock\Rubrique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rubrique>
 */
class RubriqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rubrique::class);
    }

    /**
     * Libelles des rubriques actives, triees par position puis par libelle.
     *
     * @return string[]
     */
    public function libelles(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.libelle')
            ->andWhere('r.active = true')
            ->orderBy('r.position', 'ASC')
            ->addOrderBy('r.libelle', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_map(static fn ($row) => $row['libelle'], $rows));
    }
}
