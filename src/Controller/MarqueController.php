<?php

namespace App\Controller;

use App\Entity\Marque;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MarqueController extends AbstractController
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    #[Route('/marque', name: 'app_marque')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('marque/index.html.twig',[
            'marques'=>$this->registry->getRepository(Marque::class)->findAll()
        ]);
    }
}
