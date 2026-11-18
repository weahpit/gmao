<?php

namespace App\Entity\Stock;

use App\Entity\Production\Lot;
use App\Repository\Stock\LotDechargementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Lien de TRACABILITE entre un lot fabrique (FEVES, table production.lot) et un
 * dechargement (un camion de cacao).
 *
 * Relation plusieurs-a-plusieurs portee par une entite dediee : c'est ce qui
 * permet d'enregistrer, pour chaque lien, le POIDS affecte au lot et l'auteur de
 * l'association. Un lot peut donc etre fabrique a partir de plusieurs camions,
 * et un camion peut alimenter plusieurs lots (scission / melange).
 *
 * Le poids affecte est la piece maitresse de la tracabilite : la somme des poids
 * affectes d'un lot est comparee au poids theorique du lot (PN th) pour detecter
 * un manque ou un exces de matiere declaree.
 */
#[ORM\Entity(repositoryClass: LotDechargementRepository::class)]
#[ORM\Table(name: 'stock_lot_dechargement')]
#[ORM\UniqueConstraint(name: 'uniq_stock_lot_dechargement', columns: ['lot_id', 'dechargement_id'])]
#[ORM\Index(name: 'idx_stock_lot_dechargement_lot', columns: ['lot_id'])]
#[ORM\Index(name: 'idx_stock_lot_dechargement_dech', columns: ['dechargement_id'])]
class LotDechargement
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** Lot fabrique (production.lot). */
    #[ORM\ManyToOne(targetEntity: Lot::class)]
    #[ORM\JoinColumn(name: 'lot_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Lot $lot = null;

    /** Camion dechargement d'origine (stock_dechargement_cacao). */
    #[ORM\ManyToOne(targetEntity: DechargementCacao::class)]
    #[ORM\JoinColumn(name: 'dechargement_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?DechargementCacao $dechargement = null;

    /** Poids net du camion affecte a ce lot (kg). */
    #[ORM\Column(name: 'poids_affecte', type: Types::FLOAT, nullable: true)]
    private ?float $poidsAffecte = null;

    /** Nombre de sacs affectes (facultatif, pour le detail de fabrication). */
    #[ORM\Column(name: 'nbre_sacs', nullable: true)]
    private ?int $nbreSacs = null;

    /**
     * Ordre d'usinage qui a cree ce rattachement (nullable : les anciennes
     * associations, ou un rattachement unitaire, n'en ont pas).
     */
    #[ORM\ManyToOne(targetEntity: OrdreUsinage::class)]
    #[ORM\JoinColumn(name: 'ordre_usinage_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?OrdreUsinage $ordreUsinage = null;

    /** Commentaire libre (ex. "2e passage", "melange avec le lot ..."). */
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

    public function getLot(): ?Lot { return $this->lot; }
    public function setLot(?Lot $lot): static { $this->lot = $lot; return $this; }

    public function getDechargement(): ?DechargementCacao { return $this->dechargement; }
    public function setDechargement(?DechargementCacao $dechargement): static { $this->dechargement = $dechargement; return $this; }

    public function getPoidsAffecte(): ?float { return $this->poidsAffecte; }
    public function setPoidsAffecte(?float $poidsAffecte): static { $this->poidsAffecte = $poidsAffecte; return $this; }

    public function getNbreSacs(): ?int { return $this->nbreSacs; }
    public function setNbreSacs(?int $nbreSacs): static { $this->nbreSacs = $nbreSacs; return $this; }

    public function getOrdreUsinage(): ?OrdreUsinage { return $this->ordreUsinage; }
    public function setOrdreUsinage(?OrdreUsinage $ordre): static { $this->ordreUsinage = $ordre; return $this; }

    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $observation): static { $this->observation = $observation; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getCreatedBy(): ?string { return $this->createdBy; }
    public function setCreatedBy(?string $createdBy): static { $this->createdBy = $createdBy; return $this; }
}
