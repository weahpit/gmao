<?php

namespace App\Entity\Logistique;

use App\Entity\Production\Lot;
use App\Entity\Production\Produit;
use App\Repository\Logistique\DossierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'logistique.dossier')]
#[ORM\Entity(repositoryClass: DossierRepository::class)]
class Dossier
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 20)]
    private ?string $numero_ot = null;

    #[ORM\Column]
    private ?Uuid $tonnage = null;

    #[ORM\Column(nullable: true)]
    private ?bool $formule = null;

    #[ORM\Column(nullable: true)]
    private ?bool $cheque = null;

    #[ORM\Column]
    private ?Uuid $nb_tc = null;

    #[ORM\Column(nullable: true)]
    private ?bool $tc_selectionnne = null;

    #[ORM\Column(nullable: true)]
    private ?bool $tc_positionne = null;

    #[ORM\Column(nullable: true)]
    private ?bool $tc_fumigue = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_empotage = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_relevage = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $eta = null;

    #[ORM\Column(nullable: true)]
    private ?float $poids_sigam = null;

    #[ORM\Column(nullable: true)]
    private ?float $poids_vgm = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $packing_list = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $certificat_poids = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $coa = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $certificat_phyto = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $co = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $certificat_halah = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $certificat_conformite = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $certificat_hygienne = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $certificat_fumigation = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $remise_doc = null;

    #[ORM\ManyToOne(inversedBy: 'dossiers')]
    private ?Produit $produit = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $cv_deblocage = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $cv_execution = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $soutien = null;

    #[ORM\Column(nullable: true)]
    private ?Uuid $reversement = null;

    #[ORM\ManyToOne(inversedBy: 'dossiers')]
    private ?Client $client = null;

    #[ORM\ManyToOne(inversedBy: 'dossiers')]
    private ?Destination $destination = null;

    #[ORM\ManyToOne(inversedBy: 'dossiers')]
    private ?Proprietaire $proprietaire = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $created_by = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $updated_by = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\ManyToOne(inversedBy: 'dossiers')]
    private ?Navire $code_navire = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_embarquement = null;

    #[ORM\Column(nullable: true)]
    private ?bool $etiquette = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $draft = null;

    /**
     * @var Collection<Uuid, Lot>
     */
    #[ORM\ManyToMany(targetEntity: Lot::class, mappedBy: 'code_dossier')]
    private Collection $lots;

    /**
     * @var Collection<Uuid, Tc>
     */
    #[ORM\ManyToMany(targetEntity: Tc::class, mappedBy: 'code_dossier')]
    private Collection $tcs;

    /**
     * @var Collection<Uuid, Documentation>
     */
    #[ORM\OneToMany(targetEntity: Documentation::class, mappedBy: 'code_dossier')]
    private Collection $documentations;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filename = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $remarques = null;

    /**
     * @var Collection<Uuid, DocsDossier>
     */
    #[ORM\OneToMany(targetEntity: DocsDossier::class, mappedBy: 'code_dossier')]
    private Collection $docsDossiers;

    /**
     * @var Collection<Uuid, PhotosDocs>
     */
    #[ORM\OneToMany(targetEntity: PhotosDocs::class, mappedBy: 'code_dossier')]
    private Collection $PhotosDocs;

    #[ORM\Column(nullable: true)]
    private ?bool $cloture = null;

    public function __construct()
    {
        $this->lots = new ArrayCollection();
        $this->tcs = new ArrayCollection();
        $this->documentations = new ArrayCollection();
        $this->docsDossiers = new ArrayCollection();
        $this->PhotosDocs = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNumeroOt(): ?string
    {
        return $this->numero_ot;
    }

    public function setNumeroOt(string $numero_ot): static
    {
        $this->numero_ot = $numero_ot;

        return $this;
    }

    public function getTonnage(): ?Uuid
    {
        return $this->tonnage;
    }

    public function setTonnage(int $tonnage): static
    {
        $this->tonnage = $tonnage;

        return $this;
    }

    public function isFormule(): ?bool
    {
        return $this->formule;
    }

    public function setFormule(?bool $formule): static
    {
        $this->formule = $formule;

        return $this;
    }

    public function isCheque(): ?bool
    {
        return $this->cheque;
    }

    public function setCheque(?bool $cheque): static
    {
        $this->cheque = $cheque;

        return $this;
    }

    public function getNbTc(): ?Uuid
    {
        return $this->nb_tc;
    }

    public function setNbTc(int $nb_tc): static
    {
        $this->nb_tc = $nb_tc;

        return $this;
    }

    public function isTcSelectionnne(): ?bool
    {
        return $this->tc_selectionnne;
    }

    public function setTcSelectionnne(?bool $tc_selectionnne): static
    {
        $this->tc_selectionnne = $tc_selectionnne;

        return $this;
    }

    public function isTcPositionne(): ?bool
    {
        return $this->tc_positionne;
    }

    public function setTcPositionne(?bool $tc_positionne): static
    {
        $this->tc_positionne = $tc_positionne;

        return $this;
    }

    public function isTcFumigue(): ?bool
    {
        return $this->tc_fumigue;
    }

    public function setTcFumigue(?bool $tc_fumigue): static
    {
        $this->tc_fumigue = $tc_fumigue;

        return $this;
    }

    public function getDateEmpotage(): ?\DateTime
    {
        return $this->date_empotage;
    }

    public function setDateEmpotage(?\DateTime $date_empotage): static
    {
        $this->date_empotage = $date_empotage;

        return $this;
    }

    public function getDateRelevage(): ?\DateTime
    {
        return $this->date_relevage;
    }

    public function setDateRelevage(?\DateTime $date_relevage): static
    {
        $this->date_relevage = $date_relevage;

        return $this;
    }

    public function getEta(): ?\DateTime
    {
        return $this->eta;
    }

    public function setEta(?\DateTime $eta): static
    {
        $this->eta = $eta;

        return $this;
    }

    public function getPoidsSigam(): ?float
    {
        return $this->poids_sigam;
    }

    public function setPoidsSigam(?float $poids_sigam): static
    {
        $this->poids_sigam = $poids_sigam;

        return $this;
    }

    public function getPoidsVgm(): ?float
    {
        return $this->poids_vgm;
    }

    public function setPoidsVgm(?float $poids_vgm): static
    {
        $this->poids_vgm = $poids_vgm;

        return $this;
    }

    public function getPackingList(): ?string
    {
        return $this->packing_list;
    }

    public function setPackingList(?string $packing_list): static
    {
        $this->packing_list = $packing_list;

        return $this;
    }

    public function getCertificatPoids(): ?string
    {
        return $this->certificat_poids;
    }

    public function setCertificatPoids(?string $certificat_poids): static
    {
        $this->certificat_poids = $certificat_poids;

        return $this;
    }

    public function getCoa(): ?string
    {
        return $this->coa;
    }

    public function setCoa(?string $coa): static
    {
        $this->coa = $coa;

        return $this;
    }

    public function getCertificatPhyto(): ?string
    {
        return $this->certificat_phyto;
    }

    public function setCertificatPhyto(?string $certificat_phyto): static
    {
        $this->certificat_phyto = $certificat_phyto;

        return $this;
    }

    public function getCo(): ?string
    {
        return $this->co;
    }

    public function setCo(?string $co): static
    {
        $this->co = $co;

        return $this;
    }

    public function getCertificatHalah(): ?string
    {
        return $this->certificat_halah;
    }

    public function setCertificatHalah(?string $certificat_halah): static
    {
        $this->certificat_halah = $certificat_halah;

        return $this;
    }

    public function getCertificatConformite(): ?string
    {
        return $this->certificat_conformite;
    }

    public function setCertificatConformite(?string $certificat_conformite): static
    {
        $this->certificat_conformite = $certificat_conformite;

        return $this;
    }

    public function getCertificatHygienne(): ?string
    {
        return $this->certificat_hygienne;
    }

    public function setCertificatHygienne(?string $certificat_hygienne): static
    {
        $this->certificat_hygienne = $certificat_hygienne;

        return $this;
    }

    public function getCertificatFumigation(): ?string
    {
        return $this->certificat_fumigation;
    }

    public function setCertificatFumigation(?string $certificat_fumigation): static
    {
        $this->certificat_fumigation = $certificat_fumigation;

        return $this;
    }

    public function getRemiseDoc(): ?\DateTime
    {
        return $this->remise_doc;
    }

    public function setRemiseDoc(?\DateTime $remise_doc): static
    {
        $this->remise_doc = $remise_doc;

        return $this;
    }

    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(?Produit $produit): static
    {
        $this->produit = $produit;

        return $this;
    }

    public function getCvDeblocage(): ?string
    {
        return $this->cv_deblocage;
    }

    public function setCvDeblocage(?string $cv_deblocage): static
    {
        $this->cv_deblocage = $cv_deblocage;

        return $this;
    }

    public function getCvExecution(): ?string
    {
        return $this->cv_execution;
    }

    public function setCvExecution(?string $cv_execution): static
    {
        $this->cv_execution = $cv_execution;

        return $this;
    }

    public function getSoutien(): ?string
    {
        return $this->soutien;
    }

    public function setSoutien(?string $soutien): static
    {
        $this->soutien = $soutien;

        return $this;
    }

    public function getReversement(): ?Uuid
    {
        return $this->reversement;
    }

    public function setReversement(?Uuid $reversement): static
    {
        $this->reversement = $reversement;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getDestination(): ?dESTINATION
    {
        return $this->destination;
    }

    public function setDestination(?dESTINATION $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    public function getProprietaire(): ?Proprietaire
    {
        return $this->proprietaire;
    }

    public function setProprietaire(?Proprietaire $proprietaire): static
    {
        $this->proprietaire = $proprietaire;

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

    public function setCreatedBy(?string $created_by): static
    {
        $this->created_by = $created_by;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updated_by;
    }

    public function setUpdatedBy(?string $updated_by): static
    {
        $this->updated_by = $updated_by;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getCodeNavire(): ?Navire
    {
        return $this->code_navire;
    }

    public function setCodeNavire(?Navire $code_navire): static
    {
        $this->code_navire = $code_navire;

        return $this;
    }

    public function getDateEmbarquement(): ?\DateTime
    {
        return $this->date_embarquement;
    }

    public function setDateEmbarquement(?\DateTime $date_embarquement): static
    {
        $this->date_embarquement = $date_embarquement;

        return $this;
    }

    public function isEtiquette(): ?bool
    {
        return $this->etiquette;
    }

    public function setEtiquette(?bool $etiquette): static
    {
        $this->etiquette = $etiquette;

        return $this;
    }

    public function getDraft(): ?string
    {
        return $this->draft;
    }

    public function setDraft(?string $draft): static
    {
        $this->draft = $draft;

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
            $lot->addCodeDossier($this);
        }

        return $this;
    }

    public function removeLot(Lot $lot): static
    {
        if ($this->lots->removeElement($lot)) {
            $lot->removeCodeDossier($this);
        }

        return $this;
    }

    /**
     * @return Collection<Uuid, Tc>
     */
    public function getTcs(): Collection
    {
        return $this->tcs;
    }

    public function addTc(Tc $tc): static
    {
        if (!$this->tcs->contains($tc)) {
            $this->tcs->add($tc);
            $tc->addCodeDossier($this);
        }

        return $this;
    }

    public function removeTc(Tc $tc): static
    {
        if ($this->tcs->removeElement($tc)) {
            $tc->removeCodeDossier($this);
        }

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
            $documentation->setCodeDossier($this);
        }

        return $this;
    }

    public function removeDocumentation(Documentation $documentation): static
    {
        if ($this->documentations->removeElement($documentation)) {
            // set the owning side to null (unless already changed)
            if ($documentation->getCodeDossier() === $this) {
                $documentation->setCodeDossier(null);
            }
        }

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getRemarques(): ?string
    {
        return $this->remarques;
    }

    public function setRemarques(?string $remarques): static
    {
        $this->remarques = $remarques;

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
            $docsDossier->setCodeDossier($this);
        }

        return $this;
    }

    public function removeDocsDossier(DocsDossier $docsDossier): static
    {
        if ($this->docsDossiers->removeElement($docsDossier)) {
            // set the owning side to null (unless already changed)
            if ($docsDossier->getCodeDossier() === $this) {
                $docsDossier->setCodeDossier(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<Uuid, PhotosDocs>
     */
    public function getPhotosDocs(): Collection
    {
        return $this->PhotosDocs;
    }

    public function addPhotosDoc(PhotosDocs $PhotosDoc): static
    {
        if (!$this->PhotosDocs->contains($PhotosDoc)) {
            $this->PhotosDocs->add($PhotosDoc);
            $PhotosDoc->setCodeDossier($this);
        }

        return $this;
    }

    public function removePhotosDoc(PhotosDocs $PhotosDoc): static
    {
        if ($this->PhotosDocs->removeElement($PhotosDoc)) {
            // set the owning side to null (unless already changed)
            if ($PhotosDoc->getCodeDossier() === $this) {
                $PhotosDoc->setCodeDossier(null);
            }
        }

        return $this;
    }

    public function isCloture(): ?bool
    {
        return $this->cloture;
    }

    public function setCloture(?bool $cloture): static
    {
        $this->cloture = $cloture;

        return $this;
    }
}
