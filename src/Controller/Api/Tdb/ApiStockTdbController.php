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
                $equipements_type = $this->em->getRepository(EquipementType::class)->findAll();
                $reponse = array();
                $data = array();
                foreach ($equipements_type as $eq){
                    if ($eq->getQte() > 0 and $eq->getSeuil() > 0 and $eq->getQte() <= $eq->getSeuil()){
                        $data[] = array(
                            'id'=>$eq->getId(),
                            'libelle'=>$eq->getNomEquipement(),
                            'qte'=>$eq->getQte(),
                            'seuil'=>$eq->getSeuil(),
                            'photo'=>$eq->getPhoto()
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
                        'equipement'=>$mvt->getCodeEquipementType() ? $mvt->getCodeEquipementType()->getNomEquipement() : "",
                        'nb'=>$mvt->getValue(),
                        'photo'=>$mvt->getCodeEquipementType() ? $mvt->getCodeEquipementType()->getPhoto() : "",
                        'date_operation'=>$mvt->getDateOperation()->format("d/m/Y")
                    );
                }
            }

            $reponse = array('code'=>'success', 'msg'=>'Success', 'data'=>$data);
        } catch (\Throwable $throwable) {
            $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
        }

        return new JsonResponse($reponse);
    }
}
