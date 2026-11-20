<?php

namespace App\Entity\Rh;

use App\Repository\Rh\CandidatureRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Candidature d'un candidat a une offre.
 *
 * Le pipeline de preselection et l'entretien sont suivis sur la meme fiche :
 * statut, notes de tests, date d'entretien, evaluateurs et note finale.
 */
#[ORM\Entity(repositoryClass: CandidatureRepository::class)]
#[ORM\Table(name: 'rh_candidature')]
#[ORM\UniqueConstraint(name: 'uniq_candidature_offre_candidat', columns: ['offre_id', 'candidat_id'])]
#[ORM\Index(columns: ['statut'], name: 'idx_candidature_statut')]
class Candidature
{
    public const RECUE = 'recue';
    public const PRESELECTION = 'preselection';
    public const TESTS = 'tests';
    public const ENTRETIEN = 'entretien';
    public const RETENUE = 'retenue';
    public const REFUSEE = 'refusee';

    public const STATUTS = [
        self::RECUE => 'Recue',
        self::PRESELECTION => 'Preselection',
        self::TESTS => 'Tests',
        self::ENTRETIEN => 'Entretien',
        self::RETENUE => 'Retenue',
        self::REFUSEE => 'Refusee',
    ];

    /** Ordre du pipeline, pour l'affichage type entonnoir. */
    public const PIPELINE = [
        self::RECUE,
        self::PRESELECTION,
        self::TESTS,
        self::ENTRETIEN,
        self::RETENUE,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $reference = null;

    #[ORM\ManyToOne(targetEntity: OffreEmploi::class, inversedBy: 'candidatures')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?OffreEmploi $offre = null;

    #[ORM\ManyToOne(targetEntity: Candidat::class, inversedBy: 'candidatures')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Candidat $candidat = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dateSoumission = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::RECUE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observations = null;

    /** Resultat des tests techniques / psychotechniques (0-20). */
    #[ORM\Column(nullable: true)]
    private ?int $noteTests = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateEntretien = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $evaluateurs = null;

    /** Note d'entretien (0-20). */
    #[ORM\Column(nullable: true)]
    private ?int $noteEntretien = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $compteRenduEntretien = null;

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

    public function getOffre(): ?OffreEmploi
    {
        return $this->offre;
    }

    public function setOffre(?OffreEmploi $offre): static
    {
        $this->offre = $offre;

        return $this;
    }

    public function getCandidat(): ?Candidat
    {
        return $this->candidat;
    }

    public function setCandidat(?Candidat $candidat): static
    {
        $this->candidat = $candidat;

        return $this;
    }

    public function getDateSoumission(): ?\DateTimeInterface
    {
        return $this->dateSoumission;
    }

    public function setDateSoumission(?\DateTimeInterface $dateSoumission): static
    {
        $this->dateSoumission = $dateSoumission === null ? null : \DateTime::createFromInterface($dateSoumission);

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = isset(self::STATUTS[$statut]) ? $statut : self::RECUE;

        return $this;
    }

    public function getStatutLibelle(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): static
    {
        $this->observations = $observations === null ? null : mb_strtoupper(trim($observations), 'UTF-8');

        return $this;
    }

    public function getNoteTests(): ?int
    {
        return $this->noteTests;
    }

    public function setNoteTests(?int $noteTests): static
    {
        $this->noteTests = $noteTests;

        return $this;
    }

    public function getDateEntretien(): ?\DateTimeInterface
    {
        return $this->dateEntretien;
    }

    public function setDateEntretien(?\DateTimeInterface $dateEntretien): static
    {
        $this->dateEntretien = $dateEntretien;

        return $this;
    }

    public function getEvaluateurs(): ?string
    {
        return $this->evaluateurs;
    }

    public function setEvaluateurs(?string $evaluateurs): static
    {
        $this->evaluateurs = $evaluateurs;

        return $this;
    }

    public function getNoteEntretien(): ?int
    {
        return $this->noteEntretien;
    }

    public function setNoteEntretien(?int $noteEntretien): static
    {
        $this->noteEntretien = $noteEntretien;

        return $this;
    }

    public function getCompteRenduEntretien(): ?string
    {
        return $this->compteRenduEntretien;
    }

    public function setCompteRenduEntretien(?string $compteRendu): static
    {
        $this->compteRenduEntretien = $compteRendu === null ? null : mb_strtoupper(trim($compteRendu), 'UTF-8');

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Moyenne des notes disponibles (tests + entretien), null si aucune. */
    public function getNoteGlobale(): ?float
    {
        $notes = array_values(array_filter([$this->noteTests, $this->noteEntretien], static fn (?int $n) => $n !== null));

        return $notes === [] ? null : round(array_sum($notes) / count($notes), 1);
    }

    public function isRetenue(): bool
    {
        return $this->statut === self::RETENUE;
    }
}
