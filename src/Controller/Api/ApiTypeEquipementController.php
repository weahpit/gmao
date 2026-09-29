<?php

namespace App\Controller\Api;

use App\Entity\Famille;
use App\Entity\Menu;
use App\Entity\TypeEquipement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiTypeEquipementController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllTypeEquipement', name: 'app_api_get_type_equipement')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Types d'équipements
                    $types = $this->em->getRepository(TypeEquipement::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($types as $type){
                        $data[] = array(
                            'id'=>$type->getId(),
                            'categorie'=>$type->getLibelle(),
                            'famille'=>$type->getCodeFamille()? $type->getCodeFamille()->getLibelle():""
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
        #[Route('api/getTypeEquipementByFamille/{id_famille}', name: 'app_api_get_type_equipement_by_famille')]
    public function app_api_get_type_equipement_by_famille($id_famille): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    if (Uuid::isValid($id_famille)) {
                        $uuid_famille = Uuid::fromString($id_famille);
                        $famille = $this->em->getRepository(Famille::class)->find($uuid_famille);

                        // Liste des Types d'équipements
                        $types = $this->em->getRepository(TypeEquipement::class)->findBy(['code_famille'=>$famille], ['libelle'=>'ASC']);
                        foreach ($types as $type){
                            $data[] = array(
                                'id'=>$type->getId(),
                                'categorie'=>$type->getLibelle(),
                                'famille'=>$type->getCodeFamille()? $type->getCodeFamille()->getLibelle():""
                            );
                        }
                        $reponse = array('code'=>'success', 'msg'=>'Succès', 'data'=>$data);
                    } else {
                        $reponse = array('code'=>'warning', 'msg'=>'Famille non sélectionnée !');
                    }
                } catch (\Throwable $throwable){
                    $reponse = array('code'=>'error', 'msg'=>'Erreur ! <br>'. $throwable->getMessage());
                }
            } else {
                $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
            }
            return new JsonResponse(json_encode($reponse));
        }
    #[Route('api/getSingleTypeEquipement/{$id_type_equipement}', name: 'app_api_get_single_type_equipement')]
    public function app_api_get_single_type_equipement(int $id_type_equipement): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $type_equipement = $this->em->getRepository(TypeEquipement::class)->find($id_type_equipement);

            if ($type_equipement){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$type_equipement->getLibelle(),
                    'famille'=>$type_equipement->getCodeFamille()? $type_equipement->getCodeFamille()->getLibelle():""
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une type dans la liste !'
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
    #[Route('api/saveTypeEquipement', name: 'app_api_save_type_equipement')]
    public function app_api_save_type_equipement(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_type_equipement =Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string) $request->request->get('id_type')) ;
                $libelle = $request->request->get('type_equipement');
                $code_famille = $request->request->get('code_famille');

                if (!$libelle && Uuid::isValid($code_famille)) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $type_equipement = $this->em->getRepository(TypeEquipement::class)->find($id_type_equipement);
                    $isNew = false;
                    if (!$type_equipement) {
                        $type_equipement = new TypeEquipement();
                        $isNew = true;
                    }

                    $type_equipement->setLibelle(strtoupper($libelle));
                    $uuid_famille = Uuid::fromString($code_famille);
                    $famille = $this->em->getRepository(Famille::class)->find($uuid_famille);
                    $type_equipement->setCodeFamille($famille);

                    $this->em->persist($type_equipement);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Catégorie Equipement créé avec succès !' : 'Catégorie Equipement créé mise à jour avec succès !'
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

    #[Route('api/deleteTypeEquipement/{$id_type_equipement}', name: 'app_api_delete_type_equipement')]
    public function app_api_delete_type_equipement(Request $request, int $id_type_equipement): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $type_equipement = $this->em->getRepository(TypeEquipement::class)->find($id_type_equipement);

                if ($type_equipement){
                    $this->em->remove($type_equipement);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Catégorie Equipement supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une type d\'équipements !'
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
