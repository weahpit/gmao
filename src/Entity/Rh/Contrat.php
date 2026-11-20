<?php

namespace App\Entity\Rh;

use App\Entity\Poste;
use App\Repository\Rh\ContratRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Contrat de travail d'un employe : CDI, CDD, stage ou interim.
 * Un employe peut avoir plusieurs contrats successifs ; un seul est "en cours".
 */
#[ORM\Entity(repositoryClass: ContratRepository::class)]
#[ORM\Table(name: 'rh_contrat')]
#[ORM\Index(columns: ['type'], name: 'idx_contrat_type')]
#[ORM\Index(columns: ['date_fin'], name: 'idx_contrat_fin')]
class Contrat
{
    public const CDI = 'cdi';
    public const CDD = 'cdd';
    public const STAGE = 'stage';
    public const INTERIM = 'interim';

    public const TYPES = [
        self::CDI => 'CDI',
        self::CDD => 'CDD',
        self::STAGE => 'Stage',
        self::INTERIM => 'Interim',
    ];

    public const EN_COURS = 'en_cours';
    public const TERMINE = 'termine';
    public const ROMPU = 'rompu';

    public const STATUTS = [
        self::EN_COURS => 'En cours',
        self::TERMINE => 'Termine',
        self::ROMPU => 'Rompu',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $reference = null;

    #[ORM\ManyToOne(targetEntity: Employe::class, inversedBy: 'contrats')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    #[ORM\Column(length: 20)]
    private string $type = self::CDI;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $fonction = null;

    #[ORM\ManyToOne(targetEntity: Poste::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Poste $poste = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $salaire = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::EN_COURS;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motifFin = null;

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
        $this->type = isset(self::TYPES[$type]) ? $type : self::CDI;

        return $this;
    }

    public function getTypeLibelle(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getFonction(): ?string
    {
        return $this->fonction;
    }

    public function setFonction(?string $fonction): static
    {
        $this->fonction = $fonction === null ? null : mb_strtoupper(trim($fonction), 'UTF-8');

        return $this;
    }

    public function getPoste(): ?Poste
    {
        return $this->poste;
    }

    public function setPoste(?Poste $poste): static
    {
        $this->poste = $poste;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut === null ? null : \DateTime::createFromInterface($dateDebut);

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin === null ? null : \DateTime::createFromInterface($dateFin);

        return $this;
    }

    public function getSalaire(): ?string
    {
        return $this->salaire;
    }

    public function setSalaire(?string $salaire): static
    {
        $this->salaire = $salaire;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = isset(self::STATUTS[$statut]) ? $statut : self::EN_COURS;

        return $this;
    }

    public function getStatutLibelle(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function isEnCours(): bool
    {
        return $this->statut === self::EN_COURS;
    }

    /** Nombre de jours avant l'echeance (negatif si depassee), null si CDI sans terme. */
    public function getJoursRestants(): ?int
    {
        if ($this->dateFin === null) {
            return null;
        }

        $aujourdhui = new \DateTimeImmutable('today');

        return (int) $aujourdhui->diff(\DateTimeImmutable::createFromInterface($this->dateFin))->format('%r%a');
    }

    public function getMotifFin(): ?string
    {
        return $this->motifFin;
    }

    public function setMotifFin(?string $motifFin): static
    {
        $this->motifFin = $motifFin === null ? null : mb_strtoupper(trim($motifFin), 'UTF-8');

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDureeMois(): ?int
    {
        if ($this->dateDebut === null) {
            return null;
        }
        $fin = $this->dateFin !== null ? \DateTimeImmutable::createFromInterface($this->dateFin) : new \DateTimeImmutable();

        return max(0, (int) $this->dateDebut->diff($fin)->format('%m') + 12 * (int) $this->dateDebut->diff($fin)->format('%y'));
    }
}
