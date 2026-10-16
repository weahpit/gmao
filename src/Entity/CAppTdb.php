<?php

namespace App\Entity;

use App\Repository\CAppTdbRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CAppTdbRepository::class)]
class CAppTdb
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, Widget>
     */
    #[ORM\ManyToMany(targetEntity: Widget::class, inversedBy: 'cAppTdbs')]
    private Collection $code_widget;

    #[ORM\Column(nullable: true)]
    private ?int $ordre = null;

    #[ORM\ManyToOne(inversedBy: 'cAppTdbs')]
    private ?user $code_user = null;

    public function __construct()
    {
        $this->code_widget = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Widget>
     */
    public function getCodeWidget(): Collection
    {
        return $this->code_widget;
    }

    public function addCodeWidget(Widget $codeWidget): static
    {
        if (!$this->code_widget->contains($codeWidget)) {
            $this->code_widget->add($codeWidget);
        }

        return $this;
    }

    public function removeCodeWidget(Widget $codeWidget): static
    {
        $this->code_widget->removeElement($codeWidget);

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(?int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getCodeUser(): ?user
    {
        return $this->code_user;
    }

    public function setCodeUser(?user $code_user): static
    {
        $this->code_user = $code_user;

        return $this;
    }
}
