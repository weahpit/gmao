<?php

namespace App\Entity\Stock;

use App\Entity\Emplacement;
use App\Entity\Services;
use App\Entity\User;
use App\Repository\Stock\SaisieStockRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Saisie de stock effectuee par un agent d'un service (Production,
 * Laboratoires, etc.) dans une rubrique predefinie.
 * Alimentee notamment par l'application mobile (mode hors-ligne + synchro).
 *
 * Depuis la v2, une entree porte aussi trois informations complementaires :
 *  - source  : d'ou provient le stock entre (provenance) ;
 *  - emplacement : ou le stock est range (relation vers Emplacement) ;
 *  - motif   : pourquoi cette entree est enregistree.
 *
 * Ces trois colonnes sont NULLABLE en base : les saisies deja existantes
 * restent valides et les envois hors-ligne de l'ancienne application mobile
 * ne cassent pas. Le caractere "obligatoire a la saisie" est applique a
 * l'entree (formulaire web + API), pas au schema.
 */
#[ORM\Entity(repositoryClass: SaisieStockRepository::class)]
#[ORM\Table(name: 'saisie_stock')]
class SaisieStock
{
    /** Rubriques predefinies (liste fermee). */
    public const RUBRIQUES = [
        'Stock déchets Recyclables',
        'Stock déchets non recyclable',
        'Stock échantillothèque',
        'Stock Refonte',
        'Stock échantillons Client',
    ];

    /** Unites autorisees (Kg par defaut). */
    public const UNITES = ['Kg', 'Litres'];

    /** Nature du produit : liste FERMEE (valeurs imposees). */
    public const NATURES = [
        'Fèves',
        'Masse',
        'Beurre',
        'Tourteau',
        'Tout venant',
    ];

    /** Suggestions de source du stock entre (la saisie reste libre). */
    public const SOURCES = [
        'Production interne',
        'Laboratoire',
        'Collecte terrain',
        'Réception fournisseur',
        'Retour client',
        'Récupération / tri',
        'Transfert interne',
    ];

    /** Suggestions de motifs d'entree (la saisie reste libre). */
    public const MOTIFS = [
        'Réception / collecte',
        'Production interne',
        'Retour / réintégration',
        'Transfert entre emplacements',
        'Récupération / don',
        "Correction d'inventaire",
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** Agent ayant effectue la saisie (grise cote mobile). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $agent = null;

    /** Libelle de l'agent au moment de la saisie (trace lisible). */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $agentLibelle = null;

    /** Service concerne par la saisie. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Services $service = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $serviceLibelle = null;

    /** Rubrique predefinie (cf. self::RUBRIQUES). */
    #[ORM\Column(length: 100)]
    private ?string $rubrique = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $quantite = null;

    #[ORM\Column(length: 20)]
    private ?string $unite = 'Kg';

    /** Nature du produit (liste fermee, cf. self::NATURES). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nature = null;

    /** Source / provenance du stock entre (saisie libre, cf. self::SOURCES). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $source = null;

    /** Emplacement de rangement du stock entre. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Emplacement $emplacement = null;

    /** Libelle de l'emplacement au moment de la saisie (trace lisible). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emplacementLibelle = null;

    /** Motif de l'entree (saisie libre, cf. self::MOTIFS). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motif = null;

    /** Nom du fichier photo stocke cote serveur. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    /** Date de saisie declaree par l'appareil. */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateSaisie = null;

    /** Date de reception serveur. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Identifiant genere par le mobile : garantit l'idempotence lors de la
     * synchronisation hors-ligne (evite les doublons si l'envoi est rejoue).
     */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $clientUuid = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->dateSaisie = new \DateTime();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getAgent(): ?User { return $this->agent; }
    public function setAgent(?User $agent): static { $this->agent = $agent; return $this; }

    public function getAgentLibelle(): ?string { return $this->agentLibelle; }
    public function setAgentLibelle(?string $v): static { $this->agentLibelle = $v; return $this; }

    public function getService(): ?Services { return $this->service; }
    public function setService(?Services $service): static { $this->service = $service; return $this; }

    public function getServiceLibelle(): ?string { return $this->serviceLibelle; }
    public function setServiceLibelle(?string $v): static { $this->serviceLibelle = $v; return $this; }

    public function getRubrique(): ?string { return $this->rubrique; }
    public function setRubrique(?string $v): static { $this->rubrique = $v; return $this; }

    public function getQuantite(): ?float { return $this->quantite; }
    public function setQuantite(?float $v): static { $this->quantite = $v; return $this; }

    public function getUnite(): ?string { return $this->unite; }
    public function setUnite(?string $v): static { $this->unite = $v; return $this; }

    public function getNature(): ?string { return $this->nature; }
    public function setNature(?string $v): static { $this->nature = $v; return $this; }

    public function getSource(): ?string { return $this->source; }
    public function setSource(?string $v): static { $this->source = $v; return $this; }

    public function getEmplacement(): ?Emplacement { return $this->emplacement; }
    public function setEmplacement(?Emplacement $emplacement): static { $this->emplacement = $emplacement; return $this; }

    public function getEmplacementLibelle(): ?string { return $this->emplacementLibelle; }
    public function setEmplacementLibelle(?string $v): static { $this->emplacementLibelle = $v; return $this; }

    public function getMotif(): ?string { return $this->motif; }
    public function setMotif(?string $v): static { $this->motif = $v; return $this; }

    public function getPhoto(): ?string { return $this->photo; }
    public function setPhoto(?string $v): static { $this->photo = $v; return $this; }

    public function getDateSaisie(): ?\DateTimeInterface { return $this->dateSaisie; }
    public function setDateSaisie(?\DateTimeInterface $v): static { $this->dateSaisie = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeImmutable $v): static { $this->createdAt = $v; return $this; }

    public function getClientUuid(): ?string { return $this->clientUuid; }
    public function setClientUuid(?string $v): static { $this->clientUuid = $v; return $this; }
}
