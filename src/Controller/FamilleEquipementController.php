<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FamilleEquipementController extends AbstractController
{
    #[Route('/famillesEquipement', name: 'app_famille_equipement')]
    public function index(): Response
    {
        return $this->render('famille_equipement/index.html.twig', [
            'controller_name' => 'FamilleEquipementController',
        ]);
    }
}
