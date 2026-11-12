<?php

namespace App\Entity\Reunion;

use App\Repository\Reunion\ReunionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Une reunion (assemblee generale, reunion de service, point hebdomadaire...).
 *
 * Cycle de vie :
 *  - en_attente : creee par l'admin, pas encore ouverte aux participants ;
 *  - ouverte    : les participants peuvent s'enregistrer depuis la page publique ;
 *  - en_cours   : toujours ouverte (variante "reunion demarree") ;
 *  - cloturee   : l'admin a clos la reunion, plus aucune inscription possible.
 */
#[ORM\Entity(repositoryClass: ReunionRepository::class)]
#[ORM\Table(name: 'reunion')]
class Reunion
{
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_OUVERTE    = 'ouverte';
    public const STATUT_CLOTUREE   = 'cloturee';

    /** Libelles affichables des statuts. */
    public const STATUTS = [
        self::STATUT_EN_ATTENTE => 'En attente',
        self::STATUT_OUVERTE    => 'Ouverte aux inscriptions',
        self::STATUT_CLOTUREE   => 'Cloturee',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $objet = null;

    /** Lieu / salle de la reunion. */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $lieu = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_ATTENTE;

    /**
     * Jeton present dans l'URL publique d'inscription :
     * /presence/reunion/{jeton}. Evite de deviner les reunions existantes.
     */
    #[ORM\Column(length: 40, unique: true)]
    private ?string $jeton = null;

    /** Nom du fichier PDF du compte rendu (dossier data/reunions/comptes_rendus/). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $compteRendu = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $compteRenduNomOriginal = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $compteRenduAjouteLe = null;

    /** Le compte rendu est-il visible par les participants ? */
    #[ORM\Column(type: 'boolean')]
    private bool $compteRenduPublie = false;

    /** Date du dernier envoi du compte rendu par email aux participants. */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $compteRenduEnvoyeLe = null;

    /** Nombre de destinataires du dernier envoi par email. */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $compteRenduEnvoyeA = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $clotureeLe = null;

    /** @var Collection<int, ParticipantReunion> */
    #[ORM\OneToMany(targetEntity: ParticipantReunion::class, mappedBy: 'reunion', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $participants;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->jeton = bin2hex(random_bytes(12));
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }

    public function getObjet(): ?string { return $this->objet; }
    public function setObjet(?string $objet): static { $this->objet = $objet; return $this; }

    public function getLieu(): ?string { return $this->lieu; }
    public function setLieu(?string $lieu): static { $this->lieu = $lieu; return $this; }

    public function getDateDebut(): ?\DateTimeImmutable { return $this->dateDebut; }
    public function setDateDebut(\DateTimeImmutable $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }

    public function getDateFin(): ?\DateTimeImmutable { return $this->dateFin; }
    public function setDateFin(?\DateTimeImmutable $dateFin): static { $this->dateFin = $dateFin; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    /** Reunion ouverte : les participants peuvent encore s'enregistrer. */
    public function isOuverte(): bool { return $this->statut === self::STATUT_OUVERTE; }

    public function isCloturee(): bool { return $this->statut === self::STATUT_CLOTUREE; }

    public function getJeton(): ?string { return $this->jeton; }
    public function setJeton(?string $jeton): static { $this->jeton = $jeton; return $this; }

    public function getCompteRendu(): ?string { return $this->compteRendu; }
    public function setCompteRendu(?string $compteRendu): static { $this->compteRendu = $compteRendu; return $this; }

    public function getCompteRenduNomOriginal(): ?string { return $this->compteRenduNomOriginal; }
    public function setCompteRenduNomOriginal(?string $nom): static { $this->compteRenduNomOriginal = $nom; return $this; }

    public function getCompteRenduAjouteLe(): ?\DateTimeImmutable { return $this->compteRenduAjouteLe; }
    public function setCompteRenduAjouteLe(?\DateTimeImmutable $d): static { $this->compteRenduAjouteLe = $d; return $this; }

    public function isCompteRenduPublie(): bool { return $this->compteRenduPublie; }
    public function setCompteRenduPublie(bool $publie): static { $this->compteRenduPublie = $publie; return $this; }

    /** Un compte rendu existe ET il est publiable aux participants. */
    public function isCompteRenduConsultable(): bool
    {
        return $this->compteRenduPublie && $this->compteRendu !== null && $this->compteRendu !== '';
    }

    public function getCompteRenduEnvoyeLe(): ?\DateTimeImmutable { return $this->compteRenduEnvoyeLe; }
    public function setCompteRenduEnvoyeLe(?\DateTimeImmutable $d): static { $this->compteRenduEnvoyeLe = $d; return $this; }

    public function getCompteRenduEnvoyeA(): ?int { return $this->compteRenduEnvoyeA; }
    public function setCompteRenduEnvoyeA(?int $n): static { $this->compteRenduEnvoyeA = $n; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function getClotureeLe(): ?\DateTimeImmutable { return $this->clotureeLe; }
    public function setClotureeLe(?\DateTimeImmutable $d): static { $this->clotureeLe = $d; return $this; }

    /** @return Collection<int, ParticipantReunion> */
    public function getParticipants(): Collection { return $this->participants; }

    public function addParticipant(ParticipantReunion $participant): static
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
            $participant->setReunion($this);
        }

        return $this;
    }

    public function removeParticipant(ParticipantReunion $participant): static
    {
        if ($this->participants->removeElement($participant)) {
            if ($participant->getReunion() === $this) {
                $participant->setReunion(null);
            }
        }

        return $this;
    }

    public function getNombreParticipants(): int { return $this->participants->count(); }

    public function __toString(): string { return (string) $this->titre; }
}
