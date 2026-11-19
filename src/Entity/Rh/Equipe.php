<?php

namespace App\Entity\Rh;

use App\Repository\Rh\EquipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rh_equipe')]
#[ORM\Entity(repositoryClass: EquipeRepository::class)]
class Equipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom_equipe = null;

    /**
     * @var Collection<int, Employe>
     */
    #[ORM\OneToMany(targetEntity: Employe::class, mappedBy: 'code_equipe')]
    private Collection $employes;

    /**
     * @var Collection<int, Programmation>
     */
    #[ORM\OneToMany(targetEntity: Programmation::class, mappedBy: 'code_equipe')]
    private Collection $programmations;

    public function __construct()
    {
        $this->employes = new ArrayCollection();
        $this->programmations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomEquipe(): ?string
    {
        return $this->nom_equipe;
    }

    public function setNomEquipe(string $nom_equipe): static
    {
        $this->nom_equipe = $nom_equipe;

        return $this;
    }

    /**
     * @return Collection<int, Employe>
     */
    public function getEmployes(): Collection
    {
        return $this->employes;
    }

    public function addEmploye(Employe $employe): static
    {
        if (!$this->employes->contains($employe)) {
            $this->employes->add($employe);
            $employe->setCodeEquipe($this);
        }

        return $this;
    }

    public function removeEmploye(Employe $employe): static
    {
        if ($this->employes->removeElement($employe)) {
            // set the owning side to null (unless already changed)
            if ($employe->getCodeEquipe() === $this) {
                $employe->setCodeEquipe(null);
            }
        }

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
            $programmation->setCodeEquipe($this);
        }

        return $this;
    }

    public function removeProgrammation(Programmation $programmation): static
    {
        if ($this->programmations->removeElement($programmation)) {
            // set the owning side to null (unless already changed)
            if ($programmation->getCodeEquipe() === $this) {
                $programmation->setCodeEquipe(null);
            }
        }

        return $this;
    }
}
