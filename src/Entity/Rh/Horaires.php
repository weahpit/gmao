<?php

namespace App\Entity\Rh;

use App\Repository\Rh\HorairesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rh_horaires')]
#[ORM\Entity(repositoryClass: HorairesRepository::class)]
class Horaires
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heure1 = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heure2 = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heure3 = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heure4 = null;

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
}
