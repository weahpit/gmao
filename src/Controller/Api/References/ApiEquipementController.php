<?php

namespace App\Controller\Api\References;

use App\Entity\Criticite;
use App\Entity\Equipement;
use App\Entity\EquipementType;
use App\Entity\EtatEquipement;
use App\Entity\Fournisseur;
use App\Entity\Marque;
use App\Entity\MouvementEquipement;
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

final class ApiEquipementController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllEquipements', name: 'app_api_get_equipement')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $equipements = $this->em->getRepository(Equipement::class)->findBy([], ['nom'=>'ASC']);
                    foreach ($equipements as $equipement){
                        $derniere_date = "";
                        $lastMvt = $this->em->getRepository(MouvementEquipement::class)->findOneBy(['code_equipement'=>$equipement], ['created_at'=>'DESC']);
                        if ($lastMvt){$derniere_date =$lastMvt->getCreatedAt() ? $lastMvt->getCreatedAt()->format('d/m/Y H:i:s') : "";}
                        $data[] = array(
                            'id'=>$equipement->getId(),
                            'nom'=>strtoupper($equipement->getNom()),
                            'marque'=>$equipement->getMarque()? $equipement->getMarque()->getNom() : "" ,
                            'modele'=>strtoupper($equipement->getModele()),
                            'code'=>$equipement->getCode(),
                            'etat'=>$equipement->getEtat()?$equipement->getEtat()->getLibelle() : "" ,
                            'criticite'=>$equipement->getCriticite()? $equipement->getCriticite()->getLibelle() : "",
                            'nature'=>$equipement->getNature()?$equipement->getNature()->getLibelle() : "",
                            'adresse'=>$equipement->getCodeFournisseur() ? $equipement->getCodeFournisseur()->getSigle() : "",
                            'categorie'=>$equipement->getTypeUnicite() ? $equipement->getTypeUnicite()->getLibelle() : "",
                            'qte'=>$equipement->getQte(),
                            'lastMvt'=>$derniere_date
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

    #[Route('api/getAllEquipementsByEquimentType/{idTypeEq}', name: 'app_api_get_equipement_by_type_eq')]
    public function app_api_get_equipement_by_type_eq($idTypeEq): Response
    {
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        $reponse = array();
        $data = array();
        if ($this->isGranted("ROLE_USER")){
            try{
                // Liste des Natures d'équipements
                $eqType = $this->em->getRepository(EquipementType::class)->find(Uuid::fromString($idTypeEq));

                $equipements = $this->em->getRepository(Equipement::class)->findBy(['code_equipement_type'=>$eqType], ['marque'=>'ASC','nom'=>'ASC']);
                foreach ($equipements as $equipement){
                    $data[] = array(
                        'id'=>$equipement->getId(),
                        'nom'=>strtoupper($equipement->getNom()),
                        'marque'=>$equipement->getMarque()? $equipement->getMarque()->getNom() : "" ,
                        'modele'=>strtoupper($equipement->getModele()),
                        'code'=>$equipement->getCode(),
                        'etat'=>$equipement->getEtat()?$equipement->getEtat()->getLibelle() : "" ,
                        'criticite'=>$equipement->getCriticite()? $equipement->getCriticite()->getLibelle() : "",
                        'nature'=>$equipement->getNature()?$equipement->getNature()->getLibelle() : "",
                        'adresse'=>$equipement->getCodeFournisseur() ? $equipement->getCodeFournisseur()->getSigle() : "",
                        'categorie'=>$equipement->getTypeUnicite() ? $equipement->getTypeUnicite()->getLibelle() : "",
                        'stockFinal'=>$equipement->getStockFinal()
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

    #[Route('api/getSingleEquipement', name: 'app_api_get_single_equipement')]
    public function app_api_get_single_equipement(Request $request): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            $id_equipement = $request->request->get('id_equipement');
            try {
                $equipement = $this->em->getRepository(Equipement::class)->find(Uuid::fromString($id_equipement));

            if ($equipement){
                $criticite = "";
                if ($equipement->getCodeEquipementType()){
                    if ($equipement->getCodeEquipementType()->getCriticite()){
                        $criticite = $equipement->getCodeEquipementType()->getCriticite()->getId();
                    }
                }
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'id'=>$equipement->getId(),
                    'nom'=>strtoupper($equipement->getNom()),
                    'marque'=>$equipement->getMarque()? $equipement->getMarque()->getId() : "" ,
                    'modele'=>strtoupper($equipement->getModele()),
                    'code_equipement'=>$equipement->getCode(),
                    'etat'=>$equipement->getEtat()?$equipement->getEtat()->getId() : "" ,
                    'criticite'=>$criticite,
                    'nature'=>$equipement->getNature()?$equipement->getNature()->getId() : "",
                    'adresse'=>$equipement->getCodeFournisseur() ? $equipement->getCodeFournisseur()->getSigle() : "",
                    'categorie'=>$equipement->getTypeUnicite() ? $equipement->getTypeUnicite()->getId() : "",
                    'famille'=>$equipement->getTypeUnicite() ? $equipement->getTypeUnicite()->getCodeFamille()->getId() : "",
                    'stockFinal'=>$equipement->getStockFinal(),
                    'photo'=>$equipement->getCodeEquipementType() ? $equipement->getCodeEquipementType()->getPhoto() : "",
                    'type_eq'=>$equipement->getCodeEquipementType() ? $equipement->getCodeEquipementType()->getTypeEq()->getId() : "",
                    'seuil'=>$equipement->getCodeEquipementType() ? $equipement->getCodeEquipementType()->getSeuil(): 0,
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
    #[Route('api/saveEquipement', name: 'app_api_save_equipement')]
    public function app_api_save_equipement(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_PDR")) {
            try {
                $id_equipement = $request->request->get('idEquipement');
                $CodeEquipement = $request->request->get('CodeEquipement');
                $nomEquipement = $request->request->get('nomEquipement');
                $codeFournisseur  = $request->request->get('codeFournisseur');
                $marque= $request->request->get('marque');
                $modele = $request->request->get('modele');
                $date_entree = $request->request->get('date_entree');
                $etat_equipement = $request->request->get('etat_equipement');
                $categorie = $request->request->get('categorie');
                $criticite_id = $request->request->get('criticite_id');
                $type_unicite_id = $request->request->get('type_unicite_id');
                $numero_serie = $request->request->get('numero_serie');
                $stock_initial = $request->request->get('stock_initial');
                $stock_actuel = $request->request->get('stock_actuel');
                $stock_final = $request->request->get('stock_final');
                $photo = $request->files->get('photo');

                $fournisseur = $this->em->getRepository(Fournisseur::class)->find($codeFournisseur);
                $criticite = $this->em->getRepository(Criticite::class)->find($criticite_id);
                $nature = $this->em->getRepository(NatureEquipement::class)->find($type_unicite_id);
                $etat = $this->em->getRepository(EtatEquipement::class)->find($etat_equipement);
                $mrq = $this->em->getRepository(Marque::class)->find($marque);
                $typeEq = $this->em->getRepository(TypeEquipement::class)->find($categorie);


                if (!$nomEquipement || !$CodeEquipement || !$marque) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_equipement);
                    $equipement = $this->em->getRepository(Equipement::class)->find($uuid);
                    $isNew = false;
                    if (!$equipement) {
                        $equipement = new Equipement();
                        $isNew = true;
                    }

                    $equipement->setCode(strtoupper($CodeEquipement));
                    $equipement->setNom(strtoupper($nomEquipement));

                    if ($fournisseur) { $equipement->setCodeFournisseur($fournisseur);}
                    if ($criticite) { $equipement->setCriticite($criticite);}
                    if ($nature) { $equipement->setNature($nature);;}
                    if ($etat) { $equipement->setEtat($etat);;}
                    if ($mrq) { $equipement->setMarque($mrq);;}
                    if ($typeEq) { $equipement->setTypeUnicite($typeEq);;}

                    $equipement->setModele(strtoupper($modele));
                    $equipement->setDateEntree(new \DateTime($date_entree));
                    $equipement->setNumeroSerie(strtoupper($numero_serie));
                    $equipement->setStockActuel(floatval($stock_actuel));
                    $equipement->setStockInitial(floatval($stock_initial));
                    $equipement->setStockFinal(floatval($stock_final));

                    // Charger la photo de l'équipement
                   /* $photo = $request->files->get('photo');*/
                    if ($photo) {
                        $filename = $equipement->getId().$photo->guessExtension();
                        $photo->move($this->getParameter('photo_equipement'), $filename);
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

    #[Route('api/editEquipement', name: 'app_api_edit_equipement')]
    public function app_api_edit_equipement(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_PDR")) {
            try {
                $id_equipement = $request->request->get('id_equipement');
                $CodeEquipement = $request->request->get('code_equipement');
                $nomEquipement = $request->request->get('nom');
                $marque= $request->request->get('marque');
                $modele = $request->request->get('modele');
                $categorie = $request->request->get('categorie');
                $criticite_id = $request->request->get('criticite');
                $type_eq = $request->request->get('type_eq');
                $photo = $request->files->get('photo');
                $seuil = $request->request->get('seuil');

                $criticite = $this->em->getRepository(Criticite::class)->find($criticite_id);
                $mrq = $this->em->getRepository(Marque::class)->find($marque);
                $typeEquipement = $this->em->getRepository(TypeEquipement::class)->find($categorie);
                $typeEq = $this->em->getRepository(TypeEq::class)->find($type_eq);

                if (!$nomEquipement || !$CodeEquipement) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {

                    $uuid = Uuid::fromString($id_equipement);
                    $equipement = $this->em->getRepository(Equipement::class)->find($uuid);

                    if (!$equipement) {
                        $reponse = ['code' => 'warning', 'msg' => 'Merci de sélectionner un article !'];
                    } else {
                        $equipement->setCode(strtoupper($CodeEquipement));
                        $equipement->setNom(strtoupper($nomEquipement));

                        if ($criticite) { $equipement->setCriticite($criticite);}
                        if ($mrq) { $equipement->setMarque($mrq);;}
                        if ($typeEquipement) { $equipement->setTypeUnicite($typeEquipement);;}
                        if ($typeEq) {
                            if ($equipement->getCodeEquipementType()) {
                                $type_equip = $equipement->getCodeEquipementType();
                                $type_equip->setTypeEq($typeEq) ;
                            }
                        }
                        $equipement->setModele(strtoupper($modele));

                        if ($equipement->getCodeEquipementType()) {
                            $type_equip = $equipement->getCodeEquipementType();
                            $type_equip->setSeuil((int) $seuil);
                        }


                        if ($photo) {
                            $filename = uniqid().'.'.$photo->guessExtension();
                            $photo->move($this->getParameter('photo_equipement'), $filename);
                            if ($equipement->getCodeEquipementType()) {
                                $type_equip = $equipement->getCodeEquipementType();
                                $type_equip->setPhoto($filename);
                            }

                        }

                        $this->em->persist($equipement);
                        if ($equipement->getCodeEquipementType()) {
                            $type_equip = $equipement->getCodeEquipementType();
                            $this->em->persist($type_equip);
                        }

                        $this->em->flush();
                        $reponse = [
                            'code' => "success", 'msg' =>  'Equipement créé avec succès !'
                        ];
                    }
                }
            } catch (\Throwable $throwable) {
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }




    #[Route('api/saveEquipementWithReference', name: 'saveEquipementWithReference')]
    public function saveEquipementWithReference(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_PDR")) {
            try {
                $type_ajout= $request->request->get('type_ajout');
                $id_equipement = $request->request->get('selectEq');
                $CodeEquipement = $request->request->get('code');
                $nomEquipement = $request->request->get('nom');
                $fournisseur  = $request->request->get('fournisseur');
                $marque= $request->request->get('marque');
                $modele = $request->request->get('modele');
                $date_entree = $request->request->get('date_entree');
                $type_eq = $request->request->get('type_eq');
                $categorie = $request->request->get('categorie');
                $criticite = $request->request->get('criticite');
                $seuil = $request->request->get('seuil');
                $photo = $request->files->get('photo');


                if ($type_ajout === "0"){
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de cliquer sur Nouvel article ou Sélectionnez un article !'];
                } else{
                    if ($type_ajout === "1"){ // Nouvel article
                        if (!$CodeEquipement || !$nomEquipement || ! $categorie || ! $type_eq ){
                            $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs !'];
                        } else{
                            // Vérifie si une occurrence a été apportée
                            if (!$modele){
                                $reponse = ['code' => 'warning', 'msg' => 'Inscrivez au moins la référence !'];
                            } else {

                                $valeur = $this->em->getRepository(EquipementType::class)->findOneBy(['nom_equipement'=>trim(strtoupper($nomEquipement))]);
                                if ($valeur){
                                    $reponse = ['code' => "error", 'msg' => 'ce nom existe déjà'];
                                } else {
                                    $nouvelEq = new  EquipementType();
                                    $nouvelEq->setCode($CodeEquipement);
                                    $nouvelEq->setSeuil($seuil);
                                    $nouvelEq->setNomEquipement(trim(strtoupper($nomEquipement)));
                                    $nouvelEq->setQte(0);

                                    if ($categorie){
                                        $Categorie = $this->em->getRepository(TypeEquipement::class)->find(Uuid::fromString($categorie));
                                        if ($Categorie){ $nouvelEq->setCategorie($Categorie);}
                                    }

                                    if ($criticite){
                                        $Criticite = $this->em->getRepository(Criticite::class)->find(Uuid::fromString($criticite));
                                        if ($Criticite){ $nouvelEq->setCriticite($Criticite);}
                                    }

                                    if ($type_eq){
                                        $TypeEq = $this->em->getRepository(TypeEq::class)->find(Uuid::fromString($type_eq));
                                        if ($TypeEq){ $nouvelEq->setTypeEq($TypeEq);}
                                    }
                                    // Charger la photo de l'équipement
                                    if ($photo) {
                                        $filename = uniqid().'.'.$photo->guessExtension();
                                        $photo->move($this->getParameter('photo_equipement'), $filename);
                                        $nouvelEq->setPhoto($filename);
                                    }

                                    $this->em->persist($nouvelEq);


                                    // Enregistrement de l'occurrence

                                    $nouvelleOccurrence = new Equipement();
                                    $nouvelleOccurrence->setCodeEquipementType($nouvelEq);
                                    $nouvelleOccurrence->setModele($modele);
                                    $nouvelleOccurrence->setDateEntree(new \DateTime());
                                    $nouvelleOccurrence->setCode($CodeEquipement);
                                    $nouvelleOccurrence->setNom($nouvelEq->getNomEquipement());
                                    if ($nouvelEq->getCategorie()){$nouvelleOccurrence->setTypeUnicite($nouvelEq->getCategorie());}
                                    if ($fournisseur){
                                        $Fournisseur = $this->em->getRepository(Fournisseur::class)->find(Uuid::fromString($fournisseur));
                                        if ($Fournisseur){ $nouvelleOccurrence->setCodeFournisseur($Fournisseur);}
                                    }

                                    if ($marque){
                                        $Marque = $this->em->getRepository(Fournisseur::class)->find(Uuid::fromString($marque));
                                        if ($Marque){ $nouvelleOccurrence->setMarque($Marque);}
                                    }

                                    $this->em->persist($nouvelleOccurrence);

                                    $this->em->flush();
                                    $reponse = ['code' => 'success', 'msg' => 'Article Ajouté avec succès !'];

                                }
                                }
                        }

                    } else{ // Mise à jour
                        // Recherche de l'équipement
                        $EquipementType = $this->em->getRepository(EquipementType::class)->find(Uuid::fromString($id_equipement));

                        if ($EquipementType){
                            // Enregistrement de l'occurrence

                            $nouvelleOccurrence = new Equipement();
                            $nouvelleOccurrence->setCodeEquipementType($EquipementType);
                            if ($EquipementType->getCode()){ $nouvelleOccurrence->setCode($EquipementType->getCode());}

                            $nouvelleOccurrence->setModele($modele);
                            $nouvelleOccurrence->setDateEntree(new \DateTime());

                            $nouvelleOccurrence->setNom($EquipementType->getNomEquipement());
                            if ($fournisseur){
                                $Fournisseur = $this->em->getRepository(Fournisseur::class)->find(Uuid::fromString($fournisseur));
                                if ($Fournisseur){ $nouvelleOccurrence->setCodeFournisseur($Fournisseur);}
                            }

                            if ($marque){
                                $Marque = $this->em->getRepository(Fournisseur::class)->find(Uuid::fromString($marque));
                                if ($Marque){ $nouvelleOccurrence->setMarque($Marque);}
                            }

                            $this->em->persist($nouvelleOccurrence);
                            $this->em->flush();
                            $reponse = ['code' => 'success', 'msg' => 'Article mis à jour avec succès !'];
                        } else {
                            $reponse = ['code' => 'error', 'msg' => 'Erreur constatée lors de l\'enregistrement de l\'article !'];
                        }
                    }
                }

            } catch (\Throwable $throwable) {
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }

    #[Route('api/deleteEquipement/{$id_equipement}', name: 'app_api_delete_equipement')]
    public function app_api_delete_equipement(Request $request, int $id_equipement): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $equipement = $this->em->getRepository(Equipement::class)->find($id_equipement);

                if ($equipement){
                    $this->em->remove($equipement);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Nature Equipement supprimée avec succès !'
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
