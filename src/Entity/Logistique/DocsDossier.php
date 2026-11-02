<?php

namespace App\Entity\Logistique;

use App\Repository\Logistique\DocsDossierRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'logistique.doc_dossier')]
#[ORM\Entity(repositoryClass: DocsDossierRepository::class)]
class DocsDossier
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'docsDossiers')]
    private ?ModeleDoc $code_modele = null;

    #[ORM\ManyToOne(inversedBy: 'docsDossiers')]
    private ?Dossier $code_dossier = null;

    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filename = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCodeModele(): ?ModeleDoc
    {
        return $this->code_modele;
    }

    public function setCodeModele(?ModeleDoc $code_modele): static
    {
        $this->code_modele = $code_modele;

        return $this;
    }

    public function getCodeDossier(): ?Dossier
    {
        return $this->code_dossier;
    }

    public function setCodeDossier(?Dossier $code_dossier): static
    {
        $this->code_dossier = $code_dossier;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }
}
