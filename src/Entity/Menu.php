<?php

namespace App\Entity;

use App\Repository\MenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'public.menu')]
#[ORM\Entity(repositoryClass: MenuRepository::class)]
class Menu
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom_menu = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $icon_menu = null;

    #[ORM\Column(nullable: true)]
    private ?int $parent_menu = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $classname_menu = null;

    /**
     * @var Collection<int, Permission>
     */
    #[ORM\OneToMany(targetEntity: Permission::class, mappedBy: 'code_menu')]
    private Collection $permissions;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'menus')]
    private ?self $parent_m = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent_m')]
    private Collection $menus;

    public function __construct()
    {
        $this->permissions = new ArrayCollection();
        $this->menus = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNomMenu(): ?string
    {
        return $this->nom_menu;
    }

    public function setNomMenu(string $nom_menu): static
    {
        $this->nom_menu = $nom_menu;

        return $this;
    }

    public function getIconMenu(): ?string
    {
        return $this->icon_menu;
    }

    public function setIconMenu(?string $icon_menu): static
    {
        $this->icon_menu = $icon_menu;

        return $this;
    }

    public function getParentMenu(): ?int
    {
        return $this->parent_menu;
    }

    public function setParentMenu(?int $parent_menu): static
    {
        $this->parent_menu = $parent_menu;

        return $this;
    }


    public function getClassnameMenu(): ?string
    {
        return $this->classname_menu;
    }

    public function setClassnameMenu(string $classname_menu): static
    {
        $this->classname_menu = $classname_menu;

        return $this;
    }

    public function __toString(): string
    {
       return $this->nom_menu;
    }

    /**
     * @return Collection<int, Permission>
     */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(Permission $permission): static
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
            $permission->setCodeMenu($this);
        }

        return $this;
    }

    public function removePermission(Permission $permission): static
    {
        if ($this->permissions->removeElement($permission)) {
            // set the owning side to null (unless already changed)
            if ($permission->getCodeMenu() === $this) {
                $permission->setCodeMenu(null);
            }
        }

        return $this;
    }

    public function getParentM(): ?self
    {
        return $this->parent_m;
    }

    public function setParentM(?self $parent_m): static
    {
        $this->parent_m = $parent_m;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getMenus(): Collection
    {
        return $this->menus;
    }

    public function addMenu(self $menu): static
    {
        if (!$this->menus->contains($menu)) {
            $this->menus->add($menu);
            $menu->setParentM($this);
        }

        return $this;
    }

    public function removeMenu(self $menu): static
    {
        if ($this->menus->removeElement($menu)) {
            // set the owning side to null (unless already changed)
            if ($menu->getParentM() === $this) {
                $menu->setParentM(null);
            }
        }

        return $this;
    }
}
