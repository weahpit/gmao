<?php

namespace App\Entity\Rh;

use App\Repository\Rh\TypeHoraireRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rh_type_horaire')]
#[ORM\Entity(repositoryClass: TypeHoraireRepository::class)]
class TypeHoraire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $libelle = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }
}
