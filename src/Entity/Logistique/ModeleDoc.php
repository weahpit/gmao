<?php

namespace App\Entity\Logistique;

use App\Entity\Production\Produit;
use App\Repository\Logistique\ModeleDocRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'logistique.modele_doc')]
#[ORM\Entity(repositoryClass: ModeleDocRepository::class)]
class ModeleDoc
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nom_modele = null;

    #[ORM\ManyToOne(inversedBy: 'modeleDocs')]
    private ?Produit $code_produit = null;

    #[ORM\ManyToOne(inversedBy: 'modeleDocs')]
    private ?Client $code_client = null;

    #[ORM\Column(nullable: true)]
    private ?bool $standard = null;

    /**
     * @var Collection<Uuid, DetailsModeleDoc>
     */
    #[ORM\OneToMany(targetEntity: DetailsModeleDoc::class, mappedBy: 'code_modele')]
    private Collection $detailsModeleDocs;

    /**
     * @var Collection<Uuid, DocsDossier>
     */
    #[ORM\OneToMany(targetEntity: DocsDossier::class, mappedBy: 'code_modele')]
    private Collection $docsDossiers;

    public function __construct()
    {
        $this->detailsModeleDocs = new ArrayCollection();
        $this->docsDossiers = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNomModele(): ?string
    {
        return $this->nom_modele;
    }

    public function setNomModele(?string $nom_modele): static
    {
        $this->nom_modele = $nom_modele;

        return $this;
    }

    public function getCodeProduit(): ?Produit
    {
        return $this->code_produit;
    }

    public function setCodeProduit(?Produit $code_produit): static
    {
        $this->code_produit = $code_produit;

        return $this;
    }

    public function getCodeClient(): ?Client
    {
        return $this->code_client;
    }

    public function setCodeClient(?Client $code_client): static
    {
        $this->code_client = $code_client;

        return $this;
    }

    public function isStandard(): ?bool
    {
        return $this->standard;
    }

    public function setStandard(?bool $standard): static
    {
        $this->standard = $standard;

        return $this;
    }

    /**
     * @return Collection<Uuid, DetailsModeleDoc>
     */
    public function getDetailsModeleDocs(): Collection
    {
        return $this->detailsModeleDocs;
    }

    public function addDetailsModeleDoc(DetailsModeleDoc $detailsModeleDoc): static
    {
        if (!$this->detailsModeleDocs->contains($detailsModeleDoc)) {
            $this->detailsModeleDocs->add($detailsModeleDoc);
            $detailsModeleDoc->setCodeModele($this);
        }

        return $this;
    }

    public function removeDetailsModeleDoc(DetailsModeleDoc $detailsModeleDoc): static
    {
        if ($this->detailsModeleDocs->removeElement($detailsModeleDoc)) {
            // set the owning side to null (unless already changed)
            if ($detailsModeleDoc->getCodeModele() === $this) {
                $detailsModeleDoc->setCodeModele(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<Uuid, DocsDossier>
     */
    public function getDocsDossiers(): Collection
    {
        return $this->docsDossiers;
    }

    public function addDocsDossier(DocsDossier $docsDossier): static
    {
        if (!$this->docsDossiers->contains($docsDossier)) {
            $this->docsDossiers->add($docsDossier);
            $docsDossier->setCodeModele($this);
        }

        return $this;
    }

    public function removeDocsDossier(DocsDossier $docsDossier): static
    {
        if ($this->docsDossiers->removeElement($docsDossier)) {
            // set the owning side to null (unless already changed)
            if ($docsDossier->getCodeModele() === $this) {
                $docsDossier->setCodeModele(null);
            }
        }

        return $this;
    }
}
