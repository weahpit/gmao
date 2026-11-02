<?php

namespace App\Entity\Production;

use App\Entity\Logistique\DocType;
use App\Entity\Logistique\Dossier;
use App\Entity\Logistique\ModeleDoc;
use App\Entity\Logistique\Proprietaire;
use App\Repository\Production\ProduitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'production.produit')]
#[ORM\Entity(repositoryClass: ProduitRepository::class)]
class Produit
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100)]
    private ?string $libelle = null;

    /**
     * @var Collection<Uuid, Dossier>
     */
    #[ORM\OneToMany(targetEntity: Dossier::class, mappedBy: 'produit')]
    private Collection $dossiers;

    #[ORM\ManyToOne(inversedBy: 'produits')]
    private ?Proprietaire $code_proprio = null;

    /**
     * @var Collection<Uuid, DocType>
     */
    #[ORM\ManyToMany(targetEntity: DocType::class, mappedBy: 'code_produit')]
    private Collection $docTypes;

    /**
     * @var Collection<Uuid, ModeleDoc>
     */
    #[ORM\OneToMany(targetEntity: ModeleDoc::class, mappedBy: 'code_produit')]
    private Collection $modeleDocs;

    /**
     * @var Collection<Uuid, Lot>
     */
    #[ORM\OneToMany(targetEntity: Lot::class, mappedBy: 'code_produit')]
    private Collection $lots;

    public function __construct()
    {
        $this->dossiers = new ArrayCollection();
        $this->docTypes = new ArrayCollection();
        $this->modeleDocs = new ArrayCollection();
        $this->lots = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

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
            $dossier->setProduit($this);
        }

        return $this;
    }

    public function removeDossier(Dossier $dossier): static
    {
        if ($this->dossiers->removeElement($dossier)) {
            // set the owning side to null (unless already changed)
            if ($dossier->getProduit() === $this) {
                $dossier->setProduit(null);
            }
        }

        return $this;
    }

    public function getCodeProprio(): ?Proprietaire
    {
        return $this->code_proprio;
    }

    public function setCodeProprio(?Proprietaire $code_proprio): static
    {
        $this->code_proprio = $code_proprio;

        return $this;
    }

    /**
     * @return Collection<Uuid, DocType>
     */
    public function getDocTypes(): Collection
    {
        return $this->docTypes;
    }

    public function addDocType(DocType $docType): static
    {
        if (!$this->docTypes->contains($docType)) {
            $this->docTypes->add($docType);
            $docType->addCodeProduit($this);
        }

        return $this;
    }

    public function removeDocType(DocType $docType): static
    {
        if ($this->docTypes->removeElement($docType)) {
            $docType->removeCodeProduit($this);
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
            $modeleDoc->setCodeProduit($this);
        }

        return $this;
    }

    public function removeModeleDoc(ModeleDoc $modeleDoc): static
    {
        if ($this->modeleDocs->removeElement($modeleDoc)) {
            // set the owning side to null (unless already changed)
            if ($modeleDoc->getCodeProduit() === $this) {
                $modeleDoc->setCodeProduit(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<Uuid, Lot>
     */
    public function getLots(): Collection
    {
        return $this->lots;
    }

    public function addLot(Lot $lot): static
    {
        if (!$this->lots->contains($lot)) {
            $this->lots->add($lot);
            $lot->setCodeProduit($this);
        }

        return $this;
    }

    public function removeLot(Lot $lot): static
    {
        if ($this->lots->removeElement($lot)) {
            // set the owning side to null (unless already changed)
            if ($lot->getCodeProduit() === $this) {
                $lot->setCodeProduit(null);
            }
        }

        return $this;
    }
}
