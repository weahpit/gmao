<?php

namespace App\Entity\Reunion;

use App\Repository\Reunion\ParticipantReunionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Enregistrement de presence d'un participant a une reunion.
 *
 * Saisie depuis la page publique (hors session) :
 * nom, prenoms, service, qualite, numero de telephone, adresse email et signature manuscrite.
 * La signature est stockee en image PNG (base64 decode) dans
 * data/reunions/signatures/.
 */
#[ORM\Entity(repositoryClass: ParticipantReunionRepository::class)]
#[ORM\Table(name: 'participant_reunion')]
class ParticipantReunion
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Reunion $reunion = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 150)]
    private ?string $prenoms = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $service = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $qualite = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $telephone = null;

    /** Adresse email du participant (facultative). */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    /** Nom du fichier image de la signature (dossier data/reunions/signatures/). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $signature = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Date et heure d'ARRIVEE du participant (saisie a l'enregistrement de la
     * presence). Null pour les enregistrements anterieurs : createdAt fait foi.
     */
    #[ORM\Column(name: 'arrivee_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $arriveeAt = null;

    /** Seuil (en minutes) au-dela duquel un participant est considere en retard. */
    public const SEUIL_RETARD_MINUTES = 15;

    public function getArriveeAt(): ?\DateTimeImmutable { return $this->arriveeAt; }
    public function setArriveeAt(?\DateTimeImmutable $v): static { $this->arriveeAt = $v; return $this; }

    /** Instant d'arrivee effectif (arriveeAt, sinon createdAt). */
    public function getInstantArrivee(): ?\DateTimeImmutable { return $this->arriveeAt ?? $this->createdAt; }

    /** Retard en minutes par rapport au debut de la reunion (0 si a l'heure). */
    public function getRetardMinutes(): int
    {
        $debut = $this->reunion?->getDateDebut();
        $arrivee = $this->getInstantArrivee();
        if (!$debut || !$arrivee) {
            return 0;
        }
        $minutes = (int) floor(($arrivee->getTimestamp() - $debut->getTimestamp()) / 60);

        return max(0, $minutes);
    }

    /** Le participant est-il en retard (arrivee >= 15 min apres le debut) ? */
    public function isEnRetard(): bool
    {
        return $this->getRetardMinutes() >= self::SEUIL_RETARD_MINUTES;
    }

    /** Adresse IP de l'enregistrement (tracabilite). */
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getReunion(): ?Reunion { return $this->reunion; }
    public function setReunion(?Reunion $reunion): static { $this->reunion = $reunion; return $this; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getPrenoms(): ?string { return $this->prenoms; }
    public function setPrenoms(string $prenoms): static { $this->prenoms = $prenoms; return $this; }

    public function getService(): ?string { return $this->service; }
    public function setService(?string $service): static { $this->service = $service; return $this; }

    public function getQualite(): ?string { return $this->qualite; }
    public function setQualite(?string $qualite): static { $this->qualite = $qualite; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }

    public function getSignature(): ?string { return $this->signature; }
    public function setSignature(?string $signature): static { $this->signature = $signature; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getIp(): ?string { return $this->ip; }
    public function setIp(?string $ip): static { $this->ip = $ip; return $this; }

    /** "NOM Prenoms" en majuscules pour les listes. */
    public function getNomComplet(): string
    {
        return trim(mb_strtoupper((string) $this->nom) . ' ' . (string) $this->prenoms);
    }

    public function __toString(): string { return $this->getNomComplet(); }
}
