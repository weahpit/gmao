<?php

namespace App\Repository\Production;

use App\Entity\Production\OrdreProduction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrdreProduction>
 */
class OrdreProductionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrdreProduction::class);
    }

    /** Numero suivant au format OP-AAAA-0001 (compteur remis a 1 chaque annee). */
    public function prochainNumero(): string
    {
        $annee = (int) (new \DateTimeImmutable())->format('Y');
        $prefixe = sprintf('OP-%d-', $annee);

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
     * Derniers ordres avec leurs lignes, produits finis et lots de feves.
     * On limite d'abord les identifiants pour eviter qu'un fetch-join ne
     * tronque la liste (une ligne par couple).
     *
     * @return OrdreProduction[]
     */
    public function recents(int $limite = 25): array
    {
        $ordres = $this->findBy([], ['dateCreation' => 'DESC', 'numero' => 'DESC'], $limite);
        if ($ordres === []) {
            return [];
        }

        return $this->createQueryBuilder('o')
            ->addSelect('lg', 'fini', 'feve')
            ->leftJoin('o.lignes', 'lg')
            ->leftJoin('lg.lotFini', 'fini')
            ->leftJoin('lg.lotFeve', 'feve')
            ->andWhere('o.id IN (:ordres)')
            ->setParameter('ordres', $ordres)
            ->orderBy('o.dateCreation', 'DESC')
            ->addOrderBy('o.numero', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Ordres de production auxquels un produit fini participe.
     *
     * @return OrdreProduction[]
     */
    public function parLotFini(\App\Entity\Production\Lot $lot): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('lg', 'fini', 'feve')
            ->leftJoin('o.lignes', 'lg')
            ->leftJoin('lg.lotFini', 'fini')
            ->leftJoin('lg.lotFeve', 'feve')
            ->andWhere('lg.lotFini = :lot')
            ->setParameter('lot', $lot)
            ->orderBy('o.dateCreation', 'DESC')
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
