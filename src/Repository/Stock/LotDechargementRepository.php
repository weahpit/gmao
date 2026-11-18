<?php

namespace App\Repository\Stock;

use App\Entity\Production\Lot;
use App\Entity\Stock\DechargementCacao;
use App\Entity\Stock\LotDechargement;
use App\Entity\Stock\OrdreUsinage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LotDechargement>
 */
class LotDechargementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LotDechargement::class);
    }

    /**
     * Camions rattaches a un lot, du plus ancien au plus recent.
     *
     * @return LotDechargement[]
     */
    public function parLot(Lot $lot): array
    {
        return $this->createQueryBuilder('ld')
            ->addSelect('d', 'o')
            ->join('ld.dechargement', 'd')
            ->leftJoin('ld.ordreUsinage', 'o')
            ->andWhere('ld.lot = :lot')
            ->setParameter('lot', $lot)
            ->orderBy('o.numero', 'DESC')
            ->addOrderBy('d.dateDechargement', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Lots rattaches a un camion (tracabilite inverse).
     *
     * @return LotDechargement[]
     */
    public function parDechargement(DechargementCacao $dechargement): array
    {
        return $this->createQueryBuilder('ld')
            ->addSelect('l', 'o')
            ->join('ld.lot', 'l')
            ->leftJoin('ld.ordreUsinage', 'o')
            ->andWhere('ld.dechargement = :dech')
            ->setParameter('dech', $dechargement)
            ->orderBy('o.numero', 'ASC')
            ->addOrderBy('l.numero_lot', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rattachements crees par un ordre d'usinage donne.
     *
     * @return LotDechargement[]
     */
    public function parOrdre(OrdreUsinage $ordre): array
    {
        return $this->createQueryBuilder('ld')
            ->andWhere('ld.ordreUsinage = :ordre')
            ->setParameter('ordre', $ordre)
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les rattachements issus d'un ordre d'usinage, groupes par ordre.
     *
     * @return array<string, LotDechargement[]>
     */
    public function grouperParOrdre(): array
    {
        $liens = $this->createQueryBuilder('ld')
            ->addSelect('l', 'o')
            ->join('ld.lot', 'l')
            ->join('ld.ordreUsinage', 'o')
            ->orderBy('l.numero_lot', 'ASC')
            ->getQuery()
            ->getResult();

        $groupes = [];
        foreach ($liens as $lien) {
            $ordre = $lien->getOrdreUsinage();
            if ($ordre === null) {
                continue;
            }
            $groupes[(string) $ordre->getId()][] = $lien;
        }

        return $groupes;
    }

    /**
     * Statistiques par lot en UNE requete : nombre de camions et poids couvert.
     * Le poids affecte est utilise s'il existe, sinon le poids net du camion.
     *
     * @return array<string, array{nb: int, poids: float, sacs: int}>
     */
    public function statistiques(): array
    {
        $rows = $this->createQueryBuilder('ld')
            ->select('IDENTITY(ld.lot) AS lotId, COUNT(ld.id) AS nb, SUM(COALESCE(ld.poidsAffecte, d.poidsNet)) AS poids, SUM(COALESCE(ld.nbreSacs, d.nbreSacs)) AS sacs')
            ->join('ld.dechargement', 'd')
            ->groupBy('ld.lot')
            ->getQuery()
            ->getArrayResult();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(string) $row['lotId']] = [
                'nb'    => (int) $row['nb'],
                'poids' => (float) ($row['poids'] ?? 0),
                'sacs'  => (int) ($row['sacs'] ?? 0),
            ];
        }

        return $stats;
    }

    public function pourLotEtDechargement(Lot $lot, DechargementCacao $dechargement): ?LotDechargement
    {
        return $this->findOneBy(['lot' => $lot, 'dechargement' => $dechargement]);
    }

    public function totalPoidsLot(Lot $lot): float
    {
        $total = $this->createQueryBuilder('ld')
            ->select('SUM(COALESCE(ld.poidsAffecte, d.poidsNet))')
            ->join('ld.dechargement', 'd')
            ->andWhere('ld.lot = :lot')
            ->setParameter('lot', $lot)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($total ?? 0);
    }

    public function compterPourDechargement(DechargementCacao $dechargement): int
    {
        return (int) $this->createQueryBuilder('ld')
            ->select('COUNT(ld.id)')
            ->andWhere('ld.dechargement = :dech')
            ->setParameter('dech', $dechargement)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Nombre de camions rattaches a un lot donne.
     */
    public function compterParLot(Lot $lot): int
    {
        return (int) $this->createQueryBuilder('ld')
            ->select('COUNT(ld.id)')
            ->andWhere('ld.lot = :lot')
            ->setParameter('lot', $lot)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Nombre de camions rattaches a un lot donne.
     */
    /*public function compterParLot(Lot $lot): int
    {
        return (int) $this->createQueryBuilder('ld')
            ->select('COUNT(ld.id)')
            ->andWhere('ld.lot = :lot')
            ->setParameter('lot', $lot)
            ->getQuery()
            ->getSingleScalarResult();
    }*/

    /**
     * Nombre de lots deja rattaches a un camion (affiche avant le choix).
     *
     * @return array<string, int>
     */
    public function compterParDechargement(): array
    {
        $rows = $this->createQueryBuilder('ld')
            ->select('IDENTITY(ld.dechargement) AS dechId, COUNT(ld.id) AS nb')
            ->groupBy('ld.dechargement')
            ->getQuery()
            ->getArrayResult();

        $index = [];
        foreach ($rows as $row) {
            $index[(string) $row['dechId']] = (int) $row['nb'];
        }

        return $index;
    }

    /**
     * Totaux globaux affiches en tete de la page de tracabilite.
     *
     * @return array{lots: int, camions: int, poids: float}
     */
    public function totauxGlobaux(): array
    {
        $row = $this->createQueryBuilder('ld')
            ->select('COUNT(DISTINCT ld.lot) AS lots, COUNT(DISTINCT ld.dechargement) AS camions, SUM(COALESCE(ld.poidsAffecte, d.poidsNet)) AS poids')
            ->join('ld.dechargement', 'd')
            ->getQuery()
            ->getArrayResult()[0] ?? [];

        return [
            'lots'    => (int) ($row['lots'] ?? 0),
            'camions' => (int) ($row['camions'] ?? 0),
            'poids'   => (float) ($row['poids'] ?? 0),
        ];
    }
}
