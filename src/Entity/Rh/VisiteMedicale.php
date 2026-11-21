<?php

namespace App\Entity\Rh;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Visite medicale du travail : embauche, periodique, reprise ou speciale. */
#[ORM\Entity]
#[ORM\Table(name: 'rh_visite_medicale')]
#[ORM\Index(columns: ['date'], name: 'idx_visite_date')]
class VisiteMedicale
{
    public const EMBAUCHE = 'embauche';
    public const PERIODIQUE = 'periodique';
    public const REPRISE = 'reprise';
    public const SPECIALE = 'speciale';
    public const MALADIE = 'maladie';

    public const TYPES = [
        self::EMBAUCHE => 'Visite d\'embauche',
        self::PERIODIQUE => 'Visite periodique',
        self::REPRISE => 'Visite de reprise',
        self::SPECIALE => 'Visite speciale',
        self::MALADIE => 'Visite maladie',
    ];

    public const APTE = 'apte';
    public const APTE_RESTRICTION = 'apte_restriction';
    public const INAPTE_TEMPORAIRE = 'inapte_temporaire';
    public const INAPTE_DEFINITIF = 'inapte_definitif';

    public const APTITUDES = [
        self::APTE => 'Apte',
        self::APTE_RESTRICTION => 'Apte avec restriction',
        self::INAPTE_TEMPORAIRE => 'Inapte temporaire',
        self::INAPTE_DEFINITIF => 'Inapte definitif',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: DossierMedical::class, inversedBy: 'visites')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DossierMedical $dossier = null;

    #[ORM\Column(length: 20)]
    private string $type = self::PERIODIQUE;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $medecin = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $centreMedical = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $conclusion = null;

    #[ORM\Column(length: 30)]
    private string $aptitude = self::APTE;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateProchaine = null;

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

    public function getDossier(): ?DossierMedical
    {
        return $this->dossier;
    }

    public function setDossier(?DossierMedical $dossier): static
    {
        $this->dossier = $dossier;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = isset(self::TYPES[$type]) ? $type : self::PERIODIQUE;

        return $this;
    }

    public function getTypeLibelle(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): static
    {
        $this->date = $date === null ? null : \DateTime::createFromInterface($date);

        return $this;
    }

    public function getMedecin(): ?string
    {
        return $this->medecin;
    }

    public function setMedecin(?string $medecin): static
    {
        $this->medecin = $medecin === null ? null : mb_strtoupper(trim($medecin), 'UTF-8');

        return $this;
    }

    public function getCentreMedical(): ?string
    {
        return $this->centreMedical;
    }

    public function setCentreMedical(?string $centre): static
    {
        $this->centreMedical = $centre === null ? null : mb_strtoupper(trim($centre), 'UTF-8');

        return $this;
    }

    public function getConclusion(): ?string
    {
        return $this->conclusion;
    }

    public function setConclusion(?string $conclusion): static
    {
        $this->conclusion = $conclusion === null ? null : mb_strtoupper(trim($conclusion), 'UTF-8');

        return $this;
    }

    public function getAptitude(): string
    {
        return $this->aptitude;
    }

    public function setAptitude(string $aptitude): static
    {
        $this->aptitude = isset(self::APTITUDES[$aptitude]) ? $aptitude : self::APTE;

        return $this;
    }

    public function getAptitudeLibelle(): string
    {
        return self::APTITUDES[$this->aptitude] ?? $this->aptitude;
    }

    public function isApte(): bool
    {
        return in_array($this->aptitude, [self::APTE, self::APTE_RESTRICTION], true);
    }

    public function getDateProchaine(): ?\DateTimeInterface
    {
        return $this->dateProchaine;
    }

    public function setDateProchaine(?\DateTimeInterface $date): static
    {
        $this->dateProchaine = $date === null ? null : \DateTime::createFromInterface($date);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
