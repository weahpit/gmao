<?php

namespace App\Entity;

use App\Repository\ServicesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ServicesRepository::class)]
class Services
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $libelle = null;

    #[ORM\ManyToOne(inversedBy: 'service')]
    private ?Directions $code_direction = null;

    /**
     * @var Collection<int, MouvementEquipement>
     */
    #[ORM\OneToMany(targetEntity: MouvementEquipement::class, mappedBy: 'code_service')]
    private Collection $mouvementEquipements;

    /**
     * @var Collection<int, Poste>
     */
    #[ORM\OneToMany(targetEntity: Poste::class, mappedBy: 'code_service')]
    private Collection $postes;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'code_service')]
    private Collection $users;

    public function __construct()
    {
        $this->mouvementEquipements = new ArrayCollection();
        $this->postes = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getCodeDirection(): ?Directions
    {
        return $this->code_direction;
    }

    public function setCodeDirection(?Directions $code_direction): static
    {
        $this->code_direction = $code_direction;

        return $this;
    }

    /**
     * @return Collection<int, MouvementEquipement>
     */
    public function getMouvementEquipements(): Collection
    {
        return $this->mouvementEquipements;
    }

    public function addMouvementEquipement(MouvementEquipement $mouvementEquipement): static
    {
        if (!$this->mouvementEquipements->contains($mouvementEquipement)) {
            $this->mouvementEquipements->add($mouvementEquipement);
            $mouvementEquipement->setCodeService($this);
        }

        return $this;
    }

    public function removeMouvementEquipement(MouvementEquipement $mouvementEquipement): static
    {
        if ($this->mouvementEquipements->removeElement($mouvementEquipement)) {
            // set the owning side to null (unless already changed)
            if ($mouvementEquipement->getCodeService() === $this) {
                $mouvementEquipement->setCodeService(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Poste>
     */
    public function getPostes(): Collection
    {
        return $this->postes;
    }

    public function addPoste(Poste $poste): static
    {
        if (!$this->postes->contains($poste)) {
            $this->postes->add($poste);
            $poste->setCodeService($this);
        }

        return $this;
    }

    public function removePoste(Poste $poste): static
    {
        if ($this->postes->removeElement($poste)) {
            // set the owning side to null (unless already changed)
            if ($poste->getCodeService() === $this) {
                $poste->setCodeService(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setCodeService($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getCodeService() === $this) {
                $user->setCodeService(null);
            }
        }

        return $this;
    }
}
