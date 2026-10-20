<?php

namespace App\Controller\Api\Maintenance;

use App\Entity\Maintenance\NatureIntervention;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiNatureInterventionController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllNatureIntervention', name: 'app_api_get_nature_intervention')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $nature_interventions = $this->em->getRepository(NatureIntervention::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($nature_interventions as $nature_intervention){
                        $data[] = array(
                            'id'=>$nature_intervention->getId(),
                            'libelle'=>$nature_intervention->getLibelle()
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
    #[Route('api/getSingleNatureIntervention/{$id_nature_intervention}', name: 'app_api_get_single_nature_intervention')]
    public function app_api_get_single_nature_intervention(int $id_nature_intervention): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $nature_intervention = $this->em->getRepository(NatureIntervention::class)->find(Uuid::fromString($id_nature_intervention));

            if ($nature_intervention){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$nature_intervention->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une Nature Intervention dans la liste !'
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
    #[Route('api/saveNatureIntervention', name: 'app_api_save_nature_intervention')]
    public function app_api_save_nature_intervention(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_nature_intervention = $request->request->get('id_nature_intervention');
                $libelle = $request->request->get('libelle');

                if (!$libelle) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $isNew = false;
                    if ($id_nature_intervention === "0") {
                        $nature_intervention = new NatureIntervention();
                        $isNew = true;
                    } else {
                        $uuid = Uuid::fromString($id_nature_intervention);
                        $nature_intervention = $this->em->getRepository(NatureIntervention::class)->find($uuid);
                    }

                    $nature_intervention->setLibelle(strtoupper($libelle));

                    $this->em->persist($nature_intervention);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Nature Intervention créée avec succès !' : 'Nature Intervention mise à jour avec succès !'
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
    #[Route('api/deleteNatureIntervention/{$id_nature_intervention}', name: 'app_api_delete_nature_intervention')]
    public function app_api_delete_nature_intervention($id_nature_intervention): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $nature_intervention = $this->em->getRepository(NatureIntervention::class)->find(Uuid::fromString($id_nature_intervention));

                if ($nature_intervention){
                    $this->em->remove($nature_intervention);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Nature Intervention supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une Nature Intervention !'
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
