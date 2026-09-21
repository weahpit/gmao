<?php

namespace App\Controller\Api;

use App\Entity\NatureEquipement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiNatureEquipementController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllNatureEquipement', name: 'app_api_get_nature_equipement')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $natures = $this->em->getRepository(NatureEquipement::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($natures as $nature){
                        $data[] = array(
                            'id'=>$nature->getId(),
                            'nature'=>$nature->getLibelle()
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
    #[Route('api/getSingleNatureEquipement/{$id_nature_equipement}', name: 'app_api_get_single_nature_equipement')]
    public function app_api_get_single_nature_equipement(int $id_nature_equipement): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $nature_equipement = $this->em->getRepository(NatureEquipement::class)->find($id_nature_equipement);

            if ($nature_equipement){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$nature_equipement->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une nature d\'équipements dans la liste !'
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
    #[Route('api/saveNatureEquipement', name: 'app_api_save_nature_equipement')]
    public function app_api_save_nature_equipement(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_nature_equipement = $request->request->get('id_nature_equipement');
                $libelle = $request->request->get('libelle_nature_equipement');

                if (!$libelle) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $nature_equipement = $this->em->getRepository(NatureEquipement::class)->find($id_nature_equipement);
                    $isNew = false;
                    if (!$nature_equipement) {
                        $nature_equipement = new NatureEquipement();
                        $isNew = true;
                    }

                    $nature_equipement->setLibelle(strtoupper($libelle));

                    $this->em->persist($nature_equipement);
                    $this->em->flush();

                    $reponse = [
                        'code' => 1,
                        'msg' => $isNew ? 'Nature Equipement créé avec succès !' : 'Nature Equipement créé mise à jour avec succès !'
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
    #[Route('api/deleteNatureEquipement/{$id_nature_equipement}', name: 'app_api_delete_nature_equipement')]
    public function app_api_delete_nature_equipement(Request $request, int $id_nature_equipement): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $nature_equipement = $this->em->getRepository(NatureEquipement::class)->find($id_nature_equipement);

                if ($nature_equipement){
                    $this->em->remove($nature_equipement);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Nature Equipement supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une nature d\'équipements !'
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
