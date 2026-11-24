<?php

declare(strict_types=1);

namespace App\Entity\Logistique;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'lg_formule_export')]
class FormuleExport
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: CvExecution::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?CvExecution $execution = null;

    #[ORM\ManyToOne(targetEntity: Dossier::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Dossier $dossier = null;

    #[ORM\Column(length: 60, unique: true)]
    private ?string $numero = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 3)]
    private string $volume = '0.000';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 3)]
    private string $utilise = '0.000';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fichier = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomOriginal = null;

    public function __construct() { $this->id = Uuid::v4(); }
    public function getId(): Uuid { return $this->id; }
    public function getExecution(): ?CvExecution { return $this->execution; }
    public function setExecution(?CvExecution $valeur): static { $this->execution = $valeur; return $this; }
    public function getDossier(): ?Dossier { return $this->dossier; }
    public function setDossier(?Dossier $valeur): static { $this->dossier = $valeur; return $this; }
    public function getNumero(): ?string { return $this->numero; }
    public function setNumero(?string $valeur): static { $this->numero = $valeur; return $this; }
    public function getVolume(): string { return $this->volume; }
    public function setVolume(string $valeur): static { $this->volume = $valeur; return $this; }
    public function getUtilise(): string { return $this->utilise; }
    public function setUtilise(string $valeur): static { $this->utilise = $valeur; return $this; }
    public function getFichier(): ?string { return $this->fichier; }
    public function setFichier(?string $valeur): static { $this->fichier = $valeur; return $this; }
    public function getNomOriginal(): ?string { return $this->nomOriginal; }
    public function setNomOriginal(?string $valeur): static { $this->nomOriginal = $valeur; return $this; }
}
