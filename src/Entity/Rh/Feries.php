<?php

namespace App\Entity\Rh;

use App\Repository\Rh\FeriesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rh_feries')]
#[ORM\Entity(repositoryClass: FeriesRepository::class)]
class Feries
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_ferie = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $denomination = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateFerie(): ?\DateTime
    {
        return $this->date_ferie;
    }

    public function setDateFerie(?\DateTime $date_ferie): static
    {
        $this->date_ferie = $date_ferie;

        return $this;
    }

    public function getDenomination(): ?string
    {
        return $this->denomination;
    }

    public function setDenomination(?string $denomination): static
    {
        $this->denomination = $denomination;

        return $this;
    }
}
