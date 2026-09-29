<?php

namespace App\Controller\Api;

use App\Entity\EtatEquipement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiEtatEquipementController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllEtatEquipement', name: 'app_api_get_etat_equipement')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Etats d'équipements
                    $etats = $this->em->getRepository(EtatEquipement::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($etats as $etat){
                        $data[] = array(
                            'id'=>$etat->getId(),
                            'etat'=>$etat->getLibelle()
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
    #[Route('api/getSingleEtatEquipement/{$id_etat_equipement}', name: 'app_api_get_single_etat_equipement')]
    public function app_api_get_single_etat_equipement(int $id_etat_equipement): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $etat_equipement = $this->em->getRepository(EtatEquipement::class)->find($id_etat_equipement);

            if ($etat_equipement){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$etat_equipement->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une état dans la liste !'
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
    #[Route('api/saveEtatEquipement', name: 'app_api_save_etat_equipement')]
    public function app_api_save_etat_equipement(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_etat_equipement = $request->request->get('id_etat_equipement');
                $libelle = $request->request->get('etat');

                if (!$libelle) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $etat_equipement = $this->em->getRepository(EtatEquipement::class)->find($id_etat_equipement);
                    $isNew = false;
                    if (!$etat_equipement) {
                        $etat_equipement = new EtatEquipement();
                        $isNew = true;
                    }

                    $etat_equipement->setLibelle(strtoupper($libelle));

                    $this->em->persist($etat_equipement);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Etat Equipement créé avec succès !' : 'Etat Equipement créé mise à jour avec succès !'
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
    #[Route('api/deleteEtatEquipement/{$id_etat_equipement}', name: 'app_api_delete_etat_equipement')]
    public function app_api_delete_etat_equipement(Request $request, int $id_etat_equipement): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $etat_equipement = $this->em->getRepository(EtatEquipement::class)->find($id_etat_equipement);

                if ($etat_equipement){
                    $this->em->remove($etat_equipement);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Etat Equipement supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une etat_equipement d\'équipements !'
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
