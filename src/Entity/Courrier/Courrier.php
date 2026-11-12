<?php

namespace App\Entity\Courrier;

use App\Entity\Services;
use App\Entity\User;
use App\Repository\Courrier\CourrierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Courrier entrant ou sortant du GMAO.
 *
 *  - entrant : courrier recu (provenance = expediteur externe) ;
 *  - sortant : courrier emis (destinataire externe).
 *
 * Chaque courrier peut porter plusieurs pieces jointes (PDF, images,
 * documents bureautiques) stockees dans data/courriers/ et decrites
 * par l'entite PieceJointeCourrier.
 */
#[ORM\Entity(repositoryClass: CourrierRepository::class)]
#[ORM\Table(name: 'courrier')]
#[ORM\Index(columns: ['sens'], name: 'idx_courrier_sens')]
#[ORM\Index(columns: ['date_courrier'], name: 'idx_courrier_date')]
class Courrier
{
    public const SENS_ENTRANT = 'entrant';
    public const SENS_SORTANT = 'sortant';

    public const SENS = [
        self::SENS_ENTRANT => 'Courrier entrant',
        self::SENS_SORTANT => 'Courrier sortant',
    ];

    public const SENS_COURT = [
        self::SENS_ENTRANT => 'Entrant',
        self::SENS_SORTANT => 'Sortant',
    ];

    public const STATUT_A_TRAITER = 'a_traiter';
    public const STATUT_EN_COURS  = 'en_cours';
    public const STATUT_TRAITE    = 'traite';
    public const STATUT_ARCHIVE   = 'archive';

    public const STATUTS = [
        self::STATUT_A_TRAITER => 'A traiter',
        self::STATUT_EN_COURS  => 'En cours',
        self::STATUT_TRAITE    => 'Traite',
        self::STATUT_ARCHIVE   => 'Archive',
    ];

    /** Couleur (fond, texte) de chaque statut, pour les badges. */
    public const STATUT_COULEURS = [
        self::STATUT_A_TRAITER => ['#f3ecdd', '#8a6a2a'],
        self::STATUT_EN_COURS  => ['#e6eef7', '#2b5f8c'],
        self::STATUT_TRAITE    => ['#e3f4ec', '#22684d'],
        self::STATUT_ARCHIVE   => ['#eeeeee', '#666666'],
    ];

    public const PRIORITES = ['normale', 'urgente', 'tres_urgente'];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** Numero d'enregistrement lisible (ex : ENT-2026-0001). */
    #[ORM\Column(length: 30, unique: true)]
    private ?string $numero = null;

    #[ORM\Column(length: 20)]
    private string $sens = self::SENS_ENTRANT;

    /** Objet / reference du courrier. */
    #[ORM\Column(length: 255)]
    private ?string $objet = null;

    /** Expediteur (entrant) ou destinataire principal (sortant). */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $correspondant = null;

    /** Organisme, service ou societe du correspondant. */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $organisme = null;

    /** Reference portee par le courrier (numero de leur cote). */
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $dateCourrier = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateAccuse = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_A_TRAITER;

    #[ORM\Column(length: 20)]
    private string $priorite = 'normale';

    /** Service interne concerne, choisi dans la table des services. */
    #[ORM\ManyToOne(targetEntity: Services::class)]
    #[ORM\JoinColumn(name: 'code_service_id', nullable: true, onDelete: 'SET NULL')]
    private ?Services $codeService = null;

    /**
     * Libelle du service au moment de l'enregistrement.
     * Conserve pour que le registre reste lisible meme si le service est
     * renomme ou retire de la table des services.
     */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $service = null;

    /** Personne qui a traite / suivi le courrier en interne. */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $responsable = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $suiteDonnee = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /** @var Collection<int, PieceJointeCourrier> */
    #[ORM\OneToMany(targetEntity: PieceJointeCourrier::class, mappedBy: 'courrier', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $piecesJointes;

