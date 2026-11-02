<?php

namespace App\Entity\Maintenance;

use App\Entity\Emplacement;
use App\Entity\Equipement;
use App\Entity\EquipementType;
use App\Entity\Services;
use App\Entity\User;
use App\Entity\ZoneExploitation;
use App\Entity\Maintenance\OrigineIntervention;
use App\Entity\Maintenance\StatutIntervention;
use App\Repository\Maintenance\FicheInterventionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'fiche_intervention', schema: 'maintenance')]
#[ORM\Entity(repositoryClass: FicheInterventionRepository::class)]
class FicheIntervention
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'ficheInterventions')]
    private ?TypeIntervention $type_intervention = null;

    #[ORM\ManyToOne(inversedBy: 'ficheInterventions')]
    private ?Priorite $priorite = null;

    #[ORM\ManyToOne(inversedBy: 'ficheInterventions')]
    private ?StatutIntervention $statut = null;

    #[ORM\ManyToOne(inversedBy: 'ficheInterventions')]
    private ?OrigineIntervention $origine = null;

    #[ORM\ManyToOne(inversedBy: 'ficheInterventions')]
    private ?EquipementType $equipement_type = null;

    #[ORM\ManyToOne(inversedBy: 'ficheInterventions')]
    private ?Equipement $equipement = null;

    #[ORM\ManyToOne(inversedBy: 'ficheInterventions')]
    private ?ZoneExploitation $zone = null;

    #[ORM\ManyToOne(inversedBy: 'ficheInterventions')]
    private ?Emplacement $emplacement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $preciser_emplacement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $demandeur = null;

    #[ORM\ManyToOne(inversedBy: 'ficheInterventions')]
    private ?Services $service_demandeur = null;

    #[ORM\Column(length: 255)]
    private ?string $sujet = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cause = null;

    #[ORM\ManyToOne(inversedBy: 'ficheInterventions')]
    private ?NatureIntervention $nature = null;

    #[ORM\Column(nullable: true)]
    private ?bool $Arret_machine = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'ficheInterventions')]
    private Collection $code_technicien;

    #[ORM\ManyToOne(inversedBy: 'ficheInterventions')]
    private ?SousTraitant $sous_traitant = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_debut_planifie = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_fin_planifie = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_debut_reel = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_fin_reel = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(length: 255)]
    private ?string $created_by = null;

    #[ORM\Column(length: 50)]
    private ?string $reference = null;

    public function __construct()
    {
        $this->code_technicien = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTypeIntervention(): ?TypeIntervention
    {
        return $this->type_intervention;
    }

    public function setTypeIntervention(?TypeIntervention $type_intervention): static
    {
        $this->type_intervention = $type_intervention;

        return $this;
    }

    public function getPriorite(): ?Priorite
    {
        return $this->priorite;
    }

    public function setPriorite(?Priorite $priorite): static
    {
        $this->priorite = $priorite;

        return $this;
    }

    public function getStatut(): ?StatutIntervention
    {
        return $this->statut;
    }

    public function setStatut(?StatutIntervention $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getOrigine(): ?OrigineIntervention
    {
        return $this->origine;
    }

    public function setOrigine(?OrigineIntervention $origine): static
    {
        $this->origine = $origine;

        return $this;
    }

    public function getEquipementType(): ?EquipementType
    {
        return $this->equipement_type;
    }

    public function setEquipementType(?EquipementType $equipement_type): static
    {
        $this->equipement_type = $equipement_type;

        return $this;
    }

    public function getEquipement(): ?Equipement
    {
        return $this->equipement;
    }

    public function setEquipement(?Equipement $equipement): static
    {
        $this->equipement = $equipement;

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

    public function getEmplacement(): ?Emplacement
    {
        return $this->emplacement;
    }

    public function setEmplacement(?Emplacement $emplacement): static
    {
        $this->emplacement = $emplacement;

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

    public function getDemandeur(): ?string
    {
        return $this->demandeur;
    }

    public function setDemandeur(?string $demandeur): static
    {
        $this->demandeur = $demandeur;

        return $this;
    }

    public function getServiceDemandeur(): ?Services
    {
        return $this->service_demandeur;
    }

    public function setServiceDemandeur(?Services $service_demandeur): static
    {
        $this->service_demandeur = $service_demandeur;

        return $this;
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

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCause(): ?string
    {
        return $this->cause;
    }

    public function setCause(?string $cause): static
    {
        $this->cause = $cause;

        return $this;
    }

    public function getNature(): ?NatureIntervention
    {
        return $this->nature;
    }

    public function setNature(?NatureIntervention $nature): static
    {
        $this->nature = $nature;

        return $this;
    }

    public function isArretMachine(): ?bool
    {
        return $this->Arret_machine;
    }

    public function setArretMachine(?bool $Arret_machine): static
    {
        $this->Arret_machine = $Arret_machine;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getCodeTechnicien(): Collection
    {
        return $this->code_technicien;
    }

    public function addCodeTechnicien(User $codeTechnicien): static
    {
        if (!$this->code_technicien->contains($codeTechnicien)) {
            $this->code_technicien->add($codeTechnicien);
        }

        return $this;
    }

    public function removeCodeTechnicien(User $codeTechnicien): static
    {
        $this->code_technicien->removeElement($codeTechnicien);

        return $this;
    }

    public function getSousTraitant(): ?SousTraitant
    {
        return $this->sous_traitant;
    }

    public function setSousTraitant(?SousTraitant $sous_traitant): static
    {
        $this->sous_traitant = $sous_traitant;

        return $this;
    }

    public function getDateDebutPlanifie(): ?\DateTime
    {
        return $this->date_debut_planifie;
    }

    public function setDateDebutPlanifie(?\DateTime $date_debut_planifie): static
    {
        $this->date_debut_planifie = $date_debut_planifie;

        return $this;
    }

    public function getDateFinPlanifie(): ?\DateTime
    {
        return $this->date_fin_planifie;
    }

    public function setDateFinPlanifie(?\DateTime $date_fin_planifie): static
    {
        $this->date_fin_planifie = $date_fin_planifie;

        return $this;
    }

    public function getDateDebutReel(): ?\DateTime
    {
        return $this->date_debut_reel;
    }

    public function setDateDebutReel(?\DateTime $date_debut_reel): static
    {
        $this->date_debut_reel = $date_debut_reel;

        return $this;
    }

    public function getDateFinReel(): ?\DateTime
    {
        return $this->date_fin_reel;
    }

    public function setDateFinReel(?\DateTime $date_fin_reel): static
    {
        $this->date_fin_reel = $date_fin_reel;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->created_by;
    }

    public function setCreatedBy(string $created_by): static
    {
        $this->created_by = $created_by;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }
}
