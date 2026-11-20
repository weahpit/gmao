<?php

namespace App\Entity\Rh;

use App\Entity\User;
use App\Repository\Rh\DocumentRhRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Document rattache a la gestion RH : contrat, diplome, piece d'identite,
 * compte rendu d'entretien, document medical...
 *
 * Le fichier est stocke hors du dossier public (data/rh/...) et n'est servi
 * que par un controleur qui verifie les droits.
 */
#[ORM\Entity(repositoryClass: DocumentRhRepository::class)]
#[ORM\Table(name: 'rh_document')]
#[ORM\Index(columns: ['categorie'], name: 'idx_document_categorie')]
class DocumentRh
{
    public const CATEGORIE_RH = 'rh';
    public const CATEGORIE_MEDICAL = 'medical';
    public const CATEGORIE_RECRUTEMENT = 'recrutement';

    public const CATEGORIES = [
        self::CATEGORIE_RH => 'Dossier RH',
        self::CATEGORIE_MEDICAL => 'Medical (confidentiel)',
        self::CATEGORIE_RECRUTEMENT => 'Recrutement',
    ];

    public const TYPES = [
        'contrat' => 'Contrat',
        'diplome' => 'Diplome',
        'piece_identite' => 'Piece d\'identite',
        'attestation' => 'Attestation',
        'cv' => 'Curriculum vitae',
        'lettre_motivation' => 'Lettre de motivation',
        'compte_rendu_entretien' => 'Compte rendu d\'entretien',
        'certificat_medical' => 'Certificat medical',
        'autre' => 'Autre',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 20)]
    private string $categorie = self::CATEGORIE_RH;

    #[ORM\Column(length: 40)]
    private string $type = 'autre';

    #[ORM\Column(length: 200)]
    private ?string $titre = null;

    #[ORM\ManyToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    #[ORM\ManyToOne(targetEntity: Candidat::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Candidat $candidat = null;

    /** Chemin relatif au dossier de stockage (jamais expose tel quel). */
    #[ORM\Column(length: 255)]
    private ?string $chemin = null;

    #[ORM\Column(length: 200)]
    private ?string $nomOriginal = null;

    #[ORM\Column(length: 120)]
    private ?string $mime = null;

    #[ORM\Column]
    private int $taille = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $deposePar = null;

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

    public function getCategorie(): string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): static
    {
        $this->categorie = isset(self::CATEGORIES[$categorie]) ? $categorie : self::CATEGORIE_RH;

        return $this;
    }

    public function getCategorieLibelle(): string
    {
        return self::CATEGORIES[$this->categorie] ?? $this->categorie;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = isset(self::TYPES[$type]) ? $type : 'autre';

        return $this;
    }

    public function getTypeLibelle(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre === null ? null : mb_strtoupper(trim($titre), 'UTF-8');

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

    public function getCandidat(): ?Candidat
    {
        return $this->candidat;
    }

    public function setCandidat(?Candidat $candidat): static
    {
        $this->candidat = $candidat;

        return $this;
    }

    public function getChemin(): ?string
    {
        return $this->chemin;
    }

    public function setChemin(?string $chemin): static
    {
        $this->chemin = $chemin;

        return $this;
    }

    public function getNomOriginal(): ?string
    {
        return $this->nomOriginal;
    }

    public function setNomOriginal(?string $nom): static
    {
        $this->nomOriginal = $nom;

        return $this;
    }

    public function getMime(): ?string
    {
        return $this->mime;
    }

    public function setMime(?string $mime): static
    {
        $this->mime = $mime;

        return $this;
    }

    public function getTaille(): int
    {
        return $this->taille;
    }

    public function setTaille(int $taille): static
    {
        $this->taille = $taille;

        return $this;
    }

    public function getTailleLibelle(): string
    {
        if ($this->taille < 1024) {
            return $this->taille . ' o';
        }
        if ($this->taille < 1048576) {
            return number_format($this->taille / 1024, 1, ',', ' ') . ' Ko';
        }

        return number_format($this->taille / 1048576, 1, ',', ' ') . ' Mo';
    }

    public function getDeposePar(): ?User
    {
        return $this->deposePar;
    }

    public function setDeposePar(?User $user): static
    {
        $this->deposePar = $user;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExtension(): string
    {
        return $this->nomOriginal !== null ? strtolower((string) pathinfo($this->nomOriginal, PATHINFO_EXTENSION)) : '';
    }
}
