<?php

namespace App\Entity;

use App\Repository\DemandeAchatRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Demande d'Achat (DA).
 *
 * Circuit :
 *   1. NOUVELLE    : un utilisateur d'un service crée la demande (adressée
 *                    au magasinier).
 *   2. SOUMISE     : le magasinier enregistre la demande et la soumet à la
 *                    Direction Générale pour validation.
 *   3. VALIDEE     : la Direction valide → confirmation au magasinier pour
 *                    permettre la sortie des articles.
 *      REFUSEE     : la Direction refuse (avec motif).
 *   4a. SERVIE     : le magasinier sort les articles (stock suffisant).
 *   4b. EN_COMMANDE: stock insuffisant → le magasinier passe commande chez un
 *                    fournisseur ; à réception, la demande peut être SERVIE.
 *
 * Le demandeur suit l'avancement via le jeton de suivi (token) et
 * l'historique (timeline) stocké au fil des étapes.
 */
#[ORM\Entity(repositoryClass: DemandeAchatRepository::class)]
#[ORM\Table(name: 'demande_achat')]
class DemandeAchat
{
    public const STATUT_NOUVELLE    = 'NOUVELLE';    // créée par le demandeur
    public const STATUT_SOUMISE     = 'SOUMISE';     // soumise à la DG par le magasinier
    public const STATUT_VALIDEE     = 'VALIDEE';     // validée par la DG
    public const STATUT_REFUSEE     = 'REFUSEE';     // refusée par la DG
    public const STATUT_EN_COMMANDE = 'EN_COMMANDE'; // commandée chez le fournisseur
    public const STATUT_SERVIE      = 'SERVIE';      // articles sortis
    public const STATUT_ANNULEE     = 'ANNULEE';

    /** Libellés métier (FR) affichés dans les interfaces. */
    public const LIBELLES = [
        self::STATUT_NOUVELLE    => 'Nouvelle demande',
        self::STATUT_SOUMISE     => 'Soumise à la direction',
        self::STATUT_VALIDEE     => 'Validée par la direction',
        self::STATUT_REFUSEE     => 'Refusée par la direction',
        self::STATUT_EN_COMMANDE => 'En commande fournisseur',
        self::STATUT_SERVIE      => 'Servie (articles sortis)',
        self::STATUT_ANNULEE     => 'Annulée',
    ];

    public const TRAITEMENT_SORTIE   = 'SORTIE_STOCK';
    public const TRAITEMENT_COMMANDE = 'COMMANDE_FOURNISSEUR';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    // Référence lisible (DA-AAAAMMJJ-XXXXXX).
    #[ORM\Column(length: 50, unique: true)]
    private ?string $numero = null;

    // Jeton de suivi (tracking côté demandeur).
    #[ORM\Column(length: 64, unique: true)]
    private ?string $token = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_NOUVELLE;

    #[ORM\ManyToOne]
    private ?Services $code_service = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $service = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $demandeur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $created_by = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    // Lignes demandées : [{code, designation, quantite, observation}].
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $lignes = [];

    // Motif / justification de la demande.
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motif = null;

    // Étape magasinier : enregistrement + soumission à la DG.
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $enregistre_par = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $soumise_at = null;

    // Étape direction : validation / refus.
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $valide_par = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $valide_at = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire_dg = null;

    // Traitement par le magasinier après validation.
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $type_traitement = null;

    #[ORM\ManyToOne]
    private ?Fournisseur $fournisseur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fournisseur_nom = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $commande_at = null;

    // Service final (sortie des articles).
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $servie_par = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $servie_at = null;

    // Référence du bon de sortie éventuellement généré.
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $bon_sortie_ref = null;

    // Historique (timeline) : [{statut, libelle, date, par, note}].
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $historique = [];

