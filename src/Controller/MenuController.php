<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MenuController extends AbstractController
{
    #[Route('/menus', name: 'app_menu')]
    public function index(): Response
    {
        return $this->render('admin/menu/index.html.twig');
    }
}
