<?php

namespace App\Controller;

use App\Entity\Equipement;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EquipementController extends AbstractController
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    #[Route('/equipement', name: 'app_equipement')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('equipement/index.html.twig',[
            'equipements'=>$this->registry->getRepository(Equipement::class)->findAll()
        ]);
    }
}
