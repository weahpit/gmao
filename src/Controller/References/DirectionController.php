<?php

namespace App\Controller\References;

use App\Entity\Directions;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DirectionController extends AbstractController
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    #[Route('/directions', name: 'app_direction')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('references/direction/index.html.twig',[
            'directions'=>$this->registry->getRepository(Directions::class)->findAll()
        ]);
    }
}
