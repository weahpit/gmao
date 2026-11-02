<?php

namespace App\Controller\Maintenance;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DemandeInterventionController extends AbstractController
{
    #[Route('/demande/intervention', name: 'app_demande_intervention')]
    public function index(): Response
    {
        return $this->render('demande_intervention/index.html.twig', [
            'controller_name' => 'DemandeInterventionController',
        ]);
    }
}
