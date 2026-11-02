<?php

namespace App\Controller\Maintenance;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ResultatInterventionController extends AbstractController
{
    #[Route('/ResultatsIntervention', name: 'app_resultats_intervention')]
    public function index(): Response
    {
        return $this->render('maintenance/resultat_intervention/index.html.twig');
    }
}
