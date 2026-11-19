<?php

namespace App\Repository\Chat;

use App\Entity\Chat\DeviceToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DeviceToken>
 */
class DeviceTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeviceToken::class);
    }

    public function parToken(string $token): ?DeviceToken
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * Jetons des utilisateurs indiques (une entree par appareil).
     *
     * @param User[] $utilisateurs
     *
     * @return DeviceToken[]
     */
    public function pourUtilisateurs(array $utilisateurs): array
    {
        $ids = [];
        foreach ($utilisateurs as $utilisateur) {
            if ($utilisateur instanceof User && $utilisateur->getId() !== null) {
                $ids[] = $utilisateur->getId();
            }
        }

        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('d')
            ->andWhere('d.user IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}
