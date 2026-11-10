<?php

namespace App\Controller\Production;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProduitController extends AbstractController
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    #[Route('/Produits', name: 'app_produit')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('production/produit/index.html.twig');
    }
}
