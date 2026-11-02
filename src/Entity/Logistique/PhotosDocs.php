<?php

namespace App\Entity\Logistique;

use App\Repository\Logistique\PhotosDocsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'logistique.photos_docs')]
#[ORM\Entity(repositoryClass: PhotosDocsRepository::class)]
class PhotosDocs
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'PhotosDocs')]
    private ?Dossier $code_dossier = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filename = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fichier = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $extension = null;

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): static
    {
        $this->filename = $filename;

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

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(?string $extension): static
    {
        $this->extension = $extension;

        return $this;
    }
}
