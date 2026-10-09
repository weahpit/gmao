<?php

namespace App\Controller\Maintenance;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AlerteAnomalieController extends AbstractController
{
    #[Route('/AlertesAnomalies', name: 'app_maintenance_alerte_anomalie')]
    public function index(): Response
    {
        return $this->render('maintenance/alerte_anomalie/index.html.twig', [
            'controller_name' => 'AlerteAnomalieController',
        ]);
    }
}
