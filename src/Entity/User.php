<?php

namespace App\Entity;

use App\Entity\Maintenance\FicheIntervention;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $matricule = null;

    #[ORM\Column(length: 50)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenoms = null;

    #[ORM\Column(length: 50)]
    private ?string $mobile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(nullable: true)]
    private ?bool $active = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    private ?Groupe $code_groupe = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    private ?Poste $code_poste = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    private ?Services $code_service = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    private ?Directions $code_direction = null;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'destinataire')]
    private Collection $notifications;

    /**
     * @var Collection<Uuid, CAppTdb>
     */
    #[ORM\OneToMany(targetEntity: CAppTdb::class, mappedBy: 'code_user')]
    private Collection $cAppTdbs;

    #[ORM\Column(nullable: true)]
    private ?bool $admin = null;

    /**
     * @var Collection<int, FicheIntervention>
     */
    #[ORM\ManyToMany(targetEntity: FicheIntervention::class, mappedBy: 'code_technicien')]
    private Collection $ficheInterventions;

    #[ORM\Column(nullable: true)]
    private ?bool $bloque = null;

    #[ORM\Column(nullable: true)]
    private ?bool $signataire = null;

    public function __construct()
    {
        $this->notifications = new ArrayCollection();
        $this->cAppTdbs = new ArrayCollection();
        $this->ficheInterventions = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(?string $matricule): static
    {
        $this->matricule = $matricule;

        return $this;
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

    public function getPrenoms(): ?string
    {
        return $this->prenoms;
    }

    public function setPrenoms(string $prenoms): static
    {
        $this->prenoms = $prenoms;

        return $this;
    }

    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    public function setMobile(string $mobile): static
    {
        $this->mobile = $mobile;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getCodeGroupe(): ?Groupe
    {
        return $this->code_groupe;
    }

    public function setCodeGroupe(?Groupe $code_groupe): static
    {
        $this->code_groupe = $code_groupe;

        return $this;
    }

    public function getCodePoste(): ?Poste
    {
        return $this->code_poste;
    }

    public function setCodePoste(?Poste $code_poste): static
    {
        $this->code_poste = $code_poste;

        return $this;
    }

    public function getCodeService(): ?Services
    {
        return $this->code_service;
    }

    public function setCodeService(?Services $code_service): static
    {
        $this->code_service = $code_service;

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
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setDestinataire($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getDestinataire() === $this) {
                $notification->setDestinataire(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<Uuid, CAppTdb>
     */
    public function getCAppTdbs(): Collection
    {
        return $this->cAppTdbs;
    }

    public function addCAppTdb(CAppTdb $cAppTdb): static
    {
        if (!$this->cAppTdbs->contains($cAppTdb)) {
            $this->cAppTdbs->add($cAppTdb);
            $cAppTdb->setCodeUser($this);
        }

        return $this;
    }

    public function removeCAppTdb(CAppTdb $cAppTdb): static
    {
        if ($this->cAppTdbs->removeElement($cAppTdb)) {
            // set the owning side to null (unless already changed)
            if ($cAppTdb->getCodeUser() === $this) {
                $cAppTdb->setCodeUser(null);
            }
        }

        return $this;
    }

    public function isAdmin(): ?bool
    {
        return $this->admin;
    }

    public function setAdmin(?bool $admin): static
    {
        $this->admin = $admin;

        return $this;
    }

    /**
     * @return Collection<int, FicheIntervention>
     */
    public function getFicheInterventions(): Collection
    {
        return $this->ficheInterventions;
    }

    public function addFicheIntervention(FicheIntervention $ficheIntervention): static
    {
        if (!$this->ficheInterventions->contains($ficheIntervention)) {
            $this->ficheInterventions->add($ficheIntervention);
            $ficheIntervention->addCodeTechnicien($this);
        }

        return $this;
    }

    public function removeFicheIntervention(FicheIntervention $ficheIntervention): static
    {
        if ($this->ficheInterventions->removeElement($ficheIntervention)) {
            $ficheIntervention->removeCodeTechnicien($this);
        }

        return $this;
    }

    public function __toString(): string
    {
        return  $this->nom . " " . $this->prenoms;
    }

    public function isBloque(): ?bool
    {
        return $this->bloque;
    }

    public function setBloque(bool $bloque): static
    {
        $this->bloque = $bloque;

        return $this;
    }

    public function isSignataire(): ?bool
    {
        return $this->signataire;
    }

    public function setSignataire(bool $signataire): static
    {
        $this->signataire = $signataire;

        return $this;
    }
}
