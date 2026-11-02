<?php

namespace App\Entity\Logistique;

use App\Repository\Logistique\DetailsModeleDocRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'logistique.details_modele')]
#[ORM\Entity(repositoryClass: DetailsModeleDocRepository::class)]
class DetailsModeleDoc
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'detailsModeleDocs')]
    private ?DocType $code_doc = null;

    #[ORM\ManyToOne(inversedBy: 'detailsModeleDocs')]
    private ?ModeleDoc $code_modele = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCodeDoc(): ?DocType
    {
        return $this->code_doc;
    }

    public function setCodeDoc(?DocType $code_doc): static
    {
        $this->code_doc = $code_doc;

        return $this;
    }

    public function getCodeModele(): ?ModeleDoc
    {
        return $this->code_modele;
    }

    public function setCodeModele(?ModeleDoc $code_modele): static
    {
        $this->code_modele = $code_modele;

        return $this;
    }
}
