<?php

namespace App\Service\Stock;

use App\Entity\Stock\SaisieStock;
use App\Repository\Production\ProduitRepository;
use App\Repository\Stock\RubriqueRepository;

/**
 * Source UNIQUE de verite pour les listes de reference du module Stock,
 * partagee par l'application web ET l'application mobile :
 *
 *  - rubriques (type de stock) : lues dans la table stock_rubrique ;
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
    public function __construct(
        private readonly RubriqueRepository $rubriqueRepo,
        private readonly ProduitRepository $produitRepo,
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

    /** Vrai si la nature fournie fait partie de la liste de reference. */
    public function natureValide(?string $valeur): bool
    {
        return $valeur !== null && in_array($valeur, $this->natures(), true);
    }
}
