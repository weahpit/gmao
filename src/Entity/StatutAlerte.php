<?php

namespace App\Entity;

use App\Repository\StatutAlerteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatutAlerteRepository::class)]
class StatutAlerte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $libelle = null;

    /**
     * @var Collection<int, AlerteAnomalie>
     */
    #[ORM\OneToMany(targetEntity: AlerteAnomalie::class, mappedBy: 'statut')]
    private Collection $alerteAnomalies;

    public function __construct()
    {
        $this->alerteAnomalies = new ArrayCollection();
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

    /**
     * @return Collection<int, AlerteAnomalie>
     */
    public function getAlerteAnomalies(): Collection
    {
        return $this->alerteAnomalies;
    }

    public function addAlerteAnomaly(AlerteAnomalie $alerteAnomaly): static
    {
        if (!$this->alerteAnomalies->contains($alerteAnomaly)) {
            $this->alerteAnomalies->add($alerteAnomaly);
            $alerteAnomaly->setStatut($this);
        }

        return $this;
    }

    public function removeAlerteAnomaly(AlerteAnomalie $alerteAnomaly): static
    {
        if ($this->alerteAnomalies->removeElement($alerteAnomaly)) {
            // set the owning side to null (unless already changed)
            if ($alerteAnomaly->getStatut() === $this) {
                $alerteAnomaly->setStatut(null);
            }
        }

        return $this;
    }
}
