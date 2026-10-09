<?php

namespace App\Entity;

use App\Repository\AlerteAnomalieRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AlerteAnomalieRepository::class)]
class AlerteAnomalie
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $sujet = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\ManyToOne(inversedBy: 'alerteAnomalies')]
    private ?EquipementType $code_equipement = null;

    #[ORM\ManyToOne(inversedBy: 'alerteAnomalies')]
    private ?ZoneExploitation $zone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $preciser_emplacement = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getSujet(): ?string
    {
        return $this->sujet;
    }

    public function setSujet(string $sujet): static
    {
        $this->sujet = $sujet;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getCodeEquipement(): ?EquipementType
    {
        return $this->code_equipement;
    }

    public function setCodeEquipement(?EquipementType $code_equipement): static
    {
        $this->code_equipement = $code_equipement;

        return $this;
    }

    public function getZone(): ?ZoneExploitation
    {
        return $this->zone;
    }

    public function setZone(?ZoneExploitation $zone): static
    {
        $this->zone = $zone;

        return $this;
    }

    public function getPreciserEmplacement(): ?string
    {
        return $this->preciser_emplacement;
    }

    public function setPreciserEmplacement(?string $preciser_emplacement): static
    {
        $this->preciser_emplacement = $preciser_emplacement;

        return $this;
    }
}
