<?php

namespace App\Controller\Api\Maintenance;

use App\Entity\Maintenance\TypeIntervention;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiTypeInterventionController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllTypeIntervention', name: 'app_api_get_type_intervention')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $type_interventions = $this->em->getRepository(TypeIntervention::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($type_interventions as $type_intervention){
                        $data[] = array(
                            'id'=>$type_intervention->getId(),
                            'libelle'=>$type_intervention->getLibelle()
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
    #[Route('api/getSingleTypeIntervention/{$id_type_intervention}', name: 'app_api_get_single_type_intervention')]
    public function app_api_get_single_type_intervention(int $id_type_intervention): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $type_intervention = $this->em->getRepository(TypeIntervention::class)->find(Uuid::fromString($id_type_intervention));

            if ($type_intervention){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$type_intervention->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner un Type Intervention dans la liste !'
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
    #[Route('api/saveTypeIntervention', name: 'app_api_save_type_intervention')]
    public function app_api_save_type_intervention(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_type_intervention = $request->request->get('id_type_intervention');
                $libelle = $request->request->get('libelle');

                if (!$libelle) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $isNew = false;
                    if ($id_type_intervention === "0") {
                        $type_intervention = new TypeIntervention();
                        $isNew = true;
                    } else {
                        $uuid = Uuid::fromString($id_type_intervention);
                        $type_intervention = $this->em->getRepository(TypeIntervention::class)->find($uuid);
                    }

                    $type_intervention->setLibelle(strtoupper($libelle));

                    $this->em->persist($type_intervention);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Type Intervention créé avec succès !' : 'Type Intervention créé mise à jour avec succès !'
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
    #[Route('api/deleteTypeIntervention/{$id_type_intervention}', name: 'app_api_delete_type_intervention')]
    public function app_api_delete_type_intervention($id_type_intervention): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $type_intervention = $this->em->getRepository(TypeIntervention::class)->find(Uuid::fromString($id_type_intervention));

                if ($type_intervention){
                    $this->em->remove($type_intervention);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Type Intervention supprimé avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner un Type Intervention !'
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
