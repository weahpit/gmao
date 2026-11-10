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

class Lots extends  AbstractController
{
    #[Route('logistique/ProductionLots', name: 'production_lot')]
    public function production_lot(ManagerRegistry $registry): Response
    {
        return $this->render('logistique/lots/index.html.twig',
            [

            ]
        );
    }
}
