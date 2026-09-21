<?php

namespace App\Controller;

use App\Entity\Fournisseur;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FournisseurController extends AbstractController
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    #[Route('/fournisseur', name: 'app_fournisseur')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('fournisseur/index.html.twig',[
            'fournisseurs'=>$this->registry->getRepository(Fournisseur::class)->findAll()
        ]);
    }
}
