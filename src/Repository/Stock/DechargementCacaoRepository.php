<?php

namespace App\Repository\Stock;

use App\Entity\Stock\DechargementCacao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DechargementCacao>
 */
class DechargementCacaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DechargementCacao::class);
    }

    /**
     * Index des dechargements existants, par code de bon.
     *
     * L'index est construit en memoire pour que la comparaison d'un fichier de
     * plusieurs milliers de lignes ne declenche jamais une requete par ligne.
     *
     * @return array<string, DechargementCacao>
     */
    public function indexParCode(): array
    {
        $index = [];
        foreach ($this->createQueryBuilder('d')->getQuery()->toIterable() as $dechargement) {
            /** @var DechargementCacao $dechargement */
            if ($dechargement->getCode() !== null) {
                $index[$dechargement->getCode()] = $dechargement;
            }
        }

        return $index;
    }

    public function findOneByCode(string $code): ?DechargementCacao
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Recherche paginee pour l'ecran de consultation.
     *
     * @return array{items: DechargementCacao[], total: int}
     */
    public function rechercher(?string $terme, ?string $cooperative, int $page = 1, int $parPage = 25): array
    {
        $qb = $this->createQueryBuilder('d');

        if ($terme !== null && trim($terme) !== '') {
            $qb->andWhere('d.code LIKE :terme OR d.immatriculation LIKE :terme OR d.transporteur LIKE :terme OR d.traitant LIKE :terme')
                ->setParameter('terme', '%' . trim($terme) . '%');
        }
        if ($cooperative !== null && trim($cooperative) !== '') {
            $qb->andWhere('d.cooperative = :coop')->setParameter('coop', $cooperative);
        }

        $total = (int) (clone $qb)->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();

        $items = $qb->orderBy('d.dateBon', 'DESC')
            ->addOrderBy('d.code', 'DESC')
            ->setFirstResult(max(0, ($page - 1) * $parPage))
            ->setMaxResults($parPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /** @return string[] */
    public function cooperatives(): array
    {
        return $this->createQueryBuilder('d')
            ->select('DISTINCT d.cooperative')
            ->andWhere('d.cooperative IS NOT NULL')
            ->orderBy('d.cooperative', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * KPI d'en-tete de la page (totaux globaux).
     *
     * @return array{nb: int, sacs: float, poids: float, pnp: float, derniere: ?\DateTimeInterface}
     */
    public function totaux(): array
    {
        $r = $this->createQueryBuilder('d')
            ->select('COUNT(d.id) AS nb, COALESCE(SUM(d.nbreSacs), 0) AS sacs, COALESCE(SUM(d.poidsNet), 0) AS poids, COALESCE(SUM(d.pnp), 0) AS pnp, MAX(d.dateBon) AS derniere')
            ->getQuery()
            ->getSingleResult();

        return [
            'nb'       => (int) $r['nb'],
            'sacs'     => (float) $r['sacs'],
            'poids'    => (float) $r['poids'],
            'pnp'      => (float) $r['pnp'],
            'derniere' => $r['derniere'] instanceof \DateTimeInterface ? $r['derniere'] : null,
        ];
    }
}
