<?php

namespace App\Entity\Logistique;

use App\Entity\Production\Produit;
use App\Repository\Logistique\DocTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'logistique.doc_type')]
#[ORM\Entity(repositoryClass: DocTypeRepository::class)]
class DocType
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * @var Collection<Uuid, Documentation>
     */
    #[ORM\OneToMany(targetEntity: Documentation::class, mappedBy: 'code_type_doc')]
    private Collection $documentations;

    /**
     * @var Collection<Uuid, Produit>
     */
    #[ORM\ManyToMany(targetEntity: Produit::class, inversedBy: 'docTypes')]
    private Collection $code_produit;

    /**
     * @var Collection<Uuid, DetailsModeleDoc>
     */
    #[ORM\OneToMany(targetEntity: DetailsModeleDoc::class, mappedBy: 'code_doc')]
    private Collection $detailsModeleDocs;


    public function __construct()
    {
        $this->documentations = new ArrayCollection();
        $this->code_produit = new ArrayCollection();
        $this->detailsModeleDocs = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<Uuid, Documentation>
     */
    public function getDocumentations(): Collection
    {
        return $this->documentations;
    }

    public function addDocumentation(Documentation $documentation): static
    {
        if (!$this->documentations->contains($documentation)) {
            $this->documentations->add($documentation);
            $documentation->setCodeTypeDoc($this);
        }

        return $this;
    }

    public function removeDocumentation(Documentation $documentation): static
    {
        if ($this->documentations->removeElement($documentation)) {
            // set the owning side to null (unless already changed)
            if ($documentation->getCodeTypeDoc() === $this) {
                $documentation->setCodeTypeDoc(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<Uuid, Produit>
     */
    public function getCodeProduit(): Collection
    {
        return $this->code_produit;
    }

    public function addCodeProduit(Produit $codeProduit): static
    {
        if (!$this->code_produit->contains($codeProduit)) {
            $this->code_produit->add($codeProduit);
        }

        return $this;
    }

    public function removeCodeProduit(Produit $codeProduit): static
    {
        $this->code_produit->removeElement($codeProduit);

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
            $detailsModeleDoc->setCodeDoc($this);
        }

        return $this;
    }

    public function removeDetailsModeleDoc(DetailsModeleDoc $detailsModeleDoc): static
    {
        if ($this->detailsModeleDocs->removeElement($detailsModeleDoc)) {
            // set the owning side to null (unless already changed)
            if ($detailsModeleDoc->getCodeDoc() === $this) {
                $detailsModeleDoc->setCodeDoc(null);
            }
        }

        return $this;
    }

}
