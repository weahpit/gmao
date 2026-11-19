<?php

namespace App\Entity\Rh;

use App\Repository\Rh\ProgrammationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'rh_programmation')]
#[ORM\Entity(repositoryClass: ProgrammationRepository::class)]
class Programmation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date_programme = null;

    #[ORM\ManyToOne(inversedBy: 'programmations')]
    private ?HorairesQuarts $code_quart = null;

    #[ORM\ManyToOne(inversedBy: 'programmations')]
    private ?Equipe $code_equipe = null;

    #[ORM\ManyToOne(inversedBy: 'programmations')]
    private ?Mois $code_mois = null;

    /**
     * @var Collection<int, HorairesQuarts>
     */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateProgramme(): ?\DateTime
    {
        return $this->date_programme;
    }

    public function setDateProgramme(\DateTime $date_programme): static
    {
        $this->date_programme = $date_programme;

        return $this;
    }

    public function getCodeQuart(): ?HorairesQuarts
    {
        return $this->code_quart;
    }

    public function setCodeQuart(?HorairesQuarts $code_quart): static
    {
        $this->code_quart = $code_quart;

        return $this;
    }

    public function getCodeEquipe(): ?Equipe
    {
        return $this->code_equipe;
    }

    public function setCodeEquipe(?Equipe $code_equipe): static
    {
        $this->code_equipe = $code_equipe;

        return $this;
    }

    public function getCodeMois(): ?Mois
    {
        return $this->code_mois;
    }

    public function setCodeMois(?Mois $code_mois): static
    {
        $this->code_mois = $code_mois;

        return $this;
    }

}
