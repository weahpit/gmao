<?php

namespace App\Controller\Api\Maintenance;

use App\Entity\StatutAlerte;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiStatutAlerteController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}
    #[Route('api/getAllStatutAlerte', name: 'app_api_get_statut_alerte')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $statut_alertes = $this->em->getRepository(StatutAlerte::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($statut_alertes as $statut_alerte){
                        $data[] = array(
                            'id'=>$statut_alerte->getId(),
                            'libelle'=>$statut_alerte->getLibelle()
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
    #[Route('api/getSingleStatutAlerte/{$id_statut_alerte}', name: 'app_api_get_single_statut_alerte')]
    public function app_api_get_single_statut_alerte(int $id_statut_alerte): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $statut_alerte = $this->em->getRepository(StatutAlerte::class)->find(Uuid::fromString($id_statut_alerte));

            if ($statut_alerte){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$statut_alerte->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une StatutAlerte dans la liste !'
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
    #[Route('api/saveStatutAlerte', name: 'app_api_save_statut_alerte')]
    public function app_api_save_statut_alerte(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_statut_alerte = $request->request->get('id_statut_alerte');
                $libelle = $request->request->get('libelle');

                if (!$libelle) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $isNew = false;
                    if ($id_statut_alerte === "0") {
                        $statut_alerte = new StatutAlerte();
                        $isNew = true;
                    } else {
                        $uuid = Uuid::fromString($id_statut_alerte);
                        $statut_alerte = $this->em->getRepository(StatutAlerte::class)->find($uuid);
                    }

                    $statut_alerte->setLibelle(strtoupper($libelle));

                    $this->em->persist($statut_alerte);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'StatutAlerte créée avec succès !' : 'StatutAlerte mise à jour avec succès !'
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
    #[Route('api/deleteStatutAlerte/{$id_statut_alerte}', name: 'app_api_delete_statut_alerte')]
    public function app_api_delete_statut_alerte($id_statut_alerte): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $statut_alerte = $this->em->getRepository(StatutAlerte::class)->find(Uuid::fromString($id_statut_alerte));

                if ($statut_alerte){
                    $this->em->remove($statut_alerte);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'StatutAlerte supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une StatutAlerte !'
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
