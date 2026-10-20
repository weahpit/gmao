<?php

namespace App\Controller\Maintenance;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StatutInterventionController extends AbstractController
{
    #[Route('/StatutsIntervention', name: 'app_statuts_intervention')]
    public function index(): Response
    {
        return $this->render('maintenance/statut_intervention/index.html.twig');
    }
}
