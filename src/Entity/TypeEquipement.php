<?php

namespace App\Entity;

use App\Repository\TypeEquipementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TypeEquipementRepository::class)]
class TypeEquipement
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $libelle = null;

    /**
     * @var Collection<int, Equipement>
     */
    #[ORM\OneToMany(targetEntity: Equipement::class, mappedBy: 'type_unicite')]
    private Collection $equipements;

    #[ORM\ManyToOne(inversedBy: 'typeEquipements')]
    private ?Famille $code_famille = null;

    /**
     * @var Collection<int, EquipementType>
     */
    #[ORM\OneToMany(targetEntity: EquipementType::class, mappedBy: 'categorie')]
    private Collection $equipementTypes;

    public function __construct()
    {
        $this->equipements = new ArrayCollection();
        $this->equipementTypes = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    /**
     * @return Collection<int, Equipement>
     */
    public function getEquipements(): Collection
    {
        return $this->equipements;
    }

    public function addEquipement(Equipement $equipement): static
    {
        if (!$this->equipements->contains($equipement)) {
            $this->equipements->add($equipement);
            $equipement->setTypeUnicite($this);
        }

        return $this;
    }

    public function removeEquipement(Equipement $equipement): static
    {
        if ($this->equipements->removeElement($equipement)) {
            // set the owning side to null (unless already changed)
            if ($equipement->getTypeUnicite() === $this) {
                $equipement->setTypeUnicite(null);
            }
        }

        return $this;
    }

    public function getCodeFamille(): ?Famille
    {
        return $this->code_famille;
    }

    public function setCodeFamille(?Famille $code_famille): static
    {
        $this->code_famille = $code_famille;

        return $this;
    }

    /**
     * @return Collection<int, EquipementType>
     */
    public function getEquipementTypes(): Collection
    {
        return $this->equipementTypes;
    }

    public function addEquipementType(EquipementType $equipementType): static
    {
        if (!$this->equipementTypes->contains($equipementType)) {
            $this->equipementTypes->add($equipementType);
            $equipementType->setCategorie($this);
        }

        return $this;
    }

    public function removeEquipementType(EquipementType $equipementType): static
    {
        if ($this->equipementTypes->removeElement($equipementType)) {
            // set the owning side to null (unless already changed)
            if ($equipementType->getCategorie() === $this) {
                $equipementType->setCategorie(null);
            }
        }

        return $this;
    }
}
