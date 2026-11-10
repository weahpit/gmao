<?php

namespace App\Controller\Api\Logistique;

use App\Entity\Logistique\Navire;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiNavireController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllNavires', name: 'app_api_get_navire')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $navires = $this->em->getRepository(Navire::class)->findBy([], ['nom'=>'ASC']);
                    foreach ($navires as $navire){
                        $data[] = array(
                            'id'=>$navire->getId(),
                            'navire'=>$navire->getNom(),
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
    #[Route('api/getSingleNavire/{$id_navire}', name: 'app_api_get_single_navire')]
    public function app_api_get_single_navire($id_navire): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $navire = $this->em->getRepository(Navire::class)->find(Uuid::fromString($id_navire));

            if ($navire){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'navire'=>$navire->getNom(),
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
    #[Route('api/saveNavire', name: 'app_api_save_navire')]
    public function app_api_save_navire(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG")) {
            try {
                $id_navire = $request->request->get('id_navire');
                $libelle_navire = $request->request->get('navire');

                if (!$libelle_navire) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir le propriétaire !'];
                } else {
                    if ($id_navire){
                        $navire = $this->em->getRepository(Navire::class)->find(Uuid::fromString($id_navire));
                    } else {
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_navire);
                        $navire = $this->em->getRepository(Navire::class)->find($uuid);
                    }

                    $isNew = false;
                    if (!$navire) {
                        $navire = new Navire();
                        $isNew = true;
                    }

                    $navire->setNom(strtoupper($libelle_navire));

                    $this->em->persist($navire);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Propriétaire créé avec succès !' : 'Propriétaire  mis à jour avec succès !'
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
    #[Route('api/deleteNavire/{$id_navire}', name: 'app_api_delete_navire')]
    public function app_api_delete_navire(Request $request,  $id_navire): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $navire = $this->em->getRepository(Navire::class)->find(Uuid::fromString($id_navire));

                if ($navire){
                    $this->em->remove($navire);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Propriétaire supprimé avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner un Propriétaire !'
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
