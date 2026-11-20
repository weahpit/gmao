<?php

namespace App\Entity\Rh;

use App\Repository\Rh\PointageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Pointage journalier : arrivee, depart, retard et heures supplementaires.
 * Un seul pointage par employe et par jour (contrainte d'unicite).
 */
#[ORM\Entity(repositoryClass: PointageRepository::class)]
#[ORM\Table(name: 'rh_pointage')]
#[ORM\UniqueConstraint(name: 'uniq_pointage_jour', columns: ['employe_id', 'jour'])]
#[ORM\Index(columns: ['jour'], name: 'idx_pointage_jour')]
class Pointage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $jour = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $heureArrivee = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $heureDepart = null;

    #[ORM\Column]
    private int $retardMinutes = 0;

    #[ORM\Column]
    private int $heuresSupplementaires = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaire = null;

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

    public function getEmploye(): ?Employe
    {
        return $this->employe;
    }

    public function setEmploye(?Employe $employe): static
    {
        $this->employe = $employe;

        return $this;
    }

    public function getJour(): ?\DateTimeInterface
    {
        return $this->jour;
    }

    public function setJour(?\DateTimeInterface $jour): static
    {
        $this->jour = $jour === null ? null : \DateTime::createFromInterface($jour);

        return $this;
    }

    public function getHeureArrivee(): ?\DateTimeInterface
    {
        return $this->heureArrivee;
    }

    public function setHeureArrivee(?\DateTimeInterface $heureArrivee): static
    {
        $this->heureArrivee = $heureArrivee;

        return $this;
    }

    public function getHeureDepart(): ?\DateTimeInterface
    {
        return $this->heureDepart;
    }

    public function setHeureDepart(?\DateTimeInterface $heureDepart): static
    {
        $this->heureDepart = $heureDepart;

        return $this;
    }

    public function getRetardMinutes(): int
    {
        return $this->retardMinutes;
    }

    public function setRetardMinutes(int $retardMinutes): static
    {
        $this->retardMinutes = max(0, $retardMinutes);

        return $this;
    }

    public function getHeuresSupplementaires(): int
    {
        return $this->heuresSupplementaires;
    }

    public function setHeuresSupplementaires(int $minutes): static
    {
        $this->heuresSupplementaires = max(0, $minutes);

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire === null ? null : mb_strtoupper(trim($commentaire), 'UTF-8');

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Duree de presence en minutes, 0 si le depart n'est pas encore pointe. */
    public function getDureeMinutes(): int
    {
        if ($this->heureArrivee === null || $this->heureDepart === null) {
            return 0;
        }

        $debut = (int) $this->heureArrivee->format('H') * 60 + (int) $this->heureArrivee->format('i');
        $fin = (int) $this->heureDepart->format('H') * 60 + (int) $this->heureDepart->format('i');

        return max(0, $fin - $debut);
    }

    public function getDureeLibelle(): string
    {
        $minutes = $this->getDureeMinutes();

        return intdiv($minutes, 60) . ' h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    public function isPresent(): bool
    {
        return $this->heureArrivee !== null;
    }

    public function isEnRetard(): bool
    {
        return $this->retardMinutes > 0;
    }
}
