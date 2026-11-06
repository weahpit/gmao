<?php

namespace App\Entity\Stock;

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
 */
#[ORM\Entity(repositoryClass: SaisieStockRepository::class)]
#[ORM\Table(name: 'saisie_stock')]
class SaisieStock
{
    /** Rubriques predefinies (liste fermee). */
    public const RUBRIQUES = [
        'Stock déchets Recyclables',
        'Stock déchets non recyclables',
        'Stock échantillothèque',
        'Stock Refonte',
        'Stock échantillons Client',
    ];

    /** Unites autorisees (Kg par defaut). */
    public const UNITES = ['Kg', 'Litres'];

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

    public function getPhoto(): ?string { return $this->photo; }
    public function setPhoto(?string $v): static { $this->photo = $v; return $this; }

    public function getDateSaisie(): ?\DateTimeInterface { return $this->dateSaisie; }
    public function setDateSaisie(?\DateTimeInterface $v): static { $this->dateSaisie = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeImmutable $v): static { $this->createdAt = $v; return $this; }

    public function getClientUuid(): ?string { return $this->clientUuid; }
    public function setClientUuid(?string $v): static { $this->clientUuid = $v; return $this; }
}
