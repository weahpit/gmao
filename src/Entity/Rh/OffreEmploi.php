<?php

namespace App\Entity\Rh;

use App\Entity\Directions;
use App\Entity\Services;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Offre de recrutement : de la demande du service jusqu'a la publication.
 *
 * Cycle : brouillon -> soumise (validation DRH) -> publiee -> cloturee
 *         (ou refusee si la DRH / la Direction generale n'approuve pas).
 */
#[ORM\Entity]
#[ORM\Table(name: 'rh_offre_emploi')]
#[ORM\Index(columns: ['statut'], name: 'idx_offre_statut')]
class OffreEmploi
{
    public const BROUILLON = 'brouillon';
    public const SOUMISE = 'soumise';
    public const VALIDEE_DRH = 'validee_drh';
    public const PUBLIEE = 'publiee';
    public const CLOTUREE = 'cloturee';
    public const REFUSEE = 'refusee';

    public const STATUTS = [
        self::BROUILLON => 'Brouillon',
        self::SOUMISE => 'Soumise a la DRH',
        self::VALIDEE_DRH => 'Validee DRH',
        self::PUBLIEE => 'Publiee',
        self::CLOTUREE => 'Cloturee',
        self::REFUSEE => 'Refusee',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 180)]
    private ?string $intitule = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $profil = null;

    #[ORM\Column]
    private int $nombrePostes = 1;

    #[ORM\ManyToOne(targetEntity: Directions::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Directions $direction = null;

    #[ORM\ManyToOne(targetEntity: Services::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Services $service = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $datePublication = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateCloture = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::BROUILLON;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Candidature> */
    #[ORM\OneToMany(targetEntity: Candidature::class, mappedBy: 'offre')]
    private Collection $candidatures;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->candidatures = new ArrayCollection();
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

    public function getIntitule(): ?string
    {
        return $this->intitule;
    }

    public function setIntitule(?string $intitule): static
    {
        $this->intitule = $intitule === null ? null : mb_strtoupper(trim($intitule), 'UTF-8');

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

    public function getProfil(): ?string
    {
        return $this->profil;
    }

    public function setProfil(?string $profil): static
    {
        $this->profil = $profil;

        return $this;
    }

    public function getNombrePostes(): int
    {
        return $this->nombrePostes;
    }

    public function setNombrePostes(int $nombrePostes): static
    {
        $this->nombrePostes = max(1, $nombrePostes);

        return $this;
    }

    public function getDirection(): ?Directions
    {
        return $this->direction;
    }

    public function setDirection(?Directions $direction): static
    {
        $this->direction = $direction;

        return $this;
    }

    public function getService(): ?Services
    {
        return $this->service;
    }

    public function setService(?Services $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getServiceLibelle(): string
    {
        return $this->service?->getLibelle() ?? 'Service non precise';
    }

    public function getDatePublication(): ?\DateTimeInterface
    {
        return $this->datePublication;
    }

    public function setDatePublication(?\DateTimeInterface $datePublication): static
    {
        $this->datePublication = $datePublication === null ? null : \DateTime::createFromInterface($datePublication);

        return $this;
    }

    public function getDateCloture(): ?\DateTimeInterface
    {
        return $this->dateCloture;
    }

    public function setDateCloture(?\DateTimeInterface $dateCloture): static
    {
        $this->dateCloture = $dateCloture === null ? null : \DateTime::createFromInterface($dateCloture);

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = isset(self::STATUTS[$statut]) ? $statut : self::BROUILLON;

        return $this;
    }

    public function getStatutLibelle(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function isOuverte(): bool
    {
        return in_array($this->statut, [self::VALIDEE_DRH, self::PUBLIEE], true);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Candidature> */
    public function getCandidatures(): Collection
    {
        return $this->candidatures;
    }

    public function getNombreCandidatures(): int
    {
        return $this->candidatures->count();
    }
}
