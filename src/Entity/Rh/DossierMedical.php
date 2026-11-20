<?php

namespace App\Entity\Rh;

use App\Repository\Rh\DossierMedicalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Dossier medical d'un employe.
 *
 * Donnees sensibles : l'acces est reserve au medecin, a l'infirmier(ere)
 * et a l'administrateur par le service RhAcces. Le service RH "administratif"
 * n'y a pas acces.
 */
#[ORM\Entity(repositoryClass: DossierMedicalRepository::class)]
#[ORM\Table(name: 'rh_dossier_medical')]
class DossierMedical
{
    public const GROUPES_SANGUINS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Inconnu'];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private ?string $numeroDossier = null;

    #[ORM\OneToOne(targetEntity: Employe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $groupeSanguin = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $allergies = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $antecedents = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, VisiteMedicale> */
    #[ORM\OneToMany(targetEntity: VisiteMedicale::class, mappedBy: 'dossier', cascade: ['remove'])]
    #[ORM\OrderBy(['date' => 'DESC'])]
    private Collection $visites;

    /** @var Collection<int, Vaccination> */
    #[ORM\OneToMany(targetEntity: Vaccination::class, mappedBy: 'dossier', cascade: ['remove'])]
    #[ORM\OrderBy(['date' => 'DESC'])]
    private Collection $vaccinations;

    /** @var Collection<int, AccidentTravail> */
    #[ORM\OneToMany(targetEntity: AccidentTravail::class, mappedBy: 'dossier', cascade: ['remove'])]
    #[ORM\OrderBy(['date' => 'DESC'])]
    private Collection $accidents;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->visites = new ArrayCollection();
        $this->vaccinations = new ArrayCollection();
        $this->accidents = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNumeroDossier(): ?string
    {
        return $this->numeroDossier;
    }

    public function setNumeroDossier(?string $numeroDossier): static
    {
        $this->numeroDossier = $numeroDossier;

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

    public function getGroupeSanguin(): ?string
    {
        return $this->groupeSanguin;
    }

    public function setGroupeSanguin(?string $groupeSanguin): static
    {
        $this->groupeSanguin = $groupeSanguin;

        return $this;
    }

    public function getAllergies(): ?string
    {
        return $this->allergies;
    }

    public function setAllergies(?string $allergies): static
    {
        $this->allergies = $allergies === null ? null : mb_strtoupper(trim($allergies), 'UTF-8');

        return $this;
    }

    public function getAntecedents(): ?string
    {
        return $this->antecedents;
    }

    public function setAntecedents(?string $antecedents): static
    {
        $this->antecedents = $antecedents === null ? null : mb_strtoupper(trim($antecedents), 'UTF-8');

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

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

    /** @return Collection<int, VisiteMedicale> */
    public function getVisites(): Collection
    {
        return $this->visites;
    }

    /** @return Collection<int, Vaccination> */
    public function getVaccinations(): Collection
    {
        return $this->vaccinations;
    }

    /** @return Collection<int, AccidentTravail> */
    public function getAccidents(): Collection
    {
        return $this->accidents;
    }

    /** Derniere visite medicale enregistree, null si aucune. */
    public function getDerniereVisite(): ?VisiteMedicale
    {
        $derniere = null;
        foreach ($this->visites as $visite) {
            if ($derniere === null || $visite->getDate() > $derniere->getDate()) {
                $derniere = $visite;
            }
        }

        return $derniere;
    }

    public function isVisiteARenouveler(int $joursAvant = 30): bool
    {
        $prochaine = $this->getDerniereVisite()?->getDateProchaine();
        if ($prochaine === null) {
            return false;
        }

        $limite = (new \DateTimeImmutable('today'))->modify('+' . $joursAvant . ' days');

        return \DateTimeImmutable::createFromInterface($prochaine) <= $limite;
    }
}
