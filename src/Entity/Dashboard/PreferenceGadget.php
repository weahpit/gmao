<?php

namespace App\Entity\Dashboard;

use App\Entity\User;
use App\Repository\Dashboard\PreferenceGadgetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Etat d'un gadget de tableau de bord pour un utilisateur et un role donnes.
 *
 * Rien n'est cree tant que l'utilisateur ne touche a rien : un gadget absent
 * de cette table est considere comme affiche et actif (valeurs par defaut).
 */
#[ORM\Entity(repositoryClass: PreferenceGadgetRepository::class)]
#[ORM\Table(name: 'dashboard_preference_gadget')]
#[ORM\UniqueConstraint(name: 'uniq_dashboard_pref', columns: ['utilisateur_id', 'role_key', 'code'])]
class PreferenceGadget
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $utilisateur = null;

    #[ORM\Column(name: 'role_key', length: 40)]
    private string $role = '';

    #[ORM\Column(length: 60)]
    private string $code = '';

    #[ORM\Column]
    private bool $visible = true;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column(name: 'maj_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $majAt;

    public function __construct()
    {
        $this->majAt = new \DateTimeImmutable();
    }

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

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;
        $this->majAt = new \DateTimeImmutable();

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        $this->majAt = new \DateTimeImmutable();

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getMajAt(): \DateTimeImmutable
    {
        return $this->majAt;
    }
}
