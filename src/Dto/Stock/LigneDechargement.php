<?php

namespace App\Dto\Stock;

/**
 * Une ligne du fichier de dechargement de cacao, normalisee.
 */
final class LigneDechargement
{
    /** Indices de colonnes du fichier (lettres Excel ou pistes CSV). */
    public array $carte = [];

    public int $ligne = 0;

    public string $code = '';
    public ?string $date = null;
    public ?string $site = null;
    public ?string $produit = null;
    public ?string $cooperative = null;
    public ?string $immat = null;
    public ?string $transporteur = null;
    public ?int $nbreSacs = null;
    public ?float $poidsNet = null;
    public ?string $statut = null;
    public ?string $dateDechargement = null;
    public ?string $observation = null;
    public ?float $pnp = null;
    public ?string $traitant = null;
    public ?string $categorie = null;

    public ?float $ecart = null;
    public string $verdict = 'nouveau';
    public ?string $idExistant = null;
    /** @var string[] */
    public array $anomalies = [];
    /** @var string[] */
    public array $modifications = [];

    /** @var array<string, mixed> */
    public array $existant = [];

    public function aDesAnomalies(): bool { return $this->anomalies !== []; }

    public function estEnregistrable(): bool
    {
        return !in_array($this->verdict, ['invalide', 'rejete'], true) && !$this->aDesAnomalies();
    }

    /** Dictionnaire normalise pret a etre enregistre (sert aussi d'empreinte anti-double envoi). */
    public function versTableau(): array
    {
        return [
            'code'              => $this->code,
            'date'              => $this->date,
            'site'              => $this->site,
            'produit'           => $this->produit,
            'cooperative'       => $this->cooperative,
            'immat'             => $this->immat,
            'transporteur'      => $this->transporteur,
            'nbre_sacs'         => $this->nbreSacs,
            'poids_net'         => $this->poidsNet,
            'statut'            => $this->statut,
            'date_dechargement' => $this->dateDechargement,
            'observation'       => $this->observation,
            'pnp'               => $this->pnp,
            'traitant'          => $this->traitant,
            'categorie'         => $this->categorie,
        ];
    }
}
