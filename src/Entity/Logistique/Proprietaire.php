<?php

namespace App\Entity\Logistique;

use App\Entity\Production\Lot;
use App\Entity\Production\Produit;
use App\Repository\Logistique\ProprietaireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'logistique.proprietaire')]
#[ORM\Entity(repositoryClass: ProprietaireRepository::class)]
class Proprietaire
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 50)]
    private ?string $denomination = null;

    /**
     * @var Collection<Uuid, Dossier>
     */
    #[ORM\OneToMany(targetEntity: Dossier::class, mappedBy: 'proprietaire')]
    private Collection $dossiers;

    /**
     * @var Collection<Uuid, Produit>
     */
    #[ORM\OneToMany(targetEntity: Produit::class, mappedBy: 'code_proprio')]
    private Collection $produits;

    /**
     * @var Collection<Uuid, Lot>
     */
    #[ORM\OneToMany(targetEntity: Lot::class, mappedBy: 'code_proprio')]
    private Collection $lots;

    /**
     * @var Collection<Uuid, CvDeblocage>
     */
    #[ORM\OneToMany(targetEntity: CvDeblocage::class, mappedBy: 'code_proprietaire')]
    private Collection $cvDeblocages;

    public function __construct()
    {
        $this->dossiers = new ArrayCollection();
        $this->produits = new ArrayCollection();
        $this->lots = new ArrayCollection();
        $this->cvDeblocages = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getDenomination(): ?string
    {
        return $this->denomination;
    }

    public function setDenomination(string $denomination): static
    {
        $this->denomination = $denomination;

        return $this;
    }

    /**
     * @return Collection<Uuid, Dossier>
     */
    public function getDossiers(): Collection
    {
        return $this->dossiers;
    }

    public function addDossier(Dossier $dossier): static
    {
        if (!$this->dossiers->contains($dossier)) {
            $this->dossiers->add($dossier);
            $dossier->setProprietaire($this);
        }

        return $this;
    }

    public function removeDossier(Dossier $dossier): static
    {
        if ($this->dossiers->removeElement($dossier)) {
            // set the owning side to null (unless already changed)
            if ($dossier->getProprietaire() === $this) {
                $dossier->setProprietaire(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<Uuid, Produit>
     */
    public function getProduits(): Collection
    {
        return $this->produits;
    }

    public function addProduit(Produit $produit): static
    {
        if (!$this->produits->contains($produit)) {
            $this->produits->add($produit);
            $produit->setCodeProprio($this);
        }

        return $this;
    }

    public function removeProduit(Produit $produit): static
    {
        if ($this->produits->removeElement($produit)) {
            // set the owning side to null (unless already changed)
            if ($produit->getCodeProprio() === $this) {
                $produit->setCodeProprio(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<Uuid, Lot>
     */
    public function getLots(): Collection
    {
        return $this->lots;
    }

    public function addLot(Lot $lot): static
    {
        if (!$this->lots->contains($lot)) {
            $this->lots->add($lot);
            $lot->setCodeProprio($this);
        }

        return $this;
    }

    public function removeLot(Lot $lot): static
    {
        if ($this->lots->removeElement($lot)) {
            // set the owning side to null (unless already changed)
            if ($lot->getCodeProprio() === $this) {
                $lot->setCodeProprio(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<Uuid, CvDeblocage>
     */
    public function getCvDeblocages(): Collection
    {
        return $this->cvDeblocages;
    }

    public function addCvDeblocage(CvDeblocage $cvDeblocage): static
    {
        if (!$this->cvDeblocages->contains($cvDeblocage)) {
            $this->cvDeblocages->add($cvDeblocage);
            $cvDeblocage->setCodeProprietaire($this);
        }

        return $this;
    }

    public function removeCvDeblocage(CvDeblocage $cvDeblocage): static
    {
        if ($this->cvDeblocages->removeElement($cvDeblocage)) {
            // set the owning side to null (unless already changed)
            if ($cvDeblocage->getCodeProprietaire() === $this) {
                $cvDeblocage->setCodeProprietaire(null);
            }
        }

        return $this;
    }
}
