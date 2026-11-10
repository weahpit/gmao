<?php

namespace App\Controller\Api\Logistique;

use App\Entity\Logistique\Proprietaire;
use App\Entity\ZoneExploitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiProprietaireController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllProprietaires', name: 'app_api_get_proprietaire')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $proprietaires = $this->em->getRepository(Proprietaire::class)->findBy([], ['denomination'=>'ASC']);
                    foreach ($proprietaires as $proprietaire){
                        $data[] = array(
                            'id'=>$proprietaire->getId(),
                            'proprietaire'=>$proprietaire->getDenomination(),
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
    #[Route('api/getSingleProprietaire/{$id_proprietaire}', name: 'app_api_get_single_proprietaire')]
    public function app_api_get_single_proprietaire($id_proprietaire): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $proprietaire = $this->em->getRepository(Proprietaire::class)->find(Uuid::fromString($id_proprietaire));

            if ($proprietaire){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'proprietaire'=>$proprietaire->getDenomination(),
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
    #[Route('api/saveProprietaire', name: 'app_api_save_proprietaire')]
    public function app_api_save_proprietaire(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG")) {
            try {
                $id_proprietaire = $request->request->get('id_proprietaire');
                $libelle_proprietaire = $request->request->get('proprietaire');

                if (!$libelle_proprietaire) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir le propriétaire !'];
                } else {
                    if ($id_proprietaire){
                        $proprietaire = $this->em->getRepository(Proprietaire::class)->find(Uuid::fromString($id_proprietaire));
                    } else {
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_proprietaire);
                        $proprietaire = $this->em->getRepository(Proprietaire::class)->find($uuid);
                    }

                    $isNew = false;
                    if (!$proprietaire) {
                        $proprietaire = new Proprietaire();
                        $isNew = true;
                    }

                    $proprietaire->setDenomination(strtoupper($libelle_proprietaire));

                    $this->em->persist($proprietaire);
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
    #[Route('api/deleteProprietaire/{$id_proprietaire}', name: 'app_api_delete_proprietaire')]
    public function app_api_delete_proprietaire(Request $request,  $id_proprietaire): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $proprietaire = $this->em->getRepository(Proprietaire::class)->find(Uuid::fromString($id_proprietaire));

                if ($proprietaire){
                    $this->em->remove($proprietaire);
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
