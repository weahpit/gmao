<?php

namespace App\Entity\Rh;

use App\Repository\Rh\PosteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rh_poste')]
#[ORM\Entity(repositoryClass: PosteRepository::class)]
class Poste
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $libelle = null;

    #[ORM\ManyToOne(inversedBy: 'postes')]
    private ?Departement $code_departement = null;

    /**
     * @var Collection<int, Employe>
     */
    #[ORM\OneToMany(targetEntity: Employe::class, mappedBy: 'poste')]
    private Collection $employes;

    public function __construct()
    {
        $this->employes = new ArrayCollection();
    }

    public function getId(): ?int
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

    public function getCodeDepartement(): ?Departement
    {
        return $this->code_departement;
    }

    public function setCodeDepartement(?Departement $code_departement): static
    {
        $this->code_departement = $code_departement;

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
            $employe->setPoste($this);
        }

        return $this;
    }

    public function removeEmploye(Employe $employe): static
    {
        if ($this->employes->removeElement($employe)) {
            // set the owning side to null (unless already changed)
            if ($employe->getPoste() === $this) {
                $employe->setPoste(null);
            }
        }

        return $this;
    }
}
