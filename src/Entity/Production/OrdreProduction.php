<?php

namespace App\Entity\Production;

use App\Repository\Production\OrdreProductionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * ORDRE DE PRODUCTION : association metier entre UN OU PLUSIEURS lots de produit
 * fini (masse, beurre, tourteau, poudre) et UN GROUPE DE LOTS DE FEVES CITRAC.
 *
 * C'est le pendant de l'ordre d'usinage, cote produits finis : l'operateur coche
 * des produits finis, coche un ou plusieurs lots de feves, valide, et
 * l'application genere un numero (OP-AAAA-0001) qui regroupe tous les couples.
 *
 * Chaque couple produit fini x lot de feves est porte par une ligne
 * (LigneOrdreProduction) : la fiche « Ordre de production » restitue ainsi la
 * chaine produit fini -> feves -> camions de dechargement.
 */
#[ORM\Entity(repositoryClass: OrdreProductionRepository::class)]
#[ORM\Table(name: 'production.ordre_production')]
#[ORM\UniqueConstraint(name: 'uniq_ordre_production_numero', columns: ['numero'])]
class OrdreProduction
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** Numero genere automatiquement, ex. OP-2026-0001. */
    #[ORM\Column(length: 30)]
    private ?string $numero = null;

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(name: 'created_by', length: 180, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observation = null;

    /**
     * Couples produit fini x lot de feves enregistres dans cet ordre.
     *
     * @var Collection<int, LigneOrdreProduction>
     */
    #[ORM\OneToMany(mappedBy: 'ordre', targetEntity: LigneOrdreProduction::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $lignes;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
        $this->lignes = new ArrayCollection();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getNumero(): ?string { return $this->numero; }
    public function setNumero(string $numero): static { $this->numero = $numero; return $this; }

    public function getDateCreation(): ?\DateTimeImmutable { return $this->dateCreation; }
    public function setDateCreation(\DateTimeImmutable $date): static { $this->dateCreation = $date; return $this; }

    public function getCreatedBy(): ?string { return $this->createdBy; }
    public function setCreatedBy(?string $createdBy): static { $this->createdBy = $createdBy; return $this; }

    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $observation): static { $this->observation = $observation; return $this; }

    /** @return Collection<int, LigneOrdreProduction> */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(LigneOrdreProduction $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setOrdre($this);
        }

        return $this;
    }

    /** Produits finis distincts de l'ordre, tries par numero de lot. */
    public function produitsFinis(): array
    {
        $lots = [];
        foreach ($this->lignes as $ligne) {
            $lot = $ligne->getLotFini();
            if ($lot !== null) {
                $lots[(string) $lot->getId()] = $lot;
            }
        }
        ksort($lots);

        return array_values($lots);
    }

    /** Lots de feves distincts de l'ordre, tries par numero de lot. */
    public function lotsFeves(): array
    {
        $lots = [];
        foreach ($this->lignes as $ligne) {
            $lot = $ligne->getLotFeve();
            if ($lot !== null) {
                $lots[(string) $lot->getId()] = $lot;
            }
        }
        ksort($lots);

        return array_values($lots);
    }

    public function __toString(): string
    {
        return (string) $this->numero;
    }
}
