<?php

namespace App\Entity\Chat;

use App\Entity\User;
use App\Repository\Chat\DeviceTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Jeton Firebase Cloud Messaging (FCM) d'un appareil.
 *
 * Un utilisateur peut avoir plusieurs appareils (telephone, tablette).
 * Le jeton est unique : s'il est enregistre a nouveau, il est simplement
 * rattache a l'utilisateur courant et sa date de mise a jour change.
 */
#[ORM\Entity(repositoryClass: DeviceTokenRepository::class)]
#[ORM\Table(name: 'device_token')]
#[ORM\UniqueConstraint(name: 'uniq_device_token', columns: ['token'])]
#[ORM\Index(name: 'idx_device_token_user', columns: ['user_id'])]
class DeviceToken
{
    public const PLATEFORME_ANDROID = 'android';
    public const PLATEFORME_IOS = 'ios';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $token = null;

    #[ORM\Column(length: 20, options: ['default' => 'android'])]
    private string $plateforme = self::PLATEFORME_ANDROID;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getPlateforme(): string
    {
        return $this->plateforme;
    }

    public function setPlateforme(string $plateforme): static
    {
        $this->plateforme = $plateforme === self::PLATEFORME_IOS ? self::PLATEFORME_IOS : self::PLATEFORME_ANDROID;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function toucher(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
