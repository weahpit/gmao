<?php

namespace App\Entity\Stock;

use App\Repository\Stock\DechargementCacaoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Dechargement de cacao : un bon de dechargement (un camion) tel qu'il figure
 * dans le fichier Excel/CSV d'exploitation.
 *
 * Les lignes importees proviennent du modele officiel :
 *   Code | Date | Site | Produit | Cooperative | Immat | Transporteur |
 *   Nbre Sacs | PoidsNet | Statut | Date de Dechargement | Obersavation |
 *   PNP | Traitant | Categorie
 *
 * Le champ `code` (numero de bon, ex. SC101389-2025-2-15) est la cle
 * metier : il est UNIQUE, ce qui garantit qu'un meme bon ne peut pas etre
 * enregistre deux fois, meme si deux utilisateurs importent le meme fichier.
 */
#[ORM\Entity(repositoryClass: DechargementCacaoRepository::class)]
#[ORM\Table(name: 'stock_dechargement_cacao')]
#[ORM\UniqueConstraint(name: 'uniq_dechargement_code', columns: ['code'])]
#[ORM\Index(name: 'idx_dechargement_date', columns: ['date_bon'])]
#[ORM\Index(name: 'idx_dechargement_coop', columns: ['cooperative'])]
class DechargementCacao
{
    public const SOURCE_IMPORT = 'IMPORT_XLS';
    public const SOURCE_SAISIE = 'SAISIE';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** Numero de bon de chargement / dechargement (cle metier). */
    #[ORM\Column(length: 60, unique: true)]
    private ?string $code = null;

    /** Date du bon (date de chargement / depart). */
    #[ORM\Column(name: 'date_bon', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateBon = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $site = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $produit = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $cooperative = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $immatriculation = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $transporteur = null;

    #[ORM\Column(name: 'nbre_sacs', nullable: true)]
    private ?int $nbreSacs = null;

    #[ORM\Column(name: 'poids_net', type: Types::FLOAT, nullable: true)]
    private ?float $poidsNet = null;

    /** Statut brut du fichier (ex. "Accepte(05/12/2025)"). */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(name: 'date_dechargement', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateDechargement = null;

    /** Observation (colonne "Obersavation" du fichier). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observation = null;

    /** Poids net pondere / PNP. */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $pnp = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $traitant = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $source = null;

    /** Empreinte de l'import ayant cree la ligne (tracabilite). */
    #[ORM\Column(name: 'import_uuid', length: 40, nullable: true)]
    private ?string $importUuid = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'created_by', length: 180, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column(name: 'updated_by', length: 180, nullable: true)]
    private ?string $updatedBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $v): static { $this->code = $v; return $this; }

    public function getDateBon(): ?\DateTimeInterface { return $this->dateBon; }
    public function setDateBon(?\DateTimeInterface $v): static { $this->dateBon = $v; return $this; }

    public function getSite(): ?string { return $this->site; }
    public function setSite(?string $v): static { $this->site = $v; return $this; }

    public function getProduit(): ?string { return $this->produit; }
    public function setProduit(?string $v): static { $this->produit = $v; return $this; }

    public function getCooperative(): ?string { return $this->cooperative; }
    public function setCooperative(?string $v): static { $this->cooperative = $v; return $this; }

    public function getImmatriculation(): ?string { return $this->immatriculation; }
    public function setImmatriculation(?string $v): static { $this->immatriculation = $v; return $this; }

    public function getTransporteur(): ?string { return $this->transporteur; }
    public function setTransporteur(?string $v): static { $this->transporteur = $v; return $this; }

    public function getNbreSacs(): ?int { return $this->nbreSacs; }
    public function setNbreSacs(?int $v): static { $this->nbreSacs = $v; return $this; }

    public function getPoidsNet(): ?float { return $this->poidsNet; }
    public function setPoidsNet(?float $v): static { $this->poidsNet = $v; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $v): static { $this->statut = $v; return $this; }

    public function getDateDechargement(): ?\DateTimeInterface { return $this->dateDechargement; }
    public function setDateDechargement(?\DateTimeInterface $v): static { $this->dateDechargement = $v; return $this; }

    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $v): static { $this->observation = $v; return $this; }

    public function getPnp(): ?float { return $this->pnp; }
    public function setPnp(?float $v): static { $this->pnp = $v; return $this; }

    public function getTraitant(): ?string { return $this->traitant; }
    public function setTraitant(?string $v): static { $this->traitant = $v; return $this; }

    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(?string $v): static { $this->categorie = $v; return $this; }

    public function getSource(): ?string { return $this->source; }
    public function setSource(?string $v): static { $this->source = $v; return $this; }

    public function getImportUuid(): ?string { return $this->importUuid; }
    public function setImportUuid(?string $v): static { $this->importUuid = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeImmutable $v): static { $this->createdAt = $v; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $v): static { $this->updatedAt = $v; return $this; }

    public function touch(): static { $this->updatedAt = new \DateTimeImmutable(); return $this; }

    public function getCreatedBy(): ?string { return $this->createdBy; }
    public function setCreatedBy(?string $v): static { $this->createdBy = $v; return $this; }

    public function getUpdatedBy(): ?string { return $this->updatedBy; }
    public function setUpdatedBy(?string $v): static { $this->updatedBy = $v; return $this; }
}
