<?php

namespace App\Controller\Maintenance;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StatutAlerteController extends AbstractController
{
    #[Route('/StatutAlertes', name: 'app_statut_alertes')]
    public function index(): Response
    {
        return $this->render('maintenance/statut_alerte/index.html.twig');
    }
}
