<?php

namespace App\Entity\Production;

use App\Repository\Production\LigneOrdreProductionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Ligne d'un ordre de production : UN lot de produit fini fabrique a partir
 * d'UN lot de feves CITRAC.
 *
 * C'est la meme logique que LotDechargement cote camions : l'entite porte la
 * reference de l'ordre, ce qui permet de lister, d'annuler et de compter les
 * rattachements sans toucher aux lots eux-memes.
 */
#[ORM\Entity(repositoryClass: LigneOrdreProductionRepository::class)]
#[ORM\Table(name: 'production.ordre_production_ligne')]
#[ORM\UniqueConstraint(name: 'uniq_ordre_production_ligne', columns: ['ordre_id', 'lot_fini_id', 'lot_feve_id'])]
#[ORM\Index(name: 'idx_ordre_production_ligne_fini', columns: ['lot_fini_id'])]
#[ORM\Index(name: 'idx_ordre_production_ligne_feve', columns: ['lot_feve_id'])]
class LigneOrdreProduction
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: OrdreProduction::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(name: 'ordre_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?OrdreProduction $ordre = null;

    /** Lot de produit fini (masse, beurre, tourteau, poudre). */
    #[ORM\ManyToOne(targetEntity: Lot::class)]
    #[ORM\JoinColumn(name: 'lot_fini_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Lot $lotFini = null;

    /** Lot de feves CITRAC utilise pour fabriquer ce produit fini. */
    #[ORM\ManyToOne(targetEntity: Lot::class)]
    #[ORM\JoinColumn(name: 'lot_feve_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Lot $lotFeve = null;

    /** Poids de feves consomme (facultatif, pour le detail de fabrication). */
    #[ORM\Column(name: 'poids_affecte', type: Types::FLOAT, nullable: true)]
    private ?float $poidsAffecte = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observation = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'created_by', length: 180, nullable: true)]
    private ?string $createdBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getOrdre(): ?OrdreProduction { return $this->ordre; }
    public function setOrdre(?OrdreProduction $ordre): static { $this->ordre = $ordre; return $this; }

    public function getLotFini(): ?Lot { return $this->lotFini; }
    public function setLotFini(?Lot $lot): static { $this->lotFini = $lot; return $this; }

    public function getLotFeve(): ?Lot { return $this->lotFeve; }
    public function setLotFeve(?Lot $lot): static { $this->lotFeve = $lot; return $this; }

    public function getPoidsAffecte(): ?float { return $this->poidsAffecte; }
    public function setPoidsAffecte(?float $poids): static { $this->poidsAffecte = $poids; return $this; }

    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $observation): static { $this->observation = $observation; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $date): static { $this->createdAt = $date; return $this; }

    public function getCreatedBy(): ?string { return $this->createdBy; }
    public function setCreatedBy(?string $createdBy): static { $this->createdBy = $createdBy; return $this; }
}
