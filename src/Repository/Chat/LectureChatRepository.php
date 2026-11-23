<?php

namespace App\Repository\Chat;

use App\Entity\Chat\LectureChat;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LectureChat>
 */
class LectureChatRepository extends ServiceEntityRepository
{
    public const CANAL_GENERAL = 'general';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LectureChat::class);
    }

    /** Date du dernier passage de l'utilisateur sur un canal (null = jamais). */
    public function dernierLu(User $utilisateur, string $canal): ?\DateTimeImmutable
    {
        $lecture = $this->findOneBy(['utilisateur' => $utilisateur, 'canal' => $canal]);

        return $lecture?->getDernierLuAt();
    }

    /**
     * Reperes de lecture de tous les utilisateurs sur un canal
     * (sert a calculer « Lu par 3 / 12 »).
     *
     * @return LectureChat[]
     */
    public function parCanal(string $canal): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.utilisateur', 'u')->addSelect('u')
            ->andWhere('l.canal = :canal')
            ->setParameter('canal', $canal)
            ->getQuery()
            ->getResult();
    }

    /** Enregistre (ou met à jour) le repère de lecture d'un canal. */
    public function marquerLu(User $utilisateur, string $canal, ?\DateTimeImmutable $quand = null): void
    {
        $lecture = $this->findOneBy(['utilisateur' => $utilisateur, 'canal' => $canal]);
        if ($lecture === null) {
            $lecture = (new LectureChat())
                ->setUtilisateur($utilisateur)
                ->setCanal($canal);
            $this->getEntityManager()->persist($lecture);
        }

        $lecture->setDernierLuAt($quand ?? new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }
}
