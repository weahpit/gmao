<?php

namespace App\Service\Stock;

use App\Entity\Stock\SortieStock;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Genere et gere le BON DE SORTIE de stock.
 *
 * TOUTES les sorties de stock (quel que soit le type de stock / la rubrique)
 * sont soumises a un bon signe par le Directeur General, sur le modele des
 * bons de sortie PDR (App\Entity\BonSortie) :
 *   1. une sortie est enregistree            -> bon EN_ATTENTE
 *   2. le Directeur General signe le bon      -> bon VALIDE
 *   3. la Direction Generale peut annuler     -> bon ANNULE
 *
 * Le bon est porte par la sortie elle-meme (colonnes bon_reference, bon_token,
 * bon_statut, signe_at, signe_par) : pas de doublon de donnees, la sortie
 * reste la source de verite pour les stocks.
 */
class BonSortieStockService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Emet un bon de sortie pour une sortie (si elle n'en a pas encore).
     * Ne fait pas de flush : l'appelant maitrise la transaction.
     */
    public function emettreBon(SortieStock $sortie): SortieStock
    {
        if ($sortie->getReference() === null) {
            $sortie->setReference($this->genererReference($sortie));
        }
        if ($sortie->getToken() === null || $sortie->getToken() === '') {
            $sortie->setToken(bin2hex(random_bytes(16)));
        }
        $sortie->setStatut(SortieStock::STATUT_EN_ATTENTE);

        return $sortie;
    }

    /**
     * Signature du bon par la Direction Generale.
     * Retourne null si le bon ne peut pas etre signe (statut inattendu).
     */
    public function signer(SortieStock $sortie, User $signataire, ?string $observation = null): ?SortieStock
    {
        if (!$sortie->isEnAttente()) {
            return null;
        }

        $sortie->setStatut(SortieStock::STATUT_VALIDE);
        $sortie->setSigneAt(new \DateTimeImmutable());
        $sortie->setSignePar($this->nomUtilisateur($signataire));
        if ($observation !== null && trim($observation) !== '') {
            $sortie->setObservationSignature(mb_substr(trim($observation), 0, 255));
        }

        return $sortie;
    }

    /** Annulation / refus du bon par la Direction Generale. */
    public function annuler(SortieStock $sortie, User $signataire, ?string $observation = null): ?SortieStock
    {
        if (!$sortie->isEnAttente()) {
            return null;
        }

        $sortie->setStatut(SortieStock::STATUT_ANNULE);
        $sortie->setSigneAt(new \DateTimeImmutable());
        $sortie->setSignePar($this->nomUtilisateur($signataire));
        if ($observation !== null && trim($observation) !== '') {
            $sortie->setObservationSignature(mb_substr(trim($observation), 0, 255));
        }

        return $sortie;
    }

    /** Nom lisible du signataire (prenoms + nom, ou e-mail a defaut). */
    public function nomUtilisateur(User $user): string
    {
        $nom = trim(((string) $user->getPrenoms()) . ' ' . ((string) $user->getNom()));

        return $nom !== '' ? $nom : (string) $user->getEmail();
    }

    /** Reference lisible du bon : BS-AAAAMMJJ-XXXXXX. */
    public function genererReference(SortieStock $sortie): string
    {
        $date = $sortie->getDateSortie() ?? new \DateTime();

        return sprintf('BS-%s-%s', $date->format('Ymd'), strtoupper(bin2hex(random_bytes(3))));
    }

    /**
     * Lignes "facon BonSortie" pour le PDF (une seule ligne : la sortie).
     *
     * @return array<int, array<string, string>>
     */
    public function lignesPdf(SortieStock $sortie): array
    {
        $qte = $sortie->getQuantite() !== null
            ? rtrim(rtrim(number_format((float) $sortie->getQuantite(), 2, '.', ''), '0'), '.')
            : '';

        return [[
            'code'        => '',
            'designation' => strtoupper((string) $sortie->getRubrique()),
            'qte'         => $qte . ' ' . ((string) $sortie->getUnite()),
            'stock_avant' => '',
            'stock_apres' => '',
            'mention'     => (string) $sortie->getMotif(),
        ]];
    }

    /**
     * Informations d'en-tete du PDF, au meme format que pdfService::genererBonSortie().
     *
     * @return array<string, string>
     */
    public function infosPdf(SortieStock $sortie): array
    {
        return [
            'reference'   => (string) $sortie->getReference(),
            'date'        => $sortie->getDateSortie() ? $sortie->getDateSortie()->format('d/m/Y \u00e0 H:i') : '',
            'service'     => (string) $sortie->getServiceLibelle(),
            'demandeur'   => (string) $sortie->getAgentLibelle(),
            'etabli_par'  => (string) $sortie->getAgentLibelle(),
            'statut'      => (string) ($sortie->getStatut() ?? SortieStock::STATUT_EN_ATTENTE),
            'signe_par'   => (string) $sortie->getSignePar(),
            'signe_at'    => $sortie->getSigneAt() ? $sortie->getSigneAt()->format('d/m/Y \u00e0 H:i') : '',
            'valide_par'  => (string) $sortie->getSignePar(),
            'valide_at'   => $sortie->getSigneAt() ? $sortie->getSigneAt()->format('d/m/Y \u00e0 H:i') : '',
            'cloture_par' => '',
            'cloture_at'  => '',
        ];
    }

    /** Contenu encode dans le QR du bon (l'app mobile peut le verifier a la guerite). */
    public function qrData(SortieStock $sortie): string
    {
        return (string) json_encode([
            'app' => 'CITRAC',
            'typ' => 'BON_SORTIE_STOCK',
            'ref' => (string) $sortie->getReference(),
            'tok' => (string) $sortie->getToken(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
