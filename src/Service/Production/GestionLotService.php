<?php

namespace App\Service\Production;

use App\Entity\Logistique\Magasin;
use App\Entity\Logistique\Proprietaire;
use App\Entity\Production\Lot;
use App\Entity\Production\Produit;
use App\Repository\Logistique\MagasinRepository;
use App\Repository\Production\LotRepository;
use App\Repository\Stock\LotDechargementRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Regles metier des lots de production, partagees par l'ecran web
 * /logistique/ProductionLots et par les anciennes routes API (embarquement).
 *
 * Points corriges par rapport a l'ancienne logique dupliquee dans le JS :
 *  - le doublon de numero est cherche sur le NUMERO DE LOT (et non sur
 *    "origine", qui n'est pas unique et n'est pas rempli partout) ;
 *  - le poids net du lot NC vaut le poids demande (et non le poids total, qui
 *    etait ensuite soustrait au lot d'origine) ;
 *  - le nombre de sacs et le poids brut du lot NC sont calcules AVANT de
 *    modifier le lot d'origine ;
 *  - les numeros issus d'une scission sont numerotes sans collision
 *    (BASE-1, BASE-2, BASE-3...) meme apres plusieurs scissions ;
 *  - toute la scission est jouee dans une transaction.
 */
final class GestionLotService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LotRepository $lots,
        private readonly LotDechargementRepository $liens,
        private readonly MagasinRepository $magasins,
        private readonly NatureLot $nature,
    ) {
    }

    /**
     * Cree ou met a jour un lot apres validation.
     *
     * @param array<string, mixed> $donnees
     *
     * @return array{ok: bool, erreurs: string[], lot: Lot|null, creation: bool}
     */
    public function enregistrer(array $donnees, ?Lot $lot, string $utilisateur): array
    {
        $creation = null === $lot;
        $lot ??= new Lot();

        $erreurs = [];
        if (!$creation && !$lot->getCodeDossier()->isEmpty()) $erreurs[] = 'Lot deja rattache a un dossier d’embarquement : ses donnees de production doivent etre rapprochees avant toute modification.';

        $numero = mb_strtoupper(trim((string) ($donnees['numero_lot'] ?? '')));
        $proprio = $donnees['proprietaire'] ?? null;
        $produit = $donnees['produit'] ?? null;
        $magasin = $donnees['magasin'] ?? null;
        $dateProd = $this->date((string) ($donnees['date_prod'] ?? ''));

        if ('' === $numero) {
            $erreurs[] = 'Le numéro de lot est obligatoire.';
        } elseif (mb_strlen($numero) > 20) {
            $erreurs[] = 'Le numéro de lot ne peut pas dépasser 20 caractères.';
        } elseif ($this->lots->numeroDejaUtilise($numero, $lot->getId())) {
            $erreurs[] = sprintf('Le lot « %s » existe déjà : choisissez un autre numéro.', $numero);
        }

        if (!$proprio instanceof Proprietaire) {
            $erreurs[] = 'Le propriétaire est obligatoire.';
        }
        if (!$produit instanceof Produit) {
            $erreurs[] = 'Le produit est obligatoire.';
        }
        if (null === $dateProd) {
            $erreurs[] = 'La date de production est obligatoire.';
        }
        // Emplacement deduit du produit : les feves vont au magasin des feves,
        // le beurre / la masse / le tourteau au magasin des produits finis.
        // Le choix automatique prime sur la liste deroulante pour eviter les
        // erreurs de rangement.
        [$magasinAttendu, $magasinAuto] = $produit instanceof Produit
            ? $this->emplacementDuProduit($produit)
            : [null, null];

        if (null !== $magasinAuto) {
            $magasin = $magasinAuto;
        } elseif (null !== $magasinAttendu) {
            $erreurs[] = sprintf(
                'Le magasin « %s » est introuvable : créez-le avant d\'enregistrer ce lot.',
                $magasinAttendu
            );
        } elseif (!$magasin instanceof Magasin) {
            $erreurs[] = 'L\'emplacement (magasin) est obligatoire.';
        }

        // Poids net theorique : valeur saisie, sinon valeur par defaut du produit.
        $pn = $this->nombre($donnees['pn_th'] ?? null);
        if (null === $pn && $produit instanceof Produit) {
            $pn = $this->nombre($produit->getPoidsNetDefault());
        }
        if (null === $pn || $pn <= 0) {
            $erreurs[] = 'Le poids net théorique doit être supérieur à 0.';
        }

        $nbSacs = $this->nombre($donnees['nb_sacs'] ?? null);
        if (null === $nbSacs && $produit instanceof Produit) {
            $nbSacs = $this->nombre($produit->getNbDefault());
        }
        if (null !== $nbSacs && $nbSacs < 0) {
            $erreurs[] = 'Le nombre de sacs ne peut pas être négatif.';
        }

        $tare = 0.0;
        if ($produit instanceof Produit) {
            $tare = (float) ($produit->getTareUniteDefault() ?? 0);
        }

        // Poids brut : valeur saisie, sinon poids net + tare x sacs.
        $pb = $this->nombre($donnees['pb_th'] ?? null);
        if (null === $pb && null !== $pn) {
            $pb = $pn + ($nbSacs ?? 0) * $tare;
        }
        if (null !== $pb && $pb < 0) {
            $erreurs[] = 'Le poids brut ne peut pas être négatif.';
        }

        // Qualite selon la nature du produit :
        //  - feves : Grade 1, Grade 2 ou Sous-grade (jamais C / NC) ;
        //  - produits finis (masse, beurre, tourteau, poudre) : C ou NC.
        $nature = $this->nature->natureProduit($produit instanceof Produit ? $produit : null);
        $qualite = $this->nature->normaliser((string) ($donnees['qualite'] ?? ''), $produit instanceof Produit ? $produit : null);
        if (null === $qualite) {
            $erreurs[] = match ($nature) {
                NatureLot::FEVE => 'La qualité d\'un lot de fèves doit être « Grade 1 », « Grade 2 » ou « Sous-grade ».',
                NatureLot::FINI => 'La qualité d\'un produit fini doit être « C » (conforme) ou « NC » (non conforme).',
                default => 'Qualité invalide : Grade 1, Grade 2 ou Sous-grade pour des fèves ; C ou NC pour un produit fini.',
            };
        }

        if ($erreurs !== []) {
            return ['ok' => false, 'erreurs' => $erreurs, 'lot' => null, 'creation' => $creation];
        }

        $lot->setNumeroLot($numero);
        if (null === $lot->getOrigine() || '' === (string) $lot->getOrigine()) {
            $lot->setOrigine($numero);
        }
        $lot->setCodeProprio($proprio);
        $lot->setCodeProduit($produit);
        $lot->setCodeMagasin($magasin);
        $lot->setDateProd($dateProd);
        $lot->setPnTh(round((float) $pn, 3));
        $lot->setPbTht(null !== $pb ? round($pb, 3) : null);
        $lot->setNbSacs(null !== $nbSacs ? round($nbSacs, 3) : null);
        $lot->setQualite($qualite);

        if ($creation) {
            $lot->setCreatedAt(new \DateTimeImmutable());
            $lot->setCreatedBy($utilisateur);
            $lot->setScission(false);
            $lot->setPoidsNetDispo(round((float) $pn, 3));
        } else {
            $lot->setUpdatedAt(new \DateTimeImmutable());
            $lot->setUpdatedBy($utilisateur);
        }

        $this->em->persist($lot);
        $this->em->flush();

        return ['ok' => true, 'erreurs' => [], 'lot' => $lot, 'creation' => $creation];
    }


    /**
     * Emplacement attendu pour un produit.
     *
     * @return array{0: string|null, 1: Magasin|null} libelle attendu, magasin trouve
     */
    public function emplacementDuProduit(Produit $produit): array
    {
        $nom = MagasinRepository::normaliser((string) $produit->getLibelle());

        $feves = false !== strpos($nom, 'feve') || false !== strpos($nom, 'feves');
        $fini = false !== strpos($nom, 'beurre')
            || false !== strpos($nom, 'masse')
            || false !== strpos($nom, 'tourteau')
            || false !== strpos($nom, 'poudre');

        if ($feves) {
            $magasin = $this->magasins->trouverParLibelle(
                'MAGASIN FEVES CITRAC',
                'MAGASIN FEVES',
                'MAGASIN DES FEVES',
            );

            return ['MAGASIN FEVES CITRAC', $magasin];
        }

        if ($fini) {
            $magasin = $this->magasins->trouverParLibelle(
                'MAGASIN PRODUITS FINIS',
                'MAGASIN PRODUIT FINI',
                'PRODUITS FINIS',
            );

            return ['MAGASIN PRODUITS FINIS', $magasin];
        }

        return [null, null];
    }

    /**
     * Scission conforme / non conforme d'un lot.
     *
     * @return array{ok: bool, erreurs: string[], lotC: Lot|null, lotNc: Lot|null, poidsNc: float, sacsNc: float}
     */
    public function scinder(Lot $lot, float $poidsNc, string $utilisateur): array
    {
        $erreurs = [];

        // Un lot de feves n'est pas scindable : il reste entier, sa qualite se
        // saisit en Grade 1 / Grade 2 / Sous-grade. Seuls les produits finis se
        // scindent en conforme / non conforme.
        if ($this->nature->estFeve($lot)) {
            $erreurs[] = 'Un lot de fèves ne peut pas être scindé : sa qualité se saisit en Grade 1, Grade 2 ou Sous-grade. Seuls les produits finis (masse, beurre, tourteau) se scindent en C / NC.';

            return ['ok' => false, 'erreurs' => $erreurs, 'lotC' => null, 'lotNc' => null, 'poidsNc' => 0.0, 'sacsNc' => 0.0];
        }

        if (!$lot->getCodeDossier()->isEmpty()) $erreurs[] = 'Lot deja rattache a un dossier d’embarquement : scission bloquee pour conserver les poids reserves.';
        $pn = (float) ($lot->getPnTh() ?? 0);
        $poidsNc = round($poidsNc, 3);

        if ('NC' === mb_strtoupper((string) $lot->getQualite())) {
            $erreurs[] = 'Ce lot est déjà non conforme : la scission ne s\'applique pas.';
        }
        if ($poidsNc <= 0) {
            $erreurs[] = 'Saisissez un poids non conforme supérieur à 0 kg.';
        }
        if ($pn <= 0) {
            $erreurs[] = 'Ce lot n\'a pas de poids net théorique : renseignez-le avant de le scinder.';
        }
        if ($pn > 0 && $poidsNc >= $pn) {
            $erreurs[] = sprintf('Le poids non conforme doit être inférieur au poids du lot (%.0f kg).', $pn);
        }
        if (!$lot->getCodeProduit()) {
            $erreurs[] = 'Ce lot n\'a pas de produit : impossible de calculer la scission.';
        }

        if ($erreurs !== []) {
            return ['ok' => false, 'erreurs' => $erreurs, 'lotC' => null, 'lotNc' => null, 'poidsNc' => 0.0, 'sacsNc' => 0.0];
        }

        $numeroActuel = (string) $lot->getNumeroLot();
        $base = preg_replace('/-\d+$/', '', $numeroActuel) ?: $numeroActuel;
        $occupes = $this->lots->suffixesUtilises($base);

        $suffixe = $occupes === [] ? 2 : max($occupes) + 1;
        $numeroNc = $base . '-' . max(2, $suffixe);
        while ($this->lots->numeroDejaUtilise($numeroNc)) {
            ++$suffixe;
            $numeroNc = $base . '-' . max(2, $suffixe);
        }

        // Le lot d'origine garde son numero s'il est deja suffixe (scission en
        // plusieurs etapes), sinon il devient BASE-1 pour rester lisible.
        $numeroParent = $numeroActuel;
        if ($occupes === [] && !preg_match('/-\d+$/', $numeroActuel)) {
            $numeroParent = $base . '-1';
        }

        $nbSacs = (float) ($lot->getNbSacs() ?? 0);
        $sacsNc = $pn > 0 ? round($nbSacs * $poidsNc / $pn, 3) : 0.0;
        $sacsNc = min($sacsNc, $nbSacs);

        $tare = (float) ($lot->getCodeProduit()->getTareUniteDefault() ?? 0);
        $pbNc = round($poidsNc + $sacsNc * $tare, 3);
        $pbParent = round(max(0.0, (float) ($lot->getPbTht() ?? 0) - $pbNc), 3);

        $magasinNc = $this->magasinNonConforme() ?? $lot->getCodeMagasin();

        $lotNc = new Lot();
        $lotNc->setNumeroLot($numeroNc);
        $lotNc->setOrigine($lot->getOrigine() ?: $base);
        $lotNc->setScission(true);
        $lotNc->setQualite('NC');
        $lotNc->setPnTh($poidsNc);
        $lotNc->setPbTht($pbNc);
        $lotNc->setNbSacs($sacsNc);
        $lotNc->setPoidsNetDispo($poidsNc);
        $lotNc->setCodeMagasin($magasinNc);
        $lotNc->setCodeProduit($lot->getCodeProduit());
        $lotNc->setCodeProprio($lot->getCodeProprio());
        $lotNc->setDateProd($lot->getDateProd());
        $lotNc->setCreatedAt(new \DateTimeImmutable());
        $lotNc->setCreatedBy($utilisateur);

        $lot->setNumeroLot($numeroParent);
        $lot->setScission(true);
        $lot->setQualite('C');
        $lot->setPnTh(round($pn - $poidsNc, 3));
        $lot->setNbSacs(round($nbSacs - $sacsNc, 3));
        $lot->setPbTht($pbParent);
        $lot->setPoidsNetDispo(round($pn - $poidsNc, 3));
        $lot->setUpdatedAt(new \DateTimeImmutable());
        $lot->setUpdatedBy($utilisateur);

        $this->em->wrapInTransaction(function () use ($lot, $lotNc): void {
            $this->em->persist($lotNc);
            $this->em->persist($lot);
        });

        return ['ok' => true, 'erreurs' => [], 'lotC' => $lot, 'lotNc' => $lotNc, 'poidsNc' => $poidsNc, 'sacsNc' => $sacsNc];
    }

    /**
     * Suppression d'un lot jamais utilise.
     *
     * @return array{ok: bool, message: string}
     */
    public function supprimer(Lot $lot): array
    {
        if (!$lot->getCodeDossier()->isEmpty()) return ['ok'=>false, 'message'=>'Lot rattache a un dossier d’embarquement : suppression interdite.'];
        if ($this->liens->compterParLot($lot) > 0) {
            return ['ok' => false, 'message' => 'Ce lot est rattaché à un ou plusieurs camions : retirez-le de l\'ordre d\'usinage avant de le supprimer.'];
        }
        if (true === $lot->isScission()) {
            return ['ok' => false, 'message' => 'Ce lot est issu d\'une scission : il ne peut pas être supprimé seul.'];
        }

        $numero = (string) $lot->getNumeroLot();
        $this->em->remove($lot);
        $this->em->flush();

        return ['ok' => true, 'message' => sprintf('Le lot « %s » a été supprimé.', $numero)];
    }

    private function magasinNonConforme(): ?Magasin
    {
        $magasin = $this->magasins->createQueryBuilder('m')
            ->andWhere('UPPER(m.libelle) LIKE :nc')
            ->setParameter('nc', '%NON CONFORME%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $magasin instanceof Magasin ? $magasin : null;
    }

    private function date(string $valeur): ?\DateTime
    {
        $valeur = trim($valeur);
        if ('' === $valeur) {
            return null;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $valeur);

        return $date instanceof \DateTime ? $date : null;
    }

    private function nombre(mixed $valeur): ?float
    {
        if (null === $valeur || '' === $valeur) {
            return null;
        }
        if (is_string($valeur)) {
            $valeur = str_replace([' ', ','], ['', '.'], $valeur);
        }

        return is_numeric($valeur) ? (float) $valeur : null;
    }
}
