<?php

namespace App\Controller\Api\References;

use App\Entity\Famille;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiFamilleController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllFamilles', name: 'app_api_get_famille')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $familles = $this->em->getRepository(Famille::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($familles as $famille){
                        $data[] = array(
                            'id'=>$famille->getId(),
                            'famille'=>$famille->getLibelle()
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
    #[Route('api/getSingleFamille/{$id_famille}', name: 'app_api_get_single_famille')]
    public function app_api_get_single_famille(int $id_famille): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $famille = $this->em->getRepository(Famille::class)->find($id_famille);

            if ($famille){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'famille'=>$famille->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une famille  dans la liste !'
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
    #[Route('api/saveFamille', name: 'app_api_save_famille')]
    public function app_api_save_famille(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_famille = $request->request->get('id_famille');
                $libelle_famille = $request->request->get('famille');

                if (!$libelle_famille) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir la famille !'];
                } else {
                    $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_famille);
                    $famille = $this->em->getRepository(Famille::class)->find($uuid);
                    $isNew = false;
                    if (!$famille) {
                        $famille = new Famille();
                        $isNew = true;
                    }

                    $famille->setLibelle(strtoupper($libelle_famille));

                    $this->em->persist($famille);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Famille créée avec succès !' : 'Famille  mise à jour avec succès !'
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
    #[Route('api/deleteFamille/{$id_famille}', name: 'app_api_delete_famille')]
    public function app_api_delete_famille(Request $request, int $id_famille): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_famille);
                $famille = $this->em->getRepository(Famille::class)->find($uuid);

                if ($famille){
                    $this->em->remove($famille);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Famille supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une famille d\'équipements !'
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
