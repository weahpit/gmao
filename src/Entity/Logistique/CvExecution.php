<?php

namespace App\Entity\Logistique;

use App\Repository\Logistique\CvExecutionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'logistique.cv_execution')]
#[ORM\Entity(repositoryClass: CvExecutionRepository::class)]
class CvExecution
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 25)]
    private ?string $numero_cv_execution = null;

    #[ORM\Column(nullable: true)]
    private ?float $poids = null;

    #[ORM\ManyToOne(inversedBy: 'cvExecutions')]
    private ?CvDeblocage $code_cv_deblocage = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNumeroCvExecution(): ?string
    {
        return $this->numero_cv_execution;
    }

    public function setNumeroCvExecution(string $numero_cv_execution): static
    {
        $this->numero_cv_execution = $numero_cv_execution;

        return $this;
    }

    public function getPoids(): ?float
    {
        return $this->poids;
    }

    public function setPoids(?float $poids): static
    {
        $this->poids = $poids;

        return $this;
    }

    public function getCodeCvDeblocage(): ?CvDeblocage
    {
        return $this->code_cv_deblocage;
    }

    public function setCodeCvDeblocage(?CvDeblocage $code_cv_deblocage): static
    {
        $this->code_cv_deblocage = $code_cv_deblocage;

        return $this;
    }
}
