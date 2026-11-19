<?php

namespace App\Controller\Rh;

use App\Entity\Rh\Options;
use Doctrine\Persistence\ManagerRegistry;
use Monolog\Registry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_USER')]
final class OptionsController extends AbstractController
{
    #[Route('/options', name: 'rh_app_options')]
    public function index(ManagerRegistry $registry): Response
    {
        return $this->render('rh/options/index.html.twig', [
            'options' => $registry->getRepository(Options::class)->find(1),
        ]);
    }
    #[Route('/options/edit/{valeur}', name: 'rh_editOptions')]
    public function editOptions(ManagerRegistry $registry, string $valeur): Response
    {
        $reponse = false;
        $option = $registry->getRepository(Options::class)->find(1);

        if ($option){
            $option->setTolerance((int) $valeur);
            $registry->getManager()->persist($option);
            $registry->getManager()->flush();
            $reponse = true;
        }

        return new JsonResponse(json_encode($reponse));

    }
}
