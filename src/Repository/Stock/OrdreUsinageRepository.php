<?php

namespace App\Repository\Stock;

use App\Entity\Stock\DechargementCacao;
use App\Entity\Stock\OrdreUsinage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrdreUsinage>
 */
class OrdreUsinageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrdreUsinage::class);
    }

    /**
     * Numero suivant au format OU-AAAA-0001 (compteur remis a 1 chaque annee).
     */
    public function prochainNumero(): string
    {
        $annee = (int) (new \DateTimeImmutable())->format('Y');
        $prefixe = sprintf('OU-%d-', $annee);

        $dernier = $this->createQueryBuilder('o')
            ->select('MAX(o.numero)')
            ->andWhere('o.numero LIKE :prefixe')
            ->setParameter('prefixe', $prefixe . '%')
            ->getQuery()
            ->getSingleScalarResult();

        $suite = 1;
        if (is_string($dernier) && $dernier !== '') {
            $suffixe = substr($dernier, strlen($prefixe));
            if (ctype_digit($suffixe)) {
                $suite = (int) $suffixe + 1;
            }
        }

        return $prefixe . str_pad((string) $suite, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Derniers ordres d'usinage avec leurs camions.
     *
     * On charge d'abord les identifiants (LIMIT sur une seule table), puis les
     * ordres et leurs camions en une seule requete : un fetch-join direct
     * combine a setMaxResults tronquerait la liste (une ligne par camion).
     *
     * @return OrdreUsinage[]
     */
    public function recents(int $limite = 25): array
    {
        $ordres = $this->findBy([], ['dateCreation' => 'DESC', 'numero' => 'DESC'], $limite);
        if ($ordres === []) {
            return [];
        }

        return $this->createQueryBuilder('o')
            ->addSelect('c')
            ->leftJoin('o.camions', 'c')
            ->andWhere('o.id IN (:ordres)')
            ->setParameter('ordres', $ordres)
            ->orderBy('o.dateCreation', 'DESC')
            ->addOrderBy('o.numero', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Ordres d'usinage auxquels un camion participe.
     *
     * @return OrdreUsinage[]
     */
    public function parDechargement(DechargementCacao $dechargement): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere(':dech MEMBER OF o.camions OR o.dechargement = :dech')
            ->setParameter('dech', $dechargement)
            ->orderBy('o.numero', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function compter(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
