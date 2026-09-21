<?php

namespace App\Entity;

use App\Repository\EquipementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EquipementRepository::class)]
class Equipement
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 20)]
    private ?string $code = null;

    #[ORM\ManyToOne(inversedBy: 'equipements')]
    private ?Marque $marque = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $modele = null;

    #[ORM\ManyToOne(inversedBy: 'equipements')]
    private ?Fournisseur $code_fournisseur = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $numero_serie = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_entree = null;

    #[ORM\ManyToOne(inversedBy: 'equipements')]
    private ?TypeEquipement $type_unicite = null;

    #[ORM\Column(nullable: true)]
    private ?float $stock_initial = null;

    #[ORM\Column(nullable: true)]
    private ?float $stock_actuel = null;

    #[ORM\Column(nullable: true)]
    private ?float $stock_final = null;

    #[ORM\ManyToOne(inversedBy: 'equipements')]
    private ?EtatEquipement $etat = null;

    #[ORM\ManyToOne(inversedBy: 'equipements')]
    private ?Criticite $criticite = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'equipements')]
    private ?NatureEquipement $nature = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getMarque(): ?Marque
    {
        return $this->marque;
    }

    public function setMarque(?Marque $marque): static
    {
        $this->marque = $marque;

        return $this;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(?string $modele): static
    {
        $this->modele = $modele;

        return $this;
    }

    public function getCodeFournisseur(): ?Fournisseur
    {
        return $this->code_fournisseur;
    }

    public function setCodeFournisseur(?Fournisseur $code_fournisseur): static
    {
        $this->code_fournisseur = $code_fournisseur;

        return $this;
    }

    public function getNumeroSerie(): ?string
    {
        return $this->numero_serie;
    }

    public function setNumeroSerie(?string $numero_serie): static
    {
        $this->numero_serie = $numero_serie;

        return $this;
    }

    public function getDateEntree(): ?\DateTime
    {
        return $this->date_entree;
    }

    public function setDateEntree(?\DateTime $date_entree): static
    {
        $this->date_entree = $date_entree;

        return $this;
    }

    public function getTypeUnicite(): ?TypeEquipement
    {
        return $this->type_unicite;
    }

    public function setTypeUnicite(?TypeEquipement $type_unicite): static
    {
        $this->type_unicite = $type_unicite;

        return $this;
    }

    public function getStockInitial(): ?float
    {
        return $this->stock_initial;
    }

    public function setStockInitial(?float $stock_initial): static
    {
        $this->stock_initial = $stock_initial;

        return $this;
    }

    public function getStockActuel(): ?float
    {
        return $this->stock_actuel;
    }

    public function setStockActuel(?float $stock_actuel): static
    {
        $this->stock_actuel = $stock_actuel;

        return $this;
    }

    public function getStockFinal(): ?float
    {
        return $this->stock_final;
    }

    public function setStockFinal(?float $stock_final): static
    {
        $this->stock_final = $stock_final;

        return $this;
    }

    public function getEtat(): ?EtatEquipement
    {
        return $this->etat;
    }

    public function setEtat(?EtatEquipement $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function getCriticite(): ?Criticite
    {
        return $this->criticite;
    }

    public function setCriticite(?Criticite $criticite): static
    {
        $this->criticite = $criticite;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getNature(): ?NatureEquipement
    {
        return $this->nature;
    }

    public function setNature(?NatureEquipement $nature): static
    {
        $this->nature = $nature;

        return $this;
    }
}
