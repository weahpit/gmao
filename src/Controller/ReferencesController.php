<?php

namespace App\Controller;

use App\Entity\Menu;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReferencesController extends AbstractController
{
    #[Route('/references', name: 'app_references')]
    public function index(ManagerRegistry $registry): Response
    {
        $references = $registry->getRepository(Menu::class)->findOneBy(['nom_menu'=>'REFERENCES']);
        $menus = $registry->getRepository(Menu::class)->findBy(['parent_m'=>$references],['nom_menu'=>'ASC']);
        return $this->render('references/index.html.twig', [
            'menus' => $menus
        ]);
    }
}
