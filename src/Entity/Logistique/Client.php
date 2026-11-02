<?php

namespace App\Entity\Logistique;

use App\Repository\Logistique\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'logistique.client')]
#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom_client = null;

    /**
     * @var Collection<Uuid, Dossier>
     */
    #[ORM\OneToMany(targetEntity: Dossier::class, mappedBy: 'client')]
    private Collection $dossiers;

    /**
     * @var Collection<Uuid, ModeleDoc>
     */
    #[ORM\OneToMany(targetEntity: ModeleDoc::class, mappedBy: 'code_client')]
    private Collection $modeleDocs;

    public function __construct()
    {
        $this->dossiers = new ArrayCollection();
        $this->modeleDocs = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNomClient(): ?string
    {
        return $this->nom_client;
    }

    public function setNomClient(string $nom_client): static
    {
        $this->nom_client = $nom_client;

        return $this;
    }

    /**
     * @return Collection<Uuid, Dossier>
     */
    public function getDossiers(): Collection
    {
        return $this->dossiers;
    }

    public function addDossier(Dossier $dossier): static
    {
        if (!$this->dossiers->contains($dossier)) {
            $this->dossiers->add($dossier);
            $dossier->setClient($this);
        }

        return $this;
    }

    public function removeDossier(Dossier $dossier): static
    {
        if ($this->dossiers->removeElement($dossier)) {
            // set the owning side to null (unless already changed)
            if ($dossier->getClient() === $this) {
                $dossier->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<Uuid, ModeleDoc>
     */
    public function getModeleDocs(): Collection
    {
        return $this->modeleDocs;
    }

    public function addModeleDoc(ModeleDoc $modeleDoc): static
    {
        if (!$this->modeleDocs->contains($modeleDoc)) {
            $this->modeleDocs->add($modeleDoc);
            $modeleDoc->setCodeClient($this);
        }

        return $this;
    }

    public function removeModeleDoc(ModeleDoc $modeleDoc): static
    {
        if ($this->modeleDocs->removeElement($modeleDoc)) {
            // set the owning side to null (unless already changed)
            if ($modeleDoc->getCodeClient() === $this) {
                $modeleDoc->setCodeClient(null);
            }
        }

        return $this;
    }
}
