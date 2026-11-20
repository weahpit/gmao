<?php

namespace App\Entity\Rh;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Action de formation : theme, organisme, cout, dates et participants. */
#[ORM\Entity]
#[ORM\Table(name: 'rh_formation')]
class Formation
{
    public const PLANIFIEE = 'planifiee';
    public const EN_COURS = 'en_cours';
    public const TERMINEE = 'terminee';
    public const ANNULEE = 'annulee';

    public const STATUTS = [
        self::PLANIFIEE => 'Planifiee',
        self::EN_COURS => 'En cours',
        self::TERMINEE => 'Terminee',
        self::ANNULEE => 'Annulee',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180)]
    private ?string $intitule = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $organisme = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $cout = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column]
    private int $nombrePlaces = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::PLANIFIEE;

    /** Support de presentation (PowerPoint, PDF...) : chemin relatif a public/data/rh/. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $support = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $supportNom = null;

    /** Liste de presences signee (PDF, scan, tableur) : chemin relatif a public/data/rh/. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $listePresences = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $listePresencesNom = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, ParticipationFormation> */
    #[ORM\OneToMany(targetEntity: ParticipationFormation::class, mappedBy: 'formation', cascade: ['remove'])]
    private Collection $participations;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->participations = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getIntitule(): ?string
    {
        return $this->intitule;
    }

    public function setIntitule(?string $intitule): static
    {
        $this->intitule = $intitule === null ? null : mb_strtoupper(trim($intitule), 'UTF-8');

        return $this;
    }

    public function getOrganisme(): ?string
    {
        return $this->organisme;
    }

    public function setOrganisme(?string $organisme): static
    {
        $this->organisme = $organisme === null ? null : mb_strtoupper(trim($organisme), 'UTF-8');

        return $this;
    }

    public function getCout(): ?string
    {
        return $this->cout;
    }

    public function setCout(?string $cout): static
    {
        $this->cout = $cout;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $date): static
    {
        $this->dateDebut = $date === null ? null : \DateTime::createFromInterface($date);

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $date): static
    {
        $this->dateFin = $date === null ? null : \DateTime::createFromInterface($date);

        return $this;
    }

    public function getNombrePlaces(): int
    {
        return $this->nombrePlaces;
    }

    public function setNombrePlaces(int $nombrePlaces): static
    {
        $this->nombrePlaces = max(0, $nombrePlaces);

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

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = isset(self::STATUTS[$statut]) ? $statut : self::PLANIFIEE;

        return $this;
    }

    public function getStatutLibelle(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function getSupport(): ?string
    {
        return $this->support;
    }

    public function setSupport(?string $support): static
    {
        $this->support = $support;

        return $this;
    }

    public function getSupportNom(): ?string
    {
        return $this->supportNom;
    }

    public function setSupportNom(?string $nom): static
    {
        $this->supportNom = $nom;

        return $this;
    }

    public function getListePresences(): ?string
    {
        return $this->listePresences;
    }

    public function setListePresences(?string $chemin): static
    {
        $this->listePresences = $chemin;

        return $this;
    }

    public function getListePresencesNom(): ?string
    {
        return $this->listePresencesNom;
    }

    public function setListePresencesNom(?string $nom): static
    {
        $this->listePresencesNom = $nom;

        return $this;
    }

    /** Le support et la liste de presences sont-ils deja enregistres ? */
    public function aDesDocuments(): bool
    {
        return $this->support !== null || $this->listePresences !== null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, ParticipationFormation> */
    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(ParticipationFormation $participation): self
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setFormation($this);
        }

        return $this;
    }

    public function removeParticipation(ParticipationFormation $participation): self
    {
        if ($this->participations->removeElement($participation) && $participation->getFormation() === $this) {
            $participation->setFormation(null);
        }

        return $this;
    }

    public function getNombreParticipants(): int
    {
        return $this->participations->count();
    }

    public function getCoutTotal(): string
    {
        return number_format((float) ($this->cout ?? 0), 0, '', ' ');
    }

    public function getPeriodeLibelle(): string
    {
        if ($this->dateDebut === null) {
            return 'Dates a definir';
        }

        return $this->dateDebut->format('d/m/Y')
            . ($this->dateFin !== null ? ' au ' . $this->dateFin->format('d/m/Y') : '');
    }
}
