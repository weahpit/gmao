<?php

namespace App\Controller\Api\Maintenance;

use App\Entity\AlerteAnomalie;
use App\Entity\EquipementType;
use App\Entity\Maintenance\FicheIntervention;
use App\Entity\Maintenance\OrigineIntervention;
use App\Entity\Maintenance\StatutIntervention;
use App\Entity\Maintenance\TypeIntervention;
use App\Entity\Services;
use App\Entity\ZoneExploitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiFicheInterventionController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllFichesIntervention', name: 'app_api_get_all_fiches_intervention')]
    public function app_api_get_all_fiches_intervention(): Response
        {
            $reponse = array();
            $data = array();
                try{
                    // Liste des Natures d'équipements
                    $fiches = $this->em->getRepository(FicheIntervention::class)->findBy([], ['created_at'=>'DESC']);
                    foreach ($fiches as $fiche){
                        $data[] = array(
                            'id'=>$fiche->getId(),
                            'date_intervention'=>$fiche->getDateDebutReel()? $fiche->getDateDebutReel()->format('d/m/Y') : "" ,
                            'date_creation'=>$fiche->getCreatedAt()? $fiche->getCreatedAt()->format('d/m/Y') : "" ,
                            'reference'=>$fiche->getReference(),
                            'type_intervention'=>$fiche->getTypeIntervention()?$fiche->getTypeIntervention()->getLibelle() : "",
                            'nature_intervention'=>$fiche->getNature() ? $fiche->getNature()->getLibelle() : "",
                            'zone'=>$fiche->getZone() ? $fiche->getZone()->getNom() : "",
                            'statut'=>$fiche->getStatut() ? $fiche->getStatut()->getLibelle() : "",
                            'service'=>$fiche->getServiceDemandeur() ? $fiche->getServiceDemandeur()->getLibelle() : "",
                        );
                    }
                    $reponse = array('code'=>'success', 'msg'=>'Succès', 'data'=>$data);
                } catch (\Throwable $throwable){
                    $reponse = array('code'=>'error', 'msg'=>'Erreur ! <br>'. $throwable->getMessage());
                }

            return new JsonResponse(json_encode($reponse));
        }

    #[Route('api/getNewRefFiche', name: 'app_api_get_new_ref_fiche')]
    public function app_api_get_new_ref_fiche(): Response
    {
        $reponse = array();
        try{
            // Liste des Natures d'équipements
            $nbfiche = $this->em->getRepository(FicheIntervention::class)->count([]) + 1 ;
            $annee = new \DateTime();
            if (strlen($nbfiche) == 1){
                $countFiche = "00". $nbfiche;
            }elseif (strlen($nbfiche) == 2){
                $countFiche = "0". $nbfiche;
            }else {
                $countFiche = $nbfiche;
            }

            $numero_ref = $annee->format('Y') . "-". $countFiche;
            $reponse = array('code'=>'success', 'msg'=>'Succès', 'ref'=>$numero_ref);
        } catch (\Throwable $throwable){
            $reponse = array('code'=>'error', 'msg'=>'Erreur ! <br>'. $throwable->getMessage());
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
    #[Route('api/genererFicheIntervention', name: 'genererFicheIntervention')]
    public function genererFicheIntervention(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                /*$id_fiche_intervention = $request->request->get('idFicheIntervention');*/
                $reference = $request->request->get('reference');
                $sujet = $request->request->get('sujet');
                $statut = $request->request->get('statut');
                $nom_demandeur = $request->request->get('nom_demandeur');
                $service_demandeur= $request->request->get('service_demandeur');
                $type_intervention= $request->request->get('type_intervention');
                $origine_intervention = $request->request->get('origine_intervention');
                $zone_exploitation= $request->request->get('zone_exploitation');

                if (!$reference || !$sujet || !$statut || !$type_intervention || !$origine_intervention || !$zone_exploitation) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir les données obligatoires !'];
                } else {
                        $fiche_intervention = new FicheIntervention();
                        $fiche_intervention->setReference($reference);
                        $fiche_intervention->setDateDebutPlanifie(new \DateTime());
                        $fiche_intervention->setDemandeur(strtoupper($nom_demandeur));
                        $fiche_intervention->setCreatedAt(new \DateTimeImmutable());
                        $fiche_intervention->setCreatedBy(strtoupper($this->getUser()));
                        $fiche_intervention->setSujet(strtoupper($sujet));

                        if ($service_demandeur){
                            $ServiceDemandeur = $this->em->getRepository(Services::class)->find(Uuid::fromString($service_demandeur));
                            if ($ServiceDemandeur) {$fiche_intervention->setServiceDemandeur($ServiceDemandeur);}
                        }

                    //====>
                        $Statut = $this->em->getRepository(StatutIntervention::class)->find(Uuid::fromString($statut));
                        if ($Statut){ $fiche_intervention->setStatut($Statut);}

                       //====>
                        $TypeIntervention = $this->em->getRepository(TypeIntervention::class)->find(Uuid::fromString($type_intervention));
                        if ($Statut){ $fiche_intervention->setTypeIntervention($TypeIntervention);}

                       //====>
                        $OrigineIntervention = $this->em->getRepository(OrigineIntervention::class)->find(Uuid::fromString($origine_intervention));
                        if ($Statut){ $fiche_intervention->setOrigine($OrigineIntervention);}

                       //====>
                        $ZoneIntervention = $this->em->getRepository(ZoneExploitation::class)->find(Uuid::fromString($zone_exploitation));
                        if ($Statut){ $fiche_intervention->setZone($ZoneIntervention);}


                    $this->em->persist($fiche_intervention);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => 'Fiche Intervention créée avec succès !'
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
                $alerte = $this->em->getRepository(AlerteAnomalie::class)->find($uuid);

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
