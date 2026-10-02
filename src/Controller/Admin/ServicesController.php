<?php

namespace App\Controller\Admin;

use App\Entity\Services;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ServicesController extends AbstractController
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    #[Route('/Services', name: 'app_services')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('admin/service/index.html.twig',[
            'servicess'=>$this->registry->getRepository(Services::class)->findAll()
        ]);
    }
}
