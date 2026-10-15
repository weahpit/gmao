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
use App\Repository\AlerteAnomalieRepository;
use App\Services\Functions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiAlertesAnomaliesTdbController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/StatsAlertesMensuelles', name: 'stats_alertes_mensuelles')]
    public function stats_alertes_mensuelles(Request $request, Functions $functions, AlerteAnomalieRepository $repo): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            try {
                $data = $repo->countByDayOfCurrentMonthNative();
                return $this->json($data);

                } catch (\Throwable $throwable) {
                        $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
                    }

        return new JsonResponse($reponse);
    }
/*
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
    }*/

    #[Route('/api/StatsAlertesZones', name: 'stats_alertes_zones')]
    public function stats_alertes_zones(AlerteAnomalieRepository $repo): JsonResponse
    {
        $data = $repo->countByZoneOfCurrentMonth();
        return $this->json($data);
    }


}
