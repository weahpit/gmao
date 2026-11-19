<?php

namespace App\Controller\Rh;

use App\Entity\Rh\Departement;
use App\Entity\Rh\Employe;
use App\Entity\Rh\Poste;
use App\Service\Rh\PointageService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_USER')]
final class PosteRHController extends AbstractController
{
    #[Route('/postes', name: 'rh_app_datarh')]
    public function index(ManagerRegistry $registry): Response
    {
        $postes = array();
        foreach ($registry->getRepository(Poste::class)->findAll() as $poste) {
            $postes[] = array(
                'id' => $poste->getId(),
                'libelle' => $poste->getLibelle(),
                'departement' => $poste->getCodeDepartement() ? $poste->getCodeDepartement()->getLibelle() : '-',
                'nb_employes' => $registry->getRepository(Employe::class)->count(array('poste' => $poste, 'supprimer' => false, 'demission' => false)),
            );
        }
        sort($postes);

        return $this->render('rh/poste_rh/index.html.twig', [
            'postes' => $postes,
            'departements' => $registry->getRepository(Departement::class)->findBy(array(), array('libelle' => 'ASC')),
        ]);
    }
    #[Route('/getPostes/{id_departement}', name: 'rh_get_postes')]
    public function get_postes(
        ManagerRegistry $registry,
        int $id_departement

    ): Response
    {
        $reponse = array();
        if ($id_departement == 0){
            $postes = $registry->getRepository(Poste::class)->findAll();
            foreach ($postes as $poste){
                $reponse[] = array(
                    'libelle'=>$poste->getLibelle(),
                    'id'=>$poste->getId(),
                    'departement'=>$poste->getCodeDepartement()->getLibelle()
                );

            }
        } else {
            $departement = $registry->getRepository(Departement::class)->find($id_departement);
            if ($departement){
                $postes = $registry->getRepository(Poste::class)->findBy(['code_departement'=>$departement]);
                foreach ($postes as $poste){
                    $reponse[] = array(
                        'libelle'=>$poste->getLibelle(),
                        'id'=>$poste->getId(),
                        'departement'=>$departement->getLibelle()
                    );

                }

            }
        }

        sort($reponse);

        return new JsonResponse(json_encode($reponse));
    }
    #[Route('/addPoste/{id_departement}/{libelle}', name: 'rh_add_poste')]
    public function add_poste(
        ManagerRegistry $registry,
        int $id_departement,
        string $libelle

    ): Response
    {
        $reponse = array();
        $departement = $registry->getRepository(Departement::class)->find($id_departement);
        if (!$departement){
            $reponse[] = array(
                'code'=>'ERREUR'
            );
        } else {
            $poste = $registry->getRepository(Poste::class)->findOneBy(['libelle'=>$libelle, 'code_departement'=>$departement]);
            if (!$poste){
                $poste = new Poste();
                $poste->setLibelle(strtoupper($libelle));
                $poste->setCodeDepartement($departement);
                $registry->getManager()->persist($poste);
                $registry->getManager()->flush();
                $reponse[] = array(
                    'code'=>'SUCCESS'
                );
            }

        }
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/deletePoste/{id_poste}', name: 'rh_delete_poste')]
    public function delete_poste(
        ManagerRegistry $registry,
        int $id_poste
    ): Response
    {
        $reponse = array();
        $poste = $registry->getRepository(Poste::class)->find($id_poste);
        if (!$poste){
            $reponse[] = array(
                'code'=>'ERREUR'
            );
        } else {
                $registry->getManager()->remove($poste);
                $registry->getManager()->flush();
                $reponse[] = array(
                    'code'=>'SUCCESS'
                );
        }

        return new JsonResponse(json_encode($reponse));
    }
}