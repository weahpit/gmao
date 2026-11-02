<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WidgetController extends AbstractController
{
    #[Route('/widget', name: 'app_widget')]
    public function index(): Response
    {
        return $this->render('admin/widget/index.html.twig', [
            'controller_name' => 'WidgetController',
        ]);
    }
}
