<?php

namespace App\Entity\Logistique;

use App\Repository\Logistique\TcRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'logistique.tc')]
#[ORM\Entity(repositoryClass: TcRepository::class)]
class Tc
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 20)]
    private ?string $type_tc = null;

    #[ORM\Column(length: 50)]
    private ?string $numero_tc = null;

    #[ORM\Column]
    private ?int $pn_th = null;

    #[ORM\Column(nullable: true)]
    private ?int $pn_reel = null;

    /**
     * @var Collection<Uuid, Dossier>
     */
    #[ORM\ManyToMany(targetEntity: Dossier::class, inversedBy: 'tcs')]
    private Collection $code_dossier;

    public function __construct()
    {
        $this->code_dossier = new ArrayCollection();
    }


    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTypeTc(): ?string
    {
        return $this->type_tc;
    }

    public function setTypeTc(string $type_tc): static
    {
        $this->type_tc = $type_tc;

        return $this;
    }

    public function getNumeroTc(): ?string
    {
        return $this->numero_tc;
    }

    public function setNumeroTc(string $numero_tc): static
    {
        $this->numero_tc = $numero_tc;

        return $this;
    }

    public function getPnTh(): ?int
    {
        return $this->pn_th;
    }

    public function setPnTh(int $pn_th): static
    {
        $this->pn_th = $pn_th;

        return $this;
    }

    public function getPnReel(): ?int
    {
        return $this->pn_reel;
    }

    public function setPnReel(?int $pn_reel): static
    {
        $this->pn_reel = $pn_reel;

        return $this;
    }

    /**
     * @return Collection<Uuid, Dossier>
     */
    public function getCodeDossier(): Collection
    {
        return $this->code_dossier;
    }

    public function addCodeDossier(Dossier $codeDossier): static
    {
        if (!$this->code_dossier->contains($codeDossier)) {
            $this->code_dossier->add($codeDossier);
        }

        return $this;
    }

    public function removeCodeDossier(Dossier $codeDossier): static
    {
        $this->code_dossier->removeElement($codeDossier);

        return $this;
    }
}
