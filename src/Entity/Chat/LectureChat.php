<?php

namespace App\Entity\Chat;

use App\Entity\User;
use App\Repository\Chat\LectureChatRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Repère de lecture d'un utilisateur sur un canal.
 *
 * Sert au CANAL GÉNÉRAL (tout le monde lit le même fil) : on mémorise la date
 * du dernier message vu pour compter les non-lus sans stocker un drapeau par
 * message et par personne.
 */
#[ORM\Entity(repositoryClass: LectureChatRepository::class)]
#[ORM\Table(name: 'chat_lecture')]
#[ORM\UniqueConstraint(name: 'uniq_chat_lecture', columns: ['utilisateur_id', 'canal'])]
class LectureChat
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $utilisateur = null;

    /**
     * Identifiant du canal : "general", "liste:<uuid>" ou l'uuid du
     * correspondant d'une conversation privee. Un canal de liste mesure
     * 42 caracteres (6 + uuid) : la colonne doit donc être plus large que 40.
     */
    #[ORM\Column(length: 64)]
    private ?string $canal = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dernier_lu_at = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?User $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getCanal(): ?string
    {
        return $this->canal;
    }

    public function setCanal(string $canal): static
    {
        $this->canal = $canal;

        return $this;
    }

    public function getDernierLuAt(): ?\DateTimeImmutable
    {
        return $this->dernier_lu_at;
    }

    public function setDernierLuAt(\DateTimeImmutable $dernier_lu_at): static
    {
        $this->dernier_lu_at = $dernier_lu_at;

        return $this;
    }
}
