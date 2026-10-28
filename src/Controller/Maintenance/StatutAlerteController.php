<?php

namespace App\Controller\Maintenance;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PrioriteController extends AbstractController
{
    #[Route('/Priorites', name: 'app_priorites_intervention')]
    public function index(): Response
    {
        return $this->render('maintenance/priorite/index.html.twig');
    }
}
