<?php

namespace App\Repository\Rh;

use App\Entity\Rh\Employe;
use App\Entity\Services;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Employe>
 */
class EmployeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employe::class);
    }

    /** @return Employe[] */
    public function rechercher(?string $terme = null, ?string $statut = null, ?Services $service = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.service', 's')->addSelect('s')
            ->leftJoin('e.poste', 'p')->addSelect('p')
            ->leftJoin('e.direction', 'd')->addSelect('d')
            ->orderBy('e.nom', 'ASC')
            ->addOrderBy('e.prenoms', 'ASC');

        if ($terme !== null && trim($terme) !== '') {
            $terme = trim($terme);
            $qb->andWhere('e.nom LIKE :terme OR e.prenoms LIKE :terme OR e.matricule LIKE :terme OR e.email LIKE :terme OR e.telephone LIKE :terme')
                ->setParameter('terme', '%' . $terme . '%');
        }

        if ($statut !== null && $statut !== '') {
            $qb->andWhere('e.statut = :statut')->setParameter('statut', $statut);
        }

        if ($service !== null) {
            $qb->andWhere('s.id = :service')->setParameter('service', $service);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Employe[] */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.statut = :statut')
            ->setParameter('statut', Employe::STATUT_ACTIF)
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Employes actifs sans dossier medical : a completer par le service medical. */
    /** @return Employe[] */
    public function findActifsSansDossierMedical(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('App\Entity\Rh\DossierMedical', 'dm', 'WITH', 'dm.employe = e')
            ->andWhere('e.statut = :statut')
            ->andWhere('dm.id IS NULL')
            ->setParameter('statut', Employe::STATUT_ACTIF)
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function compterParStatut(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.statut AS statut', 'COUNT(e.id) AS total')
            ->groupBy('e.statut')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['statut']] = (int) $row['total'];
        }

        return $result;
    }

    public function compterParSexe(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.sexe AS sexe', 'COUNT(e.id) AS total')
            ->andWhere('e.statut = :statut')
            ->setParameter('statut', Employe::STATUT_ACTIF)
            ->groupBy('e.sexe')
            ->getQuery()
            ->getArrayResult();

        $result = ['M' => 0, 'F' => 0];
        foreach ($rows as $row) {
            $result[$row['sexe'] ?: 'N'] = (int) $row['total'];
        }

        return $result;
    }

    /** Effectif actif par service, pour le tableau de bord. */
    public function compterParService(): array
    {
        return $this->createQueryBuilder('e')
            ->select('COALESCE(s.libelle, :sansService) AS service', 'COUNT(e.id) AS total')
            ->leftJoin('e.service', 's')
            ->andWhere('e.statut = :statut')
            ->setParameter('statut', Employe::STATUT_ACTIF)
            ->setParameter('sansService', 'Non affecte')
            ->groupBy('s.libelle')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function compterParDirection(): array
    {
        return $this->createQueryBuilder('e')
            ->select('COALESCE(d.libelle, :sansDirection) AS direction', 'COUNT(e.id) AS total')
            ->leftJoin('e.direction', 'd')
            ->andWhere('e.statut = :statut')
            ->setParameter('statut', Employe::STATUT_ACTIF)
            ->setParameter('sansDirection', 'Non affecte')
            ->groupBy('d.libelle')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function effectifActif(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.statut = :statut')
            ->setParameter('statut', Employe::STATUT_ACTIF)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function prochainMatricule(): string
    {
        $dernier = $this->createQueryBuilder('e')
            ->select('e.matricule')
            ->andWhere('e.matricule LIKE :prefixe')
            ->setParameter('prefixe', 'EMP-%')
            ->orderBy('e.matricule', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $numero = 1;
        if ($dernier !== null && isset($dernier['matricule'])) {
            $numero = (int) substr($dernier['matricule'], 4) + 1;
        }

        return 'EMP-' . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }
}
