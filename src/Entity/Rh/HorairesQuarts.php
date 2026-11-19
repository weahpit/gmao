<?php

namespace App\Entity\Rh;

use App\Repository\Rh\HorairesQuartsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rh_horaires_quarts')]
#[ORM\Entity(repositoryClass: HorairesQuartsRepository::class)]
class HorairesQuarts
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heure1 = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heure2 = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $libelle = null;

    /**
     * @var Collection<int, Programmation>
     */
    #[ORM\OneToMany(targetEntity: Programmation::class, mappedBy: 'code_quart')]
    private Collection $programmations;

    public function __construct()
    {
        $this->programmations = new ArrayCollection();
    }



    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHeure1(): ?\DateTime
    {
        return $this->heure1;
    }

    public function setHeure1(?\DateTime $heure1): static
    {
        $this->heure1 = $heure1;

        return $this;
    }

    public function getHeure2(): ?\DateTime
    {
        return $this->heure2;
    }

    public function setHeure2(?\DateTime $heure2): static
    {
        $this->heure2 = $heure2;

        return $this;
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
            $programmation->setCodeQuart($this);
        }

        return $this;
    }

    public function removeProgrammation(Programmation $programmation): static
    {
        if ($this->programmations->removeElement($programmation)) {
            // set the owning side to null (unless already changed)
            if ($programmation->getCodeQuart() === $this) {
                $programmation->setCodeQuart(null);
            }
        }

        return $this;
    }

}
