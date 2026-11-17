<?php

namespace App\Entity\Stock;

use App\Entity\Production\Produit;
use App\Repository\Stock\UniteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Unite de mesure du module Stock (Kg, Litres, Sacs, Tonnes, ...).
 *
 * Liste de reference PARTAGEE par l'application web et l'application mobile :
 * les formulaires d'entree (saisie) et de sortie de stock lisent leurs
 * valeurs ici, au lieu de la constante SaisieStock::UNITES codee en dur.
 *
 * Pour ajouter une unite, il suffit d'ajouter une ligne dans cette table
 * (via les fixtures, une commande SQL ou l'API) : web et mobile la voient
 * immediatement, sans modification de code.
 *
 * Si la table est vide (base pas encore peuplee), le service
 * App\Service\Stock\ReferentielStock retombe sur SaisieStock::UNITES
 * afin de ne jamais presenter une liste vide.
 */
#[ORM\Entity(repositoryClass: UniteRepository::class)]
#[ORM\Table(name: 'stock_unite')]
class Unite
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** Libelle de l'unite tel qu'affiche et enregistre (ex. "Kg"). Unique. */
    #[ORM\Column(length: 30, unique: true)]
    private ?string $libelle = null;

    /** Symbole court facultatif (ex. "kg" pour "Kilogrammes"). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $symbole = null;

    /** Unite proposee par defaut dans les formulaires. */
    #[ORM\Column(type: 'boolean')]
    private bool $parDefaut = false;

    /** Unite active (affichee dans les listes). */
    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    /** Ordre d'affichage (tri croissant). */
    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    /**
     * @var Collection<int, Produit>
     */
    #[ORM\OneToMany(targetEntity: Produit::class, mappedBy: 'unite')]
    private Collection $produits;

    public function __construct()
    {
        $this->produits = new ArrayCollection();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getLibelle(): ?string { return $this->libelle; }
    public function setLibelle(string $libelle): static { $this->libelle = $libelle; return $this; }

    public function getSymbole(): ?string { return $this->symbole; }
    public function setSymbole(?string $symbole): static { $this->symbole = $symbole; return $this; }

    public function isParDefaut(): bool { return $this->parDefaut; }
    public function setParDefaut(bool $parDefaut): static { $this->parDefaut = $parDefaut; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }

    public function __toString(): string { return (string) $this->libelle; }

    /**
     * @return Collection<int, Produit>
     */
    public function getProduits(): Collection
    {
        return $this->produits;
    }

    public function addProduit(Produit $produit): static
    {
        if (!$this->produits->contains($produit)) {
            $this->produits->add($produit);
            $produit->setUnite($this);
        }

        return $this;
    }

    public function removeProduit(Produit $produit): static
    {
        if ($this->produits->removeElement($produit)) {
            // set the owning side to null (unless already changed)
            if ($produit->getUnite() === $this) {
                $produit->setUnite(null);
            }
        }

        return $this;
    }
}
