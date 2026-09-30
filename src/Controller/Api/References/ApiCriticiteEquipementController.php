<?php

namespace App\Controller\Api\References;

use App\Entity\Criticite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiCriticiteEquipementController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllCriticites', name: 'app_api_get_criticite_equipement')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Criticites d'équipements
                    $criticites = $this->em->getRepository(Criticite::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($criticites as $criticite){
                        $data[] = array(
                            'id'=>$criticite->getId(),
                            'criticite'=>$criticite->getLibelle()
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
    #[Route('api/getSingleCriticite/{$id_criticite_equipement}', name: 'app_api_get_single_criticite_equipement')]
    public function app_api_get_single_criticite_equipement(int $id_criticite_equipement): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $criticite_equipement = $this->em->getRepository(Criticite::class)->find($id_criticite_equipement);

            if ($criticite_equipement){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$criticite_equipement->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner un état de criticité dans la liste !'
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
    #[Route('api/saveCriticite', name: 'app_api_save_criticite_equipement')]
    public function app_api_save_criticite_equipement(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_criticite_equipement = $request->request->get('id_criticite_equipement');
                $libelle = $request->request->get('libelle_criticite_equipement');

                if (!$libelle) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $criticite_equipement = $this->em->getRepository(Criticite::class)->find($id_criticite_equipement);
                    $isNew = false;
                    if (!$criticite_equipement) {
                        $criticite_equipement = new Criticite();
                        $isNew = true;
                    }

                    $criticite_equipement->setLibelle(strtoupper($libelle));

                    $this->em->persist($criticite_equipement);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Criticite Equipement créé avec succès !' : 'Criticite Equipement créé mise à jour avec succès !'
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
    #[Route('api/deleteCriticite/{$id_criticite_equipement}', name: 'app_api_delete_criticite_equipement')]
    public function app_api_delete_criticite_equipement(Request $request, int $id_criticite_equipement): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $criticite_equipement = $this->em->getRepository(Criticite::class)->find($id_criticite_equipement);

                if ($criticite_equipement){
                    $this->em->remove($criticite_equipement);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Criticite Equipement supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une criticite d\'équipements !'
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
