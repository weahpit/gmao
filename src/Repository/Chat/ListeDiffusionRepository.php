<?php

namespace App\Repository\Chat;

use App\Entity\Chat\ListeDiffusion;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ListeDiffusion>
 */
class ListeDiffusionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ListeDiffusion::class);
    }

    /**
     * Listes visibles par un utilisateur (membre direct, membre d'un groupe
     * rattache, ou administrateur).
     *
     * @return ListeDiffusion[]
     */
    public function pourUtilisateur(User $user, bool $toutVoir = false): array
    {
        $toutes = $this->createQueryBuilder('l')
            ->leftJoin('l.groupes', 'g')->addSelect('g')
            ->leftJoin('l.membres', 'm')->addSelect('m')
            ->andWhere('l.active = true')
            ->orderBy('l.nom', 'ASC')
            ->getQuery()
            ->getResult();

        if ($toutVoir) {
            return $toutes;
        }

        return array_values(array_filter(
            $toutes,
            static fn (ListeDiffusion $liste): bool => $liste->contient($user)
        ));
    }

    /** Toutes les listes, actives ou non (ecran d'administration). */
    public function toutesTriees(): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.groupes', 'g')->addSelect('g')
            ->leftJoin('l.membres', 'm')->addSelect('m')
            ->orderBy('l.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
