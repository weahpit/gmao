<?php

namespace App\Entity\Rh;

use App\Entity\Directions;
use App\Entity\Poste;
use App\Entity\Services;
use App\Entity\User;
use App\Repository\Rh\EmployeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Dossier administratif d'un employe CITRAC.
 *
 * Le domaine Medical est volontairement separe : Employe ne porte aucune
 * donnee de sante, celles-ci vivent dans DossierMedical.
 */
#[ORM\Entity(repositoryClass: EmployeRepository::class)]
#[ORM\Table(name: 'rh_employe')]
#[ORM\Index(columns: ['nom', 'prenoms'], name: 'idx_employe_nom')]
#[ORM\Index(columns: ['statut'], name: 'idx_employe_statut')]
#[ORM\Index(columns: ['code_pointage'], name: 'idx_employe_code_pointage')]
class Employe
{
    public const STATUT_ACTIF = 'actif';
    public const STATUT_SUSPENDU = 'suspendu';
    public const STATUT_SORTI = 'sorti';

    public const STATUTS = [
        self::STATUT_ACTIF => 'Actif',
        self::STATUT_SUSPENDU => 'Suspendu',
        self::STATUT_SORTI => 'Sorti',
    ];

    public const SEXES = [
        'M' => 'Masculin',
        'F' => 'Feminin',
    ];

    public const SITUATIONS = [
        'celibataire' => 'Celibataire',
        'marie' => 'Marie(e)',
        'divorce' => 'Divorce(e)',
        'veuf' => 'Veuf(ve)',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $matricule = null;

    /** Numero de badge enregistre sur la pointeuse ZKTECO (Badgenumber). */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $codePointage = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $prenoms = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(length: 1, nullable: true)]
    private ?string $sexe = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $situationMatrimoniale = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $nationalite = 'Ivoirienne';

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    /** Chemin relatif de la photo dans public/data/rh/photos/. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $numeroCnps = null;

    #[ORM\ManyToOne(targetEntity: Directions::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Directions $direction = null;

    #[ORM\ManyToOne(targetEntity: Services::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Services $service = null;

    #[ORM\ManyToOne(targetEntity: Poste::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Poste $poste = null;

    /** Responsable hierarchique (autre employe). */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'subordonnes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Employe $manager = null;

    /** @var Collection<int, Employe> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'manager')]
    private Collection $subordonnes;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dateEmbauche = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_ACTIF;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateSortie = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $motifSortie = null;

    /** Compte applicatif rattache, si l'employe se connecte a la GMAO. */
    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $utilisateur = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Contrat> */
    #[ORM\OneToMany(targetEntity: Contrat::class, mappedBy: 'employe')]
    private Collection $contrats;

    /** @var Collection<int, Affectation> */
    #[ORM\OneToMany(targetEntity: Affectation::class, mappedBy: 'employe')]
    private Collection $affectations;

    /** @var Collection<int, Absence> */
    #[ORM\OneToMany(targetEntity: Absence::class, mappedBy: 'employe')]
    private Collection $absences;

    /** @var Collection<int, ParticipationFormation> */
    #[ORM\OneToMany(targetEntity: ParticipationFormation::class, mappedBy: 'employe')]
    private Collection $formations;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->subordonnes = new ArrayCollection();
        $this->contrats = new ArrayCollection();
        $this->affectations = new ArrayCollection();
        $this->absences = new ArrayCollection();
        $this->formations = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(?string $matricule): static
    {
        $this->matricule = $matricule;

        return $this;
    }

    public function getCodePointage(): ?string
    {
        return $this->codePointage;
    }

    public function setCodePointage(?string $code): static
    {
        $code = $code === null ? null : trim($code);
        $this->codePointage = $code === '' ? null : $code;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom !== null ? mb_strtoupper(trim($nom), 'UTF-8') : null;
        $this->touch();

        return $this;
    }

    public function getPrenoms(): ?string
    {
        return $this->prenoms;
    }

    public function setPrenoms(?string $prenoms): static
    {
        $this->prenoms = $prenoms === null ? null : mb_strtoupper(trim($prenoms), 'UTF-8');
        $this->touch();

        return $this;
    }

    public function getNomComplet(): string
    {
        return trim(($this->nom ?? '') . ' ' . ($this->prenoms ?? ''));
    }

