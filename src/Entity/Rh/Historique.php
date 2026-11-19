<?php

namespace App\Entity\Rh;

use App\Repository\Rh\HistoriqueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rh_historique')]
#[ORM\Entity(repositoryClass: HistoriqueRepository::class)]
class Historique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date_operation = null;

    #[ORM\ManyToOne(inversedBy: 'historiques')]
    private ?Employe $code_employe = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type_operation = null;

    #[ORM\Column(length: 255)]
    private ?string $valeur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateOperation(): ?\DateTime
    {
        return $this->date_operation;
    }

    public function setDateOperation(\DateTime $date_operation): static
    {
        $this->date_operation = $date_operation;

        return $this;
    }

    public function getCodeEmploye(): ?Employe
    {
        return $this->code_employe;
    }

    public function setCodeEmploye(?Employe $code_employe): static
    {
        $this->code_employe = $code_employe;

        return $this;
    }

    public function getTypeOperation(): ?string
    {
        return $this->type_operation;
    }

    public function setTypeOperation(string $type_operation): static
    {
        $this->type_operation = $type_operation;

        return $this;
    }

    public function getValeur(): ?string
    {
        return $this->valeur;
    }

    public function setValeur(string $valeur): static
    {
        $this->valeur = $valeur;

        return $this;
    }
}
