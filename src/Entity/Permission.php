<?php

namespace App\Entity;

use App\Repository\PermissionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PermissionRepository::class)]
class Permission
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'permissions')]
    private ?Menu $code_menu = null;

    #[ORM\ManyToOne(inversedBy: 'permissions')]
    private ?Groupe $code_groupe = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(length: 255)]
    private ?string $created_by = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCodeMenu(): ?Menu
    {
        return $this->code_menu;
    }

    public function setCodeMenu(?Menu $code_menu): static
    {
        $this->code_menu = $code_menu;

        return $this;
    }

    public function getCodeGroupe(): ?Groupe
    {
        return $this->code_groupe;
    }

    public function setCodeGroupe(?Groupe $code_groupe): static
    {
        $this->code_groupe = $code_groupe;

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

    public function setCreatedBy(string $created_by): static
    {
        $this->created_by = $created_by;

        return $this;
    }
}
