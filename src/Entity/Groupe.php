<?php

namespace App\Entity;

use App\Repository\GroupeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'public.groupe')]
#[ORM\Entity(repositoryClass: GroupeRepository::class)]
class Groupe
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom_groupe = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $craeted_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $created_by = null;

    /**
     * @var Collection<Uuid, Permission>
     */
    #[ORM\OneToMany(targetEntity: Permission::class, mappedBy: 'code_groupe')]
    private Collection $permissions;

    public function __construct()
    {
        $this->permissions = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNomGroupe(): ?string
    {
        return $this->nom_groupe;
    }

    public function setNomGroupe(string $nom_groupe): static
    {
        $this->nom_groupe = $nom_groupe;

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom_groupe;
    }

    public function getCraetedAt(): ?\DateTimeInterface
    {
        return $this->craeted_at;
    }

    public function setCraetedAt(?\DateTimeInterface $craeted_at): static
    {
        $this->craeted_at = $craeted_at;

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

    /**
     * @return Collection<Uuid, Permission>
     */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(Permission $permission): static
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
            $permission->setCodeGroupe($this);
        }

        return $this;
    }

    public function removePermission(Permission $permission): static
    {
        if ($this->permissions->removeElement($permission)) {
            // set the owning side to null (unless already changed)
            if ($permission->getCodeGroupe() === $this) {
                $permission->setCodeGroupe(null);
            }
        }

        return $this;
    }
}
