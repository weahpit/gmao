<?php

namespace App\Entity\Stock;

use App\Repository\Stock\OrdreUsinageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * ORDRE D'USINAGE : l'association metier entre UN OU PLUSIEURS camions de cacao
 * decharges et UN GROUPE DE LOTS fabriques (feves).
 *
 * C'est la forme simplifiee de la tracabilite : l'operateur selectionne
 * plusieurs lots, coche un ou plusieurs camions, valide, et l'application genere
 * un numero d'ordre d'usinage (OU-AAAA-0001). Chaque couple lot x camion est
 * ensuite rattache via une ligne de LotDechargement qui porte la reference de
 * l'ordre.
 */
#[ORM\Entity(repositoryClass: OrdreUsinageRepository::class)]
#[ORM\Table(name: 'stock_ordre_usinage')]
#[ORM\UniqueConstraint(name: 'uniq_ordre_usinage_numero', columns: ['numero'])]
#[ORM\Index(name: 'idx_ordre_usinage_dech', columns: ['dechargement_id'])]
class OrdreUsinage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** Numero genere automatiquement, ex. OU-2026-0001. */
    #[ORM\Column(length: 30)]
    private ?string $numero = null;

    /**
     * Camions (bons de dechargement) utilises pour ce groupe de lots.
     *
     * @var Collection<int, DechargementCacao>
     */
    #[ORM\ManyToMany(targetEntity: DechargementCacao::class)]
    #[ORM\JoinTable(
        name: 'stock_ordre_usinage_camion',
        joinColumns: [new ORM\JoinColumn(name: 'ordre_usinage_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'dechargement_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    )]
    #[ORM\OrderBy(['dateDechargement' => 'DESC'])]
    private Collection $camions;

    /**
     * Camion historique (premier camion de l'ordre). Conserve pour la
     * compatibilite des anciens ecrans et des liens existants ; il est
     * desormais facultatif car un ordre peut couvrir plusieurs camions.
     */
    #[ORM\ManyToOne(targetEntity: DechargementCacao::class)]
    #[ORM\JoinColumn(name: 'dechargement_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?DechargementCacao $dechargement = null;

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(name: 'created_by', length: 180, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observation = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
        $this->camions = new ArrayCollection();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getNumero(): ?string { return $this->numero; }
    public function setNumero(string $numero): static { $this->numero = $numero; return $this; }

    /** @return Collection<int, DechargementCacao> */
    public function getCamions(): Collection
    {
        return $this->camions;
    }

    public function addCamion(DechargementCacao $camion): static
    {
        if (!$this->camions->contains($camion)) {
            $this->camions->add($camion);
        }

        if ($this->dechargement === null) {
            $this->dechargement = $camion;
        }

        return $this;
    }

    public function removeCamion(DechargementCacao $camion): static
    {
        if ($this->camions->removeElement($camion) && $this->dechargement === $camion) {
            $this->dechargement = $this->camions->first() ?: null;
        }

        return $this;
    }

    /**
     * Camions de l'ordre, avec repli sur le camion historique.
     *
     * @return DechargementCacao[]
     */
    public function camionsEffectifs(): array
    {
        $camions = $this->camions->toArray();
        if ($camions === [] && $this->dechargement !== null) {
            $camions = [$this->dechargement];
        }

        return $camions;
    }

    /** Codes des camions, ex. "BC-0042, BC-0051". */
    public function getCamionsLibelle(string $separateur = ', '): string
    {
        $codes = [];
        foreach ($this->camionsEffectifs() as $camion) {
            $codes[] = $camion->getCode() ?: 'sans numero';
        }

        return $codes === [] ? '-' : implode($separateur, $codes);
    }

    public function getDechargement(): ?DechargementCacao { return $this->dechargement; }
    public function setDechargement(?DechargementCacao $dechargement): static { $this->dechargement = $dechargement; return $this; }

    public function getDateCreation(): ?\DateTimeImmutable { return $this->dateCreation; }
    public function setDateCreation(\DateTimeImmutable $date): static { $this->dateCreation = $date; return $this; }

    public function getCreatedBy(): ?string { return $this->createdBy; }
    public function setCreatedBy(?string $createdBy): static { $this->createdBy = $createdBy; return $this; }

    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $observation): static { $this->observation = $observation; return $this; }

    public function __toString(): string
    {
        return (string) $this->numero;
    }
}
