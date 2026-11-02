<?php

namespace App\Controller\Api\Admin;

use App\Entity\Groupe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiGroupeController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllGroupes', name: 'app_api_get_groupe')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $groupes = $this->em->getRepository(Groupe::class)->findBy([], ['nom_groupe'=>'ASC']);
                    foreach ($groupes as $groupe){
                        $data[] = array(
                            'id'=>$groupe->getId(),
                            'groupe'=>$groupe->getNomGroupe()
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
    #[Route('api/getSingleGroupe/{$id_groupe}', name: 'app_api_get_single_groupe')]
    public function app_api_get_single_groupe(int $id_groupe): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $groupe = $this->em->getRepository(Groupe::class)->find($id_groupe);

            if ($groupe){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'groupe'=>$groupe->getNomGroupe()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner un groupe  dans la liste !'
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
    #[Route('api/saveGroupe', name: 'app_api_save_groupe')]
    public function app_api_save_groupe(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_groupe = $request->request->get('id_groupe');
                $libelle_groupe = $request->request->get('groupe');

                if (!$libelle_groupe) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir la groupe !'];
                } else {
                    $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_groupe);
                    $groupe = $this->em->getRepository(Groupe::class)->find($uuid);
                    $isNew = false;
                    if (!$groupe) {
                        $groupe = new Groupe();
                        $isNew = true;
                    }

                    $groupe->setNomGroupe(strtoupper($libelle_groupe));

                    $this->em->persist($groupe);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Groupe créé avec succès !' : 'Groupe  mis à jour avec succès !'
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
    #[Route('api/deleteGroupe/{$id_groupe}', name: 'app_api_delete_groupe')]
    public function app_api_delete_groupe(Request $request, int $id_groupe): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_groupe);
                $groupe = $this->em->getRepository(Groupe::class)->find($uuid);

                if ($groupe){
                    $this->em->remove($groupe);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Groupe supprimé avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner un groupe !'
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
