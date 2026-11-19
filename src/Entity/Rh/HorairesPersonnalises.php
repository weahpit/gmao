<?php

namespace App\Entity\Rh;

use App\Repository\Rh\HorairesPersonnalisesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rh_horaires_personnalises')]
#[ORM\Entity(repositoryClass: HorairesPersonnalisesRepository::class)]
class HorairesPersonnalises
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $libelle = null;
    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heure1 = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heure2 = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heure3 = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heure4 = null;

    /**
     * @var Collection<int, Employe>
     */
    #[ORM\OneToMany(targetEntity: Employe::class, mappedBy: 'horaire_p')]
    private Collection $employes;

    public function __construct()
    {
        $this->employes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    /**
     * @param string|null $libelle
     */
    public function setLibelle(?string $libelle): void
    {
        $this->libelle = $libelle;
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

    public function getHeure3(): ?\DateTime
    {
        return $this->heure3;
    }

    public function setHeure3(?\DateTime $heure3): static
    {
        $this->heure3 = $heure3;

        return $this;
    }

    public function getHeure4(): ?\DateTime
    {
        return $this->heure4;
    }

    public function setHeure4(?\DateTime $heure4): static
    {
        $this->heure4 = $heure4;

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
            $employe->setHoraireP($this);
        }

        return $this;
    }

    public function removeEmploye(Employe $employe): static
    {
        if ($this->employes->removeElement($employe)) {
            // set the owning side to null (unless already changed)
            if ($employe->getHoraireP() === $this) {
                $employe->setHoraireP(null);
            }
        }

        return $this;
    }
}
