<?php

namespace App\Controller\Admin;

use App\Entity\Directions;
use App\Services\Functions;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DirectionsController extends AbstractController
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    #[Route('/Directions', name: 'app_directions')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('admin/direction/index.html.twig',[
            'directionss'=>$this->registry->getRepository(Directions::class)->findAll()
        ]);
    }

    /*#[Route('/SupprimeTables', name: 'supprime_table')]
    public function supprime_table(Functions $functions): Response
    {
        try {
            $functions->dropTableCascade('public.capp_tdb_widget');
            $functions->dropTableCascade('public.capp_tdb');
            return  new JsonResponse("Tables supprimées !");
        } catch (\Throwable $throwable){
            return  new JsonResponse($throwable->getMessage());
        }


    }*/
}