    public function __construct()
    {
        $this->created_at = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getNumero(): ?string { return $this->numero; }
    public function setNumero(string $numero): static { $this->numero = $numero; return $this; }

    public function getToken(): ?string { return $this->token; }
    public function setToken(string $token): static { $this->token = $token; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getStatutLabel(): string
    {
        return self::LIBELLES[$this->statut] ?? $this->statut;
    }

    public function isNouvelle(): bool    { return $this->statut === self::STATUT_NOUVELLE; }
    public function isSoumise(): bool     { return $this->statut === self::STATUT_SOUMISE; }
    public function isValidee(): bool     { return $this->statut === self::STATUT_VALIDEE; }
    public function isRefusee(): bool     { return $this->statut === self::STATUT_REFUSEE; }
    public function isEnCommande(): bool  { return $this->statut === self::STATUT_EN_COMMANDE; }
    public function isServie(): bool      { return $this->statut === self::STATUT_SERVIE; }

    public function getCodeService(): ?Services { return $this->code_service; }
    public function setCodeService(?Services $s): static { $this->code_service = $s; return $this; }

    public function getService(): ?string { return $this->service; }
    public function setService(?string $service): static { $this->service = $service; return $this; }

    public function getDemandeur(): ?string { return $this->demandeur; }
    public function setDemandeur(?string $d): static { $this->demandeur = $d; return $this; }

    public function getCreatedBy(): ?string { return $this->created_by; }
    public function setCreatedBy(?string $u): static { $this->created_by = $u; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->created_at; }
    public function setCreatedAt(\DateTimeImmutable $d): static { $this->created_at = $d; return $this; }

    public function getLignes(): ?array { return $this->lignes; }
    public function setLignes(?array $lignes): static { $this->lignes = $lignes; return $this; }

    public function getMotif(): ?string { return $this->motif; }
    public function setMotif(?string $m): static { $this->motif = $m; return $this; }

    public function getEnregistrePar(): ?string { return $this->enregistre_par; }
    public function setEnregistrePar(?string $u): static { $this->enregistre_par = $u; return $this; }

    public function getSoumiseAt(): ?\DateTimeImmutable { return $this->soumise_at; }
    public function setSoumiseAt(?\DateTimeImmutable $d): static { $this->soumise_at = $d; return $this; }

    public function getValidePar(): ?string { return $this->valide_par; }
    public function setValidePar(?string $u): static { $this->valide_par = $u; return $this; }

    public function getValideAt(): ?\DateTimeImmutable { return $this->valide_at; }
    public function setValideAt(?\DateTimeImmutable $d): static { $this->valide_at = $d; return $this; }

    public function getCommentaireDg(): ?string { return $this->commentaire_dg; }
    public function setCommentaireDg(?string $c): static { $this->commentaire_dg = $c; return $this; }

    public function getTypeTraitement(): ?string { return $this->type_traitement; }
    public function setTypeTraitement(?string $t): static { $this->type_traitement = $t; return $this; }

    public function getFournisseur(): ?Fournisseur { return $this->fournisseur; }
    public function setFournisseur(?Fournisseur $f): static { $this->fournisseur = $f; return $this; }

    public function getFournisseurNom(): ?string { return $this->fournisseur_nom; }
    public function setFournisseurNom(?string $n): static { $this->fournisseur_nom = $n; return $this; }

    public function getCommandeAt(): ?\DateTimeImmutable { return $this->commande_at; }
    public function setCommandeAt(?\DateTimeImmutable $d): static { $this->commande_at = $d; return $this; }

    public function getServiePar(): ?string { return $this->servie_par; }
    public function setServiePar(?string $u): static { $this->servie_par = $u; return $this; }

    public function getServieAt(): ?\DateTimeImmutable { return $this->servie_at; }
    public function setServieAt(?\DateTimeImmutable $d): static { $this->servie_at = $d; return $this; }

    public function getBonSortieRef(): ?string { return $this->bon_sortie_ref; }
    public function setBonSortieRef(?string $r): static { $this->bon_sortie_ref = $r; return $this; }

    public function getHistorique(): array { return $this->historique ?? []; }
    public function setHistorique(?array $h): static { $this->historique = $h; return $this; }

    /**
     * Ajoute une étape à la timeline de suivi.
     */
    public function addHistorique(string $statut, ?string $par = null, ?string $note = null): static
    {
        $h = $this->historique ?? [];
        $h[] = [
            'statut'  => $statut,
            'libelle' => self::LIBELLES[$statut] ?? $statut,
            'date'    => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'par'     => $par,
            'note'    => $note,
        ];
        $this->historique = $h;
        return $this;
    }

    /**
     * Lignes retenues par la direction (valide = true).
     */
    public function getLignesValidees(): array
    {
        return array_values(array_filter($this->lignes ?? [], static fn ($l) => ($l['valide'] ?? true) === true));
    }

    /**
     * Lignes a sortir du stock (validees, disponibles, non marquees a commander).
     */
    public function getLignesAServir(): array
    {
        return array_values(array_filter($this->getLignesValidees(), static fn ($l) => !($l['a_commander'] ?? false)));
    }

    /**
     * Lignes a commander chez le fournisseur (validees + marquees a commander).
     * C'est cette liste qui alimente le bon de commande fournisseur.
     */
    public function getLignesACommander(): array
    {
        return array_values(array_filter($this->getLignesValidees(), static fn ($l) => ($l['a_commander'] ?? false) === true));
    }

    /** Au moins une ligne doit etre commandee. */
    public function hasLignesACommander(): bool
    {
        return count($this->getLignesACommander()) > 0;
    }
}
