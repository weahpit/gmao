<?php

namespace App\Entity;

use App\Repository\EquipementTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EquipementTypeRepository::class)]
class EquipementType
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom_equipement = null;

    #[ORM\ManyToOne(inversedBy: 'equipementTypes')]
    private ?NatureEquipement $nature_equipement = null;

    #[ORM\ManyToOne(inversedBy: 'equipementTypes')]
    private ?Criticite $criticite = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $code = null;

    #[ORM\ManyToOne(inversedBy: 'equipementTypes')]
    private ?TypeEquipement $categorie = null;


    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNomEquipement(): ?string
    {
        return $this->nom_equipement;
    }

    public function setNomEquipement(?string $nom_equipement): static
    {
        $this->nom_equipement = $nom_equipement;

        return $this;
    }

    public function getNatureEquipement(): ?NatureEquipement
    {
        return $this->nature_equipement;
    }

    public function setNatureEquipement(?NatureEquipement $nature_equipement): static
    {
        $this->nature_equipement = $nature_equipement;

        return $this;
    }

    public function getCriticite(): ?Criticite
    {
        return $this->criticite;
    }

    public function setCriticite(?Criticite $criticite): static
    {
        $this->criticite = $criticite;

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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getCategorie(): ?TypeEquipement
    {
        return $this->categorie;
    }

    public function setCategorie(?TypeEquipement $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }
}
