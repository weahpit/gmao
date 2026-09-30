<?php

namespace App\Controller\Api\References;

use App\Entity\Marque;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiMarqueController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllMarques', name: 'app_api_get_marque')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $marques = $this->em->getRepository(Marque::class)->findBy([], ['nom'=>'ASC']);
                    foreach ($marques as $marque){
                        $data[] = array(
                            'id'=>$marque->getId(),
                            'marque'=>$marque->getNom()
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
    #[Route('api/getSingleMarque/{$id_marque}', name: 'app_api_get_single_marque')]
    public function app_api_get_single_marque(int $id_marque): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $marque = $this->em->getRepository(Marque::class)->find($id_marque);

            if ($marque){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'marque'=>$marque->getNom()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une marque  dans la liste !'
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
    #[Route('api/saveMarque', name: 'app_api_save_marque')]
    public function app_api_save_marque(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_marque = $request->request->get('id_marque');
                $libelle_marque = $request->request->get('marque');

                if (!$libelle_marque) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir la marque !'];
                } else {
                    $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_marque);
                    $marque = $this->em->getRepository(Marque::class)->find($uuid);
                    $isNew = false;
                    if (!$marque) {
                        $marque = new Marque();
                        $isNew = true;
                    }

                    $marque->setNom(strtoupper($libelle_marque));

                    $this->em->persist($marque);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Marque créée avec succès !' : 'Marque  mise à jour avec succès !'
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
    #[Route('api/deleteMarque/{$id_marque}', name: 'app_api_delete_marque')]
    public function app_api_delete_marque(Request $request, int $id_marque): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_marque);
                $marque = $this->em->getRepository(Marque::class)->find($uuid);

                if ($marque){
                    $this->em->remove($marque);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Marque supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une marque d\'équipements !'
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
