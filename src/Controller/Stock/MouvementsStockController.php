<?php

namespace App\Controller\Stock;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MouvementsStockController extends AbstractController
{
    #[Route('/OperationsEntrees/Stock', name: 'app_mouvements_stock_entree')]
    public function entree(): Response
    {
        return $this->render('mouvements_stock/entree.html.twig');
    }

    #[Route('/OperationsSorties/Stock', name: 'app_mouvements_stock_sortie')]
    public function sorties(): Response
    {
        return $this->render('mouvements_stock/sortie.html.twig');
    }
    #[Route('/OperationsJournalieres/Stock', name: 'app_mouvements_operations_jour')]
    public function operationJour(): Response
    {
        return $this->render('mouvements_stock/operations_jour.html.twig');
    }
}
