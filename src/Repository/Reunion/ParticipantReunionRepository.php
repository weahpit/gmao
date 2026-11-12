<?php

namespace App\Repository\Reunion;

use App\Entity\Reunion\ParticipantReunion;
use App\Entity\Reunion\Reunion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParticipantReunion>
 */
class ParticipantReunionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParticipantReunion::class);
    }

    /** @return ParticipantReunion[] */
    public function findParReunion(Reunion $reunion): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.reunion = :reunion')
            ->setParameter('reunion', $reunion)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Empeche un double enregistrement : meme nom (insensible a la casse)
     * et meme numero de telephone dans la meme reunion
     * (ou, a defaut de telephone, meme adresse email).
     */
    public function existeDeja(Reunion $reunion, string $nom, ?string $telephone, ?string $email = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.reunion = :reunion')
            ->andWhere('LOWER(p.nom) = :nom')
            ->setParameter('reunion', $reunion)
            ->setParameter('nom', mb_strtolower($nom));

        $tel = preg_replace('/\s+/', '', (string) $telephone);
        if ($tel !== '') {
            $qb->andWhere('REPLACE(REPLACE(p.telephone, \' \', \'\'), \'-\', \'\') = :tel')
               ->setParameter('tel', $tel);
        } elseif ($email !== null && $email !== '') {
            $qb->andWhere('LOWER(p.email) = :email')
               ->setParameter('email', mb_strtolower($email));
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
