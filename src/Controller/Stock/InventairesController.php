<?php

namespace App\Controller\Stock;

use App\Entity\EquipementType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InventairesController extends AbstractController
{
    #[Route('/inventaires', name: 'app_inventaires')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('inventaires/index.html.twig');
    }

    #[Route('api/getAllData', name: 'get_all_data')]
    public function get_all_data(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        try {
            $date_inventaire = $request->request->get('date_inventaire');
            $dateMvt = new  \DateTime($date_inventaire);

            // Recherche de tous les équipements Types
            $eqs = $entityManager->getRepository(EquipementType::class)->findBy([], ['nom_equipement'=>'ASC']);

            $reponse = array();
            $data = array();
            foreach ($eqs as $eq){
                $stock_actuelle = 0;
                $entree = 0;
                $sorties = 0;

                // Recherche les movements et compte suivant la nature de l'équipement ['Quantite' ou 'Serial']
                $mvts = $eq->getMouvementEquipements();
                // Parcours les moyuvements
                foreach ($mvts as $mvt){
                    if (strtotime($mvt->getCreatedAt()->format('Y-m-d')) <= strtotime($dateMvt->format('Y-m-d'))){
                        if ($mvt->getTypeMvt() == "1"){ // Entrée
                            $entree = $entree + $mvt->getValue();
                        } else {
                            $sorties = $sorties + $mvt->getValue();
                        }
                    }
                }

                $stock_actuelle = $entree - $sorties;

                $data[] = array(
                    'id'=>$eq->getId(),
                    'nom'=>$eq->getNomEquipement(),
                    'actuel'=>$stock_actuelle? $stock_actuelle : 0,
                    'seuil'=>$eq->getSeuil() ? $eq->getSeuil() : 0,
                    'entree'=>$entree,
                    'sortie'=>$sorties
                );
            }
            $reponse = array(
                'code'=>'success',
                'msg'=>'Inventaire extrait avec succès !',
                'data'=>$data
            );
        } catch (\Throwable $throwable){
            $reponse = array(
                'code'=>'error',
                'msg'=>"Une erreur s'est produite!  => ". $throwable->getMessage()
            );
        }
        return new JsonResponse($reponse);
    }
}
