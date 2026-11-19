<?php

namespace App\Controller\Rh;

use App\Entity\Rh\Departement;
use App\Entity\Rh\Horaires;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_USER')]
final class DepartementController extends AbstractController
{
    #[Route('/departements', name: 'rh_app_departements')]
    public function index(): Response
    {

        return $this->render('rh/departement/index.html.twig', [
            'controller_name' => 'DepartementController',
        ]);
    }

    /**
     * @throws \Exception
     */
        #[Route('/getDepartements', name: 'rh_get_departements')]
public function get_departements(
    ManagerRegistry $registry
): Response
{
    $reponse = array();

    $departements = $registry->getRepository(Departement::class)->findAll();

    foreach ($departements as $departement){
        $reponse[] = array(
            'code'=>'SUCCESS',
            'libelle'=>$departement->getLibelle(),
            'id'=>$departement->getId()
        );
    }
    sort($reponse);
    return new JsonResponse(json_encode($reponse));
}

    #[Route('/AddDepartement/{libelle}', name: 'rh_add_departement')]
    public function add_departement(
        ManagerRegistry $registry,
        string $libelle

    ): Response
    {
        $reponse = array();
        $departement = $registry->getRepository(Departement::class)->findBy(['libelle'=>$libelle]);

        if ($departement){
            $reponse[] = array(
                'code'=>'DOUBLON'
            );
        } else {
            $departement = new Departement();
            $departement->setLibelle(strtoupper($libelle));
            $registry->getManager()->persist($departement);
            $registry->getManager()->flush();
            $reponse[] = array(
                'code'=>'SUCCESS'
            );
        }

        return new JsonResponse(json_encode($reponse));
    }
}
