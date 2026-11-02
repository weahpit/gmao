<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PosteController extends AbstractController
{
    #[Route('/poste', name: 'app_poste')]
    public function index(): Response
    {
        return $this->render('admin/poste/index.html.twig', [
            'controller_name' => 'PosteController',
        ]);
    }
}
