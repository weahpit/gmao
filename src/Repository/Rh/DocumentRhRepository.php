<?php

namespace App\Repository\Rh;

use App\Entity\Rh\Candidat;
use App\Entity\Rh\DocumentRh;
use App\Entity\Rh\Employe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentRh>
 */
class DocumentRhRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentRh::class);
    }

    public function findRecents(?string $categorie = null, int $limite = 100): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.employe', 'e')->addSelect('e')
            ->leftJoin('d.candidat', 'c')->addSelect('c')
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limite);

        if ($categorie !== null && $categorie !== '') {
            $qb->andWhere('d.categorie = :categorie')->setParameter('categorie', $categorie);
        }

        return $qb->getQuery()->getResult();
    }

    public function findPourEmploye(Employe $employe): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.employe = :employe')
            ->setParameter('employe', $employe)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPourCandidat(Candidat $candidat): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.candidat = :candidat')
            ->setParameter('candidat', $candidat)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function compterParCategorie(): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('d.categorie AS categorie', 'COUNT(d.id) AS total')
            ->groupBy('d.categorie')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['categorie']] = (int) $row['total'];
        }

        return $result;
    }
}
