<?php

namespace App\Service\Stock;

use App\Entity\Stock\SaisieStock;
use App\Repository\Production\ProduitRepository;
use App\Repository\Stock\RubriqueRepository;
use App\Repository\Stock\UniteRepository;

/**
 * Source UNIQUE de verite pour les listes de reference du module Stock,
 * partagee par l'application web ET l'application mobile :
 *
 *  - rubriques (type de stock) : lues dans la table stock_rubrique ;
 *  - unites de mesure (Kg, Litres, ...) : lues dans la table stock_unite ;
 *  - natures du produit         : lues dans le referentiel Produit (Production).
 *
 * Objectif : web et mobile affichent et valident EXACTEMENT la meme liste,
 * sans valeurs codees en dur dupliquees de part et d'autre.
 *
 * Securite / compatibilite : si une table est vide (base pas encore peuplee,
 * fixtures non jouees), on retombe sur les constantes historiques de
 * SaisieStock pour ne jamais presenter une liste vide.
 */
final class ReferentielStock
{
    /** Unite proposee par defaut si la table n'en designe aucune. */
    public const UNITE_DEFAUT = 'Kg';

    public function __construct(
        private readonly RubriqueRepository $rubriqueRepo,
        private readonly ProduitRepository $produitRepo,
        private readonly UniteRepository $uniteRepo,
    ) {}

    /**
     * Rubriques de stock (type de stock).
     *
     * @return string[]
     */
    public function rubriques(): array
    {
        $libelles = $this->rubriqueRepo->libelles();

        return $libelles !== [] ? $libelles : SaisieStock::RUBRIQUES;
    }

    /**
     * Unites de mesure (Kg, Litres, ...) — table stock_unite.
     *
     * @return string[]
     */
    public function unites(): array
    {
        $libelles = $this->uniteRepo->libelles();

        return $libelles !== [] ? $libelles : SaisieStock::UNITES;
    }

    /**
     * Unite proposee par defaut dans les formulaires.
     * Si la table n'en designe aucune, on garde "Kg" s'il est disponible.
     */
    public function uniteDefaut(): string
    {
        $defaut = $this->uniteRepo->libelleParDefaut();
        if ($defaut !== null && $defaut !== '') {
            return $defaut;
        }

        $unites = $this->unites();

        return in_array(self::UNITE_DEFAUT, $unites, true)
            ? self::UNITE_DEFAUT
            : (string) ($unites[0] ?? self::UNITE_DEFAUT);
    }

    /**
     * Natures du produit (= libelles des produits du referentiel Production).
     *
     * @return string[]
     */
    public function natures(): array
    {
        $libelles = $this->produitRepo->libelles();

        return $libelles !== [] ? $libelles : SaisieStock::NATURES;
    }

    /** Vrai si la rubrique fournie fait partie de la liste de reference. */
    public function rubriqueValide(?string $valeur): bool
    {
        return $valeur !== null && in_array($valeur, $this->rubriques(), true);
    }

    /** Vrai si l'unite fournie fait partie de la liste de reference. */
    public function uniteValide(?string $valeur): bool
    {
        return $valeur !== null && in_array($valeur, $this->unites(), true);
    }

    /** Vrai si la nature fournie fait partie de la liste de reference. */
    public function natureValide(?string $valeur): bool
    {
        return $valeur !== null && in_array($valeur, $this->natures(), true);
    }
}
