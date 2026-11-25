<?php

namespace App\Entity\Production;

use App\Entity\Logistique\Dossier;
use App\Entity\Logistique\Magasin;
use App\Entity\Logistique\Proprietaire;
use App\Repository\Production\LotRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'production.lot')]
#[ORM\Entity(repositoryClass: LotRepository::class)]
class Lot
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 20)]
    private ?string $numero_lot = null;

    #[ORM\Column(nullable: true)]
    private ?float $pb_th = null;

    #[ORM\Column(nullable: true)]
    private ?float $poids_net_dispo = null;
    /**
     * @var Collection<Uuid, Dossier>
     */
    #[ORM\ManyToMany(targetEntity: Dossier::class, inversedBy: 'lots')]
    private Collection $code_dossier;

    /**
     * Lots de feves CITRAC dont ce lot est issu (produit fini : masse, beurre,
     * tourteau, poudre). Les feves portent elles-memes les camions de
     * dechargement : la chaine complete est donc fini -> feves -> camions.
     *
     * @var Collection<int, Lot>
     */
    #[ORM\ManyToMany(targetEntity: Lot::class, inversedBy: 'lotsFils')]
    #[ORM\JoinTable(name: 'production.lot_composition')]
    #[ORM\JoinColumn(name: 'lot_fini_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'lot_feve_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $lotsOrigine;

    /**
     * Lots de produits finis fabriques a partir de ce lot de feves.
     *
     * @var Collection<int, Lot>
     */
    #[ORM\ManyToMany(targetEntity: Lot::class, mappedBy: 'lotsOrigine')]
    private Collection $lotsFils;

    #[ORM\ManyToOne(inversedBy: 'lots')]
    private ?Magasin $code_magasin = null;

    #[ORM\Column(nullable: true)]
    private ?float $pn_th = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_prod = null;

    #[ORM\ManyToOne(inversedBy: 'lots')]
    private ?Proprietaire $code_proprio = null;

    #[ORM\ManyToOne(inversedBy: 'lots')]
    private ?Produit $code_produit = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(length: 255)]
    private ?string $created_by = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $updated_by = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $qualite = null;

    #[ORM\Column(nullable: true)]
    private ?bool $scission = null;

    #[ORM\Column(nullable: true)]
    private ?float $nb_sacs = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $origine = null;

    public function __construct()
    {
        $this->code_dossier = new ArrayCollection();
        $this->lotsOrigine = new ArrayCollection();
        $this->lotsFils = new ArrayCollection();
    }

    /** @return Collection<int, Lot> */
    public function getLotsOrigine(): Collection
    {
        return $this->lotsOrigine;
    }

    public function addLotOrigine(Lot $lot): static
    {
        if (!$this->lotsOrigine->contains($lot)) {
            $this->lotsOrigine->add($lot);
        }

        return $this;
    }

    public function removeLotOrigine(Lot $lot): static
    {
        $this->lotsOrigine->removeElement($lot);

        return $this;
    }

    /** @return Collection<int, Lot> */
    public function getLotsFils(): Collection
    {
        return $this->lotsFils;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNumeroLot(): ?string
    {
        return $this->numero_lot;
    }

    public function setNumeroLot(string $numero_lot): static
    {
        $this->numero_lot = $numero_lot;

        return $this;
    }

    public function getPbTht(): ?float
    {
        return $this->pb_th;
    }

    public function setPbTht(?float $pb_th): static
    {
        $this->pb_th = $pb_th;

        return $this;
    }

    /**
     * @return Collection<Uuid, Dossier>
     */
    public function getCodeDossier(): Collection
    {
        return $this->code_dossier;
    }

    public function addCodeDossier(Dossier $codeDossier): static
    {
        if (!$this->code_dossier->contains($codeDossier)) {
            $this->code_dossier->add($codeDossier);
        }

        return $this;
    }

    public function removeCodeDossier(Dossier $codeDossier): static
    {
        $this->code_dossier->removeElement($codeDossier);

        return $this;
    }

    public function getCodeMagasin(): ?Magasin
    {
        return $this->code_magasin;
    }

    public function setCodeMagasin(?Magasin $code_magasin): static
    {
        $this->code_magasin = $code_magasin;

        return $this;
    }

    public function getPnTh(): ?float
    {
        return $this->pn_th;
    }

    public function setPnTh(?float $pn_th): static
    {
        $this->pn_th = $pn_th;

        return $this;
    }

    public function getDateProd(): ?\DateTime
    {
        return $this->date_prod;
    }

    public function setDateProd(?\DateTime $date_prod): static
    {
        $this->date_prod = $date_prod;

        return $this;
    }

    public function getCodeProprio(): ?Proprietaire
    {
        return $this->code_proprio;
    }

    public function setCodeProprio(?Proprietaire $code_proprio): static
    {
        $this->code_proprio = $code_proprio;

        return $this;
    }

    public function getCodeProduit(): ?Produit
    {
        return $this->code_produit;
    }

    public function setCodeProduit(?Produit $code_produit): static
    {
        $this->code_produit = $code_produit;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

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

    public function setCreatedBy(string $created_by): static
    {
        $this->created_by = $created_by;

        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updated_by;
    }

    public function setUpdatedBy(?string $updated_by): static
    {
        $this->updated_by = $updated_by;

        return $this;
    }

    public function getQualite(): ?string
    {
        return $this->qualite;
    }

    public function setQualite(?string $qualite): static
    {
        $this->qualite = $qualite;

        return $this;
    }

    public function isScission(): ?bool
    {
        return $this->scission;
    }

    public function setScission(?bool $scission): static
    {
        $this->scission = $scission;

        return $this;
    }

    /**
     * @return float|null
     */
    public function getPoidsNetDispo(): ?float
    {
        return $this->poids_net_dispo;
    }

    /**
     * @param float|null $poids_net_dispo
     */
    public function setPoidsNetDispo(?float $poids_net_dispo): void
    {
        $this->poids_net_dispo = $poids_net_dispo;
    }

    public function getNbSacs(): ?float
    {
        return $this->nb_sacs;
    }

    public function setNbSacs(?float $nb_sacs): static
    {
        $this->nb_sacs = $nb_sacs;

        return $this;
    }

    public function getOrigine(): ?string
    {
        return $this->origine;
    }

    public function setOrigine(?string $origine): static
    {
        $this->origine = $origine;

        return $this;
    }
}
