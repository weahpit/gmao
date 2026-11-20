<?php

namespace App\Entity\Rh;

use App\Entity\User;
use App\Repository\Rh\EvaluationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Fiche d'evaluation d'un employe dans une campagne.
 *
 * Les notes par critere sont stockees en JSON (cle = identifiant du critere),
 * ce qui permet d'ajouter ou retirer un critere sans migration de structure.
 */
#[ORM\Entity(repositoryClass: EvaluationRepository::class)]
#[ORM\Table(name: 'rh_evaluation')]
#[ORM\UniqueConstraint(name: 'uniq_eval_campagne_employe', columns: ['campagne_id', 'employe_id'])]
class Evaluation
{
    public const AUTO = 'auto';
    public const SUPERIEUR = 'superieur';
    public const RH = 'rh';

    public const TYPES = [
        self::AUTO => 'Auto-evaluation',
        self::SUPERIEUR => 'Evaluation du superieur',
        self::RH => 'Validation RH',
    ];

    public const BROUILLON = 'brouillon';
    public const SOUMIS = 'soumis';
    public const VALIDE = 'valide';

    public const STATUTS = [
        self::BROUILLON => 'Brouillon',
        self::SOUMIS => 'Soumise',
        self::VALIDE => 'Validee RH',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: CampagneEvaluation::class, inversedBy: 'evaluations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CampagneEvaluation $campagne = null;

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $evaluateur = null;

    #[ORM\Column(length: 20)]
    private string $type = self::AUTO;

    /** @var array<string, int> notes par identifiant de critere (0-5) */
    #[ORM\Column(type: 'json')]
    private array $notes = [];

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2, nullable: true)]
    private ?string $noteGlobale = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    /** Points forts / axes de progres discutes avec l'employe. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $planDeveloppement = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::BROUILLON;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCampagne(): ?CampagneEvaluation
    {
        return $this->campagne;
    }

    public function setCampagne(?CampagneEvaluation $campagne): static
    {
        $this->campagne = $campagne;

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

    public function getEvaluateur(): ?User
    {
        return $this->evaluateur;
    }

    public function setEvaluateur(?User $evaluateur): static
    {
        $this->evaluateur = $evaluateur;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = isset(self::TYPES[$type]) ? $type : self::AUTO;

        return $this;
    }

    public function getTypeLibelle(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** @return array<string, int> */
    public function getNotes(): array
    {
        return $this->notes;
    }

    /** @param array<string, int> $notes */
    public function setNotes(array $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getNote(string $critereId): ?int
    {
        return $this->notes[$critereId] ?? null;
    }

    public function setNote(string $critereId, ?int $note): static
    {
        if ($note === null) {
            unset($this->notes[$critereId]);
        } else {
            $this->notes[$critereId] = max(0, min(5, $note));
        }

        return $this;
    }

    public function getNoteGlobale(): ?string
    {
        return $this->noteGlobale;
    }

    public function setNoteGlobale(?string $noteGlobale): static
    {
        $this->noteGlobale = $noteGlobale;

        return $this;
    }

    /**
     * Note sur 20, ponderee par le poids des criteres et ramenee sur 5.
     * Retourne null si aucun critere n'est note.
     */
    public function calculerNoteGlobale(): ?string
    {
        if ($this->campagne === null || $this->notes === []) {
            return null;
        }

        $somme = 0.0;
        $poidsSomme = 0;

        foreach ($this->campagne->getCriteres() as $critere) {
            $note = $this->notes[(string) $critere->getId()] ?? null;
            if ($note === null) {
                continue;
            }
            $poids = $critere->getPoids();
            $somme += $note * $poids;
            $poidsSomme += $poids;
        }

        if ($poidsSomme === 0) {
            return null;
        }

        return number_format(round($somme / $poidsSomme * 4, 2), 2, '.', '');
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

    public function getPlanDeveloppement(): ?string
    {
        return $this->planDeveloppement;
    }

    public function setPlanDeveloppement(?string $plan): static
    {
        $this->planDeveloppement = $plan === null ? null : mb_strtoupper(trim($plan), 'UTF-8');

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

    public function isValidee(): bool
    {
        return $this->statut === self::VALIDE;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
