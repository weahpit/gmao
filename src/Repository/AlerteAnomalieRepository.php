<?php

namespace App\Repository;

use App\Entity\AlerteAnomalie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AlerteAnomalie>
 */
class AlerteAnomalieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlerteAnomalie::class);
    }
    public function countByDayOfCurrentMonthNative(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
        SELECT DATE_PART('day', created_at) AS day, COUNT(id) AS total
        FROM alerte_anomalie
        WHERE created_at BETWEEN :start AND :end
        GROUP BY day
        ORDER BY day ASC
    ";

        $start = new \DateTimeImmutable('first day of this month midnight');
        $end   = new \DateTimeImmutable('last day of this month 23:59:59');

        $rows = $conn->executeQuery($sql, [
            'start' => $start->format('Y-m-d H:i:s'),
            'end'   => $end->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();

        // ⚡ Compléter les jours manquants
        $daysInMonth = (int)$end->format('t'); // nombre de jours du mois
        $result = [];
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $result[$i] = 0;
        }
        foreach ($rows as $row) {
            $result[(int)$row['day']] = (int)$row['total'];
        }

        // Transformer en tableau [{day:1,total:0}, ...]
        $final = [];
        foreach ($result as $day => $total) {
            $final[] = ['day' => $day, 'total' => $total];
        }

        return $final;
    }

    public function countByZoneOfCurrentMonth(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
        SELECT zone_exploitation.nom as zone, COUNT(alerte_anomalie.id) AS total
        FROM alerte_anomalie, zone_exploitation
        WHERE zone_exploitation.id = alerte_anomalie.zone_id and created_at BETWEEN :start AND :end
        GROUP BY zone_exploitation.nom
        ORDER BY zone_exploitation.nom ASC
    ";

        $start = new \DateTimeImmutable('first day of this month midnight');
        $end   = new \DateTimeImmutable('last day of this month 23:59:59');

        $rows = $conn->executeQuery($sql, [
            'start' => $start->format('Y-m-d H:i:s'),
            'end'   => $end->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();

        return $rows;
    }

}
