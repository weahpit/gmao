<?php

namespace App\Entity\Rh;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Vaccination enregistree dans le dossier medical (suivi des rappels). */
#[ORM\Entity]
#[ORM\Table(name: 'rh_vaccination')]
class Vaccination
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: DossierMedical::class, inversedBy: 'vaccinations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DossierMedical $dossier = null;

    #[ORM\Column(length: 120)]
    private ?string $vaccin = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $prochainRappel = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lot = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getDossier(): ?DossierMedical
    {
        return $this->dossier;
    }

    public function setDossier(?DossierMedical $dossier): static
    {
        $this->dossier = $dossier;

        return $this;
    }

    public function getVaccin(): ?string
    {
        return $this->vaccin;
    }

    public function setVaccin(?string $vaccin): static
    {
        $this->vaccin = $vaccin === null ? null : mb_strtoupper(trim($vaccin), 'UTF-8');

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): static
    {
        $this->date = $date === null ? null : \DateTime::createFromInterface($date);

        return $this;
    }

    public function getProchainRappel(): ?\DateTimeInterface
    {
        return $this->prochainRappel;
    }

    public function setProchainRappel(?\DateTimeInterface $date): static
    {
        $this->prochainRappel = $date === null ? null : \DateTime::createFromInterface($date);

        return $this;
    }

    public function getLot(): ?string
    {
        return $this->lot;
    }

    public function setLot(?string $lot): static
    {
        $this->lot = $lot;

        return $this;
    }
}
