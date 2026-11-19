<?php

namespace App\Entity\Rh;

use App\Repository\Rh\AnneeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rh_annee')]
#[ORM\Entity(repositoryClass: AnneeRepository::class)]
class Annee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $valeur = null;

    /**
     * @var Collection<int, Mois>
     */
    #[ORM\OneToMany(targetEntity: Mois::class, mappedBy: 'code_annee')]
    private Collection $mois;

    public function __construct()
    {
        $this->mois = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValeur(): ?int
    {
        return $this->valeur;
    }

    public function setValeur(int $valeur): static
    {
        $this->valeur = $valeur;

        return $this;
    }

    /**
     * @return Collection<int, Mois>
     */
    public function getMois(): Collection
    {
        return $this->mois;
    }

    public function addMoi(Mois $moi): static
    {
        if (!$this->mois->contains($moi)) {
            $this->mois->add($moi);
            $moi->setCodeAnnee($this);
        }

        return $this;
    }

    public function removeMoi(Mois $moi): static
    {
        if ($this->mois->removeElement($moi)) {
            // set the owning side to null (unless already changed)
            if ($moi->getCodeAnnee() === $this) {
                $moi->setCodeAnnee(null);
            }
        }

        return $this;
    }
}
