<?php

namespace App\Controller\Maintenance;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NatureController extends AbstractController
{
    #[Route('/NaturesIntervention', name: 'app_natures_intervention')]
    public function index(): Response
    {
        return $this->render('maintenance/nature_intervention/index.html.twig');
    }
}
