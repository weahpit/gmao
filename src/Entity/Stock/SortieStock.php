<?php

namespace App\Entity\Stock;

use App\Entity\Emplacement;
use App\Entity\Services;
use App\Entity\User;
use App\Repository\Stock\SortieStockRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Sortie de stock : quantité retirée d'une rubrique (cf. SaisieStock::RUBRIQUES)
 * avec son motif, son emplacement, le service concerné et une photo
 * justificative facultative.
 *
 * Le solde d'une rubrique = somme des saisies (entrées) - somme des sorties.
 */
#[ORM\Entity(repositoryClass: SortieStockRepository::class)]
#[ORM\Table(name: 'sortie_stock')]
class SortieStock
{
    /** Suggestions de motifs (la saisie reste libre). */
    public const MOTIFS = [
        'Enlèvement / évacuation',
        'Mise au rebut / destruction',
        'Envoi en refonte',
        'Analyse laboratoire',
        'Remise client',
        'Casse / perte',
        "Correction d'inventaire",
    ];

    /** Nature du produit : liste FERMEE (valeurs imposees, cf. SaisieStock::NATURES). */
    public const NATURES = SaisieStock::NATURES;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** Agent ayant enregistré la sortie. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $agent = null;

    /** Libellé de l'agent au moment de la sortie (trace lisible). */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $agentLibelle = null;

    #[Assert\NotNull(message: 'Choisissez le service.')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Services $service = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $serviceLibelle = null;

    /** Emplacement d'où le stock est sorti (facultatif). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Emplacement $emplacement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emplacementLibelle = null;

    // Le libelle valide (type de stock) est verifie dynamiquement dans le
    // controleur via App\Service\Stock\ReferentielStock (table stock_rubrique),
    // pas par un Assert\Choice fige sur une constante.
    #[Assert\NotBlank(message: 'Choisissez le type de stock.')]
    #[ORM\Column(length: 100)]
    private ?string $rubrique = null;

    /** Nature du produit : libelle d'un produit du referentiel Production. */
    // La valeur valide est verifiee dynamiquement dans le controleur via
    // ReferentielStock (table production.produit), pas par un Assert\Choice fige.
    #[Assert\NotBlank(message: 'Choisissez la nature du produit.')]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nature = null;

    public function getNature(): ?string { return $this->nature; }
    public function setNature(?string $v): static { $this->nature = $v; return $this; }

    #[Assert\NotNull(message: 'Quantité invalide.')]
    #[Assert\Positive(message: 'La quantité doit être supérieure à zéro.')]
    #[ORM\Column(type: Types::FLOAT)]
    private ?float $quantite = null;

    #[Assert\Choice(choices: SaisieStock::UNITES, message: 'Unité invalide.')]
    #[ORM\Column(length: 20)]
    private ?string $unite = 'Kg';

    #[Assert\NotBlank(message: 'Le motif est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le motif est trop long (255 caractères maximum).')]
    #[ORM\Column(length: 255)]
    private ?string $motif = null;

    /** Nom du fichier photo stocké côté serveur. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[Assert\NotNull(message: 'Date invalide.')]
    #[Assert\LessThanOrEqual(value: 'tomorrow', message: 'La date ne peut pas être dans le futur.')]
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateSortie = null;

    /** Date d'enregistrement serveur. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->dateSortie = new \DateTime();
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

    public function getEmplacement(): ?Emplacement { return $this->emplacement; }
    public function setEmplacement(?Emplacement $emplacement): static { $this->emplacement = $emplacement; return $this; }

    public function getEmplacementLibelle(): ?string { return $this->emplacementLibelle; }
    public function setEmplacementLibelle(?string $v): static { $this->emplacementLibelle = $v; return $this; }

    public function getRubrique(): ?string { return $this->rubrique; }
    public function setRubrique(?string $v): static { $this->rubrique = $v; return $this; }

    public function getQuantite(): ?float { return $this->quantite; }
    public function setQuantite(?float $v): static { $this->quantite = $v; return $this; }

    public function getUnite(): ?string { return $this->unite; }
    public function setUnite(?string $v): static { $this->unite = $v; return $this; }

    public function getMotif(): ?string { return $this->motif; }
    public function setMotif(?string $v): static { $this->motif = $v; return $this; }

    public function getPhoto(): ?string { return $this->photo; }
    public function setPhoto(?string $v): static { $this->photo = $v; return $this; }

    public function getDateSortie(): ?\DateTimeInterface { return $this->dateSortie; }
    public function setDateSortie(?\DateTimeInterface $v): static { $this->dateSortie = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeImmutable $v): static { $this->createdAt = $v; return $this; }
}
