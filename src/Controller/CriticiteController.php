<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CriticiteController extends AbstractController
{
    #[Route('/criticite', name: 'app_criticite')]
    public function index(): Response
    {
        return $this->render('criticite/index.html.twig', [
            'controller_name' => 'CriticiteController',
        ]);
    }
}
