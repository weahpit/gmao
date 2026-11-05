<?php

namespace App\Controller\Logistique;

use App\Entity\Logistique\Client;
use App\Entity\Logistique\Destination;
use App\Entity\Logistique\Navire;
use App\Entity\Logistique\Proprietaire;
use App\Entity\Production\Produit;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DossierEmbarquement extends  AbstractController
{
    #[Route('logistique/enregistrement-dossier', name: 'enregistrement_embarquement')]
    public function enregistrements_dossier(ManagerRegistry $registry): Response
    {
        return $this->render('logistique/embarquement.html.twig',
            [
                'produits'=>$registry->getRepository(Produit::class)->findAll(),
                'clients'=>$registry->getRepository(Client::class)->findAll(),
                'destinations'=>$registry->getRepository(Destination::class)->findAll(),
                'navires'=>$registry->getRepository(Navire::class)->findAll(),
                'proprietaires'=>$registry->getRepository(Proprietaire::class)->findAll(),
            ]
        );
    }
}
