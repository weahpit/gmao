<?php

namespace App\Repository\Rh;

use App\Entity\Rh\CampagneEvaluation;
use App\Entity\Rh\Employe;
use App\Entity\Rh\Evaluation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evaluation>
 */
class EvaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evaluation::class);
    }

    public function findParCampagne(CampagneEvaluation $campagne): array
    {
        return $this->createQueryBuilder('ev')
            ->leftJoin('ev.employe', 'e')->addSelect('e')
            ->andWhere('ev.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findUne(CampagneEvaluation $campagne, Employe $employe): ?Evaluation
    {
        return $this->createQueryBuilder('ev')
            ->andWhere('ev.campagne = :campagne')
            ->andWhere('ev.employe = :employe')
            ->setParameter('campagne', $campagne)
            ->setParameter('employe', $employe)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Moyenne des notes validees.
     *
     * La note est stockee en texte (elle peut etre vide) : AVG() directement en
     * SQL echoue sur PostgreSQL ("function avg(character varying) does not
     * exist"). On calcule donc la moyenne en PHP, ce qui fonctionne aussi sur
     * MySQL/MariaDB.
     */
    public function moyenneCampagne(CampagneEvaluation $campagne): ?float
    {
        $notes = $this->createQueryBuilder('ev')
            ->select('ev.noteGlobale')
            ->andWhere('ev.campagne = :campagne')
            ->andWhere('ev.statut = :statut')
            ->setParameter('campagne', $campagne)
            ->setParameter('statut', Evaluation::VALIDE)
            ->getQuery()
            ->getSingleColumnResult();

        $valeurs = [];
        foreach ($notes as $note) {
            if ($note === null || $note === '') {
                continue;
            }
            $valeur = str_replace(',', '.', (string) $note);
            if (is_numeric($valeur)) {
                $valeurs[] = (float) $valeur;
            }
        }

        if ($valeurs === []) {
            return null;
        }

        return array_sum($valeurs) / count($valeurs);
    }

    public function compterParStatut(CampagneEvaluation $campagne): array
    {
        $rows = $this->createQueryBuilder('ev')
            ->select('ev.statut AS statut', 'COUNT(ev.id) AS total')
            ->andWhere('ev.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->groupBy('ev.statut')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['statut']] = (int) $row['total'];
        }

        return $result;
    }
}