    public function __construct()
    {
        $this->piecesJointes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->dateCourrier = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getNumero(): ?string { return $this->numero; }
    public function setNumero(?string $numero): static { $this->numero = $numero; return $this; }

    public function getSens(): string { return $this->sens; }
    public function setSens(string $sens): static { $this->sens = $sens; return $this; }
    public function getSensLibelle(): string { return self::SENS[$this->sens] ?? $this->sens; }
    public function getSensCourt(): string { return self::SENS_COURT[$this->sens] ?? $this->sens; }
    public function isEntrant(): bool { return $this->sens === self::SENS_ENTRANT; }

    public function getObjet(): ?string { return $this->objet; }
    public function setObjet(?string $objet): static { $this->objet = $objet; return $this; }

    public function getCorrespondant(): ?string { return $this->correspondant; }
    public function setCorrespondant(?string $correspondant): static { $this->correspondant = $correspondant; return $this; }

    public function getOrganisme(): ?string { return $this->organisme; }
    public function setOrganisme(?string $organisme): static { $this->organisme = $organisme; return $this; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $reference): static { $this->reference = $reference; return $this; }

    public function getDateCourrier(): ?\DateTimeImmutable { return $this->dateCourrier; }
    public function setDateCourrier(\DateTimeImmutable $date): static { $this->dateCourrier = $date; return $this; }

    public function getDateAccuse(): ?\DateTimeImmutable { return $this->dateAccuse; }
    public function setDateAccuse(?\DateTimeImmutable $date): static { $this->dateAccuse = $date; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getStatutLibelle(): string { return self::STATUTS[$this->statut] ?? $this->statut; }

    public function getStatutCouleurFond(): string { return self::STATUT_COULEURS[$this->statut][0] ?? '#eeeeee'; }
    public function getStatutCouleurTexte(): string { return self::STATUT_COULEURS[$this->statut][1] ?? '#333333'; }

    public function getPriorite(): string { return $this->priorite; }
    public function setPriorite(string $priorite): static { $this->priorite = $priorite; return $this; }
    public function getPrioriteLibelle(): string
    {
        return match ($this->priorite) {
            'urgente' => 'Urgente',
            'tres_urgente' => 'Tres urgente',
            default => 'Normale',
        };
    }
    public function isUrgent(): bool { return $this->priorite !== 'normale'; }

    public function getCodeService(): ?Services { return $this->codeService; }

    public function setCodeService(?Services $service): static
    {
        $this->codeService = $service;
        $this->service = $service?->getLibelle();

        return $this;
    }

    public function getService(): ?string { return $this->service; }
    public function setService(?string $service): static { $this->service = $service; return $this; }

    public function getResponsable(): ?string { return $this->responsable; }
    public function setResponsable(?string $responsable): static { $this->responsable = $responsable; return $this; }

    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $observation): static { $this->observation = $observation; return $this; }

    public function getSuiteDonnee(): ?string { return $this->suiteDonnee; }
    public function setSuiteDonnee(?string $suiteDonnee): static { $this->suiteDonnee = $suiteDonnee; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $d): static { $this->createdAt = $d; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function touch(): static { $this->updatedAt = new \DateTimeImmutable(); return $this; }

    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $user): static { $this->createdBy = $user; return $this; }

    /** @return Collection<int, PieceJointeCourrier> */
    public function getPiecesJointes(): Collection { return $this->piecesJointes; }

    public function addPieceJointe(PieceJointeCourrier $piece): static
    {
        if (!$this->piecesJointes->contains($piece)) {
            $this->piecesJointes->add($piece);
            $piece->setCourrier($this);
        }

        return $this;
    }

    public function removePieceJointe(PieceJointeCourrier $piece): static
    {
        if ($this->piecesJointes->removeElement($piece)) {
            if ($piece->getCourrier() === $this) {
                $piece->setCourrier(null);
            }
        }

        return $this;
    }

    public function getNombrePiecesJointes(): int { return $this->piecesJointes->count(); }

    /** Libelle du correspondant a afficher (ex. « M. Koffi — SODECI »). */
    public function getCorrespondantComplet(): string
    {
        $txt = trim((string) $this->correspondant);
        if ($this->organisme) {
            $txt = $txt !== '' ? $txt . ' — ' . $this->organisme : (string) $this->organisme;
        }

        return $txt !== '' ? $txt : '—';
    }
}
