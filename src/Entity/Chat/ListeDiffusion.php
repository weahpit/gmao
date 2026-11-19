<?php

namespace App\Entity\Chat;

use App\Entity\Groupe;
use App\Entity\User;
use App\Repository\Chat\ListeDiffusionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Liste de diffusion de la messagerie : un canal de discussion nomme dont les
 * membres sont designes soit directement, soit par groupe d'utilisateurs.
 *
 * Les messages publies dans une liste portent `listeDiffusion` (et non plus
 * `destinataire`) : ils restent visibles par tous les membres, comme un
 * groupe de discussion, et n'apparaissent jamais dans le canal general.
 */
#[ORM\Entity(repositoryClass: ListeDiffusionRepository::class)]
#[ORM\Table(name: 'chat_liste_diffusion')]
#[ORM\UniqueConstraint(name: 'uniq_chat_liste_nom', columns: ['nom'])]
class ListeDiffusion
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 120)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'cree_par_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $creePar = null;

    /** Groupes entiers rattaches a la liste. */
    #[ORM\ManyToMany(targetEntity: Groupe::class)]
    #[ORM\JoinTable(name: 'chat_liste_diffusion_groupe')]
    private Collection $groupes;

    /** Membres designes individuellement. */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'chat_liste_diffusion_membre')]
    private Collection $membres;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->groupes = new ArrayCollection();
        $this->membres = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreePar(): ?User
    {
        return $this->creePar;
    }

    public function setCreePar(?User $creePar): static
    {
        $this->creePar = $creePar;

        return $this;
    }

    /** @return Collection<int, Groupe> */
    public function getGroupes(): Collection
    {
        return $this->groupes;
    }

    public function addGroupe(Groupe $groupe): static
    {
        if (!$this->groupes->contains($groupe)) {
            $this->groupes->add($groupe);
        }

        return $this;
    }

    public function removeGroupe(Groupe $groupe): static
    {
        $this->groupes->removeElement($groupe);

        return $this;
    }

    /** @return Collection<int, User> */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    public function addMembre(User $membre): static
    {
        if (!$this->membres->contains($membre)) {
            $this->membres->add($membre);
        }

        return $this;
    }

    public function removeMembre(User $membre): static
    {
        $this->membres->removeElement($membre);

        return $this;
    }

    /**
     * Tous les membres effectifs de la liste (membres directs + membres des
     * groupes rattaches), sans doublon, indexes par identifiant.
     *
     * @return array<string, User>
     */
    public function membresEffectifs(): array
    {
        $resultat = [];
        foreach ($this->membres as $membre) {
            $resultat[(string) $membre->getId()] = $membre;
        }
        foreach ($this->groupes as $groupe) {
            foreach ($groupe->getUsers() as $membre) {
                $resultat[(string) $membre->getId()] = $membre;
            }
        }

        return $resultat;
    }

    /** L'utilisateur fait-il partie de la liste (directement ou par groupe) ? */
    public function contient(User $user): bool
    {
        return isset($this->membresEffectifs()[(string) $user->getId()]);
    }
}
