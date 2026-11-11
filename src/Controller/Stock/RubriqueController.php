<?php

namespace App\Controller\Stock;

use App\Entity\Stock\Rubrique;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RubriqueController extends AbstractController
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    #[Route('/rubrique', name: 'app_rubrique')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('references/rubrique/index.html.twig',[
            'rubriques'=>$this->registry->getRepository(Rubrique::class)->findAll()
        ]);
    }
}
