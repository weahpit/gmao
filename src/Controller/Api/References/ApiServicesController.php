<?php

namespace App\Controller\Api\References;

use App\Entity\Directions;
use App\Entity\Services;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiServicesController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllServices', name: 'app_api_get_services')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{

                    $servicess = $this->em->getRepository(Services::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($servicess as $services){
                        $data[] = array(
                            'id'=>$services->getId(),
                            'service'=>$services->getLibelle(),
                            'direction'=>$services->getCodeDirection() ? $services->getCodeDirection()->getLibelle() : "" ,
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
    #[Route('api/getAllServicesByDirection/{code_direction}', name: 'app_api_get_services_by_direction')]
    public function app_api_get_services_by_direction($code_direction): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    $direction = $this->em->getRepository(Directions::class)->find($code_direction);

                    if ($direction){
                        $servicess = $this->em->getRepository(Services::class)->findBy([], ['libelle'=>'ASC']);
                        foreach ($servicess as $services){
                            $data[] = array(
                                'id'=>$services->getId(),
                                'service'=>$services->getLibelle(),
                                'direction'=>$services->getCodeDirection() ? $services->getCodeDirection()->getLibelle() : "" ,
                            );
                        }
                        $reponse = array('code'=>'success', 'msg'=>'Succès', 'data'=>$data);
                    } else {
                        $reponse = array('code'=>'warning', 'msg'=>'Merci de sélectionner la direction SVP');
                    }

                } catch (\Throwable $throwable){
                    $reponse = array('code'=>'error', 'msg'=>'Erreur ! <br>'. $throwable->getMessage());
                }
            } else {
                $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
            }
            return new JsonResponse(json_encode($reponse));
        }
    #[Route('api/getSingleService/{$id_services}', name: 'app_api_get_single_service')]
    public function app_api_get_single_service($id_services): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $services = $this->em->getRepository(Services::class)->find(Uuid::fromString($id_services));

            if ($services){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'services'=>$services->getLibelle(),
                    );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner un propriétaire dans la liste !'
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
    #[Route('api/saveService', name: 'app_api_save_services')]
    public function app_api_save_services(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG")) {
            try {
                $id_service = $request->request->get('id_service');
                $libelle_service = $request->request->get('service');
                $libelle_direction = $request->request->get('direction');

                $direction = $this->em->getRepository(Directions::class)->find($libelle_direction);
                if (!$libelle_service || !$direction) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir la direction et le service !'];
                } else {
                    if ($id_service){
                        $services = $this->em->getRepository(Services::class)->find(Uuid::fromString($id_service));
                    } else {
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_service);
                        $services = $this->em->getRepository(Services::class)->find($uuid);
                    }

                    $isNew = false;
                    if (!$services) {
                        $services = new Services();
                        $isNew = true;
                    }

                    $services->setLibelle(strtoupper($libelle_service));
                    $services->setCodeDirection($direction);

                    $this->em->persist($services);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Service créé avec succès !' : 'Service  mis à jour avec succès !'
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
    #[Route('api/deleteService/{$id_service}', name: 'app_api_delete_services')]
    public function app_api_delete_services(Request $request,  $id_service): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $service = $this->em->getRepository(Services::class)->find(Uuid::fromString($id_service));

                if ($service){
                    $this->em->remove($service);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Service supprimé avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner un Service !'
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
