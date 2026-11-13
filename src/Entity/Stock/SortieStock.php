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
 *
 * CIRCUIT DE VALIDATION : TOUTE sortie, quel que soit le type de stock
 * (cacao, PDR, consommables...), génère un BON DE SORTIE soumis à la
 * signature du Directeur Général (ROLE_DG dont ROLE_ADMIN hérite),
 * exactement comme les bons de sortie PDR de l'entité App\Entity\BonSortie.
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

    /* ------------------------------------------------------------------
     * CIRCUIT DE VALIDATION DU BON DE SORTIE (toutes rubriques)
     * Identique au circuit des bons de sortie PDR (entite BonSortie) :
     *   1. EN_ATTENTE : sortie enregistree (magasinier / agent) -> bon emis
     *   2. VALIDE     : bon signe par le Directeur General (ROLE_DG / ROLE_ADMIN)
     *   3. ANNULE     : bon refuse / annule par la Direction Generale
     * ------------------------------------------------------------------ */
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_VALIDE     = 'VALIDE';
    public const STATUT_ANNULE     = 'ANNULE';

    public const LIBELLES_STATUT = [
        self::STATUT_EN_ATTENTE => 'En cours de validation',
        self::STATUT_VALIDE     => 'Validé',
        self::STATUT_ANNULE     => 'Annulé',
    ];

    /**
     * Rubrique cacao (information metier uniquement : elle ne conditionne
     * PLUS le circuit de validation, qui s'applique a toutes les sorties).
     */
    public const RUBRIQUES_CACAO = ['cacao', 'feves de cacao', 'fèves de cacao', 'graines de cacao'];

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

    /**
     * Unite de mesure (table stock_unite). La liste n'est plus fermee dans
     * l'entite : elle est validee contre le referentiel partage web + mobile
     * (App\Service\Stock\ReferentielStock::uniteValide) pour qu'une unite
     * ajoutee dans la table soit immediatement acceptee, sans redéploiement.
     */
    #[Assert\NotBlank(message: 'Choisissez une unité.')]
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

    // ------------------------------------------------------------------
    // BON DE SORTIE CACAO (validation par la Direction Generale)
    // ------------------------------------------------------------------

    /** Identifiant d'envoi mobile (idempotence) — même principe que SaisieStock. */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $clientUuid = null;

    /** Référence lisible du bon de sortie (BS-AAAAMMJJ-XXXXXX). */
    #[ORM\Column(name: 'bon_reference', length: 50, nullable: true)]
    private ?string $reference = null;

    /** Jeton de signature (sert d'identifiant confidentiel dans l'URL de signature). */
    #[ORM\Column(name: 'bon_token', length: 64, nullable: true)]
    private ?string $token = null;

    /**
     * Statut de validation du bon :
     *   null       -> sortie ordinaire (anciennes données, aucun bon émis)
     *   EN_ATTENTE -> bon émis, en attente de la signature du DG
     *   VALIDE     -> signé par la Direction Générale
     *   ANNULE     -> annulé / refusé
     */
    #[ORM\Column(name: 'bon_statut', length: 20, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(name: 'signe_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $signeAt = null;

    #[ORM\Column(name: 'signe_par', length: 255, nullable: true)]
    private ?string $signePar = null;

    /** Observation saisie par le signataire (facultative). */
    #[ORM\Column(name: 'observation_signature', length: 255, nullable: true)]
    private ?string $observationSignature = null;

    public function getClientUuid(): ?string { return $this->clientUuid; }
    public function setClientUuid(?string $v): static { $this->clientUuid = $v; return $this; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $v): static { $this->reference = $v; return $this; }

    public function getToken(): ?string { return $this->token; }
    public function setToken(?string $v): static { $this->token = $v; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $v): static { $this->statut = $v; return $this; }

    public function getSigneAt(): ?\DateTimeImmutable { return $this->signeAt; }
    public function setSigneAt(?\DateTimeImmutable $v): static { $this->signeAt = $v; return $this; }

    public function getSignePar(): ?string { return $this->signePar; }
    public function setSignePar(?string $v): static { $this->signePar = $v; return $this; }

    public function getObservationSignature(): ?string { return $this->observationSignature; }
    public function setObservationSignature(?string $v): static { $this->observationSignature = $v; return $this; }

    /** Le bon de sortie est-il en attente de signature du DG ? */
    public function isEnAttente(): bool { return $this->statut === self::STATUT_EN_ATTENTE; }

    /** Le bon de sortie est-il signé (validé) par la Direction Générale ? */
    public function isValide(): bool { return $this->statut === self::STATUT_VALIDE; }

    /** Libellé métier du statut (chaîne vide si sortie ordinaire sans bon). */
    public function getStatutLibelle(): string
    {
        return $this->statut === null ? '' : (self::LIBELLES_STATUT[$this->statut] ?? (string) $this->statut);
    }

    /** La rubrique de cette sortie est-elle le cacao (information metier, sans effet sur le circuit) ? */
    public function estRubriqueCacao(): bool
    {
        return self::rubriqueEstCacao($this->rubrique);
    }

    /** Comparaison normalisée (minuscules, sans accents) d'un libellé de rubrique avec la liste cacao. */
    public static function rubriqueEstCacao(?string $rubrique): bool
    {
        if ($rubrique === null || trim($rubrique) === '') {
            return false;
        }
        $n = mb_strtolower(trim($rubrique));
        $n = str_replace(['é', 'è', 'ê', 'ë'], 'e', $n);

        return str_contains($n, 'cacao') || in_array($n, self::RUBRIQUES_CACAO, true);
    }
}
