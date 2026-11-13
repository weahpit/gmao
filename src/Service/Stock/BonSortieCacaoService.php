<?php

namespace App\Service\Stock;

use App\Entity\Stock\SortieStock;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Génère et gère le BON DE SORTIE CACAO.
 *
 * Calqué sur le circuit des bons de sortie PDR (App\Entity\BonSortie) :
 *   1. une sortie de cacao est enregistrée          -> bon EN_ATTENTE
 *   2. le Directeur Général signe le bon            -> bon VALIDE
 *   3. la Direction Générale peut annuler le bon    -> bon ANNULE
 *
 * Le bon est porté par la sortie elle-même (colonnes bon_reference, bon_token,
 * bon_statut, signe_at, signe_par) : pas de doublon de données, la sortie
 * reste la source de vérité pour les stocks.
 */
class BonSortieCacaoService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Émet un bon de sortie pour une sortie cacao (si elle n'en a pas encore).
     * Ne fait pas de flush : l'appelant maîtrise la transaction.
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
     * Signature du bon par la Direction Générale.
     * Retourne null si le bon ne peut pas être signé (statut inattendu).
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

    /** Annulation / refus du bon par la Direction Générale. */
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

    /** Nom lisible du signataire (prénoms + nom, ou e-mail à défaut). */
    public function nomUtilisateur(User $user): string
    {
        $nom = trim(((string) $user->getPrenoms()) . ' ' . ((string) $user->getNom()));

        return $nom !== '' ? $nom : (string) $user->getEmail();
    }

    /** Référence lisible du bon : BS-CAC-AAAAMMJJ-XXXXXX. */
    public function genererReference(SortieStock $sortie): string
    {
        $date = $sortie->getDateSortie() ?? new \DateTime();

        return sprintf('BS-CAC-%s-%s', $date->format('Ymd'), strtoupper(bin2hex(random_bytes(3))));
    }

    /**
     * Lignes « façon BonSortie » pour le PDF (une seule ligne : la sortie cacao).
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
     * Informations d'en-tête du PDF, au même format que pdfService::genererBonSortie().
     *
     * @return array<string, string>
     */
    public function infosPdf(SortieStock $sortie): array
    {
        return [
            'reference'   => (string) $sortie->getReference(),
            'date'        => $sortie->getDateSortie() ? $sortie->getDateSortie()->format('d/m/Y à H:i') : '',
            'service'     => (string) $sortie->getServiceLibelle(),
            'demandeur'   => (string) $sortie->getAgentLibelle(),
            'etabli_par'  => (string) $sortie->getAgentLibelle(),
            'statut'      => (string) ($sortie->getStatut() ?? SortieStock::STATUT_EN_ATTENTE),
            'signe_par'   => (string) $sortie->getSignePar(),
            'signe_at'    => $sortie->getSigneAt() ? $sortie->getSigneAt()->format('d/m/Y à H:i') : '',
            'cloture_par' => '',
            'cloture_at'  => '',
            'valide_par'  => (string) $sortie->getSignePar(),
            'valide_at'   => $sortie->getSigneAt() ? $sortie->getSigneAt()->format('d/m/Y à H:i') : '',
        ];
    }

    /** Contenu encodé dans le QR du bon (l'app mobile peut le vérifier à la guérite). */
    public function qrData(SortieStock $sortie): string
    {
        return (string) json_encode([
            'app' => 'CITRAC',
            'typ' => 'BON_SORTIE_CACAO',
            'ref' => (string) $sortie->getReference(),
            'tok' => (string) $sortie->getToken(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
