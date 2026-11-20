<?php

namespace App\Repository\Rh;

use App\Entity\Rh\DossierMedical;
use App\Entity\Rh\Employe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DossierMedical>
 */
class DossierMedicalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DossierMedical::class);
    }

    public function findTousAvecEmploye(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.employe', 'e')->addSelect('e')
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findParEmploye(Employe $employe): ?DossierMedical
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.employe = :employe')
            ->setParameter('employe', $employe)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function prochainNumero(): string
    {
        $annee = date('Y');
        $nombre = (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.numeroDossier LIKE :prefixe')
            ->setParameter('prefixe', 'MED-' . $annee . '-%')
            ->getQuery()
            ->getSingleScalarResult();

        return 'MED-' . $annee . '-' . str_pad((string) ($nombre + 1), 4, '0', STR_PAD_LEFT);
    }

    public function compter(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
