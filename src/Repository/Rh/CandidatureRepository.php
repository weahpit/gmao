<?php

namespace App\Repository\Rh;

use App\Entity\Rh\Candidature;
use App\Entity\Rh\OffreEmploi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Candidature>
 */
class CandidatureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Candidature::class);
    }

    public function findRecentes(int $limite = 100): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.offre', 'o')->addSelect('o')
            ->leftJoin('c.candidat', 'ca')->addSelect('ca')
            ->orderBy('c.dateSoumission', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    public function findParOffre(OffreEmploi $offre): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.candidat', 'ca')->addSelect('ca')
            ->andWhere('c.offre = :offre')
            ->setParameter('offre', $offre)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Candidatures encore dans le circuit (hors retenue et refusee). */
    public function findEnCours(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.offre', 'o')->addSelect('o')
            ->leftJoin('c.candidat', 'ca')->addSelect('ca')
            ->andWhere('c.statut NOT IN (:finaux)')
            ->setParameter('finaux', [Candidature::RETENUE, Candidature::REFUSEE])
            ->orderBy('c.dateEntretien', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Nombre de candidatures par etape du pipeline, dans l'ordre du processus. */
    public function compterParEtape(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.statut AS statut', 'COUNT(c.id) AS total')
            ->groupBy('c.statut')
            ->getQuery()
            ->getArrayResult();

        $brut = [];
        foreach ($rows as $row) {
            $brut[$row['statut']] = (int) $row['total'];
        }

        $result = [];
        foreach (Candidature::PIPELINE as $etape) {
            $result[$etape] = $brut[$etape] ?? 0;
        }

        return $result;
    }

    public function compterRecrutementsAnnee(int $annee): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.statut = :statut')
            ->andWhere('c.dateSoumission >= :debut')
            ->andWhere('c.dateSoumission <= :fin')
            ->setParameter('statut', Candidature::RETENUE)
            ->setParameter('debut', new \DateTimeImmutable($annee . '-01-01'))
            ->setParameter('fin', new \DateTimeImmutable($annee . '-12-31'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function prochaineReference(): string
    {
        $annee = date('Y');
        $nombre = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.reference LIKE :prefixe')
            ->setParameter('prefixe', 'CAND-' . $annee . '-%')
            ->getQuery()
            ->getSingleScalarResult();

        return 'CAND-' . $annee . '-' . str_pad((string) ($nombre + 1), 4, '0', STR_PAD_LEFT);
    }
}
