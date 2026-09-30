<?php

namespace App\Controller\Api\References;

use App\Entity\TypeEq;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiTypeEqController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllTypeEq', name: 'app_api_get_type_eq')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $types = $this->em->getRepository(TypeEq::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($types as $type){
                        $data[] = array(
                            'id'=>$type->getId(),
                            'type_eq'=>$type->getLibelle()
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
    #[Route('api/getSingleTypeEq/{$id_type_eq}', name: 'app_api_get_single_type_eq')]
    public function app_api_get_single_type_eq($id_type_eq): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $type_eq = $this->em->getRepository(TypeEq::class)->find($id_type_eq);

            if ($type_eq){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$type_eq->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner un type d\'équipements dans la liste !'
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
    #[Route('api/saveTypeEq', name: 'app_api_save_type_eq')]
    public function app_api_save_type_eq(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_type_eq = $request->request->get('id_type_eq');
                $libelle = $request->request->get('type_eq');

                if (!$libelle) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_type_eq);
                    $type_eq = $this->em->getRepository(TypeEq::class)->find($uuid);
                    $isNew = false;
                    if (!$type_eq) {
                        $type_eq = new TypeEq();
                        $isNew = true;
                    }

                    $type_eq->setLibelle(strtoupper($libelle));

                    $this->em->persist($type_eq);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Type Equipement créé avec succès !' : 'Type Equipement créé mise à jour avec succès !'
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
    #[Route('api/deleteTypeEq/{$id_type_eq}', name: 'app_api_delete_type_eq')]
    public function app_api_delete_type_eq(Request $request, int $id_type_eq): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $type_eq = $this->em->getRepository(TypeEq::class)->find($id_type_eq);

                if ($type_eq){
                    $this->em->remove($type_eq);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Nature Equipement supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner un type d\'équipements !'
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
