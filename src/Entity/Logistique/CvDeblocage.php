<?php

namespace App\Entity\Logistique;

use App\Repository\Logistique\CvDeblocageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'logistique.cv_deblocage')]
#[ORM\Entity(repositoryClass: CvDeblocageRepository::class)]
class CvDeblocage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 25)]
    private ?string $numero_cv = null;

    #[ORM\Column(nullable: true)]
    private ?float $poids = null;

    /**
     * @var Collection<Uuid, CvExecution>
     */
    #[ORM\OneToMany(targetEntity: CvExecution::class, mappedBy: 'code_cv_deblocage')]
    private Collection $cvExecutions;

    public function __construct()
    {
        $this->cvExecutions = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNumeroCv(): ?string
    {
        return $this->numero_cv;
    }

    public function setNumeroCv(string $numero_cv): static
    {
        $this->numero_cv = $numero_cv;

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

    /**
     * @return Collection<Uuid, CvExecution>
     */
    public function getCvExecutions(): Collection
    {
        return $this->cvExecutions;
    }

    public function addCvExecution(CvExecution $cvExecution): static
    {
        if (!$this->cvExecutions->contains($cvExecution)) {
            $this->cvExecutions->add($cvExecution);
            $cvExecution->setCodeCvDeblocage($this);
        }

        return $this;
    }

    public function removeCvExecution(CvExecution $cvExecution): static
    {
        if ($this->cvExecutions->removeElement($cvExecution)) {
            // set the owning side to null (unless already changed)
            if ($cvExecution->getCodeCvDeblocage() === $this) {
                $cvExecution->setCodeCvDeblocage(null);
            }
        }

        return $this;
    }
}
