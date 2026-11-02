<?php

namespace App\Entity;

use App\Repository\MouvementEquipementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MouvementEquipementRepository::class)]
#[ORM\HasLifecycleCallbacks]
class MouvementEquipement
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'mouvementEquipements')]
    private ?EquipementType $code_equipement_type = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $type_mvt = null;

    #[ORM\Column(nullable: true)]
    private ?float $value = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(nullable: true)]
    private ?float $qte = null;

    #[ORM\ManyToOne(inversedBy: 'mouvementEquipements')]
    private ?Services $code_service = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numero_bon = null;

    #[ORM\ManyToOne(inversedBy: 'mouvementEquipements')]
    private ?Emplacement $emplacement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $precision_emplacement = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bon_livraison = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_operation = null;

    #[ORM\ManyToOne(inversedBy: 'mouvementEquipements')]
    private ?Equipement $code_equipement = null;

    #[ORM\Column(nullable: true)]
    private ?float $stock_avant = null;

    #[ORM\Column(nullable: true)]
    private ?float $stock_apres = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $demandeur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $created_by = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCodeEquipementType(): ?EquipementType
    {
        return $this->code_equipement_type;
    }

    public function setCodeEquipementType(?EquipementType $code_equipement_type): static
    {
        $this->code_equipement_type = $code_equipement_type;

        return $this;
    }

    public function getTypeMvt(): ?string
    {
        return $this->type_mvt;
    }

    public function setTypeMvt(?string $type_mvt): static
    {
        $this->type_mvt = $type_mvt;

        return $this;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(?float $value): static
    {
        $this->value = $value;

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

    public function getQte(): ?float
    {
        return $this->qte;
    }

    public function setQte(?float $qte): static
    {
        $this->qte = $qte;

        return $this;
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

    public function getNumeroBon(): ?string
    {
        return $this->numero_bon;
    }

    public function setNumeroBon(?string $numero_bon): static
    {
        $this->numero_bon = $numero_bon;

        return $this;
    }

    public function getEmplacement(): ?Emplacement
    {
        return $this->emplacement;
    }

    public function setEmplacement(?Emplacement $emplacement): static
    {
        $this->emplacement = $emplacement;

        return $this;
    }

    public function getPrecisionEmplacement(): ?string
    {
        return $this->precision_emplacement;
    }

    public function setPrecisionEmplacement(?string $precision_emplacement): static
    {
        $this->precision_emplacement = $precision_emplacement;

        return $this;
    }

    public function getObservation(): ?string
    {
        return $this->observation;
    }

    public function setObservation(?string $observation): static
    {
        $this->observation = $observation;

        return $this;
    }

    public function getBonLivraison(): ?string
    {
        return $this->bon_livraison;
    }

    public function setBonLivraison(?string $bon_livraison): static
    {
        $this->bon_livraison = $bon_livraison;

        return $this;
    }

    public function getDateOperation(): ?\DateTime
    {
        return $this->date_operation;
    }

    public function setDateOperation(?\DateTime $date_operation): static
    {
        $this->date_operation = $date_operation;

        return $this;
    }

    public function getCodeEquipement(): ?Equipement
    {
        return $this->code_equipement;
    }

    public function setCodeEquipement(?Equipement $code_equipement): static
    {
        $this->code_equipement = $code_equipement;

        return $this;
    }

    public function getStockAvant(): ?float
    {
        return $this->stock_avant;
    }

    public function setStockAvant(?float $stock_avant): static
    {
        $this->stock_avant = $stock_avant;

        return $this;
    }

    public function getStockApres(): ?float
    {
        return $this->stock_apres;
    }

    public function setStockApres(?float $stock_apres): static
    {
        $this->stock_apres = $stock_apres;

        return $this;
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->created_at =
            new \DateTimeImmutable();

        $this->date_operation =
            new \DateTime();

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

    public function getCreatedBy(): ?string
    {
        return $this->created_by;
    }

    public function setCreatedBy(?string $created_by): static
    {
        $this->created_by = $created_by;

        return $this;
    }
}
