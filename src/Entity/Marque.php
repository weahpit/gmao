<?php

namespace App\Entity;

use App\Repository\MarqueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MarqueRepository::class)]
class Marque
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nom = null;

    /**
     * @var Collection<int, Fournisseur>
     */
    #[ORM\ManyToMany(targetEntity: Fournisseur::class, inversedBy: 'marques')]
    private Collection $code_fournisseur;

    /**
     * @var Collection<int, Equipement>
     */
    #[ORM\OneToMany(targetEntity: Equipement::class, mappedBy: 'marque')]
    private Collection $equipements;

    public function __construct()
    {
        $this->code_fournisseur = new ArrayCollection();
        $this->equipements = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * @return Collection<int, Fournisseur>
     */
    public function getCodeFournisseur(): Collection
    {
        return $this->code_fournisseur;
    }

    public function addCodeFournisseur(Fournisseur $codeFournisseur): static
    {
        if (!$this->code_fournisseur->contains($codeFournisseur)) {
            $this->code_fournisseur->add($codeFournisseur);
        }

        return $this;
    }

    public function removeCodeFournisseur(Fournisseur $codeFournisseur): static
    {
        $this->code_fournisseur->removeElement($codeFournisseur);

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
            $equipement->setMarque($this);
        }

        return $this;
    }

    public function removeEquipement(Equipement $equipement): static
    {
        if ($this->equipements->removeElement($equipement)) {
            // set the owning side to null (unless already changed)
            if ($equipement->getMarque() === $this) {
                $equipement->setMarque(null);
            }
        }

        return $this;
    }
}
