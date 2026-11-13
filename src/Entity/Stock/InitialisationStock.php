<?php

namespace App\Entity\Stock;

use App\Entity\User;
use App\Repository\Stock\InitialisationStockRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Stock initial d'une rubrique (type de stock) apres un inventaire physique.
 *
 * Principe metier :
 *  - L'administration compte physiquement le stock a une date donnee ;
 *  - la quantite trouvee est enregistree ici comme stock de depart ;
 *  - tous les mouvements POSTERIEURS a cette date (entrees et sorties) sont
 *    ensuite ajoutes a ce stock de depart pour obtenir le stock final.
 *
 * Il peut exister plusieurs initialisations dans le temps (une par inventaire).
 * Pour une rubrique, c'est la plus recente (dateInventaire la plus grande) qui
 * sert de reference ; on peut forcer une initialisation a prendre le pas sur
 * les precedentes en cochant « remplacer l'initialisation precedente ».
 *
 * Les mouvements anterieurs a la date d'inventaire ne sont plus comptes :
 * l'inventaire physique remplace l'historique jusqu'a cette date.
 */
#[ORM\Entity(repositoryClass: InitialisationStockRepository::class)]
#[ORM\Table(name: 'stock_initialisation')]
#[ORM\Index(name: 'IDX_STOCK_INIT_RUBRIQUE_DATE', columns: ['rubrique', 'date_inventaire'])]
class InitialisationStock
{
    /** Type d'operation journalise. */
    public const TYPE_INITIALISATION = 'INITIALISATION';
    public const TYPE_REMPLACEMENT    = 'REMPLACEMENT';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** Rubrique (type de stock) initialisee. */
    #[ORM\Column(length: 100)]
    private ?string $rubrique = null;

    /** Nature du produit (facultative : permet un stock initial par nature). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nature = null;

    /** Quantite constatee lors de l'inventaire physique. */
    #[ORM\Column(type: Types::FLOAT)]
    private ?float $quantite = 0.0;

    #[ORM\Column(length: 20)]
    private ?string $unite = 'Kg';

    /** Date (et heure) de l'inventaire physique : reference du calcul. */
    #[ORM\Column(name: 'date_inventaire', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateInventaire = null;

    /** Valeur calculee au moment de l'initialisation (photo du stock theorique). */
    #[ORM\Column(name: 'stock_theorique', type: Types::FLOAT, nullable: true)]
    private ?float $stockTheorique = null;

    /** Ecart = quantite inventoriee - stock theorique (positif = surplus). */
    #[ORM\Column(name: 'ecart', type: Types::FLOAT, nullable: true)]
    private ?float $ecart = null;

    /** Commentaire libre (PV d'inventaire, nom du compteur, etc.). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observation = null;

    /** Initialisation active : seule la derniere active est utilisee. */
    #[ORM\Column(name: 'remplace_precedent', type: 'boolean')]
    private bool $remplacePrecedent = false;

    /** Vrai si cette ligne a ete desactivee (remplacee par une plus recente). */
    #[ORM\Column(type: 'boolean')]
    private bool $desactive = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'auteur_id', nullable: true)]
    private ?User $auteur = null;

    #[ORM\Column(name: 'auteur_libelle', length: 180, nullable: true)]
    private ?string $auteurLibelle = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->dateInventaire = new \DateTime();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getRubrique(): ?string { return $this->rubrique; }
    public function setRubrique(?string $v): static { $this->rubrique = $v; return $this; }

    public function getNature(): ?string { return $this->nature; }
    public function setNature(?string $v): static { $this->nature = $v; return $this; }

    public function getQuantite(): float { return (float) $this->quantite; }
    public function setQuantite(?float $v): static { $this->quantite = $v; return $this; }

    public function getUnite(): ?string { return $this->unite; }
    public function setUnite(?string $v): static { $this->unite = $v; return $this; }

    public function getDateInventaire(): ?\DateTimeInterface { return $this->dateInventaire; }
    public function setDateInventaire(?\DateTimeInterface $v): static { $this->dateInventaire = $v; return $this; }

    public function getStockTheorique(): ?float { return $this->stockTheorique; }
    public function setStockTheorique(?float $v): static { $this->stockTheorique = $v; return $this; }

    public function getEcart(): ?float { return $this->ecart; }
    public function setEcart(?float $v): static { $this->ecart = $v; return $this; }

    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $v): static { $this->observation = $v; return $this; }

    public function isRemplacePrecedent(): bool { return $this->remplacePrecedent; }
    public function setRemplacePrecedent(bool $v): static { $this->remplacePrecedent = $v; return $this; }

    public function isDesactive(): bool { return $this->desactive; }
    public function setDesactive(bool $v): static { $this->desactive = $v; return $this; }

    public function getAuteur(): ?User { return $this->auteur; }
    public function setAuteur(?User $v): static { $this->auteur = $v; return $this; }

    public function getAuteurLibelle(): ?string { return $this->auteurLibelle; }
    public function setAuteurLibelle(?string $v): static { $this->auteurLibelle = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeImmutable $v): static { $this->createdAt = $v; return $this; }

    /** Cle de regroupement rubrique + nature (nature vide = toute la rubrique). */
    public function cle(): string
    {
        return ($this->rubrique ?? '') . '|' . ($this->nature ?? '');
    }

    public function __toString(): string
    {
        return sprintf(
            '%s %s %s (inventaire du %s)',
            $this->rubrique,
            $this->nature ? '- ' . $this->nature : '',
            number_format($this->getQuantite(), 2, ',', ' ') . ' ' . $this->unite,
            $this->dateInventaire ? $this->dateInventaire->format('d/m/Y') : '-'
        );
    }
}
