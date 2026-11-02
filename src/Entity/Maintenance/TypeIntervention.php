<?php

namespace App\Entity\Maintenance;

use App\Repository\Maintenance\TypeInterventionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'type_intervention', schema: 'maintenance')]
#[ORM\Entity(repositoryClass: TypeInterventionRepository::class)]
class TypeIntervention
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $libelle = null;

    /**
     * @var Collection<int, FicheIntervention>
     */
    #[ORM\OneToMany(targetEntity: FicheIntervention::class, mappedBy: 'type_intervention')]
    private Collection $ficheInterventions;

    public function __construct()
    {
        $this->ficheInterventions = new ArrayCollection();
    }

    public function getId(): ?Uuid
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
     * @return Collection<int, FicheIntervention>
     */
    public function getFicheInterventions(): Collection
    {
        return $this->ficheInterventions;
    }

    public function addFicheIntervention(FicheIntervention $ficheIntervention): static
    {
        if (!$this->ficheInterventions->contains($ficheIntervention)) {
            $this->ficheInterventions->add($ficheIntervention);
            $ficheIntervention->setTypeIntervention($this);
        }

        return $this;
    }

    public function removeFicheIntervention(FicheIntervention $ficheIntervention): static
    {
        if ($this->ficheInterventions->removeElement($ficheIntervention)) {
            // set the owning side to null (unless already changed)
            if ($ficheIntervention->getTypeIntervention() === $this) {
                $ficheIntervention->setTypeIntervention(null);
            }
        }

        return $this;
    }
}
