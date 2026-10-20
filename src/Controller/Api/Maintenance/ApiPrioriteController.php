<?php

namespace App\Controller\Api\Maintenance;

use App\Entity\Maintenance\Priorite;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiPrioriteController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllPriorite', name: 'app_api_get_priorite')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $priorites = $this->em->getRepository(Priorite::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($priorites as $priorite){
                        $data[] = array(
                            'id'=>$priorite->getId(),
                            'libelle'=>$priorite->getLibelle()
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
    #[Route('api/getSinglePriorite/{$id_priorite}', name: 'app_api_get_single_priorite')]
    public function app_api_get_single_priorite(int $id_priorite): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $priorite = $this->em->getRepository(Priorite::class)->find(Uuid::fromString($id_priorite));

            if ($priorite){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$priorite->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une Priorite dans la liste !'
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
    #[Route('api/savePriorite', name: 'app_api_save_priorite')]
    public function app_api_save_priorite(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_priorite = $request->request->get('id_priorite');
                $libelle = $request->request->get('libelle');

                if (!$libelle) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $isNew = false;
                    if ($id_priorite === "0") {
                        $priorite = new Priorite();
                        $isNew = true;
                    } else {
                        $uuid = Uuid::fromString($id_priorite);
                        $priorite = $this->em->getRepository(Priorite::class)->find($uuid);
                    }

                    $priorite->setLibelle(strtoupper($libelle));

                    $this->em->persist($priorite);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Priorite créée avec succès !' : 'Priorite mise à jour avec succès !'
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
    #[Route('api/deletePriorite/{$id_priorite}', name: 'app_api_delete_priorite')]
    public function app_api_delete_priorite($id_priorite): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $priorite = $this->em->getRepository(Priorite::class)->find(Uuid::fromString($id_priorite));

                if ($priorite){
                    $this->em->remove($priorite);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Priorite supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une Priorite !'
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
