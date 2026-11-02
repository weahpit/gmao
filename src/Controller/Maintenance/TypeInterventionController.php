<?php

namespace App\Controller\Maintenance;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TypeInterventionController extends AbstractController
{
    #[Route('/TypesIntervention', name: 'app_types_intervention')]
    public function index(): Response
    {
        return $this->render('maintenance/type_intervention/index.html.twig');
    }
}
