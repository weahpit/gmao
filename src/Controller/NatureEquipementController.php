<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NatureEquipementController extends AbstractController
{
    #[Route('/natureEquipements', name: 'app_nature_equipement')]
    public function index(): Response
    {
        return $this->render('nature_equipement/index.html.twig', [
            'controller_name' => 'NatureEquipementController',
        ]);
    }
}
