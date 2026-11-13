<?php

namespace App\Service\Stock;

use App\Entity\Stock\InitialisationStock;
use App\Entity\Stock\SaisieStock;
use App\Entity\Stock\SortieStock;
use App\Repository\Stock\InitialisationStockRepository;
use App\Repository\Stock\SaisieStockRepository;
use App\Repository\Stock\SortieStockRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calcul du stock a partir d'une INITIALISATION (inventaire physique).
 *
 * Regle metier :
 *   stock final = quantite inventoriee a la date D
 *               + entrees POSTERIEURES a D
 *               - sorties POSTERIEURES a D
 *
 * Consequence : les mouvements anterieurs ou egaux a la date d'inventaire sont
 * ignores pour le calcul — c'est tout l'interet de l'inventaire : il remplace
 * l'historique jusqu'a cette date.
 *
 * Ce service est la SEULE source du calcul : la page « Initialisation des
 * stocks », la consultation des stocks et l'API mobile l'appellent tous, pour
 * que les trois affichent exactement le meme chiffre.
 */
final class StockInitialService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InitialisationStockRepository $initRepo,
        private readonly SaisieStockRepository $saisieRepo,
        private readonly SortieStockRepository $sortieRepo,
        private readonly ReferentielStock $referentiel,
    ) {}

    /** Ligne de calcul a zero. */
    private function ligne(): array
    {
        return [
            'initialise'        => false,
            'dateInventaire'    => null,
            'stockInitialKg'    => 0.0,
            'stockInitialLitres'=> 0.0,
            'entreeKg'          => 0.0,
            'entreeLitres'      => 0.0,
            'sortieKg'          => 0.0,
            'sortieLitres'      => 0.0,
            'stockFinalKg'      => 0.0,
            'stockFinalLitres'  => 0.0,
            'nbEntrees'         => 0,
            'nbSorties'         => 0,
            // Detail par nature du produit : [nature => ligneNature()]
            'parNature'         => [],
        ];
    }

    /**
     * Ligne de detail d'une nature (stock initial, mouvements, stock final)
     * au sein d'une rubrique : meme structure que ligne(), sans sous-detail.
     */
    private function ligneNature(string $nature): array
    {
        $l = $this->ligne();
        $l['nom'] = $nature;
        unset($l['parNature']);

        return $l;
    }

    /** Cree (au besoin) la ligne de detail d'une nature et renvoie sa cle. */
    private function natureLigne(array &$ligne, ?string $nature): string
    {
        $cle = trim((string) $nature);
        if ($cle === '') {
            $cle = 'Nat. non precisee';
        }
        $ligne['parNature'][$cle] ??= $this->ligneNature($cle);

        return $cle;
    }

    /**
     * Apercu complet : stock initial, mouvements posterieurs, stock final.
     *
     * @param string[]|null $rubriques  rubriques a calculer (defaut : referentiel)
     * @param string|null   $rubriqueFiltre  force une seule rubrique
     *
     * @return array<string, mixed>
     */
    public function apercu(?array $rubriques = null, ?string $rubriqueFiltre = null): array
    {
        $liste = $rubriques !== null && $rubriques !== [] ? $rubriques : $this->referentiel->rubriques();
        if ($rubriqueFiltre !== null && $rubriqueFiltre !== '') {
            $liste = [$rubriqueFiltre];
        }

        $parRubrique = [];
        foreach ($liste as $r) {
            $parRubrique[$r] = $this->ligne();
        }

        // 1) Stock initial de chaque rubrique (initialisation active la plus recente).
        $initialisations = $this->initRepo->actives();
        $dateRef = [];
        foreach ($initialisations as $init) {
            $r = (string) $init->getRubrique();
            if (!isset($parRubrique[$r])) {
                $parRubrique[$r] = $this->ligne();
            }
            $litres = $init->getUnite() === 'Litres';
            if ($litres) {
                $parRubrique[$r]['stockInitialLitres'] += $init->getQuantite();
            } else {
                $parRubrique[$r]['stockInitialKg'] += $init->getQuantite();
            }
            // Meme quantite portee dans le detail par nature du produit.
            $cleNature = $this->natureLigne($parRubrique[$r], $init->getNature());
            if ($litres) {
                $parRubrique[$r]['parNature'][$cleNature]['stockInitialLitres'] += $init->getQuantite();
            } else {
                $parRubrique[$r]['parNature'][$cleNature]['stockInitialKg'] += $init->getQuantite();
            }

            $parRubrique[$r]['initialise'] = true;

            $d = $init->getDateInventaire();
            if ($d !== null && (!isset($dateRef[$r]) || $d > $dateRef[$r])) {
                $dateRef[$r] = $d;
            }
        }

        // 2) Mouvements STRICTEMENT posterieurs a la date d'inventaire.
        foreach ($parRubrique as $r => &$l) {
            $apres = $dateRef[$r] ?? null;
            $l['dateInventaire'] = $apres?->format('Y-m-d');

            foreach ($this->saisieRepo->findByRubrique($r, $apres) as $s) {
                /** @var SaisieStock $s */
                $q = (float) ($s->getQuantite() ?? 0);
                if ($s->getUnite() === 'Litres') {
                    $l['entreeLitres'] += $q;
                } else {
                    $l['entreeKg'] += $q;
                }
                $l['nbEntrees']++;

                $cn = $this->natureLigne($l, $s->getNature());
                if ($s->getUnite() === 'Litres') {
                    $l['parNature'][$cn]['entreeLitres'] += $q;
                } else {
                    $l['parNature'][$cn]['entreeKg'] += $q;
                }
                $l['parNature'][$cn]['nbEntrees']++;
            }

            foreach ($this->sortieRepo->findByRubrique($r, $apres) as $s) {
                /** @var SortieStock $s */
                $q = (float) ($s->getQuantite() ?? 0);
                if ($s->getUnite() === 'Litres') {
                    $l['sortieLitres'] += $q;
                } else {
                    $l['sortieKg'] += $q;
                }
                $l['nbSorties']++;

                $cn = $this->natureLigne($l, $s->getNature());
                if ($s->getUnite() === 'Litres') {
                    $l['parNature'][$cn]['sortieLitres'] += $q;
                } else {
                    $l['parNature'][$cn]['sortieKg'] += $q;
                }
                $l['parNature'][$cn]['nbSorties']++;
            }

            $l['stockFinalKg'] = round($l['stockInitialKg'] + $l['entreeKg'] - $l['sortieKg'], 3);
            $l['stockFinalLitres'] = round($l['stockInitialLitres'] + $l['entreeLitres'] - $l['sortieLitres'], 3);

            // Meme calcul, nature par nature.
            foreach ($l['parNature'] as $k => $n) {
                $n['stockFinalKg'] = round($n['stockInitialKg'] + $n['entreeKg'] - $n['sortieKg'], 3);
                $n['stockFinalLitres'] = round($n['stockInitialLitres'] + $n['entreeLitres'] - $n['sortieLitres'], 3);
                $l['parNature'][$k] = $n;
            }
            unset($n);
        }
        unset($l);

        // 3) Totaux.
        $totaux = $this->ligne();
        foreach ($parRubrique as $l) {
            foreach (['stockInitialKg', 'stockInitialLitres', 'entreeKg', 'entreeLitres',
                      'sortieKg', 'sortieLitres', 'stockFinalKg', 'stockFinalLitres'] as $cle) {
                $totaux[$cle] += $l[$cle];
            }
            $totaux['nbEntrees'] += $l['nbEntrees'];
            $totaux['nbSorties'] += $l['nbSorties'];
        }

        $dateGlobale = null;
        foreach ($dateRef as $d) {
            if ($dateGlobale === null || $d > $dateGlobale) {
                $dateGlobale = $d;
            }
        }

        return [
            'initialise'      => $initialisations !== [],
            'dateInventaire'  => $dateGlobale?->format('Y-m-d'),
            'parRubrique'     => $parRubrique,
            'totaux'          => $totaux,
            'initialisations' => array_map(fn (InitialisationStock $i) => $this->versApi($i), $initialisations),
        ];
    }

    /** Representation lisible d'une initialisation (API + tableau). */
    public function versApi(InitialisationStock $init): array
    {
        return [
            'id'             => (string) $init->getId(),
            'rubrique'       => $init->getRubrique(),
            'nature'         => $init->getNature(),
            'quantite'       => $init->getQuantite(),
            'unite'          => $init->getUnite(),
            'date'           => $init->getDateInventaire()?->format('Y-m-d'),
            'dateAffiche'    => $init->getDateInventaire()?->format('d/m/Y H:i'),
            'stockTheorique' => $init->getStockTheorique(),
            'ecart'          => $init->getEcart(),
            'observation'    => $init->getObservation(),
            'auteur'         => $init->getAuteurLibelle(),
            'desactive'      => $init->isDesactive(),
        ];
    }

    /**
     * Enregistre une initialisation (inventaire physique) pour une rubrique.
     *
     * @param array<string, mixed> $donnees
     *
     * @return array{succes: bool, message: string, initialisation?: InitialisationStock}
     */
    public function initialiser(array $donnees, ?object $auteur = null): array
    {
        $rubrique   = trim((string) ($donnees['rubrique'] ?? ''));
        $nature     = trim((string) ($donnees['nature'] ?? ''));
        $unite      = trim((string) ($donnees['unite'] ?? ''));
        $dateStr    = trim((string) ($donnees['dateInventaire'] ?? ''));
        $remplacer  = (bool) ($donnees['remplacer'] ?? false);
        $observation = trim((string) ($donnees['observation'] ?? ''));

        if (!$this->referentiel->rubriqueValide($rubrique)) {
            return ['succes' => false, 'message' => 'Choisissez un type de stock valide.'];
        }
        if ($unite === '' || !$this->referentiel->uniteValide($unite)) {
            return ['succes' => false, 'message' => 'Choisissez une unite de mesure valide.'];
        }
        if ($dateStr === '') {
            return ['succes' => false, 'message' => "Indiquez la date de l'inventaire physique."];
        }

        $date = \DateTime::createFromFormat('!Y-m-d', substr($dateStr, 0, 10))
            ?: \DateTime::createFromFormat('!d/m/Y', substr($dateStr, 0, 10));
        if (!$date) {
            return ['succes' => false, 'message' => "La date de l'inventaire n'est pas valide."];
        }
        // Fin de journee : tous les mouvements du jour de l'inventaire sont
        // consideres comme deja compris dans le comptage physique.
        $date->setTime(23, 59, 59);
        if ($date > new \DateTime()) {
            return ['succes' => false, 'message' => "La date de l'inventaire ne peut pas etre dans le futur."];
        }

        $quantiteBrut = str_replace([' ', "\u{00a0}", "'"], ['', '', ''], str_replace(',', '.', (string) ($donnees['quantite'] ?? '')));
        if ($quantiteBrut === '' || !is_numeric($quantiteBrut)) {
            return ['succes' => false, 'message' => 'La quantite inventoriee doit etre un nombre.'];
        }
        $quantite = (float) $quantiteBrut;
        if ($quantite < 0) {
            return ['succes' => false, 'message' => 'La quantite inventoriee ne peut pas etre negative.'];
        }

        // Une seule initialisation active par rubrique + nature : la nouvelle
        // desactive la precedente, qui reste dans l'historique.
        $existante = $this->initRepo->plusRecentePour($rubrique, $nature !== '' ? $nature : null);

        $init = new InitialisationStock();
        $init->setRubrique($rubrique);
        $init->setNature($nature !== '' ? $nature : null);
        $init->setQuantite($quantite);
        $init->setUnite($unite);
        $init->setDateInventaire($date);
        $init->setObservation($observation !== '' ? $observation : null);
        $init->setRemplacePrecedent($remplacer && $existante !== null);

        if ($auteur !== null) {
            $init->setAuteur($auteur);
            $libelle = trim(((string) ($auteur->getPrenoms() ?? '')) . ' ' . ((string) ($auteur->getNom() ?? '')));
            $init->setAuteurLibelle($libelle !== '' ? $libelle : (string) ($auteur->getUserIdentifier() ?? ''));
        }

        // Photo du stock avant inventaire, pour tracer l'ecart constate.
        $avant = $existante?->getQuantite() ?? 0.0;
        $init->setStockTheorique(round($avant, 3));
        $init->setEcart(round($quantite - $avant, 3));

        if ($existante !== null) {
            $existante->setDesactive(true);
        }

        $this->em->persist($init);
        $this->em->flush();

        return [
            'succes' => true,
            'message' => sprintf(
                'Stock initial enregistre : %s %s au %s. Les mouvements posterieurs sont desormais pris en compte dans le stock final.',
                number_format($quantite, 2, ',', ' '),
                $unite,
                $date->format('d/m/Y')
            ),
            'initialisation' => $init,
        ];
    }

    /** Desactive une initialisation. */
    public function desactiver(InitialisationStock $init): void
    {
        $init->setDesactive(true);
        $this->em->flush();
    }

    /**
     * Stock final par rubrique — format simple pour l'API mobile.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stockFinal(): array
    {
        $apercu = $this->apercu();
        $out = [];
        foreach ($apercu['parRubrique'] as $rubrique => $l) {
            $out[] = [
                'rubrique'       => $rubrique,
                'initialise'     => $l['initialise'],
                'dateInventaire' => $l['dateInventaire'],
                'stockInitial'   => ['kg' => round((float) $l['stockInitialKg'], 3), 'litres' => round((float) $l['stockInitialLitres'], 3)],
                'entrees'        => ['kg' => round((float) $l['entreeKg'], 3), 'litres' => round((float) $l['entreeLitres'], 3)],
                'sorties'        => ['kg' => round((float) $l['sortieKg'], 3), 'litres' => round((float) $l['sortieLitres'], 3)],
                'stockFinal'     => ['kg' => round((float) $l['stockFinalKg'], 3), 'litres' => round((float) $l['stockFinalLitres'], 3)],
            ];
        }

        return $out;
    }
}
