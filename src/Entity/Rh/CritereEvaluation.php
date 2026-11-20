<?php

namespace App\Entity\Rh;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Critere d'une campagne, avec son poids dans la note finale. */
#[ORM\Entity]
#[ORM\Table(name: 'rh_critere_evaluation')]
class CritereEvaluation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: CampagneEvaluation::class, inversedBy: 'criteres')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CampagneEvaluation $campagne = null;

    #[ORM\Column(length: 150)]
    private ?string $libelle = null;

    #[ORM\Column]
    private int $poids = 1;

    #[ORM\Column]
    private int $ordre = 1;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCampagne(): ?CampagneEvaluation
    {
        return $this->campagne;
    }

    public function setCampagne(?CampagneEvaluation $campagne): static
    {
        $this->campagne = $campagne;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle === null ? null : mb_strtoupper(trim($libelle), 'UTF-8');

        return $this;
    }

    public function getPoids(): int
    {
        return $this->poids;
    }

    public function setPoids(int $poids): static
    {
        $this->poids = max(1, $poids);

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = max(1, $ordre);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description === null ? null : mb_strtoupper(trim($description), 'UTF-8');

        return $this;
    }
}
