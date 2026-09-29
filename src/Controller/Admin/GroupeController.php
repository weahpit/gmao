<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GroupeController extends AbstractController
{
    #[Route('/groupe', name: 'app_groupe')]
    public function index(): Response
    {
        return $this->render('admin/groupe/index.html.twig', [
            'controller_name' => 'GroupeController',
        ]);
    }
}
