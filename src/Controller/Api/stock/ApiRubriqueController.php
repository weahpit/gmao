<?php

namespace App\Controller\Api\stock;

use App\Entity\Stock\Rubrique;
use App\Entity\ZoneExploitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiRubriqueController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllRubriques', name: 'app_api_get_rubrique')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $rubriques = $this->em->getRepository(Rubrique::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($rubriques as $rubrique){
                        $data[] = array(
                            'id'=>$rubrique->getId(),
                            'rubrique'=>$rubrique->getLibelle(),
                            'active'=>$rubrique->isActive() ? "OUI" : "NON",
                            'position'=>$rubrique->getPosition()
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
    #[Route('api/getSingleRubrique/{$id_rubrique}', name: 'app_api_get_single_rubrique')]
    public function app_api_get_single_rubrique($id_rubrique): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $rubrique = $this->em->getRepository(Rubrique::class)->find(Uuid::fromString($id_rubrique));

            if ($rubrique){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'rubrique'=>$rubrique->getLibelle(),
                    'active'=>$rubrique->isActive() ? "OUI" : "NON",
                    'position'=>$rubrique->getPosition()
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
    #[Route('api/saveRubrique', name: 'app_api_save_rubrique')]
    public function app_api_save_rubrique(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG")) {
            try {
                $id_rubrique = $request->request->get('id_rubrique');
                $libelle_rubrique = $request->request->get('rubrique');
                $position = $request->request->get('position');

                if (!$libelle_rubrique) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir la rubrique !'];
                } else {
                    if ($id_rubrique){
                        $rubrique = $this->em->getRepository(Rubrique::class)->find(Uuid::fromString($id_rubrique));
                    } else {
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_rubrique);
                        $rubrique = $this->em->getRepository(Rubrique::class)->find($uuid);
                    }

                    $isNew = false;
                    if (!$rubrique) {
                        $rubrique = new Rubrique();
                        $isNew = true;
                    }

                    $rubrique->setLibelle(strtoupper($libelle_rubrique));
                    $rubrique->setPosition($position);

                    $this->em->persist($rubrique);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Rubrique créée avec succès !' : 'Rubrique  mis à jour avec succès !'
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
    #[Route('api/deleteRubrique/{$id_rubrique}', name: 'app_api_delete_rubrique')]
    public function app_api_delete_rubrique(Request $request,  $id_rubrique): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $rubrique = $this->em->getRepository(Rubrique::class)->find(Uuid::fromString($id_rubrique));

                if ($rubrique){
                    $this->em->remove($rubrique);
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
