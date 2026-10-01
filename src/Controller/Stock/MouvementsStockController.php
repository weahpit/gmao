<?php

namespace App\Controller\Stock;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MouvementsStockController extends AbstractController
{
    #[Route('/mouvements/stock', name: 'app_mouvements_stock')]
    public function index(): Response
    {
        return $this->render('mouvements_stock/index.html.twig', [
            'controller_name' => 'MouvementsStockController',
        ]);
    }
}
