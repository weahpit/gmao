<?php

declare(strict_types=1);

namespace App\Entity\Logistique;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'lg_suivi_embarquement')]
class SuiviEmbarquement
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: Dossier::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'RESTRICT')]
    private ?Dossier $dossier = null;

    #[ORM\Column(type: 'json')]
    private array $conteneurs = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rapportEmpotage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fichierRapport = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomRapport = null;

    #[ORM\Column(type: 'json')]
    private array $certificats = [];

    #[ORM\Column(type: 'json')]
    private array $traitements = [];

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $contratAfricao = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $portDestination = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $typeMontant = null;

    #[ORM\Column(type: 'decimal', precision: 16, scale: 2, nullable: true)]
    private ?string $montant = null;

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $formulesPreparees = [];

    public function getFormulesPreparees(): array { return $this->formulesPreparees; }
    public function setFormulesPreparees(array $v): static { $this->formulesPreparees = $v; return $this; }

    public function __construct() { $this->id = Uuid::v4(); }
    public function getId(): Uuid { return $this->id; }
    public function getDossier(): ?Dossier { return $this->dossier; }
    public function setDossier(?Dossier $valeur): static { $this->dossier = $valeur; return $this; }
    public function getConteneurs(): array { return $this->conteneurs; }
    public function setConteneurs(array $valeur): static { $this->conteneurs = $valeur; return $this; }
    public function getRapportEmpotage(): ?string { return $this->rapportEmpotage; }
    public function setRapportEmpotage(?string $valeur): static { $this->rapportEmpotage = $valeur; return $this; }
    public function getFichierRapport(): ?string { return $this->fichierRapport; }
    public function setFichierRapport(?string $valeur): static { $this->fichierRapport = $valeur; return $this; }
    public function getNomRapport(): ?string { return $this->nomRapport; }
    public function setNomRapport(?string $valeur): static { $this->nomRapport = $valeur; return $this; }
    public function getCertificats(): array { return $this->certificats; }
    public function setCertificats(array $valeur): static { $this->certificats = $valeur; return $this; }
    public function getTraitements(): array { return $this->traitements; }
    public function setTraitements(array $valeur): static { $this->traitements = $valeur; return $this; }
    public function getContratAfricao(): ?string { return $this->contratAfricao; }
    public function setContratAfricao(?string $valeur): static { $this->contratAfricao = $valeur; return $this; }
    public function getPortDestination(): ?string { return $this->portDestination; }
    public function setPortDestination(?string $valeur): static { $this->portDestination = $valeur; return $this; }
    public function getTypeMontant(): ?string { return $this->typeMontant; }
    public function setTypeMontant(?string $valeur): static { $this->typeMontant = $valeur; return $this; }
    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(?string $valeur): static { $this->montant = $valeur; return $this; }

    #[ORM\Column(type: 'json', options: ['default' => '[]'])] private array $photosEmpotage = [];
    public function getPhotosEmpotage(): array { return $this->photosEmpotage; }
    public function setPhotosEmpotage(array $v): static { $this->photosEmpotage = $v; return $this; }

}
