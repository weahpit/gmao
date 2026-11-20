<?php

namespace App\Repository\Rh;

use App\Entity\Rh\Candidat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Candidat>
 */
class CandidatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Candidat::class);
    }

    /**
     * Tous les candidats, du plus recent au plus ancien.
     *
     * @return Candidat[]
     */
    public function findTous(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.nom', 'ASC')
            ->addOrderBy('c.prenoms', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche libre sur le nom, le prenom, l'e-mail ou le telephone.
     *
     * @return Candidat[]
     */
    public function rechercher(string $terme, int $limite = 50): array
    {
        $terme = trim($terme);
        if ($terme === '') {
            return $this->findTous();
        }

        return $this->createQueryBuilder('c')
            ->andWhere('LOWER(c.nom) LIKE :q OR LOWER(c.prenoms) LIKE :q OR LOWER(c.email) LIKE :q OR c.telephone LIKE :q')
            ->setParameter('q', '%' . mb_strtolower($terme) . '%')
            ->orderBy('c.nom', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
