<?php

namespace App\Controller\Api\Tdb;

use App\Entity\Emplacement;
use App\Entity\Equipement;
use App\Entity\EquipementType;
use App\Entity\Fournisseur;
use App\Entity\Marque;
use App\Entity\MouvementEquipement;
use App\Entity\NatureEquipement;
use App\Entity\Services;
use App\Services\Functions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiStockTdbController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/stockAlertesPdr', name: 'stock_alertes_pdr')]
    public function stock_alertes_pdr(Request $request, Functions $functions): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            try {
                // Liste des stocks par Equipement Type
                $equipements_type = $this->em->getRepository(Equipement::class)->findAll();
                $reponse = array();
                $data = array();
                foreach ($equipements_type as $eq){
                    $qte = $eq->getQte()? $eq->getQte() : 0;
                    $seuil = $eq->getSeuil()? $eq->getSeuil() : 0;
                    if ($qte  > 0 and $seuil > 0 and $qte <= $seuil){
                        $data[] = array(
                            'id'=>$eq->getId(),
                            'libelle'=>$eq->getNom(). ' - Réf.' . $eq->getModele(),
                            'qte'=>$qte ,
                            'seuil'=>$seuil,
                            'photo'=>$eq->getCodeEquipementType() ? $eq->getCodeEquipementType() ->getPhoto() : ""
                        );
                    }
                }
                $reponse = array('code'=>'success', 'msg'=>'Success', 'data'=>$data);
            } catch (\Throwable $throwable) {
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }

        return new JsonResponse($reponse);
    }

    #[Route('api/DerniersMouvementsStocks', name: 'derniers_mouvements_stocks')]
    public function derniers_mouvements_stocks(Request $request, Functions $functions): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        try {
            // Liste des 10 dernières opérations
            $mvts = $this->em->getRepository(MouvementEquipement::class)->findBy([], ['created_at'=>'DESC'], 10, 0);
            $reponse = array();
            $data = array();
            foreach ($mvts as $mvt){
                if ($mvt->getDateOperation()){
                    $data[] = array(
                        'id'=>$mvt->getId(),
                        'type'=>$mvt->getTypeMvt(),
                        'equipement'=>$mvt->getCodeEquipement() ? $mvt->getCodeEquipement()->getNom() . " [". $mvt->getCodeEquipement()->getModele(). "]" : "",
                        'nb'=>$mvt->getValue(),
                        'photo'=>$mvt->getCodeEquipementType() ? $mvt->getCodeEquipementType()->getPhoto() : "",
                        'date_operation'=>$mvt->getDateOperation()->format("d/m/Y"),
                    );
                }
            }

            $reponse = array('code'=>'success', 'msg'=>'Success', 'data'=>$data);
        } catch (\Throwable $throwable) {
            $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
        }

        return new JsonResponse($reponse);
    }

    #[Route('api/getAllMouvementsByEquipement/{id_equipement}', name: 'getAllMouvementsByEquipement')]
    public function getAllMouvementsByEquipement($id_equipement): Response
    {
        $reponse = array();
        $data = array();

        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        try {
            // Liste des 10 dernières opérations
            $mvt = $this->em->getRepository(MouvementEquipement::class)->find(Uuid::fromString($id_equipement));

            if ($mvt){
                $equipement = $mvt->getCodeEquipement();
                if ($equipement){
                    $mvts = $this->em->getRepository(MouvementEquipement::class)->findBy(['code_equipement'=>$equipement], ['created_at'=>'DESC']);

                    foreach ($mvts as $mvt){
                        if ($mvt->getDateOperation()){
                            $data[] = array(
                                'id'=>$mvt->getId(),
                                'type'=>$mvt->getTypeMvt(),
                                'equipement'=>$mvt->getCodeEquipementType() ? $mvt->getCodeEquipementType()->getNomEquipement() : "",
                                'nb'=>$mvt->getValue(),
                                'date_operation'=>$mvt->getDateOperation()->format("d/m/Y"),
                                'stock_avant'=>$mvt->getStockAvant(),
                                'stock_apres'=>$mvt->getStockApres(),
                                'numero_bon'=>$mvt->getNumeroBon() ? $mvt->getNumeroBon() : "",
                                'bl'=>$mvt->getBonLivraison(),
                                'demandeur'=>$mvt->getDemandeur() ? $mvt->getDemandeur()  : "",
                                'service_demandeur'=>$mvt->getCodeService() ? $mvt->getCodeService()->getLibelle() : "",
                                'observations'=>$mvt->getObservation()
                            );
                        }
                    }

                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Success',
                        'code_eq'=>$equipement->getCode(),
                        'modele'=>strtoupper($equipement->getModele()),
                        'nom'=>$equipement->getNom(),
                        'qte'=>$equipement->getQte(),
                        'photo'=>$equipement->getCodeEquipementType() ? $equipement->getCodeEquipementType()->getPhoto() : "",
                        'data'=>$data
                    );
                }
            }
        } catch (\Throwable $throwable) {
            $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
        }

        return new JsonResponse(json_encode($reponse));
    }
}
