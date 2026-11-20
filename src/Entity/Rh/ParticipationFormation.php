<?php

namespace App\Entity\Rh;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Presence d'un employe a une formation et resultat obtenu. */
#[ORM\Entity]
#[ORM\Table(name: 'rh_participation_formation')]
#[ORM\UniqueConstraint(name: 'uniq_participation', columns: ['formation_id', 'employe_id'])]
class ParticipationFormation
{
    public const EN_COURS = 'en_cours';
    public const REUSSI = 'reussi';
    public const ECHOUE = 'echoue';
    public const ABSENT = 'absent';

    public const RESULTATS = [
        self::EN_COURS => 'En cours',
        self::REUSSI => 'Reussi',
        self::ECHOUE => 'Echoue',
        self::ABSENT => 'Absent',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Formation::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Formation $formation = null;

    #[ORM\ManyToOne(targetEntity: Employe::class, inversedBy: 'formations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    #[ORM\Column(length: 20)]
    private string $resultat = self::EN_COURS;

    #[ORM\Column(nullable: true)]
    private ?int $note = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;

        return $this;
    }

    public function getEmploye(): ?Employe
    {
        return $this->employe;
    }

    public function setEmploye(?Employe $employe): static
    {
        $this->employe = $employe;

        return $this;
    }

    public function getResultat(): string
    {
        return $this->resultat;
    }

    public function setResultat(string $resultat): static
    {
        $this->resultat = isset(self::RESULTATS[$resultat]) ? $resultat : self::EN_COURS;

        return $this;
    }

    public function getResultatLibelle(): string
    {
        return self::RESULTATS[$this->resultat] ?? $this->resultat;
    }

    public function getNote(): ?int
    {
        return $this->note;
    }

    public function setNote(?int $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire === null ? null : mb_strtoupper(trim($commentaire), 'UTF-8');

        return $this;
    }
}
