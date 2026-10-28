<?php

namespace App\Entity;

use App\Repository\BonSortieRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Bon de sortie d'articles.
 *
 * Un bon est créé lors de l'enregistrement d'une sortie de stock. Il est
 * créé par défaut au statut EN_ATTENTE (non validé). La validation est
 * faite plus tard par le vigil à la guérite, en scannant le QR code avec
 * l'application mobile (endpoint api/bonSortie/valider/{token}).
 */
#[ORM\Entity(repositoryClass: BonSortieRepository::class)]
#[ORM\Table(name: 'bon_sortie')]
class BonSortie
{
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_VALIDE     = 'VALIDE';
    public const STATUT_ANNULE     = 'ANNULE';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    // Référence lisible (BS-AAAAMMJJ-XXXXXX), affichée sur le bon.
    #[ORM\Column(length: 50, unique: true)]
    private ?string $reference = null;

    // Jeton aléatoire encodé dans le QR code (sert à la validation mobile).
    #[ORM\Column(length: 64, unique: true)]
    private ?string $token = null;

    // Statut de validation : EN_ATTENTE par défaut.
    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\ManyToOne]
    private ?Services $code_service = null;

    // Libellé du service (figé au moment de la sortie).
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $service = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $demandeur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $etabli_par = null;

    // Instantané des lignes du bon (code, désignation, qté, stocks, mention).
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $lignes = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $created_by = null;

    // Infos de validation (renseignées par le vigil à la guérite).
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $valide_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $valide_par = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $poste_controle = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function isValide(): bool
    {
        return $this->statut === self::STATUT_VALIDE;
    }

    public function getCodeService(): ?Services
    {
        return $this->code_service;
    }

    public function setCodeService(?Services $code_service): static
    {
        $this->code_service = $code_service;

        return $this;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setService(?string $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getDemandeur(): ?string
    {
        return $this->demandeur;
    }

    public function setDemandeur(?string $demandeur): static
    {
        $this->demandeur = $demandeur;

        return $this;
    }

    public function getEtabliPar(): ?string
    {
        return $this->etabli_par;
    }

    public function setEtabliPar(?string $etabli_par): static
    {
        $this->etabli_par = $etabli_par;

        return $this;
    }

    public function getLignes(): ?array
    {
        return $this->lignes;
    }

    public function setLignes(?array $lignes): static
    {
        $this->lignes = $lignes;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->created_by;
    }

    public function setCreatedBy(?string $created_by): static
    {
        $this->created_by = $created_by;

        return $this;
    }

    public function getValideAt(): ?\DateTimeImmutable
    {
        return $this->valide_at;
    }

    public function setValideAt(?\DateTimeImmutable $valide_at): static
    {
        $this->valide_at = $valide_at;

        return $this;
    }

    public function getValidePar(): ?string
    {
        return $this->valide_par;
    }

    public function setValidePar(?string $valide_par): static
    {
        $this->valide_par = $valide_par;

        return $this;
    }

    public function getPosteControle(): ?string
    {
        return $this->poste_controle;
    }

    public function setPosteControle(?string $poste_controle): static
    {
        $this->poste_controle = $poste_controle;

        return $this;
    }
}
