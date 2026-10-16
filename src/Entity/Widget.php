<?php

namespace App\Entity;

use App\Repository\WidgetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: WidgetRepository::class)]
class Widget
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $libelle = null;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(length: 255)]
    private ?string $reference = null;

    /**
     * @var Collection<Uuid, Groupe>
     */
    #[ORM\ManyToMany(targetEntity: Groupe::class, inversedBy: 'widgets')]
    private Collection $code_groupe;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $render_type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $app_js = null;

    /**
     * @var Collection<int, CAppTdb>
     */
    #[ORM\OneToMany(targetEntity: CAppTdb::class, mappedBy: 'code_gadget')]
    private Collection $cAppTdbs;


    public function __construct()
    {
        $this->code_groupe = new ArrayCollection();
        $this->cAppTdbs = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * @return Collection<Uuid, Groupe>
     */
    public function getCodeGroupe(): Collection
    {
        return $this->code_groupe;
    }

    public function addCodeGroupe(Groupe $codeGroupe): static
    {
        if (!$this->code_groupe->contains($codeGroupe)) {
            $this->code_groupe->add($codeGroupe);
        }

        return $this;
    }

    public function removeCodeGroupe(Groupe $codeGroupe): static
    {
        $this->code_groupe->removeElement($codeGroupe);

        return $this;
    }

    public function getRenderType(): ?string
    {
        return $this->render_type;
    }

    public function setRenderType(?string $render_type): static
    {
        $this->render_type = $render_type;

        return $this;
    }

    public function getAppJs(): ?string
    {
        return $this->app_js;
    }

    public function setAppJs(?string $app_js): static
    {
        $this->app_js = $app_js;

        return $this;
    }

    /**
     * @return Collection<int, CAppTdb>
     */
    public function getCAppTdbs(): Collection
    {
        return $this->cAppTdbs;
    }

    public function addCAppTdb(CAppTdb $cAppTdb): static
    {
        if (!$this->cAppTdbs->contains($cAppTdb)) {
            $this->cAppTdbs->add($cAppTdb);
            $cAppTdb->setCodeGadget($this);
        }

        return $this;
    }

    public function removeCAppTdb(CAppTdb $cAppTdb): static
    {
        if ($this->cAppTdbs->removeElement($cAppTdb)) {
            // set the owning side to null (unless already changed)
            if ($cAppTdb->getCodeGadget() === $this) {
                $cAppTdb->setCodeGadget(null);
            }
        }

        return $this;
    }
}
