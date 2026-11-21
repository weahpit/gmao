<?php

namespace App\Entity\Rh;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Plan vaccinal de l'entreprise : un vaccin et sa periodicite de rappel.
 *
 * Applique aux employes actifs, il genere les vaccinations dues avec une
 * date de prochain rappel calculee a partir de la periodicite.
 */
#[ORM\Entity]
#[ORM\Table(name: 'rh_plan_vaccinal')]
class PlanVaccinal
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 150)]
    private string $vaccin = '';

    #[ORM\Column(name: 'periodicite_mois', type: 'integer', nullable: true)]
    private ?int $periodiciteMois = null;

    #[ORM\Column(type: 'boolean')]
    private bool $obligatoire = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getVaccin(): string
    {
        return $this->vaccin;
    }

    public function setVaccin(string $vaccin): self
    {
        $this->vaccin = trim($vaccin);

        return $this;
    }

    public function getPeriodiciteMois(): ?int
    {
        return $this->periodiciteMois;
    }

    public function setPeriodiciteMois(?int $periodiciteMois): self
    {
        $this->periodiciteMois = $periodiciteMois;

        return $this;
    }

    public function isObligatoire(): bool
    {
        return $this->obligatoire;
    }

    public function setObligatoire(bool $obligatoire): self
    {
        $this->obligatoire = $obligatoire;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Libelle lisible de la periodicite, par exemple « tous les 12 mois ». */
    public function getPeriodiciteLibelle(): string
    {
        if ($this->periodiciteMois === null || $this->periodiciteMois <= 0) {
            return 'Rappel non planifie';
        }

        if ($this->periodiciteMois === 1) {
            return 'Tous les mois';
        }

        if ($this->periodiciteMois === 12) {
            return 'Tous les 12 mois (annuel)';
        }

        return 'Tous les ' . $this->periodiciteMois . ' mois';
    }
}
