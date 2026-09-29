<?php

namespace App\Controller;

use App\Entity\Etat;
use App\Entity\EtatEquipement;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EtatEquipementController extends AbstractController
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    #[Route('/etat_equipement', name: 'app_etat')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('etat_equipement/index.html.twig',[
            'etats'=>$this->registry->getRepository(EtatEquipement::class)->findAll()
        ]);
    }
}
