<?php

namespace App\Controller;

use App\Entity\Marque;
use App\Entity\TypeEquipement;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EquipementTypeController extends AbstractController
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    #[Route('/EquipementsType', name: 'app_type_equipement')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('equipement_type/index.html.twig',[
            'types'=>$this->registry->getRepository(TypeEquipement::class)->findAll()
        ]);
    }
}
