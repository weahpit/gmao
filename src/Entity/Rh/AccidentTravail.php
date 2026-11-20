<?php

namespace App\Entity\Rh;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Accident du travail ou incident lie a l'activite professionnelle. */
#[ORM\Entity]
#[ORM\Table(name: 'rh_accident_travail')]
#[ORM\Index(columns: ['date'], name: 'idx_accident_date')]
class AccidentTravail
{
    public const LEGER = 'leger';
    public const MOYEN = 'moyen';
    public const GRAVE = 'grave';

    public const GRAVITES = [
        self::LEGER => 'Leger',
        self::MOYEN => 'Moyen',
        self::GRAVE => 'Grave',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: DossierMedical::class, inversedBy: 'accidents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DossierMedical $dossier = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $lieu = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private string $gravite = self::LEGER;

    #[ORM\Column]
    private bool $arretTravail = false;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateDebutArret = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateFinArret = null;

    #[ORM\Column(nullable: true)]
    private ?int $joursArret = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $numeroDeclaration = null;

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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): static
    {
        $this->date = $date === null ? null : \DateTime::createFromInterface($date);

        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu === null ? null : mb_strtoupper(trim($lieu), 'UTF-8');

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description === null ? null : mb_strtoupper(trim($description), 'UTF-8');

        return $this;
    }

    public function getGravite(): string
    {
        return $this->gravite;
    }

    public function setGravite(string $gravite): static
    {
        $this->gravite = isset(self::GRAVITES[$gravite]) ? $gravite : self::LEGER;

        return $this;
    }

    public function getGraviteLibelle(): string
    {
        return self::GRAVITES[$this->gravite] ?? $this->gravite;
    }

    public function isArretTravail(): bool
    {
        return $this->arretTravail;
    }

    public function setArretTravail(bool $arretTravail): static
    {
        $this->arretTravail = $arretTravail;

        return $this;
    }

    public function getDateDebutArret(): ?\DateTimeInterface
    {
        return $this->dateDebutArret;
    }

    public function setDateDebutArret(?\DateTimeInterface $date): static
    {
        $this->dateDebutArret = $date === null ? null : \DateTime::createFromInterface($date);

        return $this;
    }

    public function getDateFinArret(): ?\DateTimeInterface
    {
        return $this->dateFinArret;
    }

    public function setDateFinArret(?\DateTimeInterface $date): static
    {
        $this->dateFinArret = $date === null ? null : \DateTime::createFromInterface($date);

        return $this;
    }

    public function getJoursArret(): ?int
    {
        return $this->joursArret;
    }

    public function setJoursArret(?int $jours): static
    {
        $this->joursArret = $jours;

        return $this;
    }

    /** Recalcule le nombre de jours d'arret a partir des deux dates. */
    public function calculerJoursArret(): static
    {
        if ($this->dateDebutArret !== null && $this->dateFinArret !== null) {
            $debut = \DateTimeImmutable::createFromInterface($this->dateDebutArret);
            $fin = \DateTimeImmutable::createFromInterface($this->dateFinArret);
            $jours = (int) $debut->diff($fin)->format('%r%a') + 1;
            $this->joursArret = max(0, $jours);
        }

        return $this;
    }

    public function getNumeroDeclaration(): ?string
    {
        return $this->numeroDeclaration;
    }

    public function setNumeroDeclaration(?string $numero): static
    {
        $this->numeroDeclaration = $numero;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
