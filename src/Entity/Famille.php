<?php

namespace App\Entity;

use App\Repository\FamilleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FamilleRepository::class)]
class Famille
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100)]
    private ?string $libelle = null;

    /**
     * @var Collection<int, Categorie>
     */
    #[ORM\OneToMany(targetEntity: Categorie::class, mappedBy: 'code_famille')]
    private Collection $categories;

    /**
     * @var Collection<int, TypeEquipement>
     */
    #[ORM\OneToMany(targetEntity: TypeEquipement::class, mappedBy: 'code_famille')]
    private Collection $typeEquipements;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->typeEquipements = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    /**
     * @return Collection<int, Categorie>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Categorie $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->setCodeFamille($this);
        }

        return $this;
    }

    public function removeCategory(Categorie $category): static
    {
        if ($this->categories->removeElement($category)) {
            // set the owning side to null (unless already changed)
            if ($category->getCodeFamille() === $this) {
                $category->setCodeFamille(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TypeEquipement>
     */
    public function getTypeEquipements(): Collection
    {
        return $this->typeEquipements;
    }

    public function addTypeEquipement(TypeEquipement $typeEquipement): static
    {
        if (!$this->typeEquipements->contains($typeEquipement)) {
            $this->typeEquipements->add($typeEquipement);
            $typeEquipement->setCodeFamille($this);
        }

        return $this;
    }

    public function removeTypeEquipement(TypeEquipement $typeEquipement): static
    {
        if ($this->typeEquipements->removeElement($typeEquipement)) {
            // set the owning side to null (unless already changed)
            if ($typeEquipement->getCodeFamille() === $this) {
                $typeEquipement->setCodeFamille(null);
            }
        }

        return $this;
    }
}
