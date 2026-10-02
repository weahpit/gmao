<?php

namespace App\Entity;

use App\Repository\ServicesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ServicesRepository::class)]
class Services
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $libelle = null;

    #[ORM\ManyToOne(inversedBy: 'service')]
    private ?Directions $code_direction = null;

    /**
     * @var Collection<int, MouvementEquipement>
     */
    #[ORM\OneToMany(targetEntity: MouvementEquipement::class, mappedBy: 'code_service')]
    private Collection $mouvementEquipements;

    public function __construct()
    {
        $this->mouvementEquipements = new ArrayCollection();
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

    public function getCodeDirection(): ?Directions
    {
        return $this->code_direction;
    }

    public function setCodeDirection(?Directions $code_direction): static
    {
        $this->code_direction = $code_direction;

        return $this;
    }

    /**
     * @return Collection<int, MouvementEquipement>
     */
    public function getMouvementEquipements(): Collection
    {
        return $this->mouvementEquipements;
    }

    public function addMouvementEquipement(MouvementEquipement $mouvementEquipement): static
    {
        if (!$this->mouvementEquipements->contains($mouvementEquipement)) {
            $this->mouvementEquipements->add($mouvementEquipement);
            $mouvementEquipement->setCodeService($this);
        }

        return $this;
    }

    public function removeMouvementEquipement(MouvementEquipement $mouvementEquipement): static
    {
        if ($this->mouvementEquipements->removeElement($mouvementEquipement)) {
            // set the owning side to null (unless already changed)
            if ($mouvementEquipement->getCodeService() === $this) {
                $mouvementEquipement->setCodeService(null);
            }
        }

        return $this;
    }
}
