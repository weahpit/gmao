<?php

namespace App\Entity\Stock;

use App\Repository\Stock\RubriqueRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Rubrique de stock (type de stock) : liste de reference PARTAGEE par
 * l'application web et l'application mobile.
 *
 * Avant, les rubriques etaient codees en dur dans la constante
 * SaisieStock::RUBRIQUES. Elles vivent desormais dans cette table pour que
 * le web et le mobile lisent EXACTEMENT la meme liste, et qu'on puisse en
 * ajouter/retirer sans toucher au code.
 *
 * Les 5 valeurs historiques sont injectees par les fixtures
 * (cf. App\DataFixtures\AppFixtures) ; si la table est vide, le code
 * retombe sur SaisieStock::RUBRIQUES (liste de secours).
 */
#[ORM\Entity(repositoryClass: RubriqueRepository::class)]
#[ORM\Table(name: 'stock_rubrique')]
class Rubrique
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** Libelle de la rubrique (ex. "Stock Refonte"). Unique. */
    #[ORM\Column(length: 100, unique: true)]
    private ?string $libelle = null;

    /** Rubrique active (affichee dans les listes). */
    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    /** Ordre d'affichage (tri croissant). */
    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    public function getId(): ?Uuid { return $this->id; }

    public function getLibelle(): ?string { return $this->libelle; }
    public function setLibelle(string $libelle): static { $this->libelle = $libelle; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }

    public function __toString(): string { return (string) $this->libelle; }
}
