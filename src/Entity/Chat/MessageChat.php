<?php

namespace App\Entity\Chat;

use App\Entity\User;
use App\Repository\Chat\MessageChatRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Un message de la messagerie interne.
 *
 * - `destinataire` = null et `listeDiffusion` = null -> CANAL GÉNÉRAL
 * - `destinataire` = user                            -> conversation privée
 * - `listeDiffusion` = liste                         -> canal de la liste
 *   de diffusion (visible par tous ses membres)
 *
 * Un message peut porter une piece jointe : `type` vaut alors `image` ou
 * `audio` et `fichier` contient le chemin relatif dans public/uploads/chat.
 */
#[ORM\Entity(repositoryClass: MessageChatRepository::class)]
#[ORM\Table(name: 'message_chat')]
#[ORM\Index(name: 'idx_chat_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_chat_destinataire_lu', columns: ['destinataire_id', 'lu'])]
#[ORM\Index(name: 'idx_chat_liste', columns: ['liste_diffusion_id'])]
#[ORM\Index(name: 'idx_chat_client', columns: ['client_id'])]
class MessageChat
{
    /** Longueur maximale d'un message (caractères). */
    public const LONGUEUR_MAX = 2000;

    /** Types de message acceptes. */
    public const TYPE_TEXTE = 'texte';
    public const TYPE_IMAGE = 'image';
    public const TYPE_AUDIO = 'audio';

    /** Duree maximale d'un message vocal, en secondes. */
    public const DUREE_AUDIO_MAX = 60;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'expediteur_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $expediteur = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'destinataire_id', nullable: true, onDelete: 'CASCADE')]
    private ?User $destinataire = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $contenu = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $created_at = null;

    /** Lu par le destinataire (toujours vrai pour le canal général). */
    #[ORM\Column]
    private bool $lu = false;

    /** Texte, image ou audio. */
    #[ORM\Column(length: 10, options: ['default' => 'texte'])]
    private string $type = self::TYPE_TEXTE;

    /** Chemin relatif de la piece jointe (ex. 2026/11/xxx.m4a). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fichier = null;

    /** Nom d'origine du fichier, pour l'affichage et le telechargement. */
    #[ORM\Column(name: 'fichier_nom', length: 255, nullable: true)]
    private ?string $fichierNom = null;

    #[ORM\Column(name: 'fichier_mime', length: 100, nullable: true)]
    private ?string $fichierMime = null;

    /** Duree du vocal en secondes (null pour les autres types). */
    #[ORM\Column(nullable: true)]
    private ?int $duree = null;

    /** Premier acces d'un destinataire au message (il est arrive sur son appareil). */
    #[ORM\Column(name: 'remis_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $remisAt = null;

    /** Ouverture du message par le destinataire (conversation privee). */
    #[ORM\Column(name: 'lu_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $luAt = null;

    /**
     * Identifiant genere par l'application a l'envoi. Il permet de ne pas
     * creer de doublon quand un envoi est repete apres une coupure reseau
     * (le serveur avait deja enregistre le message, mais la reponse s'est perdue).
     */
    #[ORM\Column(name: 'client_id', length: 64, nullable: true)]
    private ?string $clientId = null;

    /** Liste de diffusion concernee, le cas echeant. */
    #[ORM\ManyToOne(targetEntity: ListeDiffusion::class)]
    #[ORM\JoinColumn(name: 'liste_diffusion_id', nullable: true, onDelete: 'CASCADE')]
    private ?ListeDiffusion $listeDiffusion = null;

    public function __construct()
    {
        $this->created_at = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getExpediteur(): ?User
    {
        return $this->expediteur;
    }

    public function setExpediteur(?User $expediteur): static
    {
        $this->expediteur = $expediteur;

        return $this;
    }

    public function getDestinataire(): ?User
    {
        return $this->destinataire;
    }

    public function setDestinataire(?User $destinataire): static
    {
        $this->destinataire = $destinataire;

        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function isLu(): bool
    {
        return $this->lu;
    }

    public function setLu(bool $lu): static
    {
        $this->lu = $lu;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getFichier(): ?string
    {
        return $this->fichier;
    }

    public function setFichier(?string $fichier): static
    {
        $this->fichier = $fichier;

        return $this;
    }

    public function getFichierNom(): ?string
    {
        return $this->fichierNom;
    }

    public function setFichierNom(?string $fichierNom): static
    {
        $this->fichierNom = $fichierNom;

        return $this;
    }

    public function getFichierMime(): ?string
    {
        return $this->fichierMime;
    }

    public function setFichierMime(?string $fichierMime): static
    {
        $this->fichierMime = $fichierMime;

        return $this;
    }

    public function getDuree(): ?int
    {
        return $this->duree;
    }

    public function setDuree(?int $duree): static
    {
        $this->duree = $duree;

        return $this;
    }

    public function getListeDiffusion(): ?ListeDiffusion
    {
        return $this->listeDiffusion;
    }

    public function setListeDiffusion(?ListeDiffusion $listeDiffusion): static
    {
        $this->listeDiffusion = $listeDiffusion;

        return $this;
    }

    public function getRemisAt(): ?\DateTimeImmutable
    {
        return $this->remisAt;
    }

    public function setRemisAt(?\DateTimeImmutable $remisAt): static
    {
        $this->remisAt = $remisAt;

        return $this;
    }

    public function getLuAt(): ?\DateTimeImmutable
    {
        return $this->luAt;
    }

    public function setLuAt(?\DateTimeImmutable $luAt): static
    {
        $this->luAt = $luAt;

        return $this;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(?string $clientId): static
    {
        $clientId = $clientId === null ? null : trim($clientId);
        $this->clientId = ($clientId === null || $clientId === '') ? null : mb_substr($clientId, 0, 64);

        return $this;
    }

    /**
     * Etat d'un message PRIVE vu par son expediteur :
     * `envoye` (enregistre sur le serveur), `remis` (arrive chez le
     * destinataire) ou `lu` (conversation ouverte).
     * Pour un canal de groupe, l'etat est calcule par AccuseLecture.
     */
    public function statut(): string
    {
        if ($this->lu || $this->luAt !== null) {
            return 'lu';
        }

        return $this->remisAt !== null ? 'remis' : 'envoye';
    }

    /** Le message appartient-il au canal général ? */
    public function isGeneral(): bool
    {
        return $this->destinataire === null && $this->listeDiffusion === null;
    }

    /** Le message est-il une piece jointe (image ou vocal) ? */
    public function aUnePieceJointe(): bool
    {
        return $this->type !== self::TYPE_TEXTE && null !== $this->fichier;
    }
}
