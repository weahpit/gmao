<?php

namespace App\Entity\Visite;

use App\Repository\Visite\VisiteurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Fiche d'un visiteur deja recu au moins une fois a CITRAC.
 *
 * Elle sert de base a la saisie semi-automatique : des qu'un visiteur est
 * reconnu (nom, telephone ou numero de piece), ses informations sont
 * reprises et l'agent ne ressaisit que le motif de la visite.
 */
#[ORM\Entity(repositoryClass: VisiteurRepository::class)]
#[ORM\Table(name: 'visite_visiteur')]
#[ORM\Index(columns: ['nom', 'prenoms'], name: 'idx_visiteur_nom')]
#[ORM\Index(columns: ['numero_piece'], name: 'idx_visiteur_piece')]
class Visiteur
{
    public const TYPES_PIECE = [
        'CNI'        => 'Carte nationale d\'identite',
        'PASSEPORT'  => 'Passeport',
        'PERMIS'     => 'Permis de conduire',
        'CARTE_PRO'  => 'Carte professionnelle',
        'ATTESTATION'=> 'Attestation / autre',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100)]
    private string $nom = '';

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $prenoms = null;

    #[ORM\Column(name: 'type_piece', length: 20, nullable: true)]
    private ?string $typePiece = null;

    #[ORM\Column(name: 'numero_piece', length: 60, nullable: true)]
    private ?string $numeroPiece = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $societe = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $fonction = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observations = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Visite> */
    #[ORM\OneToMany(targetEntity: Visite::class, mappedBy: 'visiteur')]
    private Collection $visites;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->visites = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = mb_strtoupper(trim($nom));

        return $this;
    }

    public function getPrenoms(): ?string
    {
        return $this->prenoms;
    }

    public function setPrenoms(?string $prenoms): static
    {
        $this->prenoms = mb_strtoupper((string) $this->nettoyer($prenoms), 'UTF-8');
        $this->prenoms = $this->prenoms === '' ? null : $this->prenoms;

        return $this;
    }

    public function getTypePiece(): ?string
    {
        return $this->typePiece;
    }

    public function setTypePiece(?string $typePiece): static
    {
        $this->typePiece = isset(self::TYPES_PIECE[(string) $typePiece]) ? (string) $typePiece : null;

        return $this;
    }

    public function getNumeroPiece(): ?string
    {
        return $this->numeroPiece;
    }

    public function setNumeroPiece(?string $numeroPiece): static
    {
        $this->numeroPiece = $this->nettoyer($numeroPiece);

        return $this;
    }

    public function getSociete(): ?string
    {
        return $this->societe;
    }

    public function setSociete(?string $societe): static
    {
        $this->societe = mb_strtoupper((string) $this->nettoyer($societe), 'UTF-8');
        $this->societe = $this->societe === '' ? null : $this->societe;

        return $this;
    }

    public function getFonction(): ?string
    {
        return $this->fonction;
    }

    public function setFonction(?string $fonction): static
    {
        $this->fonction = mb_strtoupper((string) $this->nettoyer($fonction), 'UTF-8');
        $this->fonction = $this->fonction === '' ? null : $this->fonction;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $this->nettoyer($telephone);

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $this->nettoyer($email);

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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** @return Collection<int, Visite> */
    public function getVisites(): Collection
    {
        return $this->visites;
    }

    public function getNomComplet(): string
    {
        return trim($this->nom . ' ' . (string) $this->prenoms);
    }

    public function getTypePieceLibelle(): string
    {
        return self::TYPES_PIECE[$this->typePiece ?? ''] ?? 'Piece non renseignee';
    }

    public function getPieceComplete(): string
    {
        $morceaux = array_filter([$this->getTypePieceLibelle(), $this->numeroPiece], static fn ($v) => $v !== null && $v !== '');

        return $morceaux === [] ? '-' : implode(' n° ', $morceaux);
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
