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
 * - `destinataire` = null  -> message du CANAL GÉNÉRAL (visible par tous)
 * - `destinataire` = user  -> conversation privée entre l'expéditeur et lui
 */
#[ORM\Entity(repositoryClass: MessageChatRepository::class)]
#[ORM\Table(name: 'message_chat')]
#[ORM\Index(name: 'idx_chat_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_chat_destinataire_lu', columns: ['destinataire_id', 'lu'])]
class MessageChat
{
    /** Longueur maximale d'un message (caractères). */
    public const LONGUEUR_MAX = 2000;

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

    /** Le message appartient-il au canal général ? */
    public function isGeneral(): bool
    {
        return $this->destinataire === null;
    }
}
