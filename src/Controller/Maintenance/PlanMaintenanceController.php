<?php

namespace App\Controller\Maintenance;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PlanMaintenanceController extends AbstractController
{
    // Prefixe de route "preventive_" -> active l'entree "Plans preventifs" dans la sidebar
    #[Route('/maintenance/plans-preventifs', name: 'preventive_plan_maintenance')]
    public function index(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        return $this->render('maintenance/plan_maintenance/index.html.twig');
    }
}
