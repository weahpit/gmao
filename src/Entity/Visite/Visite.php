<?php

namespace App\Entity\Visite;

use App\Entity\Services;
use App\Entity\User;
use App\Repository\Visite\VisiteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Un passage a CITRAC : une entree, et sa sortie quand la piece d'identite
 * a ete remise au visiteur.
 */
#[ORM\Entity(repositoryClass: VisiteRepository::class)]
#[ORM\Table(name: 'visite')]
#[ORM\Index(columns: ['heure_entree'], name: 'idx_visite_entree')]
#[ORM\Index(columns: ['statut'], name: 'idx_visite_statut')]
class Visite
{
    public const EN_COURS = 'en_cours';
    public const SORTI   = 'sorti';
    public const ANNULE  = 'annule';

    public const STATUTS = [
        self::EN_COURS => 'Present',
        self::SORTI    => 'Sorti',
        self::ANNULE   => 'Annule',
    ];

    public const MOTIFS = [
        'Visite professionnelle'   => 'Visite professionnelle',
        'Livraison'                => 'Livraison',
        'Entretien / candidature'  => 'Entretien / candidature',
        'Reunion'                  => 'Reunion',
        'Maintenance / travaux'    => 'Maintenance / travaux',
        'Demarche administrative'  => 'Demarche administrative',
        'Autre'                    => 'Autre',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private string $reference = '';

    #[ORM\ManyToOne(inversedBy: 'visites', targetEntity: Visiteur::class)]
    #[ORM\JoinColumn(name: 'visiteur_id', nullable: false, onDelete: 'RESTRICT')]
    private ?Visiteur $visiteur = null;

    #[ORM\ManyToOne(targetEntity: Services::class)]
    #[ORM\JoinColumn(name: 'service_id', nullable: true, onDelete: 'SET NULL')]
    private ?Services $service = null;

    #[ORM\Column(name: 'personne_visitee', length: 160, nullable: true)]
    private ?string $personneVisitee = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(name: 'nombre_accompagnants', options: ['default' => 0])]
    private int $nombreAccompagnants = 0;

    #[ORM\Column(name: 'heure_entree', type: 'datetime_immutable')]
    private \DateTimeImmutable $heureEntree;

    #[ORM\Column(name: 'heure_sortie', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureSortie = null;

    #[ORM\Column(name: 'piece_remise', options: ['default' => false])]
    private bool $pieceRemise = false;

    /** Chemin relatif de la piece d'identite scannee (dossier data/visites/). */
    #[ORM\Column(name: 'piece_identite', length: 255, nullable: true)]
    private ?string $pieceIdentite = null;

    #[ORM\Column(name: 'piece_nom_original', length: 255, nullable: true)]
    private ?string $pieceNomOriginal = null;

    #[ORM\Column(name: 'piece_type_mime', length: 120, nullable: true)]
    private ?string $pieceTypeMime = null;

    #[ORM\Column(name: 'piece_taille', nullable: true)]
    private ?int $pieceTaille = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::EN_COURS;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'agent_entree_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $agentEntree = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'agent_sortie_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $agentSortie = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observations = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->heureEntree = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getVisiteur(): ?Visiteur
    {
        return $this->visiteur;
    }

    public function setVisiteur(?Visiteur $visiteur): static
    {
        $this->visiteur = $visiteur;

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

    public function getPersonneVisitee(): ?string
    {
        return $this->personneVisitee;
    }

    public function setPersonneVisitee(?string $personneVisitee): static
    {
        $this->personneVisitee = mb_strtoupper((string) $this->nettoyer($personneVisitee), 'UTF-8');
        $this->personneVisitee = $this->personneVisitee === '' ? null : $this->personneVisitee;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $this->nettoyer($motif);

        return $this;
    }

    public function getNombreAccompagnants(): int
    {
        return $this->nombreAccompagnants;
    }

    public function setNombreAccompagnants(int $nombre): static
    {
        $this->nombreAccompagnants = max(0, min(20, $nombre));

        return $this;
    }

    public function getHeureEntree(): \DateTimeImmutable
    {
        return $this->heureEntree;
    }

    public function setHeureEntree(\DateTimeImmutable $heure): static
    {
        $this->heureEntree = $heure;

        return $this;
    }

    public function getHeureSortie(): ?\DateTimeImmutable
    {
        return $this->heureSortie;
    }

    public function setHeureSortie(?\DateTimeImmutable $heure): static
    {
        $this->heureSortie = $heure;

        return $this;
    }

    public function isPieceRemise(): bool
    {
        return $this->pieceRemise;
    }

    public function setPieceRemise(bool $remise): static
    {
        $this->pieceRemise = $remise;

        return $this;
    }

    public function getPieceIdentite(): ?string
    {
        return $this->pieceIdentite;
    }

    public function setPieceIdentite(?string $chemin): static
    {
        $this->pieceIdentite = $chemin;

        return $this;
    }

    public function getPieceNomOriginal(): ?string
    {
        return $this->pieceNomOriginal;
    }

    public function setPieceNomOriginal(?string $nom): static
    {
        $this->pieceNomOriginal = $nom;

        return $this;
    }

    public function getPieceTypeMime(): ?string
    {
        return $this->pieceTypeMime;
    }

    public function setPieceTypeMime(?string $mime): static
    {
        $this->pieceTypeMime = $mime;

        return $this;
    }

    public function getPieceTaille(): ?int
    {
        return $this->pieceTaille;
    }

    public function setPieceTaille(?int $taille): static
    {
        $this->pieceTaille = $taille;

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

    public function getAgentEntree(): ?User
    {
        return $this->agentEntree;
    }

    public function setAgentEntree(?User $agent): static
    {
        $this->agentEntree = $agent;

        return $this;
    }

    public function getAgentSortie(): ?User
    {
        return $this->agentSortie;
    }

    public function setAgentSortie(?User $agent): static
    {
        $this->agentSortie = $agent;

        return $this;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): static
    {
        $this->observations = $observations !== null && trim($observations) !== ''
            ? mb_strtoupper(trim($observations), 'UTF-8')
            : null;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isEnCours(): bool
    {
        return $this->statut === self::EN_COURS;
    }

    public function getStatutLibelle(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    /** Duree de presence en minutes (jusqu'a maintenant si le visiteur est encore la). */
    public function getDureeMinutes(): int
    {
        $fin = $this->heureSortie ?? new \DateTimeImmutable();
        $secondes = $fin->getTimestamp() - $this->heureEntree->getTimestamp();

        return max(0, (int) floor($secondes / 60));
    }

    public function getDureeLibelle(): string
    {
        $minutes = $this->getDureeMinutes();

        return intdiv($minutes, 60) . ' h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    public function getServiceLibelle(): string
    {
        if ($this->service === null) {
            return '-';
        }

        $direction = $this->service->getCodeDirection()?->getLibelle();

        return trim(($direction !== null ? $direction . ' / ' : '') . (string) $this->service->getLibelle());
    }

    public function aUnePieceJointe(): bool
    {
        return $this->pieceIdentite !== null && $this->pieceIdentite !== '';
    }

    private function nettoyer(?string $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        $valeur = trim(preg_replace('/\s+/u', ' ', $valeur) ?? $valeur);

        return $valeur === '' ? null : $valeur;
    }
}
