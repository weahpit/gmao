<?php

namespace App\Controller\Api\Maintenance;

use App\Entity\Maintenance\StatutIntervention;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiStatutInterventionController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllStatutIntervention', name: 'app_api_get_statut_intervention')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $statut_interventions = $this->em->getRepository(StatutIntervention::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($statut_interventions as $statut_intervention){
                        $data[] = array(
                            'id'=>$statut_intervention->getId(),
                            'libelle'=>$statut_intervention->getLibelle()
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
    #[Route('api/getSingleStatutIntervention/{$id_statut_intervention}', name: 'app_api_get_single_statut_intervention')]
    public function app_api_get_single_statut_intervention(int $id_statut_intervention): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $statut_intervention = $this->em->getRepository(StatutIntervention::class)->find(Uuid::fromString($id_statut_intervention));

            if ($statut_intervention){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$statut_intervention->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner un Statut Intervention dans la liste !'
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
    #[Route('api/saveStatutIntervention', name: 'app_api_save_statut_intervention')]
    public function app_api_save_statut_intervention(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_statut_intervention = $request->request->get('id_statut_intervention');
                $libelle = $request->request->get('libelle');

                if (!$libelle) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $isNew = false;
                    if ($id_statut_intervention === "0") {
                        $statut_intervention = new StatutIntervention();
                        $isNew = true;
                    } else {
                        $uuid = Uuid::fromString($id_statut_intervention);
                        $statut_intervention = $this->em->getRepository(StatutIntervention::class)->find($uuid);
                    }

                    $statut_intervention->setLibelle(strtoupper($libelle));

                    $this->em->persist($statut_intervention);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Statut Intervention créé avec succès !' : 'Statut Intervention créé mise à jour avec succès !'
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
    #[Route('api/deleteStatutIntervention/{$id_statut_intervention}', name: 'app_api_delete_statut_intervention')]
    public function app_api_delete_statut_intervention($id_statut_intervention): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $statut_intervention = $this->em->getRepository(StatutIntervention::class)->find(Uuid::fromString($id_statut_intervention));

                if ($statut_intervention){
                    $this->em->remove($statut_intervention);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Statut Intervention supprimé avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner un Statut Intervention !'
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
