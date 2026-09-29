<?php

namespace App\Controller;

use App\Entity\Marque;
use App\Entity\TypeEquipement;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TypeEquipementController extends AbstractController
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    #[Route('/CategoriesEquipements', name: 'app_type')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('type_equipement/index.html.twig',[
            'types'=>$this->registry->getRepository(TypeEquipement::class)->findAll()
        ]);
    }
}
