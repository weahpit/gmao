<?php

namespace App\Controller\Api\Admin;

use App\Entity\Directions;
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

    #[Route('api/getAllDirections', name: 'app_api_get_directions')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $directionss = $this->em->getRepository(Directions::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($directionss as $directions){
                        $data[] = array(
                            'id'=>$directions->getId(),
                            'direction'=>$directions->getLibelle()
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

    #[Route('api/getSingleDirection/{$id_directions}', name: 'app_api_get_single_directions')]
    public function app_api_get_single_directions(int $id_directions): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_directions);
                $directions = $this->em->getRepository(Directions::class)->find($uuid);

            if ($directions){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'direction'=>$directions->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une direction  dans la liste !'
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

    #[Route('api/saveDirection', name: 'app_api_save_directions')]
    public function app_api_save_directions(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_direction = $request->request->get('id_direction');
                $libelle = $request->request->get('direction');

                    if (!$libelle){
                        $reponse = ['code' => 'warning', 'msg' => 'Merci de renseigner la direction !'];
                    } else {
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_direction);
                        $directions = $this->em->getRepository(Directions::class)->find($uuid);
                        $isNew = false;
                        if (!$directions) {
                            $directions = new Directions();
                            $isNew = true;
                        }

                        $directions->setLibelle(strtoupper($libelle));
                        $this->em->persist($directions);
                        $this->em->flush();

                        $reponse = [
                            'code' => 'success',
                            'msg' => $isNew ? 'Direction créé avec succès !' : 'Direction  mise à jour avec succès !'
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

    #[Route('api/deleteDirection/{$id_directions}', name: 'app_api_delete_directions')]
    public function app_api_delete_directions(Request $request,  $id_directions): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_directions);
                $directions = $this->em->getRepository(Directions::class)->find($uuid);

                if ($directions){
                    $this->em->remove($directions);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Direction supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner un direction !'
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
