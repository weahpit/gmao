<?php

namespace App\Controller\Api\References;

use App\Entity\Criticite;
use App\Entity\EquipementType;
use App\Entity\Famille;
use App\Entity\NatureEquipement;
use App\Entity\TypeEq;
use App\Entity\TypeEquipement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiEquipementTypeController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllEquipementTypes', name: 'app_api_get_equipement_type')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $equipement_types = $this->em->getRepository(EquipementType::class)->findBy([], ['nom_equipement'=>'ASC']);
                    foreach ($equipement_types as $equipement_type){
                        $data[] = array(
                            'id'=>$equipement_type->getId(),
                            'code'=>$equipement_type->getCode(),
                            'nom'=>strtoupper($equipement_type->getNomEquipement()),
                            'categorie'=>$equipement_type->getCategorie() ? strtoupper($equipement_type->getCategorie()->getLibelle()) : "",
                            'famille'=>$equipement_type->getCategorie() ? strtoupper($equipement_type->getCategorie()->getCodeFamille()->getLibelle()) : "",
                            'photo'=>$equipement_type->getPhoto()
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

    #[Route('api/getEquipementTypeByFamille/{id_famille}', name: 'app_api_get_equipement_type_by_famille')]
    public function app_api_get_equipement_type_by_famille($id_famille): Response
    {
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        $reponse = array();
        $data = array();
        if ($this->isGranted("ROLE_USER")){
            try{
                // recuperer la famille
                $famille = $this->em->getRepository(Famille::class)->find(Uuid::fromString($id_famille));
                if ($famille){
                    // Recupere les datégories
                    $categories = $this->em->getRepository(TypeEquipement::class)->findBy(['code_famille'=>$famille]);
                    foreach ($categories as $categorie){
                        // Recherche les équipements
                        $equipement_types = $this->em->getRepository(EquipementType::class)->findBy(['categorie'=>$categorie], ['nom_equipement'=>'ASC']);
                        foreach ($equipement_types as $equipement_type){
                            $data[] = array(
                                'id'=>$equipement_type->getId(),
                                'code'=>$equipement_type->getCode(),
                                'nom'=>strtoupper($equipement_type->getNomEquipement()),
                                'categorie'=>$equipement_type->getCategorie() ? strtoupper($equipement_type->getCategorie()->getLibelle()) : "",
                                'famille'=>$equipement_type->getCategorie() ? strtoupper($equipement_type->getCategorie()->getCodeFamille()->getLibelle()) : "",
                                'photo'=>$equipement_type->getPhoto()
                            );
                        }
                    }
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

    #[Route('api/getEquipementTypeByCategorie/{id_categorie}', name: 'app_api_get_equipement_type_by_categorie')]
    public function app_api_get_equipement_type_by_categorie($id_categorie): Response
    {
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        $reponse = array();
        $data = array();
        if ($this->isGranted("ROLE_USER")){
            try{
                // recuperer la famille
                $categorie = $this->em->getRepository(TypeEquipement::class)->find(Uuid::fromString($id_categorie));
                if ($categorie){
                        // Recherche les équipements
                        $equipement_types = $this->em->getRepository(EquipementType::class)->findBy(['categorie'=>$categorie], ['nom_equipement'=>'ASC']);
                        foreach ($equipement_types as $equipement_type){
                            $data[] = array(
                                'id'=>$equipement_type->getId(),
                                'code'=>$equipement_type->getCode(),
                                'nom'=>strtoupper($equipement_type->getNomEquipement()),
                                'categorie'=>$equipement_type->getCategorie() ? strtoupper($equipement_type->getCategorie()->getLibelle()) : "",
                                'famille'=>$equipement_type->getCategorie() ? strtoupper($equipement_type->getCategorie()->getCodeFamille()->getLibelle()) : "",
                                'photo'=>$equipement_type->getPhoto()
                            );
                        }

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
    #[Route('api/getSingleEquipementType', name: 'app_api_get_single_equipement_type')]
    public function app_api_get_single_equipement_type(Request $request): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $id_equipement = $request->request->get('id_equipement');
                $equipement_type = $this->em->getRepository(EquipementType::class)->find(Uuid::fromString($id_equipement));

            if ($equipement_type){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$equipement_type->getNomEquipement(),
                    'nature'=>$equipement_type->getNatureEquipement() ? $equipement_type->getNatureEquipement()->getLibelle() : "",
                    'categorie'=>$equipement_type->getCategorie()? $equipement_type->getCategorie()->getLibelle() : "",
                    'photo'=>$equipement_type->getPhoto(),
                    'code_eq'=>$equipement_type->getCode(),
                    'criticite'=>$equipement_type->getCriticite()? $equipement_type->getCriticite()->getLibelle() : "",
                    'qte'=>$equipement_type->getQte()? :0
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une nature d\'équipements dans la liste !'
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
    #[Route('api/saveEquipementType', name: 'app_api_save_equipement_type')]
    public function app_api_save_equipement_type(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_equipement = $request->request->get('idEquipement');
                $CodeEquipement = $request->request->get('CodeEquipement');
                $nomEquipement = $request->request->get('nomEquipement');
                $categorie = $request->request->get('categorie');
                $criticite_id = $request->request->get('criticite_id');
                $type_unicite_id = $request->request->get('type_unicite_id');
                $type_eq = $request->request->get('type_eq');
                $seuil = $request->request->get('seuil');
                $photo = $request->files->get('photo');

                $criticite = $this->em->getRepository(Criticite::class)->find(Uuid::fromString($criticite_id));
                $nature = $this->em->getRepository(NatureEquipement::class)->find(Uuid::fromString($type_unicite_id));
                $typeEquipement = $this->em->getRepository(TypeEquipement::class)->find(Uuid::fromString($categorie));
                $typeEq = $this->em->getRepository(TypeEq::class)->find(Uuid::fromString($type_eq));


                if (!$nomEquipement || !$CodeEquipement) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_equipement);
                    $equipement = $this->em->getRepository(EquipementType::class)->find($uuid);
                    $isNew = false;
                    if (!$equipement) {
                        $equipement = new EquipementType();
                        $isNew = true;
                    }

                    $equipement->setCode(strtoupper($CodeEquipement));
                    $equipement->setNomEquipement(strtoupper($nomEquipement));

                    if ($criticite) { $equipement->setCriticite($criticite);}
                    if ($nature) { $equipement->setNatureEquipement($nature);;}
                    if ($typeEquipement) { $equipement->setCategorie($typeEquipement);;}
                    if ($typeEq) { $equipement->setTypeEq($typeEq);;}
                    if ($seuil){$equipement->setSeuil($seuil);} else {$equipement->setSeuil(0);}

                    // Charger la photo de l'équipement
                    if ($photo) {
                        $filename = uniqid().'.'.$photo->guessExtension();
                        $photo->move($this->getParameter('photo_equipement'), $filename);
                        $equipement->setPhoto($filename);
                    }

                    $this->em->persist($equipement);
                    $this->em->flush();

                    $reponse = [
                        'code' => "success",
                        'msg' => $isNew ? 'Equipement créé avec succès !' : 'Equipement mis à jour avec succès !'
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
    #[Route('api/deleteEquipementType/{$id_equipement_type}', name: 'app_api_delete_equipement_type')]
    public function app_api_delete_equipement_type(Request $request, int $id_equipement_type): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $equipement_type = $this->em->getRepository(EquipementType::class)->find($id_equipement_type);

                if ($equipement_type){
                    $this->em->remove($equipement_type);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Nature EquipementType supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une nature d\'équipements !'
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
