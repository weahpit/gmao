<?php

namespace App\Controller\Api\Maintenance;

use App\Entity\AlerteAnomalie;
use App\Entity\EquipementType;
use App\Entity\ZoneExploitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiAlerteAnomalieController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllAlertes', name: 'app_api_get_alerte')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $alertes = $this->em->getRepository(AlerteAnomalie::class)->findBy([], ['nom'=>'ASC']);
                    foreach ($alertes as $alerte){
                        $data[] = array(
                            'id'=>$alerte->getId(),
                            'sujet'=>$alerte->getSujet(),
                            'description'=>$alerte->getDescription(),
                            'zone'=>$alerte->getZone()?$alerte->getZone()->getNom() : "",
                            'emplacement'=>$alerte->getPreciserEmplacement(),
                            'photo'=>$alerte->getPhoto(),
                            'equipement'=>$alerte->getCodeEquipement() ? $alerte->getCodeEquipement()->getNomEquipement() : ""
                        );
                    }
                    $reponse = array('code'=>'success', 'msg'=>'Succès', 'data'=>$data);
                } catch (\Throwable $throwable){
                    $reponse = array('code'=>'error', 'msg'=>'Erreur ! <br>'. $throwable->getMessage());
                }
            } else {
                $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
            }
            return new JsonResponse(json_encode($reponse));
        }
    #[Route('api/getSingleAlerte/{$id_alerte}', name: 'app_api_get_single_alerte')]
    public function app_api_get_single_alerte(int $id_alerte): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $alerte = $this->em->getRepository(AlerteAnomalie::class)->find($id_alerte);

            if ($alerte){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'id'=>$alerte->getId(),
                    'sujet'=>$alerte->getSujet(),
                    'description'=>$alerte->getDescription(),
                    'zone'=>$alerte->getZone()?$alerte->getZone()->getNom() : "",
                    'emplacement'=>$alerte->getPreciserEmplacement(),
                    'photo'=>$alerte->getPhoto(),
                    'equipement'=>$alerte->getCodeEquipement() ? $alerte->getCodeEquipement()->getNomEquipement() : ""
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une alerte  dans la liste !'
                );
            }

            } catch (\Throwable $throwable) {
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }
    #[Route('api/saveAlerteAnomalie', name: 'app_api_save_alerte_anomalie')]
    public function app_api_save_alerte_anomalie(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_alerte = $request->request->get('id_alerte');
                $sujet = $request->request->get('sujet');
                $description = $request->request->get('description');
                $emplacement = $request->request->get('emplacement');
                $photo = $request->files->get('photo');
                $equipement= $request->request->get('equipement');
                $zone= $request->request->get('zone');


                if (!$sujet || !$emplacement) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir les données obligatoires !'];
                } else {

                        $alerte = new AlerteAnomalie();

                    $alerte->setSujet($sujet);
                    $alerte->setDescription($description);
                    if ($zone){
                        $Zone = $this->em->getRepository(ZoneExploitation::class)->find(Uuid::fromString($zone));
                        if ($Zone) { $alerte->setZone($Zone);}
                    }
                    if ($equipement){
                        $Eq = $this->em->getRepository(EquipementType::class)->find(Uuid::fromString($equipement));
                        if ($Eq) { $alerte->setCodeEquipement($Eq);}
                    }

                    $alerte->setPreciserEmplacement($emplacement);

                    // Charger la photo de l'équipement
                    if ($photo) {
                        $filename = uniqid().'.'.$photo->guessExtension();
                        $photo->move($this->getParameter('photos_alertes_anomalies'), $filename);
                        $alerte->setPhoto($filename);
                    }

                    $this->em->persist($alerte);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => 'Alerte créée avec succès !'
                    ];
                }
            } catch (\Throwable $throwable) {
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }
    #[Route('api/deleteAlerte/{$id_alerte}', name: 'app_api_delete_alerte')]
    public function app_api_delete_alerte(Request $request, int $id_alerte): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_alerte);
                $alerte = $this->em->getRepository(Alerte::class)->find($uuid);

                if ($alerte){
                    $this->em->remove($alerte);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Alerte supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une alerte d\'équipements !'
                    );
                }

            } catch (\Throwable $throwable) {
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }
}
