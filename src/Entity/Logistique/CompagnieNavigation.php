<?php
namespace App\Entity\Logistique;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
#[ORM\Entity]
#[ORM\Table(name: 'logistique.compagnie_navigation')]
class CompagnieNavigation
{
    #[ORM\Id] #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;
    #[ORM\Column(length: 150)] private string $libelle = '';
    public function __construct() { $this->id = Uuid::v4(); }
    public function getId(): Uuid { return $this->id; }
    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $v): static { $this->libelle = trim($v); return $this; }
}
