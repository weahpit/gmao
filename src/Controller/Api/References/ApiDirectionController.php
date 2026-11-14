<?php

namespace App\Controller\Api\References;

use App\Entity\Directions;
use App\Entity\ZoneExploitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiDirectionController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllDirections', name: 'app_api_get_direction')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{

                    $directions = $this->em->getRepository(Directions::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($directions as $direction){
                        $data[] = array(
                            'id'=>$direction->getId(),
                            'direction'=>$direction->getLibelle(),
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
    #[Route('api/getSingleDirection/{$id_direction}', name: 'app_api_get_single_direction')]
    public function app_api_get_single_direction($id_direction): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $direction = $this->em->getRepository(Directions::class)->find(Uuid::fromString($id_direction));

            if ($direction){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'direction'=>$direction->getLibelle(),
                    );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une direction dans la liste !'
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
    #[Route('api/saveDirection', name: 'app_api_save_direction')]
    public function app_api_save_direction(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG")) {
            try {
                $id_direction = $request->request->get('id_direction');
                $libelle_direction = $request->request->get('direction');

                if (!$libelle_direction) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir la direction !'];
                } else {
                    if ($id_direction){
                        $direction = $this->em->getRepository(Directions::class)->find(Uuid::fromString($id_direction));
                    } else {
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_direction);
                        $direction = $this->em->getRepository(Directions::class)->find($uuid);
                    }

                    $isNew = false;
                    if (!$direction) {
                        $direction = new Directions();
                        $isNew = true;
                    }

                    $direction->setLibelle(strtoupper($libelle_direction));

                    $this->em->persist($direction);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Direction créée avec succès !' : 'Direction  mise à jour avec succès !'
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
    #[Route('api/deleteDirections/{$id_direction}', name: 'app_api_delete_direction')]
    public function app_api_delete_direction(Request $request,  $id_direction): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $direction = $this->em->getRepository(Directions::class)->find(Uuid::fromString($id_direction));

                if ($direction){
                    $this->em->remove($direction);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Direction supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une Direction !'
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
