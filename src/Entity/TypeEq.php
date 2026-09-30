<?php

namespace App\Entity;

use App\Repository\TypeEqRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TypeEqRepository::class)]
class TypeEq
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $libelle = null;

    /**
     * @var Collection<Uuid, EquipementType>
     */
    #[ORM\OneToMany(targetEntity: EquipementType::class, mappedBy: 'type_eq')]
    private Collection $equipementTypes;

    public function __construct()
    {
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
            $equipementType->setTypeEq($this);
        }

        return $this;
    }

    public function removeEquipementType(EquipementType $equipementType): static
    {
        if ($this->equipementTypes->removeElement($equipementType)) {
            // set the owning side to null (unless already changed)
            if ($equipementType->getTypeEq() === $this) {
                $equipementType->setTypeEq(null);
            }
        }

        return $this;
    }
}
