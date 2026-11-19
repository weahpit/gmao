<?php

namespace App\Entity\Rh;

use App\Repository\Rh\EmployeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rh_employe')]
#[ORM\Entity(repositoryClass: EmployeRepository::class)]
class Employe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom_prenoms = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_naissance = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $sexe = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $matricule = null;

    #[ORM\Column(nullable: true)]
    private ?int $code_zkteco = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fonction = null;

    #[ORM\ManyToOne(inversedBy: 'employes')]
    private ?Departement $code_departement = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $type_contrat = null;

    #[ORM\Column(nullable: true)]
    private ?bool $soumis_au_pointage = null;

    #[ORM\Column(nullable: true)]
    private ?int $type_horraire = null;

    #[ORM\ManyToOne(inversedBy: 'employes')]
    private ?Poste $poste = null;

    #[ORM\ManyToOne(inversedBy: 'employes')]
    private ?Equipe $code_equipe = null;

    /**
     * @var Collection<int, Absences>
     */
    #[ORM\OneToMany(targetEntity: Absences::class, mappedBy: 'code_employe')]
    private Collection $absences;

    #[ORM\Column(nullable: true)]
    private ?bool $demission = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_demission = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motif_demission = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(nullable: true)]
    private ?bool $supprimer = null;

    #[ORM\Column(nullable: true)]
    private ?bool $chef_quart = null;

    /**
     * @var Collection<int, Historique>
     */
    #[ORM\OneToMany(targetEntity: Historique::class, mappedBy: 'code_employe')]
    private Collection $historiques;

    #[ORM\ManyToOne(inversedBy: 'employes')]
    private ?HorairesPersonnalises $horaire_p = null;

    public function __construct()
    {
        $this->absences = new ArrayCollection();
        $this->historiques = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomPrenoms(): ?string
    {
        return $this->nom_prenoms;
    }

    public function setNomPrenoms(string $nom_prenoms): static
    {
        $this->nom_prenoms = $nom_prenoms;

        return $this;
    }

    public function getDateNaissance(): ?\DateTime
    {
        return $this->date_naissance;
    }

    public function setDateNaissance(?\DateTime $date_naissance): static
    {
        $this->date_naissance = $date_naissance;

        return $this;
    }

    public function getSexe(): ?string
    {
        return $this->sexe;
    }

    public function setSexe(?string $sexe): static
    {
        $this->sexe = $sexe;

        return $this;
    }

    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(?string $matricule): static
    {
        $this->matricule = $matricule;

        return $this;
    }

    public function getCodeZkteco(): ?int
    {
        return $this->code_zkteco;
    }

    public function setCodeZkteco(?int $code_zkteco): static
    {
        $this->code_zkteco = $code_zkteco;

        return $this;
    }

    public function getFonction(): ?string
    {
        return $this->fonction;
    }

    public function setFonction(?string $fonction): static
    {
        $this->fonction = $fonction;

        return $this;
    }

    public function getCodeDepartement(): ?Departement
    {
        return $this->code_departement;
    }

    public function setCodeDepartement(?Departement $code_departement): static
    {
        $this->code_departement = $code_departement;

        return $this;
    }

    public function getTypeContrat(): ?string
    {
        return $this->type_contrat;
    }

    public function setTypeContrat(?string $type_contrat): static
    {
        $this->type_contrat = $type_contrat;

        return $this;
    }

    public function isSoumisAuPointage(): ?bool
    {
        return $this->soumis_au_pointage;
    }

    public function setSoumisAuPointage(?bool $soumis_au_pointage): static
    {
        $this->soumis_au_pointage = $soumis_au_pointage;

        return $this;
    }

    public function getTypeHorraire(): ?int
    {
        return $this->type_horraire;
    }

    public function setTypeHorraire(?int $type_horraire): static
    {
        $this->type_horraire = $type_horraire;

        return $this;
    }

    public function getPoste(): ?Poste
    {
        return $this->poste;
    }

    public function setPoste(?Poste $poste): static
    {
        $this->poste = $poste;

        return $this;
    }

    public function getCodeEquipe(): ?Equipe
    {
        return $this->code_equipe;
    }

    public function setCodeEquipe(?Equipe $code_equipe): static
    {
        $this->code_equipe = $code_equipe;

        return $this;
    }

    /**
     * @return Collection<int, Absences>
     */
    public function getAbsences(): Collection
    {
        return $this->absences;
    }

    public function addAbsence(Absences $absence): static
    {
        if (!$this->absences->contains($absence)) {
            $this->absences->add($absence);
            $absence->setCodeEmploye($this);
        }

        return $this;
    }

    public function removeAbsence(Absences $absence): static
    {
        if ($this->absences->removeElement($absence)) {
            // set the owning side to null (unless already changed)
            if ($absence->getCodeEmploye() === $this) {
                $absence->setCodeEmploye(null);
            }
        }

        return $this;
    }

    public function isDemission(): ?bool
    {
        return $this->demission;
    }

    public function setDemission(?bool $demission): static
    {
        $this->demission = $demission;

        return $this;
    }

    public function getDateDemission(): ?\DateTime
    {
        return $this->date_demission;
    }

    public function setDateDemission(?\DateTime $date_demission): static
    {
        $this->date_demission = $date_demission;

        return $this;
    }

    public function getMotifDemission(): ?string
    {
        return $this->motif_demission;
    }

    public function setMotifDemission(?string $motif_demission): static
    {
        $this->motif_demission = $motif_demission;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function isSupprimer(): ?bool
    {
        return $this->supprimer;
    }

    public function setSupprimer(?bool $supprimer): static
    {
        $this->supprimer = $supprimer;

        return $this;
    }

    public function isChefQuart(): ?bool
    {
        return $this->chef_quart;
    }

    public function setChefQuart(?bool $chef_quart): static
    {
        $this->chef_quart = $chef_quart;

        return $this;
    }

    /**
     * @return Collection<int, Historique>
     */
    public function getHistoriques(): Collection
    {
        return $this->historiques;
    }

    public function addHistorique(Historique $historique): static
    {
        if (!$this->historiques->contains($historique)) {
            $this->historiques->add($historique);
            $historique->setCodeEmploye($this);
        }

        return $this;
    }

    public function removeHistorique(Historique $historique): static
    {
        if ($this->historiques->removeElement($historique)) {
            // set the owning side to null (unless already changed)
            if ($historique->getCodeEmploye() === $this) {
                $historique->setCodeEmploye(null);
            }
        }

        return $this;
    }

    public function getHoraireP(): ?HorairesPersonnalises
    {
        return $this->horaire_p;
    }

    public function setHoraireP(?HorairesPersonnalises $horaire_p): static
    {
        $this->horaire_p = $horaire_p;

        return $this;
    }
}
