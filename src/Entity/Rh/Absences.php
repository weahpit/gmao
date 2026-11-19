<?php

namespace App\Entity\Rh;

use App\Repository\Rh\AbsencesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rh_absences')]
#[ORM\Entity(repositoryClass: AbsencesRepository::class)]
class Absences
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'absences')]
    private ?Employe $code_employe = null;

    #[ORM\Column]
    private ?\DateTime $date_debut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $date_fin = null;

    #[ORM\Column]
    private ?int $nb_jours = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $type_absence = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $paye = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fichier = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $extension = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodeEmploye(): ?Employe
    {
        return $this->code_employe;
    }

    public function setCodeEmploye(?Employe $code_employe): static
    {
        $this->code_employe = $code_employe;

        return $this;
    }

    public function getDateDebut(): ?\DateTime
    {
        return $this->date_debut;
    }

    public function setDateDebut(\DateTime $date_debut): static
    {
        $this->date_debut = $date_debut;

        return $this;
    }

    public function getDateFin(): ?\DateTime
    {
        return $this->date_fin;
    }

    public function setDateFin(?\DateTime $date_fin): static
    {
        $this->date_fin = $date_fin;

        return $this;
    }

    public function getNbJours(): ?int
    {
        return $this->nb_jours;
    }

    public function setNbJours(int $nb_jours): static
    {
        $this->nb_jours = $nb_jours;

        return $this;
    }

    public function getTypeAbsence(): ?string
    {
        return $this->type_absence;
    }

    public function setTypeAbsence(?string $type_absence): static
    {
        $this->type_absence = $type_absence;

        return $this;
    }

    public function getPaye(): ?string
    {
        return $this->paye;
    }

    public function setPaye(?string $paye): static
    {
        $this->paye = $paye;

        return $this;
    }

    public function getFichier(): ?string
    {
        return $this->fichier;
    }

    public function setFichier(?string $fichier): static
    {
        $this->fichier = $fichier;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getExtension(): ?string
    {
        return $this->extension;
    }

    /**
     * @param string|null $extension
     */
    public function setExtension(?string $extension): void
    {
        $this->extension = $extension;
    }


}
