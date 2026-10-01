<?php

namespace App\Entity;

use App\Repository\EquipementTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EquipementTypeRepository::class)]
class EquipementType
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom_equipement = null;

    #[ORM\ManyToOne(inversedBy: 'equipementTypes')]
    private ?NatureEquipement $nature_equipement = null;

    #[ORM\ManyToOne(inversedBy: 'equipementTypes')]
    private ?Criticite $criticite = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $code = null;

    #[ORM\ManyToOne(inversedBy: 'equipementTypes')]
    private ?TypeEquipement $categorie = null;

    #[ORM\Column(nullable: true)]
    private ?bool $sous_equipement = null;

    #[ORM\ManyToOne(inversedBy: 'equipementTypes')]
    private ?ZoneExploitation $zone = null;

    #[ORM\ManyToOne(inversedBy: 'equipementTypes')]
    private ?TypeEq $type_eq = null;

    /**
     * @var Collection<Uuid, MouvementEquipement>
     */
    #[ORM\OneToMany(targetEntity: MouvementEquipement::class, mappedBy: 'code_equipement_type')]
    private Collection $mouvementEquipements;

    #[ORM\Column(nullable: true)]
    private ?float $qte = null;

    #[ORM\Column(nullable: true)]
    private ?int $seuil = null;

    public function __construct()
    {
        $this->mouvementEquipements = new ArrayCollection();
    }


    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNomEquipement(): ?string
    {
        return $this->nom_equipement;
    }

    public function setNomEquipement(?string $nom_equipement): static
    {
        $this->nom_equipement = $nom_equipement;

        return $this;
    }

    public function getNatureEquipement(): ?NatureEquipement
    {
        return $this->nature_equipement;
    }

    public function setNatureEquipement(?NatureEquipement $nature_equipement): static
    {
        $this->nature_equipement = $nature_equipement;

        return $this;
    }

    public function getCriticite(): ?Criticite
    {
        return $this->criticite;
    }

    public function setCriticite(?Criticite $criticite): static
    {
        $this->criticite = $criticite;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getCategorie(): ?TypeEquipement
    {
        return $this->categorie;
    }

    public function setCategorie(?TypeEquipement $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function isSousEquipement(): ?bool
    {
        return $this->sous_equipement;
    }

    public function setSousEquipement(?bool $sous_equipement): static
    {
        $this->sous_equipement = $sous_equipement;

        return $this;
    }

    public function getZone(): ?ZoneExploitation
    {
        return $this->zone;
    }

    public function setZone(?ZoneExploitation $zone): static
    {
        $this->zone = $zone;

        return $this;
    }

    public function getTypeEq(): ?TypeEq
    {
        return $this->type_eq;
    }

    public function setTypeEq(?TypeEq $type_eq): static
    {
        $this->type_eq = $type_eq;

        return $this;
    }

    /**
     * @return Collection<Uuid, MouvementEquipement>
     */
    public function getMouvementEquipements(): Collection
    {
        return $this->mouvementEquipements;
    }

    public function addMouvementEquipement(MouvementEquipement $mouvementEquipement): static
    {
        if (!$this->mouvementEquipements->contains($mouvementEquipement)) {
            $this->mouvementEquipements->add($mouvementEquipement);
            $mouvementEquipement->setCodeEquipementType($this);
        }

        return $this;
    }

    public function removeMouvementEquipement(MouvementEquipement $mouvementEquipement): static
    {
        if ($this->mouvementEquipements->removeElement($mouvementEquipement)) {
            // set the owning side to null (unless already changed)
            if ($mouvementEquipement->getCodeEquipementType() === $this) {
                $mouvementEquipement->setCodeEquipementType(null);
            }
        }

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

    public function getSeuil(): ?int
    {
        return $this->seuil;
    }

    public function setSeuil(?int $seuil): static
    {
        $this->seuil = $seuil;

        return $this;
    }
}
