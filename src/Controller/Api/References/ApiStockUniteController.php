<?php

namespace App\Controller\Api\References;

use App\Entity\Stock\Unite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiStockUniteController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllStockUnites', name: 'app_api_get_stock_unite')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $stock_unites = $this->em->getRepository(Unite::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($stock_unites as $stock_unite){
                        $data[] = array(
                            'id'=>$stock_unite->getId(),
                            'libelle'=>$stock_unite->getLibelle(),
                            'symbole'=>$stock_unite->getSymbole(),
                            'position'=>$stock_unite->getPosition()
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
    #[Route('api/getSingleStockUnite/{$id_stock_unite}', name: 'app_api_get_single_stock_unite')]
    public function app_api_get_single_stock_unite($id_stock_unite): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $stock_unite = $this->em->getRepository(Unite::class)->find(Uuid::fromString($id_stock_unite));

            if ($stock_unite){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'id'=>$stock_unite->getId(),
                    'libelle'=>$stock_unite->getLibelle(),
                    'symbole'=>$stock_unite->getSymbole(),
                    'position'=>$stock_unite->getPosition()
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
    #[Route('api/saveStockUnite', name: 'app_api_save_stock_unite')]
    public function app_api_save_stock_unite(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG")) {
            try {
                $id_stock_unite = $request->request->get('id_stock_unite');
                $libelle = $request->request->get('libelle');
                $symbole = $request->request->get('symbole');
                $position = $request->request->get('position');

                if (!$libelle || !$symbole || !$position) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir toutes les informations !'];
                } else {
                    if ($id_stock_unite){
                        $stock_unite = $this->em->getRepository(Unite::class)->find(Uuid::fromString($id_stock_unite));
                    } else {
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_stock_unite);
                        $stock_unite = $this->em->getRepository(Unite::class)->find($uuid);
                    }

                    $isNew = false;
                    if (!$stock_unite) {
                        $stock_unite = new Unite();
                        $isNew = true;
                    }

                    $stock_unite->setLibelle(strtoupper($libelle));
                    $stock_unite->setSymbole($symbole);
                    $stock_unite->setPosition((int) $position);

                    $this->em->persist($stock_unite);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Unité Stock créée avec succès !' : 'Unité Stock  mise à jour avec succès !'
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
    #[Route('api/deleteStockUnite/{$id_stock_unite}', name: 'app_api_delete_stock_unite')]
    public function app_api_delete_stock_unite(Request $request,  $id_stock_unite): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $stock_unite = $this->em->getRepository(Unite::class)->find(Uuid::fromString($id_stock_unite));

                if ($stock_unite){
                    $this->em->remove($stock_unite);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Unité Stock supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une Unité !'
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
