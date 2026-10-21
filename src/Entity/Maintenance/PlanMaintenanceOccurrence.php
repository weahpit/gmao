<?php

namespace App\Entity\Maintenance;

use App\Repository\Maintenance\PlanMaintenanceOccurrenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Occurrence planifiee d'un plan de maintenance (une echeance = une date prevue).
 * Permet de suivre l'etat (a faire / fait / ignore) et de relier a la fiche generee.
 */
#[ORM\Table(name: 'plan_maintenance_occurrence', schema: 'maintenance')]
#[ORM\Entity(repositoryClass: PlanMaintenanceOccurrenceRepository::class)]
class PlanMaintenanceOccurrence
{
    public const A_FAIRE = 'A_FAIRE';
    public const FAIT    = 'FAIT';
    public const IGNORE  = 'IGNORE';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'occurrences')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlanMaintenance $plan = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date_prevue = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::A_FAIRE;

    /** Fiche d'intervention generee pour cette occurrence (si generation automatique) */
    #[ORM\ManyToOne(targetEntity: FicheIntervention::class)]
    private ?FicheIntervention $fiche = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_realisation = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getPlan(): ?PlanMaintenance { return $this->plan; }
    public function setPlan(?PlanMaintenance $plan): static { $this->plan = $plan; return $this; }

    public function getDatePrevue(): ?\DateTime { return $this->date_prevue; }
    public function setDatePrevue(?\DateTime $date_prevue): static { $this->date_prevue = $date_prevue; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getFiche(): ?FicheIntervention { return $this->fiche; }
    public function setFiche(?FicheIntervention $fiche): static { $this->fiche = $fiche; return $this; }

    public function getDateRealisation(): ?\DateTime { return $this->date_realisation; }
    public function setDateRealisation(?\DateTime $date_realisation): static { $this->date_realisation = $date_realisation; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->created_at; }
    public function setCreatedAt(\DateTimeImmutable $created_at): static { $this->created_at = $created_at; return $this; }
}
