<?php

namespace App\Controller\Rh;

use App\Entity\Rh\Departement;
use App\Entity\Rh\Employe;
use App\Entity\Rh\Poste;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_USER')]
final class AccueilController extends AbstractController
{
    #[Route('', name: 'rh_accueil')]
    public function index(ManagerRegistry $registry): Response
    {
        $reponse = array();
        $departements = $registry->getRepository(Departement::class)->findAll();
        foreach ($departements as $departement){
            $reponse[] = array(
                'departement'=>$departement->getLibelle(),
                'nb_users'=>$departement->getEmployes()->count()
            );
        }

        return $this->render('rh/accueil/index.html.twig', [
            'nb_employes'=>$registry->getRepository(Employe::class)->count([ 'demission'=>false, 'supprimer'=>false]),
            'nb_departements'=>$registry->getRepository(Departement::class)->count(),
            'nb_postes'=>$registry->getRepository(Poste::class)->count(),
            'stats_dept'=>$reponse
        ]);
    }
}
