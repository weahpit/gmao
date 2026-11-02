<?php

namespace App\Controller;

use App\Entity\Marque;
use App\Entity\TypeEq;
use App\Entity\TypeEquipement;
use App\Entity\TypeZoneExploitation;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TypeEqController extends AbstractController
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    #[Route('/TypesEq', name: 'app_type_eq')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('type_eq/index.html.twig',[
            'types'=>$this->registry->getRepository(TypeEq::class)->findAll()
        ]);
    }
}
