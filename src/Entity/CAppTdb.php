<?php

namespace App\Entity;

use App\Repository\CAppTdbRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CAppTdbRepository::class)]
class CAppTdb
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $ordre = null;

    #[ORM\ManyToOne(inversedBy: 'cAppTdbs')]
    private ?Widget $code_gadget = null;

    #[ORM\ManyToOne(inversedBy: 'cAppTdbs')]
    private ?User $code_user = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(?int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getCodeGadget(): ?Widget
    {
        return $this->code_gadget;
    }

    public function setCodeGadget(?Widget $code_gadget): static
    {
        $this->code_gadget = $code_gadget;

        return $this;
    }

    public function getCodeUser(): ?User
    {
        return $this->code_user;
    }

    public function setCodeUser(?User $code_user): static
    {
        $this->code_user = $code_user;

        return $this;
    }

}
