<?php

namespace App\Entity\Stock;

use App\Repository\Stock\JournalMouvementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Journal d'audit des actions effectuees sur les mouvements de stock.
 *
 * Une ligne est ajoutee a chaque modification ou suppression d'un mouvement
 * (pieces de rechange ou cacao). Le journal est append-only : on n'y supprime
 * jamais rien et on ne le modifie pas.
 */
#[ORM\Entity(repositoryClass: JournalMouvementRepository::class)]
#[ORM\Table(name: 'journal_mouvement')]
#[ORM\Index(name: 'IDX_JOURNAL_MVT_DATE', columns: ['date_action'])]
class JournalMouvement
{
    public const ACTION_MODIFICATION = 'MODIFICATION';
    public const ACTION_SUPPRESSION  = 'SUPPRESSION';

    public const FAMILLE_PIECES = 'PIECES';
    public const FAMILLE_CACAO  = 'CACAO';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /** Modification ou suppression. */
    #[ORM\Column(name: 'action', length: 20)]
    private ?string $action = null;

    /** PIECES ou CACAO. */
    #[ORM\Column(name: 'famille', length: 20)]
    private ?string $famille = null;

    /** Identifiant d'origine du mouvement (conserve meme apres suppression). */
    #[ORM\Column(name: 'cible_id', type: 'uuid', nullable: true)]
    private ?Uuid $cibleId = null;

    /** Libelle lisible du mouvement concerne (article, rubrique + quantite...). */
    #[ORM\Column(name: 'cible_libelle', length: 255, nullable: true)]
    private ?string $cibleLibelle = null;

    /** Etat du mouvement avant l'action (JSON). */
    #[ORM\Column(name: 'avant', type: 'json', nullable: true)]
    private ?array $avant = null;

    /** Etat du mouvement apres l'action (JSON, null en cas de suppression). */
    #[ORM\Column(name: 'apres', type: 'json', nullable: true)]
    private ?array $apres = null;

    /** Identifiant de l'administrateur auteur de l'action. */
    #[ORM\Column(name: 'auteur_id', type: 'uuid', nullable: true)]
    private ?Uuid $auteurId = null;

    #[ORM\Column(name: 'auteur_nom', length: 180, nullable: true)]
    private ?string $auteurNom = null;

    #[ORM\Column(name: 'date_action', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateAction = null;

    public function __construct()
    {
        $this->dateAction = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getAction(): ?string { return $this->action; }
    public function setAction(?string $action): static { $this->action = $action; return $this; }

    public function getFamille(): ?string { return $this->famille; }
    public function setFamille(?string $famille): static { $this->famille = $famille; return $this; }

    public function getCibleId(): ?Uuid { return $this->cibleId; }
    public function setCibleId(?Uuid $cibleId): static { $this->cibleId = $cibleId; return $this; }

    public function getCibleLibelle(): ?string { return $this->cibleLibelle; }
    public function setCibleLibelle(?string $cibleLibelle): static { $this->cibleLibelle = $cibleLibelle; return $this; }

    public function getAvant(): ?array { return $this->avant; }
    public function setAvant(?array $avant): static { $this->avant = $avant; return $this; }

    public function getApres(): ?array { return $this->apres; }
    public function setApres(?array $apres): static { $this->apres = $apres; return $this; }

    public function getAuteurId(): ?Uuid { return $this->auteurId; }
    public function setAuteurId(?Uuid $auteurId): static { $this->auteurId = $auteurId; return $this; }

    public function getAuteurNom(): ?string { return $this->auteurNom; }
    public function setAuteurNom(?string $auteurNom): static { $this->auteurNom = $auteurNom; return $this; }

    public function getDateAction(): ?\DateTimeImmutable { return $this->dateAction; }
    public function setDateAction(\DateTimeImmutable $dateAction): static { $this->dateAction = $dateAction; return $this; }
}
