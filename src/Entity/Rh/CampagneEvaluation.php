<?php

namespace App\Entity\Rh;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Campagne annuelle d'evaluation : objectifs, auto-evaluation,
 * evaluation du superieur puis validation RH.
 */
#[ORM\Entity]
#[ORM\Table(name: 'rh_campagne_evaluation')]
class CampagneEvaluation
{
    public const OUVERTE = 'ouverte';
    public const CLOTUREE = 'cloturee';

    public const STATUTS = [
        self::OUVERTE => 'Ouverte',
        self::CLOTUREE => 'Cloturee',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 120)]
    private ?string $libelle = null;

    #[ORM\Column]
    private int $annee = 2026;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::OUVERTE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $objectifs = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, CritereEvaluation> */
    #[ORM\OneToMany(targetEntity: CritereEvaluation::class, mappedBy: 'campagne', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $criteres;

    /** @var Collection<int, Evaluation> */
    #[ORM\OneToMany(targetEntity: Evaluation::class, mappedBy: 'campagne')]
    private Collection $evaluations;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->annee = (int) date('Y');
        $this->criteres = new ArrayCollection();
        $this->evaluations = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle === null ? null : mb_strtoupper(trim($libelle), 'UTF-8');

        return $this;
    }

    public function getAnnee(): int
    {
        return $this->annee;
    }

    public function setAnnee(int $annee): static
    {
        $this->annee = $annee;

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

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = isset(self::STATUTS[$statut]) ? $statut : self::OUVERTE;

        return $this;
    }

    public function getStatutLibelle(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function isOuverte(): bool
    {
        return $this->statut === self::OUVERTE;
    }

    public function getObjectifs(): ?string
    {
        return $this->objectifs;
    }

    public function setObjectifs(?string $objectifs): static
    {
        $this->objectifs = $objectifs === null ? null : mb_strtoupper(trim($objectifs), 'UTF-8');

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, CritereEvaluation> */
    public function getCriteres(): Collection
    {
        return $this->criteres;
    }

    public function addCritere(CritereEvaluation $critere): static
    {
        if (!$this->criteres->contains($critere)) {
            $this->criteres->add($critere);
            $critere->setCampagne($this);
        }

        return $this;
    }

    public function removeCritere(CritereEvaluation $critere): static
    {
        if ($this->criteres->removeElement($critere) && $critere->getCampagne() === $this) {
            $critere->setCampagne(null);
        }

        return $this;
    }

    public function getPoidsTotal(): int
    {
        $total = 0;
        foreach ($this->criteres as $critere) {
            $total += $critere->getPoids();
        }

        return $total;
    }

    /** @return Collection<int, Evaluation> */
    public function getEvaluations(): Collection
    {
        return $this->evaluations;
    }
}
