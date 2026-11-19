<?php

namespace App\Service\Stock;

use App\Entity\Stock\InitialisationStock;
use App\Entity\Stock\Rubrique;
use App\Entity\Stock\SaisieStock;
use App\Entity\Stock\SortieStock;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Suppression d'une rubrique (type de stock) en toute securite.
 *
 * Une rubrique ne peut disparaitre que si plus aucun mouvement ne la
 * reference. Dans le cas contraire, ses mouvements sont d'abord reafectes
 * vers une autre rubrique (ou vers « DIVERS », creee au besoin), puis la
 * rubrique est supprimee. Sans cela, des saisies et des sorties
 * pointeraient vers un type de stock inexistant et les etats
 * deviendraient faux.
 *
 * Les mouvements stockent le libelle de la rubrique en chaine (pas de
 * relation) : la reafectation se fait donc par mise a jour en masse sur
 * les trois tables concernees.
 */
final class SuppressionRubrique
{
    /** Libelle de la rubrique de repli. */
    public const RUBRIQUE_DIVERS = 'DIVERS';

    /**
     * Les tables de mouvements, avec leur entite et un libelle lisible.
     *
     * @var array<string, array{entite: class-string, champ: string, libelle: string}>
     */
    private const TABLES = [
        'saisie_stock'         => ['entite' => SaisieStock::class, 'champ' => 'rubrique', 'libelle' => 'saisies de stock'],
        'sortie_stock'         => ['entite' => SortieStock::class, 'champ' => 'rubrique', 'libelle' => 'sorties de stock'],
        'stock_initialisation' => ['entite' => InitialisationStock::class, 'champ' => 'rubrique', 'libelle' => 'initialisations de stock'],
    ];

    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Compte les mouvements rattaches a la rubrique.
     *
     * @return array{total: int, details: array<string, array{libelle: string, nombre: int}>}
     */
    public function impact(Rubrique $rubrique): array
    {
        $cle = $this->cle((string) $rubrique->getLibelle());
        $details = [];
        $total = 0;

        foreach (self::TABLES as $table => $meta) {
            $nombre = $this->compter($meta['entite'], $meta['champ'], $cle);
            if ($nombre > 0) {
                $details[$table] = ['libelle' => $meta['libelle'], 'nombre' => $nombre];
                $total += $nombre;
            }
        }

        return ['total' => $total, 'details' => $details];
    }

    /**
     * Reafecte les mouvements d'une rubrique vers une autre, puis supprime
     * la rubrique.
     *
     * Le libelle cible n'est obligatoire que si des mouvements existent.
     *
     * @return array{deplaces: int, cible: ?string}
     *
     * @throws \InvalidArgumentException message lisible si la suppression est refusee
     */
    public function supprimer(Rubrique $rubrique, ?string $cibleLibelle = null): array
    {
        $source = (string) $rubrique->getLibelle();
        $impact = $this->impact($rubrique);

        if ($impact['total'] > 0 && ($cibleLibelle === null || trim($cibleLibelle) === '')) {
            throw new \InvalidArgumentException(sprintf(
                'Impossible de supprimer « %s » : %d mouvement(s) y sont encore rattachés. Choisissez la rubrique qui doit les recevoir.',
                $source,
                $impact['total']
            ));
        }

        $cible = null;
        $deplaces = 0;

        if ($impact['total'] > 0) {
            $cible = trim((string) $cibleLibelle);
            if ($this->cle($cible) === $this->cle($source)) {
                throw new \InvalidArgumentException(
                    'La rubrique de destination doit être différente de la rubrique supprimée.'
                );
            }

            $existante = $this->trouverParLibelle($cible);
            if (!$existante instanceof Rubrique) {
                // La cible n'existe pas encore : on la cree (cas de « DIVERS »).
                $existante = (new Rubrique())
                    ->setLibelle(mb_strtoupper($cible))
                    ->setActive(true)
                    ->setPosition($this->positionMax() + 1);
                $this->em->persist($existante);
                $this->em->flush();
            }
            $cible = (string) $existante->getLibelle();

            $sourceCle = $source;
            $cibleCle = $cible;
            $deplaces = (int) $this->em->wrapInTransaction(function () use ($sourceCle, $cibleCle): int {
                $nombre = 0;
                foreach (self::TABLES as $meta) {
                    $nombre += $this->reaffecter($meta['entite'], $meta['champ'], $sourceCle, $cibleCle);
                }

                return $nombre;
            });
        }

        $this->em->remove($rubrique);
        $this->em->flush();

        return ['deplaces' => $deplaces, 'cible' => $cible];
    }

    /** Cree (au besoin) et renvoie la rubrique « DIVERS ». */
    public function assurerDivers(): Rubrique
    {
        $existante = $this->trouverParLibelle(self::RUBRIQUE_DIVERS);
        if ($existante instanceof Rubrique) {
            return $existante;
        }

        $divers = (new Rubrique())
            ->setLibelle(self::RUBRIQUE_DIVERS)
            ->setActive(true)
            ->setPosition($this->positionMax() + 1);

        $this->em->persist($divers);
        $this->em->flush();

        return $divers;
    }

    /** Autres rubriques proposees comme destination. */
    public function ciblesPossibles(Rubrique $rubrique): array
    {
        return array_values(array_filter(
            $this->em->getRepository(Rubrique::class)->findBy([], ['position' => 'ASC', 'libelle' => 'ASC']),
            static fn (Rubrique $r): bool => $r->getId() !== $rubrique->getId()
        ));
    }

    // ------------------------------------------------------------------
    //  Aides
    // ------------------------------------------------------------------

    /** Cle de comparaison : minuscules, espaces multiples reduits. */
    private function cle(string $libelle): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($libelle)));
    }

    private function trouverParLibelle(string $libelle): ?Rubrique
    {
        $cle = $this->cle($libelle);
        foreach ($this->em->getRepository(Rubrique::class)->findAll() as $candidate) {
            if ($this->cle((string) $candidate->getLibelle()) === $cle) {
                return $candidate;
            }
        }

        return null;
    }

    /** @param class-string $entite */
    private function compter(string $entite, string $champ, string $cle): int
    {
        try {
            return (int) $this->em->createQuery(sprintf(
                'SELECT COUNT(e.id) FROM %s e WHERE LOWER(TRIM(e.%s)) = :cle',
                $entite,
                $champ
            ))
                ->setParameter('cle', $cle)
                ->getSingleScalarResult();
        } catch (\Throwable) {
            // Table absente sur cette installation : aucun mouvement a deplacer.
            return 0;
        }
    }

    /** @param class-string $entite */
    private function reaffecter(string $entite, string $champ, string $source, string $cible): int
    {
        try {
            return (int) $this->em->createQuery(sprintf(
                'UPDATE %s e SET e.%s = :cible WHERE LOWER(TRIM(e.%s)) = :source',
                $entite,
                $champ,
                $champ
            ))
                ->setParameter('cible', $cible)
                ->setParameter('source', $this->cle($source))
                ->execute();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function positionMax(): int
    {
        try {
            return (int) ($this->em->createQuery('SELECT MAX(r.position) FROM ' . Rubrique::class . ' r')->getSingleScalarResult() ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
