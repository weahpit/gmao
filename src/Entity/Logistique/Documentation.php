<?php

namespace App\Entity\Logistique;

use App\Repository\Logistique\DocumentationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
#[ORM\Table(name: 'logistique.documentation')]
#[ORM\Entity(repositoryClass: DocumentationRepository::class)]
class Documentation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'documentations')]
    private ?Dossier $code_dossier = null;

    #[ORM\ManyToOne(inversedBy: 'documentations')]
    private ?DocType $code_type_doc = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filename = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $created_by = null;

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

    public function getCodeTypeDoc(): ?DocType
    {
        return $this->code_type_doc;
    }

    public function setCodeTypeDoc(?DocType $code_type_doc): static
    {
        $this->code_type_doc = $code_type_doc;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->created_by;
    }

    public function setCreatedBy(?string $created_by): static
    {
        $this->created_by = $created_by;

        return $this;
    }
}
