<?php

namespace App\Entity\Rh;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Personne qui postule a une ou plusieurs offres. */
#[ORM\Entity]
#[ORM\Table(name: 'rh_candidat')]
#[ORM\Index(columns: ['nom', 'prenoms'], name: 'idx_candidat_nom')]
class Candidat
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $prenoms = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cv = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $diplomes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $experiences = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Candidature> */
    #[ORM\OneToMany(targetEntity: Candidature::class, mappedBy: 'candidat')]
    private Collection $candidatures;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->candidatures = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom !== null ? mb_strtoupper($nom) : null;

        return $this;
    }

    public function getPrenoms(): ?string
    {
        return $this->prenoms;
    }

    public function setPrenoms(?string $prenoms): static
    {
        $this->prenoms = $prenoms === null ? null : mb_strtoupper(trim($prenoms), 'UTF-8');

        return $this;
    }

    public function getNomComplet(): string
    {
        return trim(($this->nom ?? '') . ' ' . ($this->prenoms ?? ''));
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

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getCv(): ?string
    {
        return $this->cv;
    }

    public function setCv(?string $cv): static
    {
        $this->cv = $cv;

        return $this;
    }

    public function getDiplomes(): ?string
    {
        return $this->diplomes;
    }

    public function setDiplomes(?string $diplomes): static
    {
        $this->diplomes = $diplomes === null ? null : mb_strtoupper(trim($diplomes), 'UTF-8');

        return $this;
    }

    public function getExperiences(): ?string
    {
        return $this->experiences;
    }

    public function setExperiences(?string $experiences): static
    {
        $this->experiences = $experiences === null ? null : mb_strtoupper(trim($experiences), 'UTF-8');

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Candidature> */
    public function getCandidatures(): Collection
    {
        return $this->candidatures;
    }

    #[ORM\Column(length: 150, nullable: true)] private ?string $posteRecherche = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $observationsAccueil = null;
    #[ORM\Column(type: 'boolean', options: ['default'=>false])] private bool $dossierSpontane = false;
    public function getPosteRecherche(): ?string { return $this->posteRecherche; }
    public function setPosteRecherche(?string $v): static { $this->posteRecherche=$v;return $this; }
    public function getObservationsAccueil(): ?string { return $this->observationsAccueil; }
    public function setObservationsAccueil(?string $v): static { $this->observationsAccueil=$v;return $this; }
    public function isDossierSpontane(): bool { return $this->dossierSpontane; }
    public function setDossierSpontane(bool $v): static { $this->dossierSpontane=$v;return $this; }

}
