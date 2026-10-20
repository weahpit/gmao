<?php

namespace App\Controller\Api\Maintenance;

use App\Entity\Maintenance\OrigineIntervention;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiOrigineInterventionController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllOrigineIntervention', name: 'app_api_get_origine_intervention')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $origine_interventions = $this->em->getRepository(OrigineIntervention::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($origine_interventions as $origine_intervention){
                        $data[] = array(
                            'id'=>$origine_intervention->getId(),
                            'libelle'=>$origine_intervention->getLibelle()
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
    #[Route('api/getSingleOrigineIntervention/{$id_origine_intervention}', name: 'app_api_get_single_origine_intervention')]
    public function app_api_get_single_origine_intervention(int $id_origine_intervention): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $origine_intervention = $this->em->getRepository(OrigineIntervention::class)->find(Uuid::fromString($id_origine_intervention));

            if ($origine_intervention){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$origine_intervention->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une Origine Intervention dans la liste !'
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
    #[Route('api/saveOrigineIntervention', name: 'app_api_save_origine_intervention')]
    public function app_api_save_origine_intervention(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_origine_intervention = $request->request->get('id_origine_intervention');
                $libelle = $request->request->get('libelle');

                if (!$libelle) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $isNew = false;
                    if ($id_origine_intervention === "0") {
                        $origine_intervention = new OrigineIntervention();
                        $isNew = true;
                    } else {
                        $uuid = Uuid::fromString($id_origine_intervention);
                        $origine_intervention = $this->em->getRepository(OrigineIntervention::class)->find($uuid);
                    }

                    $origine_intervention->setLibelle(strtoupper($libelle));

                    $this->em->persist($origine_intervention);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Origine Intervention créée avec succès !' : 'Origine Intervention mise à jour avec succès !'
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
    #[Route('api/deleteOrigineIntervention/{$id_origine_intervention}', name: 'app_api_delete_origine_intervention')]
    public function app_api_delete_origine_intervention($id_origine_intervention): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $origine_intervention = $this->em->getRepository(OrigineIntervention::class)->find(Uuid::fromString($id_origine_intervention));

                if ($origine_intervention){
                    $this->em->remove($origine_intervention);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Origine Intervention supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une Origine Intervention !'
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
