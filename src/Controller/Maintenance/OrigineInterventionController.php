<?php

namespace App\Controller\Maintenance;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OrigineInterventionController extends AbstractController
{
    #[Route('/OriginesIntervention', name: 'app_origines_intervention')]
    public function index(): Response
    {
        return $this->render('maintenance/origine_intervention/index.html.twig');
    }
}
