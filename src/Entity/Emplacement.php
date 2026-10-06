<?php

namespace App\Entity;

use App\Repository\EmplacemenbtRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EmplacemenbtRepository::class)]
class Emplacement
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $libelle = null;

    #[ORM\ManyToOne(inversedBy: 'emplacemenbts')]
    private ?TypeEmplacement $code_type_emplacement = null;

    /**
     * @var Collection<Uuid, EquipementType>
     */
    #[ORM\ManyToMany(targetEntity: EquipementType::class, mappedBy: 'emplacement')]
    private Collection $equipementTypes;

    #[ORM\ManyToOne(inversedBy: 'emplacements')]
    private ?ZoneExploitation $code_zone = null;

    /**
     * @var Collection<int, MouvementEquipement>
     */
    #[ORM\OneToMany(targetEntity: MouvementEquipement::class, mappedBy: 'emplacement')]
    private Collection $mouvementEquipements;

    public function __construct()
    {
        $this->equipementTypes = new ArrayCollection();
        $this->mouvementEquipements = new ArrayCollection();
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

    public function getCodeTypeEmplacement(): ?TypeEmplacement
    {
        return $this->code_type_emplacement;
    }

    public function setCodeTypeEmplacement(?TypeEmplacement $code_type_emplacement): static
    {
        $this->code_type_emplacement = $code_type_emplacement;

        return $this;
    }

    /**
     * @return Collection<Uuid, EquipementType>
     */
    public function getEquipementTypes(): Collection
    {
        return $this->equipementTypes;
    }

    public function addEquipementType(EquipementType $equipementType): static
    {
        if (!$this->equipementTypes->contains($equipementType)) {
            $this->equipementTypes->add($equipementType);
            $equipementType->addEmplacement($this);
        }

        return $this;
    }

    public function removeEquipementType(EquipementType $equipementType): static
    {
        if ($this->equipementTypes->removeElement($equipementType)) {
            $equipementType->removeEmplacement($this);
        }

        return $this;
    }

    public function getCodeZone(): ?ZoneExploitation
    {
        return $this->code_zone;
    }

    public function setCodeZone(?ZoneExploitation $code_zone): static
    {
        $this->code_zone = $code_zone;

        return $this;
    }

    /**
     * @return Collection<int, MouvementEquipement>
     */
    public function getMouvementEquipements(): Collection
    {
        return $this->mouvementEquipements;
    }

    public function addMouvementEquipement(MouvementEquipement $mouvementEquipement): static
    {
        if (!$this->mouvementEquipements->contains($mouvementEquipement)) {
            $this->mouvementEquipements->add($mouvementEquipement);
            $mouvementEquipement->setEmplacement($this);
        }

        return $this;
    }

    public function removeMouvementEquipement(MouvementEquipement $mouvementEquipement): static
    {
        if ($this->mouvementEquipements->removeElement($mouvementEquipement)) {
            // set the owning side to null (unless already changed)
            if ($mouvementEquipement->getEmplacement() === $this) {
                $mouvementEquipement->setEmplacement(null);
            }
        }

        return $this;
    }
}
