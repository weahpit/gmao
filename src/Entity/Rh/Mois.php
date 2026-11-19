<?php

namespace App\Entity\Rh;

use App\Repository\Rh\MoisRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rh_mois')]
#[ORM\Entity(repositoryClass: MoisRepository::class)]
class Mois
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $index = null;

    #[ORM\Column(length: 25)]
    private ?string $libelle = null;

    /**
     * @var Collection<int, Programmation>
     */
    #[ORM\OneToMany(targetEntity: Programmation::class, mappedBy: 'code_mois')]
    private Collection $programmations;

    #[ORM\ManyToOne(inversedBy: 'mois')]
    private ?Annee $code_annee = null;

    public function __construct()
    {
        $this->programmations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIndex(): ?int
    {
        return $this->index;
    }

    public function setIndex(int $index): static
    {
        $this->index = $index;

        return $this;
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
     * @return Collection<int, Programmation>
     */
    public function getProgrammations(): Collection
    {
        return $this->programmations;
    }

    public function addProgrammation(Programmation $programmation): static
    {
        if (!$this->programmations->contains($programmation)) {
            $this->programmations->add($programmation);
            $programmation->setCodeMois($this);
        }

        return $this;
    }

    public function removeProgrammation(Programmation $programmation): static
    {
        if ($this->programmations->removeElement($programmation)) {
            // set the owning side to null (unless already changed)
            if ($programmation->getCodeMois() === $this) {
                $programmation->setCodeMois(null);
            }
        }

        return $this;
    }

    public function getCodeAnnee(): ?Annee
    {
        return $this->code_annee;
    }

    public function setCodeAnnee(?Annee $code_annee): static
    {
        $this->code_annee = $code_annee;

        return $this;
    }
}
