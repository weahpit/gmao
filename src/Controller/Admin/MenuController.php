<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MenuController extends AbstractController
{
    #[Route('/menu', name: 'app_menu')]
    public function index(): Response
    {
        return $this->render('admin/menu/index.html.twig', [
            'controller_name' => 'MenuController',
        ]);
    }
}
