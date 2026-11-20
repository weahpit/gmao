<?php

namespace App\Entity\Rh;

use App\Entity\User;
use App\Repository\Rh\AbsenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Absence ou conge, avec circuit de validation :
 * soumis -> valide_chef -> valide_rh (ou refuse a n'importe quelle etape).
 */
#[ORM\Entity(repositoryClass: AbsenceRepository::class)]
#[ORM\Table(name: 'rh_absence')]
#[ORM\Index(columns: ['date_debut', 'date_fin'], name: 'idx_absence_periode')]
#[ORM\Index(columns: ['statut'], name: 'idx_absence_statut')]
class Absence
{
    public const CONGE_ANNUEL = 'conge_annuel';
    public const MALADIE = 'maladie';
    public const MATERNITE = 'maternite';
    public const EXCEPTIONNEL = 'exceptionnel';
    public const JUSTIFIEE = 'absence_justifiee';
    public const NON_JUSTIFIEE = 'absence_non_justifiee';

    public const TYPES = [
        self::CONGE_ANNUEL => 'Conge annuel',
        self::MALADIE => 'Conge maladie',
        self::MATERNITE => 'Conge maternite',
        self::EXCEPTIONNEL => 'Conge exceptionnel',
        self::JUSTIFIEE => 'Absence justifiee',
        self::NON_JUSTIFIEE => 'Absence non justifiee',
    ];

    /** Types qui ne necessitent pas de justificatif medical. */
    public const TYPES_SANS_JUSTIFICATIF = [self::CONGE_ANNUEL, self::EXCEPTIONNEL, self::NON_JUSTIFIEE];

    public const SOUMIS = 'soumis';
    public const VALIDE_CHEF = 'valide_chef';
    public const VALIDE_RH = 'valide_rh';
    public const REFUSE = 'refuse';

    public const STATUTS = [
        self::SOUMIS => 'En attente du chef de service',
        self::VALIDE_CHEF => 'Valide par le chef, en attente RH',
        self::VALIDE_RH => 'Valide',
        self::REFUSE => 'Refuse',
    ];

    public const STATUT_COURT = [
        self::SOUMIS => 'Soumis',
        self::VALIDE_CHEF => 'Valide chef',
        self::VALIDE_RH => 'Valide',
        self::REFUSE => 'Refuse',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $reference = null;

    #[ORM\ManyToOne(targetEntity: Employe::class, inversedBy: 'absences')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    #[ORM\Column(length: 30)]
    private string $type = self::CONGE_ANNUEL;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column]
    private int $nombreJours = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motif = null;

    /** Justificatif (certificat medical, convocation, etc.). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $justificatif = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::SOUMIS;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $valideChefPar = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $valideRhPar = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateValidationChef = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateValidationRh = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaireValidation = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getEmploye(): ?Employe
    {
        return $this->employe;
    }

    public function setEmploye(?Employe $employe): static
    {
        $this->employe = $employe;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = isset(self::TYPES[$type]) ? $type : self::CONGE_ANNUEL;

        return $this;
    }

    public function getTypeLibelle(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isMedical(): bool
    {
        return in_array($this->type, [self::MALADIE, self::MATERNITE], true);
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut === null ? null : \DateTime::createFromInterface($dateDebut);
        $this->recalculerJours();

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin === null ? null : \DateTime::createFromInterface($dateFin);
        $this->recalculerJours();

        return $this;
    }

    public function getNombreJours(): int
    {
        return $this->nombreJours;
    }

    public function setNombreJours(int $nombreJours): static
    {
        $this->nombreJours = max(0, $nombreJours);

        return $this;
    }

    /** Nombre de jours calendaires couverts (week-ends inclus, choix simple et lisible). */
    public function recalculerJours(): void
    {
        if ($this->dateDebut === null || $this->dateFin === null) {
            $this->nombreJours = 0;

            return;
        }

        $debut = \DateTimeImmutable::createFromInterface($this->dateDebut);
        $fin = \DateTimeImmutable::createFromInterface($this->dateFin);
        $this->nombreJours = $fin < $debut ? 0 : ((int) $debut->diff($fin)->format('%a') + 1);
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getJustificatif(): ?string
    {
        return $this->justificatif;
    }

    public function setJustificatif(?string $justificatif): static
    {
        $this->justificatif = $justificatif;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = isset(self::STATUTS[$statut]) ? $statut : self::SOUMIS;

        return $this;
    }

    public function getStatutLibelle(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function getStatutCourt(): string
    {
        return self::STATUT_COURT[$this->statut] ?? $this->statut;
    }

    public function isValide(): bool
    {
        return $this->statut === self::VALIDE_RH;
    }

    public function isEnAttente(): bool
    {
        return in_array($this->statut, [self::SOUMIS, self::VALIDE_CHEF], true);
    }

    public function getValideChefPar(): ?User
    {
        return $this->valideChefPar;
    }

    public function setValideChefPar(?User $user): static
    {
        $this->valideChefPar = $user;

        return $this;
    }

    public function getValideRhPar(): ?User
    {
        return $this->valideRhPar;
    }

    public function setValideRhPar(?User $user): static
    {
        $this->valideRhPar = $user;

        return $this;
    }

    public function getDateValidationChef(): ?\DateTimeInterface
    {
        return $this->dateValidationChef;
    }

    public function setDateValidationChef(?\DateTimeInterface $date): static
    {
        $this->dateValidationChef = $date;

        return $this;
    }

    public function getDateValidationRh(): ?\DateTimeInterface
    {
        return $this->dateValidationRh;
    }

    public function setDateValidationRh(?\DateTimeInterface $date): static
    {
        $this->dateValidationRh = $date;

        return $this;
    }

    public function getCommentaireValidation(): ?string
    {
        return $this->commentaireValidation;
    }

    public function setCommentaireValidation(?string $commentaire): static
    {
        $this->commentaireValidation = $commentaire === null ? null : mb_strtoupper(trim($commentaire), 'UTF-8');

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Deux periodes se chevauchent-elles ? */
    public function chevauche(\DateTimeInterface $debut, \DateTimeInterface $fin): bool
    {
        if ($this->dateDebut === null || $this->dateFin === null) {
            return false;
        }

        return $this->dateDebut <= $fin && $debut <= $this->dateFin;
    }

    public function getPeriodeLibelle(): string
    {
        if ($this->dateDebut === null || $this->dateFin === null) {
            return 'Periode incomplete';
        }

        return $this->dateDebut->format('d/m/Y') . ' au ' . $this->dateFin->format('d/m/Y');
    }
}
