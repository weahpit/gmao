<?php

namespace App\Entity\Rh;

use App\Repository\Rh\OptionsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rh_options')]
#[ORM\Entity(repositoryClass: OptionsRepository::class)]
class Options
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $tolerance = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTolerance(): ?int
    {
        return $this->tolerance;
    }

    public function setTolerance(?int $tolerance): static
    {
        $this->tolerance = $tolerance;

        return $this;
    }
}
