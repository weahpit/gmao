<?php

namespace App\Repository\Visite;

use App\Entity\Visite\Visiteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Visiteur>
 */
class VisiteurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Visiteur::class);
    }

    /**
     * Saisie semi-automatique : recherche sur le nom, les prenoms, le numero de
     * piece, la societe et le telephone.
     *
     * Les fiches dont le nom ou les prenoms COMMENCENT par le texte tape sont
     * remontees en premier, puis celles qui le contiennent seulement (numero de
     * piece, societe...).
     *
     * @return list<Visiteur>
     */
    public function rechercher(string $texte, int $limite = 12): array
    {
        $texte = trim($texte);
        if (mb_strlen($texte) < 2) {
            return [];
        }

        $prefixe  = mb_strtolower($texte) . '%';
        $contient = '%' . mb_strtolower($texte) . '%';

        return $this->createQueryBuilder('v')
            ->where('LOWER(v.nom) LIKE :contient')
            ->orWhere('LOWER(v.prenoms) LIKE :contient')
            ->orWhere('LOWER(v.numeroPiece) LIKE :contient')
            ->orWhere('LOWER(v.societe) LIKE :contient')
            ->orWhere('v.telephone LIKE :contient')
            ->setParameter('contient', $contient)
            ->addSelect('CASE WHEN LOWER(v.nom) LIKE :prefixe OR LOWER(v.prenoms) LIKE :prefixe THEN 0 ELSE 1 END AS HIDDEN rang')
            ->setParameter('prefixe', $prefixe)
            ->orderBy('rang', 'ASC')
            ->addOrderBy('v.nom', 'ASC')
            ->addOrderBy('v.prenoms', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /** Retrouve une fiche par sa piece d'identite (evite les doublons a la saisie). */
    public function trouverParPiece(?string $typePiece, ?string $numeroPiece): ?Visiteur
    {
        $numeroPiece = trim((string) $numeroPiece);
        if ($numeroPiece === '') {
            return null;
        }

        $qb = $this->createQueryBuilder('v')
            ->andWhere('LOWER(v.numeroPiece) = :numero')
            ->setParameter('numero', mb_strtolower($numeroPiece))
            ->setMaxResults(1);

        if ($typePiece !== null && $typePiece !== '') {
            $qb->andWhere('v.typePiece = :type')->setParameter('type', $typePiece);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /** @return list<Visiteur> */
    public function plusRecents(int $limite = 10): array
    {
        return $this->createQueryBuilder('v')
            ->orderBy('v.updatedAt', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
