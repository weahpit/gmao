<?php

namespace App\Controller\Api\Maintenance;

use App\Entity\Maintenance\ResultatIntervention;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiResultatInterventionController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllResultatIntervention', name: 'app_api_get_resultat_intervention')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $resultat_interventions = $this->em->getRepository(ResultatIntervention::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($resultat_interventions as $resultat_intervention){
                        $data[] = array(
                            'id'=>$resultat_intervention->getId(),
                            'libelle'=>$resultat_intervention->getLibelle()
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
    #[Route('api/getSingleResultatIntervention/{$id_resultat_intervention}', name: 'app_api_get_single_resultat_intervention')]
    public function app_api_get_single_resultat_intervention(int $id_resultat_intervention): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $resultat_intervention = $this->em->getRepository(ResultatIntervention::class)->find(Uuid::fromString($id_resultat_intervention));

            if ($resultat_intervention){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$resultat_intervention->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une Resultat Intervention dans la liste !'
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
    #[Route('api/saveResultatIntervention', name: 'app_api_save_resultat_intervention')]
    public function app_api_save_resultat_intervention(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_resultat_intervention = $request->request->get('id_resultat_intervention');
                $libelle = $request->request->get('libelle');

                if (!$libelle) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $isNew = false;
                    if ($id_resultat_intervention === "0") {
                        $resultat_intervention = new ResultatIntervention();
                        $isNew = true;
                    } else {
                        $uuid = Uuid::fromString($id_resultat_intervention);
                        $resultat_intervention = $this->em->getRepository(ResultatIntervention::class)->find($uuid);
                    }

                    $resultat_intervention->setLibelle(strtoupper($libelle));

                    $this->em->persist($resultat_intervention);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Resultat Intervention créée avec succès !' : 'Resultat Intervention mise à jour avec succès !'
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
    #[Route('api/deleteResultatIntervention/{$id_resultat_intervention}', name: 'app_api_delete_resultat_intervention')]
    public function app_api_delete_resultat_intervention($id_resultat_intervention): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $resultat_intervention = $this->em->getRepository(ResultatIntervention::class)->find(Uuid::fromString($id_resultat_intervention));

                if ($resultat_intervention){
                    $this->em->remove($resultat_intervention);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Resultat Intervention supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une Resultat Intervention !'
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
