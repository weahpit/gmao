<?php

namespace App\Repository\Logistique;

use App\Entity\Logistique\Magasin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Magasin>
 */
class MagasinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Magasin::class);
    }

    //    /**
    //     * @return Magasin[] Returns an array of Magasin objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Magasin
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Recherche un emplacement par son libelle, sans tenir compte de la casse,
     * des accents ni des espaces superflus (« Magasin Fèves Citrac » trouve
     * « MAGASIN FEVES CITRAC »).
     */
    public function trouverParLibelle(string ...$libelles): ?Magasin
    {
        $recherches = [];
        foreach ($libelles as $libelle) {
            $cle = self::normaliser($libelle);
            if ('' !== $cle) {
                $recherches[$cle] = true;
            }
        }
        if ([] === $recherches) {
            return null;
        }

        foreach ($this->findAll() as $magasin) {
            if (isset($recherches[self::normaliser((string) $magasin->getLibelle())])) {
                return $magasin;
            }
        }

        return null;
    }

    /**
     * Normalise un libelle pour la comparaison (minuscules, sans accents,
     * espaces multiples reduits).
     */
    public static function normaliser(?string $texte): string
    {
        $texte = mb_strtolower(trim((string) $texte));
        $texte = strtr($texte, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
            'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
        ]);

        return (string) preg_replace('/\s+/', ' ', $texte);
    }
}