    public function getInitiales(): string
    {
        return mb_substr((string) $this->nom, 0, 1) . mb_substr((string) $this->prenoms, 0, 1);
    }

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeInterface $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance === null ? null : \DateTime::createFromInterface($dateNaissance);

        return $this;
    }

    public function getAge(): ?int
    {
        return $this->dateNaissance?->diff(new \DateTimeImmutable())->y;
    }

    public function getSexe(): ?string
    {
        return $this->sexe;
    }

    public function setSexe(?string $sexe): static
    {
        $this->sexe = $sexe;

        return $this;
    }

    public function getSexeLibelle(): string
    {
        return self::SEXES[$this->sexe ?? ''] ?? 'Non renseigne';
    }

    public function getSituationMatrimoniale(): ?string
    {
        return $this->situationMatrimoniale;
    }

    public function setSituationMatrimoniale(?string $situation): static
    {
        $this->situationMatrimoniale = $situation;

        return $this;
    }

    public function getSituationLibelle(): string
    {
        return self::SITUATIONS[$this->situationMatrimoniale ?? ''] ?? 'Non renseignee';
    }

    public function getNationalite(): ?string
    {
        return $this->nationalite;
    }

    public function setNationalite(?string $nationalite): static
    {
        $this->nationalite = $nationalite === null ? null : mb_strtoupper(trim($nationalite), 'UTF-8');

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse === null ? null : mb_strtoupper(trim($adresse), 'UTF-8');

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getNumeroCnps(): ?string
    {
        return $this->numeroCnps;
    }

    public function setNumeroCnps(?string $numeroCnps): static
    {
        $this->numeroCnps = $numeroCnps;

        return $this;
    }

    public function getDirection(): ?Directions
    {
        return $this->direction;
    }

    public function setDirection(?Directions $direction): static
    {
        $this->direction = $direction;

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

    public function getPoste(): ?Poste
    {
        return $this->poste;
    }

    public function setPoste(?Poste $poste): static
    {
        $this->poste = $poste;

        return $this;
    }

    public function getManager(): ?self
    {
        return $this->manager;
    }

    public function setManager(?self $manager): static
    {
        if ($manager !== null && $manager->getId() !== null && $manager->getId() === $this->getId()) {
            return $this;
        }
        $this->manager = $manager;

        return $this;
    }

    /** @return Collection<int, Employe> */
    public function getSubordonnes(): Collection
    {
        return $this->subordonnes;
    }

    public function getDateEmbauche(): ?\DateTimeInterface
    {
        return $this->dateEmbauche;
    }

    public function setDateEmbauche(?\DateTimeInterface $dateEmbauche): static
    {
        $this->dateEmbauche = $dateEmbauche === null ? null : \DateTime::createFromInterface($dateEmbauche);

        return $this;
    }

    public function getAncienneteAnnees(): ?int
    {
        return $this->dateEmbauche?->diff(new \DateTimeImmutable())->y;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = isset(self::STATUTS[$statut]) ? $statut : self::STATUT_ACTIF;

        return $this;
    }

    public function getStatutLibelle(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function isActif(): bool
    {
        return $this->statut === self::STATUT_ACTIF;
    }

    public function getDateSortie(): ?\DateTimeInterface
    {
        return $this->dateSortie;
    }

    public function setDateSortie(?\DateTimeInterface $dateSortie): static
    {
        $this->dateSortie = $dateSortie === null ? null : \DateTime::createFromInterface($dateSortie);

        return $this;
    }

    public function getMotifSortie(): ?string
    {
        return $this->motifSortie;
    }

    public function setMotifSortie(?string $motifSortie): static
    {
        $this->motifSortie = $motifSortie === null ? null : mb_strtoupper(trim($motifSortie), 'UTF-8');

        return $this;
    }

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?User $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

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

    /** @return Collection<int, Contrat> */
    public function getContrats(): Collection
    {
        return $this->contrats;
    }

    public function getContratEnCours(): ?Contrat
    {
        foreach ($this->contrats as $contrat) {
            if ($contrat->isEnCours()) {
                return $contrat;
            }
        }

        return null;
    }

    /** @return Collection<int, Affectation> */
    public function getAffectations(): Collection
    {
        return $this->affectations;
    }

    /** @return Collection<int, Absence> */
    public function getAbsences(): Collection
    {
        return $this->absences;
    }

    /** @return Collection<int, ParticipationFormation> */
    public function getFormations(): Collection
    {
        return $this->formations;
    }
}
