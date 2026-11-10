<?php

namespace App\Controller\Api\Logistique;

use App\Entity\Logistique\Magasin;
use App\Entity\ZoneExploitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiMagasinController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllMagasins', name: 'app_api_get_magasin')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $magasins = $this->em->getRepository(Magasin::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($magasins as $magasin){
                        $data[] = array(
                            'id'=>$magasin->getId(),
                            'magasin'=>$magasin->getLibelle(),
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
    #[Route('api/getSingleMagasin/{$id_magasin}', name: 'app_api_get_single_magasin')]
    public function app_api_get_single_magasin($id_magasin): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $magasin = $this->em->getRepository(Magasin::class)->find(Uuid::fromString($id_magasin));

            if ($magasin){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'magasin'=>$magasin->getLibelle(),
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
    #[Route('api/saveMagasin', name: 'app_api_save_magasin')]
    public function app_api_save_magasin(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG")) {
            try {
                $id_magasin = $request->request->get('id_magasin');
                $libelle_magasin = $request->request->get('magasin');

                if (!$libelle_magasin) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir le propriétaire !'];
                } else {
                    if ($id_magasin){
                        $magasin = $this->em->getRepository(Magasin::class)->find(Uuid::fromString($id_magasin));
                    } else {
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_magasin);
                        $magasin = $this->em->getRepository(Magasin::class)->find($uuid);
                    }

                    $isNew = false;
                    if (!$magasin) {
                        $magasin = new Magasin();
                        $isNew = true;
                    }

                    $magasin->setLibelle(strtoupper($libelle_magasin));

                    $this->em->persist($magasin);
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
    #[Route('api/deleteMagasin/{$id_magasin}', name: 'app_api_delete_magasin')]
    public function app_api_delete_magasin(Request $request,  $id_magasin): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $magasin = $this->em->getRepository(Magasin::class)->find(Uuid::fromString($id_magasin));

                if ($magasin){
                    $this->em->remove($magasin);
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
