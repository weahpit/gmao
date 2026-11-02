<?php

namespace App\Entity\Maintenance;

use App\Entity\EquipementType;
use App\Entity\ZoneExploitation;
use App\Repository\Maintenance\PlanMaintenanceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Plan de maintenance preventive systematique (programmation annuelle).
 * Un plan decrit UNE intervention recurrente (ex: "checklist entretien long ligne 1")
 * a realiser selon une periodicite (hebdomadaire, mensuelle, ...).
 */
#[ORM\Table(name: 'plan_maintenance', schema: 'maintenance')]
#[ORM\Entity(repositoryClass: PlanMaintenanceRepository::class)]
class PlanMaintenance
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** Code du document / checklist (ex: AQ-SM-EN-004) */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $reference = null;

    /** Libelle de l'intervention recurrente */
    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[ORM\ManyToOne]
    private ?ZoneExploitation $zone = null;

    #[ORM\ManyToOne]
    private ?EquipementType $equipement_type = null;

    #[ORM\ManyToOne]
    private ?TypeIntervention $type_intervention = null;

    /** Periodicite : JOURNALIERE | HEBDOMADAIRE | MENSUELLE | TRIMESTRIELLE | SEMESTRIELLE | ANNUELLE */
    #[ORM\Column(length: 30)]
    private ?string $periodicite = 'HEBDOMADAIRE';

    /** Intervalle : "toutes les N periodes" (1 = chaque periode) */
    #[ORM\Column]
    private int $intervalle = 1;

    /** Jour de la semaine cible pour les periodicites hebdomadaires (1=lundi ... 7=dimanche) */
    #[ORM\Column(nullable: true)]
    private ?int $jour_semaine = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date_debut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_fin = null;

    #[ORM\Column]
    private bool $actif = true;

    /** Couleur d'affichage dans le planning annuel (ex: #C8953A) */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $couleur = '#C8953A';

    /** Derniere date pour laquelle les fiches ont ete generees automatiquement */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $derniere_generation = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $created_by = null;

    /** @var Collection<int, PlanMaintenanceOccurrence> */
    #[ORM\OneToMany(targetEntity: PlanMaintenanceOccurrence::class, mappedBy: 'plan', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $occurrences;

    public function __construct()
    {
        $this->occurrences = new ArrayCollection();
        $this->created_at = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $reference): static { $this->reference = $reference; return $this; }

    public function getLibelle(): ?string { return $this->libelle; }
    public function setLibelle(?string $libelle): static { $this->libelle = $libelle; return $this; }

    public function getZone(): ?ZoneExploitation { return $this->zone; }
    public function setZone(?ZoneExploitation $zone): static { $this->zone = $zone; return $this; }

    public function getEquipementType(): ?EquipementType { return $this->equipement_type; }
    public function setEquipementType(?EquipementType $equipement_type): static { $this->equipement_type = $equipement_type; return $this; }

    public function getTypeIntervention(): ?TypeIntervention { return $this->type_intervention; }
    public function setTypeIntervention(?TypeIntervention $type_intervention): static { $this->type_intervention = $type_intervention; return $this; }

    public function getPeriodicite(): ?string { return $this->periodicite; }
    public function setPeriodicite(?string $periodicite): static { $this->periodicite = $periodicite; return $this; }

    public function getIntervalle(): int { return $this->intervalle; }
    public function setIntervalle(int $intervalle): static { $this->intervalle = max(1, $intervalle); return $this; }

    public function getJourSemaine(): ?int { return $this->jour_semaine; }
    public function setJourSemaine(?int $jour_semaine): static { $this->jour_semaine = $jour_semaine; return $this; }

    public function getDateDebut(): ?\DateTime { return $this->date_debut; }
    public function setDateDebut(?\DateTime $date_debut): static { $this->date_debut = $date_debut; return $this; }

    public function getDateFin(): ?\DateTime { return $this->date_fin; }
    public function setDateFin(?\DateTime $date_fin): static { $this->date_fin = $date_fin; return $this; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): static { $this->actif = $actif; return $this; }

    public function getCouleur(): ?string { return $this->couleur; }
    public function setCouleur(?string $couleur): static { $this->couleur = $couleur; return $this; }

    public function getDerniereGeneration(): ?\DateTime { return $this->derniere_generation; }
    public function setDerniereGeneration(?\DateTime $derniere_generation): static { $this->derniere_generation = $derniere_generation; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->created_at; }
    public function setCreatedAt(\DateTimeImmutable $created_at): static { $this->created_at = $created_at; return $this; }

    public function getCreatedBy(): ?string { return $this->created_by; }
    public function setCreatedBy(?string $created_by): static { $this->created_by = $created_by; return $this; }

    /** @return Collection<int, PlanMaintenanceOccurrence> */
    public function getOccurrences(): Collection { return $this->occurrences; }

    public function addOccurrence(PlanMaintenanceOccurrence $occurrence): static
    {
        if (!$this->occurrences->contains($occurrence)) {
            $this->occurrences->add($occurrence);
            $occurrence->setPlan($this);
        }
        return $this;
    }

    public function removeOccurrence(PlanMaintenanceOccurrence $occurrence): static
    {
        if ($this->occurrences->removeElement($occurrence)) {
            if ($occurrence->getPlan() === $this) {
                $occurrence->setPlan(null);
            }
        }
        return $this;
    }
}
