<?php

namespace App\Entity\Rh;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Arret de travail : maladie, accident ou maternite.
 *
 * L'arret est rattache au dossier medical de l'employe. Il peut etre
 * simplement demande, puis valide, refuse ou termine par le service medical.
 */
#[ORM\Entity]
#[ORM\Table(name: 'rh_arret_travail')]
#[ORM\Index(columns: ['date_debut'], name: 'idx_arret_debut')]
#[ORM\Index(columns: ['statut'], name: 'idx_arret_statut')]
class ArretTravail
{
    public const MALADIE = 'maladie';
    public const ACCIDENT = 'accident';
    public const MATERNITE = 'maternite';

    public const TYPES = [
        self::MALADIE => 'Maladie',
        self::ACCIDENT => 'Accident de travail',
        self::MATERNITE => 'Maternite',
    ];

    public const DEMANDE = 'demande';
    public const VALIDE = 'valide';
    public const REFUSE = 'refuse';
    public const TERMINE = 'termine';

    public const STATUTS = [
        self::DEMANDE => 'Demande',
        self::VALIDE => 'Valide',
        self::REFUSE => 'Refuse',
        self::TERMINE => 'Termine',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: DossierMedical::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DossierMedical $dossier = null;

    #[ORM\Column(length: 20)]
    private string $type = self::MALADIE;

    #[ORM\Column(name: 'date_debut', type: 'date')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(name: 'date_fin', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $jours = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $medecin = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::DEMANDE;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $justificatif = null;

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

    public function getDossier(): ?DossierMedical
    {
        return $this->dossier;
    }

    public function setDossier(?DossierMedical $dossier): self
    {
        $this->dossier = $dossier;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTypeLibelle(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): self
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): self
    {
        $this->dateFin = $dateFin;
        $this->recalculerJours();

        return $this;
    }

    /** Nombre de jours calendaires couverts par l'arret (1 au minimum). */
    public function getJours(): ?int
    {
        return $this->jours;
    }

    public function setJours(?int $jours): self
    {
        $this->jours = $jours;

        return $this;
    }

    public function recalculerJours(): void
    {
        if ($this->dateDebut instanceof \DateTimeInterface && $this->dateFin instanceof \DateTimeInterface) {
            $debut = new \DateTimeImmutable($this->dateDebut->format('Y-m-d'));
            $fin = new \DateTimeImmutable($this->dateFin->format('Y-m-d'));
            $this->jours = max(1, (int) $debut->diff($fin)->days + 1);
        }
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): self
    {
        $this->motif = $motif;

        return $this;
    }

    public function getMedecin(): ?string
    {
        return $this->medecin;
    }

    public function setMedecin(?string $medecin): self
    {
        $this->medecin = $medecin;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getStatutLibelle(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function getJustificatif(): ?string
    {
        return $this->justificatif;
    }

    public function setJustificatif(?string $justificatif): self
    {
        $this->justificatif = $justificatif;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
