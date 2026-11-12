<?php

namespace App\Repository\Courrier;

use App\Entity\Courrier\Courrier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Courrier>
 */
class CourrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Courrier::class);
    }

    /** Champs proposes en saisie semi-automatique (nom interne => colonne). */
    public const CHAMPS_SUGGERES = [
        'correspondant' => 'correspondant',
        'organisme'     => 'organisme',
        'service'       => 'service',
        'responsable'   => 'responsable',
    ];

    /**
     * Valeurs distinctes deja saisies dans un champ, filtrees sur le texte
     * deja tape par l'utilisateur.
     *
     * @param string $champ  nom interne du champ (voir CHAMPS_SUGGERES)
     * @param string $texte  debut (ou fragment) de la valeur tapee
     * @param string|null $sens  limité aux courriers entrants ou sortants
     *
     * @return list<string> au plus 8 propositions, triees du plus court au plus long
     */
    public function suggerer(string $champ, string $texte = '', ?string $sens = null, int $limite = 8): array
    {
        if (!isset(self::CHAMPS_SUGGERES[$champ])) {
            return [];
        }

        $colonne = 'c.' . self::CHAMPS_SUGGERES[$champ];
        $texte = trim($texte);

        $qb = $this->createQueryBuilder('c')
            ->select('DISTINCT ' . $colonne . ' AS valeur')
            ->andWhere($colonne . ' IS NOT NULL')
            ->andWhere($colonne . ' <> :vide')
            ->setParameter('vide', '')
            ->orderBy('valeur', 'ASC');

        if ($sens !== null && isset(Courrier::SENS[$sens])) {
            $qb->andWhere('c.sens = :sens')->setParameter('sens', $sens);
        }

        if ($texte !== '') {
            // Les valeurs qui COMMENCENT par le texte tape passent en premier,
            // puis celles qui le contiennent seulement.
            $prefixe = mb_strtolower($texte) . '%';
            $contient = '%' . mb_strtolower($texte) . '%';

            $qb->andWhere('LOWER(' . $colonne . ') LIKE :contient')
               ->setParameter('contient', $contient)
               ->addSelect('CASE WHEN LOWER(' . $colonne . ') LIKE :prefixe THEN 0 ELSE 1 END AS HIDDEN rang')
               ->setParameter('prefixe', $prefixe)
               ->addOrderBy('rang', 'ASC');
        }

        $valeurs = [];
        foreach ($qb->getQuery()->getArrayResult() as $ligne) {
            $valeur = trim((string) ($ligne['valeur'] ?? ''));
            if ($valeur !== '' && !in_array($valeur, $valeurs, true)) {
                $valeurs[] = $valeur;
            }
            if (count($valeurs) >= $limite) {
                break;
            }
        }

        return $valeurs;
    }

    /**
     * Recherche filtree du listing.
     *
     * @param array{sens?: string, statut?: string, service?: string, q?: string, du?: string, au?: string} $filtres
     */
    public function rechercher(array $filtres = [], int $limite = 300): array
    {
        return $this->construireRequete($filtres)
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /** Tous les courriers correspondant aux filtres, sans limite (impression). */
    public function rechercherTous(array $filtres = []): array
    {
        return $this->construireRequete($filtres)->getQuery()->getResult();
    }

    private function construireRequete(array $filtres): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.dateCourrier', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC');

        if (!empty($filtres['sens']) && isset(Courrier::SENS[$filtres['sens']])) {
            $qb->andWhere('c.sens = :sens')->setParameter('sens', $filtres['sens']);
        }

        if (!empty($filtres['statut']) && isset(Courrier::STATUTS[$filtres['statut']])) {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $filtres['statut']);
        }

        // Le filtre service accepte un identifiant (liste de la table des
        // services) ou, a defaut, l'ancien libelle deja enregistre.
        if (!empty($filtres['service'])) {
            $qb->andWhere('c.codeService = :service OR c.service = :serviceLibelle')
               ->setParameter('service', $filtres['service'])
               ->setParameter('serviceLibelle', (string) $filtres['service']);
        }

        if (!empty($filtres['du'])) {
            $qb->andWhere('c.dateCourrier >= :du')->setParameter('du', new \DateTimeImmutable($filtres['du']));
        }

        if (!empty($filtres['au'])) {
            $qb->andWhere('c.dateCourrier <= :au')->setParameter('au', new \DateTimeImmutable($filtres['au']));
        }

        // Recherche libre : numero, objet, correspondant, organisme, reference.
        if (!empty($filtres['q'])) {
            $q = '%' . mb_strtolower(trim((string) $filtres['q'])) . '%';
            $qb->andWhere($qb->expr()->orX(
                'LOWER(c.numero) LIKE :q',
                'LOWER(c.objet) LIKE :q',
                'LOWER(c.correspondant) LIKE :q',
                'LOWER(c.organisme) LIKE :q',
                'LOWER(c.reference) LIKE :q',
                'LOWER(c.responsable) LIKE :q'
            ))->setParameter('q', $q);
        }

        return $qb;
    }

    /** Compteurs par statut (pour les tuiles du bandeau). */
    public function compterParStatut(?string $sens = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.statut AS statut, COUNT(c.id) AS total')
            ->groupBy('c.statut');

        if ($sens !== null) {
            $qb->andWhere('c.sens = :sens')->setParameter('sens', $sens);
        }

        $resultat = array_fill_keys(array_keys(Courrier::STATUTS), 0);
        foreach ($qb->getQuery()->getArrayResult() as $ligne) {
            $resultat[$ligne['statut']] = (int) $ligne['total'];
        }

        return $resultat;
    }

    /**
     * Dernier numero attribue pour une annee et un sens donne.
     *
     * Retourne null quand aucun courrier ne porte encore ce prefixe (premier
     * enregistrement de l'annee) : getOneOrNullResult() et non
     * getSingleScalarResult(), qui leve une exception quand rien n'est trouve.
     * Le tri porte sur la longueur puis sur le numero afin de rester juste
     * au passage a 10000.
     */
    public function dernierNumero(string $prefixe): ?string
    {
        $numero = $this->createQueryBuilder('c')
            ->select('c.numero')
            ->andWhere('c.numero LIKE :p')
            ->setParameter('p', $prefixe . '%')
            ->orderBy('LENGTH(c.numero)', 'DESC')
            ->addOrderBy('c.numero', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (is_array($numero)) {
            $numero = $numero['numero'] ?? null;
        }

        return is_string($numero) ? $numero : null;
    }

    /** Liste des services deja utilises (pour le filtre). */
    public function servicesUtilises(): array
    {
        $lignes = $this->createQueryBuilder('c')
            ->select('DISTINCT c.service AS service')
            ->andWhere('c.service IS NOT NULL')
            ->andWhere('c.service <> :vide')
            ->setParameter('vide', '')
            ->orderBy('c.service', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $l): string => (string) $l['service'], $lignes);
    }
}
