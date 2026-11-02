<?php

namespace App\Entity;

use App\Repository\TypeEmplacementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TypeEmplacementRepository::class)]
class TypeEmplacement
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $libelle = null;

    /**
     * @var Collection<Uuid, Emplacement>
     */
    #[ORM\OneToMany(targetEntity: Emplacement::class, mappedBy: 'code_type_emplacement')]
    private Collection $emplacemenbts;

    public function __construct()
    {
        $this->emplacemenbts = new ArrayCollection();
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
     * @return Collection<Uuid, Emplacement>
     */
    public function getEmplacemenbts(): Collection
    {
        return $this->emplacemenbts;
    }

    public function addEmplacemenbt(Emplacement $emplacemenbt): static
    {
        if (!$this->emplacemenbts->contains($emplacemenbt)) {
            $this->emplacemenbts->add($emplacemenbt);
            $emplacemenbt->setCodeTypeEmplacement($this);
        }

        return $this;
    }

    public function removeEmplacemenbt(Emplacement $emplacemenbt): static
    {
        if ($this->emplacemenbts->removeElement($emplacemenbt)) {
            // set the owning side to null (unless already changed)
            if ($emplacemenbt->getCodeTypeEmplacement() === $this) {
                $emplacemenbt->setCodeTypeEmplacement(null);
            }
        }

        return $this;
    }
}
