<?php

namespace App\Controller\Api\References;

use App\Entity\Emplacement;
use App\Entity\TypeEmplacement;
use App\Entity\ZoneExploitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiEmplacementController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllEmplacements', name: 'app_api_get_emplacement')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $emplacements = $this->em->getRepository(Emplacement::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($emplacements as $emplacement){
                        $data[] = array(
                            'id'=>$emplacement->getId(),
                            'emplacement'=>$emplacement->getLibelle(),
                            'zone'=>$emplacement->getCodeZone()? $emplacement->getCodeZone()->getNom() : "",
                            'type_emplacement'=>$emplacement->getCodeTypeEmplacement()? $emplacement->getCodeTypeEmplacement()->getLibelle() : ""
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
    #[Route('api/getSingleEmplacement/{$id_emplacement}', name: 'app_api_get_single_emplacement')]
    public function app_api_get_single_emplacement($id_emplacement): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $emplacement = $this->em->getRepository(Emplacement::class)->find(Uuid::fromString($id_emplacement));

            if ($emplacement){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$emplacement->getLibelle(),
                    'zone'=>$emplacement->getCodeZone()? $emplacement->getCodeZone()->getNom() : "",
                    'type_emplacement'=>$emplacement->getCodeTypeEmplacement()? $emplacement->getCodeTypeEmplacement()->getLibelle() : ""
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner un type d\'équipements dans la liste !'
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
    #[Route('api/saveEmplacement', name: 'app_api_save_emplacement')]
    public function app_api_save_emplacement(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_emplacement = $request->request->get('id_emplacement');
                $libelle = $request->request->get('emplacement');
                $zone = $request->request->get('zone');
                $type_emplacement = $request->request->get('type_emplacement');

                $ZoneExploitation = $this->em->getRepository(ZoneExploitation::class)->find(Uuid::fromString($zone));
                $TypeEmplacement = $this->em->getRepository(TypeEmplacement::class)->find(Uuid::fromString($type_emplacement));
                //dd($TypeEmplacement);
                if (!$libelle || !$ZoneExploitation || !$TypeEmplacement) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_emplacement);
                    $emplacement = $this->em->getRepository(Emplacement::class)->find($uuid);
                    $isNew = false;
                    if (!$emplacement) {
                        $emplacement = new Emplacement();
                        $isNew = true;
                    }

                    $emplacement->setLibelle(strtoupper($libelle));
                    $emplacement->setCodeZone($ZoneExploitation);
                    $emplacement->setCodeTypeEmplacement($TypeEmplacement);

                    $this->em->persist($emplacement);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Emplacement créé avec succès !' : 'Emplacement  mis à jour avec succès !'
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
    #[Route('api/deleteEmplacement/{$id_emplacement}', name: 'app_api_delete_emplacement')]
    public function app_api_delete_emplacement(Request $request,  $id_emplacement): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $emplacement = $this->em->getRepository(Emplacement::class)->find(Uuid::fromString($id_emplacement));

                if ($emplacement){
                    $this->em->remove($emplacement);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Nature Equipement supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner un type d\'équipements !'
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
