<?php

namespace App\Controller\References;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StockUniteController extends AbstractController
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    #[Route('/UnitesStock', name: 'app_unites_stock')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('references/stock_unite/index.html.twig');
    }
}
