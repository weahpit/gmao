<?php

namespace App\Controller\Maintenance;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FicheInterventionController extends AbstractController
{
    #[Route('/maintenance/fiche/intervention', name: 'app_maintenance_fiche_intervention')]
    public function index(): Response
    {
        return $this->render('maintenance/fiche_intervention/index.html.twig', [
            'controller_name' => 'FicheInterventionController',
        ]);
    }
}
