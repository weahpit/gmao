<?php

namespace App\Controller\Api\stock;

use App\Entity\Emplacement;
use App\Entity\Equipement;
use App\Entity\EquipementType;
use App\Entity\Fournisseur;
use App\Entity\Marque;
use App\Entity\MouvementEquipement;
use App\Entity\NatureEquipement;
use App\Entity\Services;
use App\Services\Functions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiMouvementsStockController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/saveMvt', name: 'app_api_save_mouvement')]
    public function app_api_save_nature_equipement(Request $request, Functions $functions): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_PDR")) {
            try {
                /*================================================
                                                    DECLARATIONS
                ================================================*/

                $id_equipement = $request->request->get('id_equipement');
                $nature = $request->request->get('nature');
                $type_mvt= $request->request->get('type_mvt');
                $nb_eq = $request->request->get('nb_eq');
                $fournisseur = $request->request->get('fournisseur');
                $marque = $request->request->get('marque');
                $modele = $request->request->get('modele');
                $etat = $request->request->get('etat');
                $numero_serie = $request->request->get('numero_serie');
                $date_entree = $request->request->get('date_entree');
                $service = $request->request->get('services');

                $numero_bon = $request->request->get('numero_bon');
                $emplacement = $request->request->get('emplacement');
                $precision_emplacement = $request->request->get('precision_emplacement');
                $observation = $request->request->get('observation');
                $bon_livraison = $request->files->get('bon_livraison');

                $select_equipement = $request->request->get('select_equipement');


               $Nature = $this->em->getRepository(NatureEquipement::class)->findOneBy(['libelle'=>$nature]);
               $aujourdhui = new \DateTime();

               if ($date_entree) { if (new \DateTime($date_entree) >$aujourdhui){ $reponse = ['code' => 'warning', 'msg' => 'Merci de sélectionner une date correcte !']; return new JsonResponse($reponse); }}

                if (!$Nature || $type_mvt == "0" ||  $type_mvt == "null") {
                    $reponse = ['code' => 'warning', 'msg' => 'Aucun équipement ou Type Mouvement n\'a été sélectionné !'];
                    return new JsonResponse($reponse);
                } else {
                    if ($nature == "QUANTITE"){
                        if (!$nb_eq){
                            $reponse = ['code' => 'error', 'msg' => 'Merci de renseigner le nombre de pièces !'];
                            return new JsonResponse($reponse);
                        } else {
                            // Mise à jour de l'équipement Type
                            $eq = $this->em->getRepository(EquipementType::class)->find(Uuid::fromString($id_equipement));
                            if ($eq){
                                if($eq->getQte() && $eq->getQte() > 0) { $nb = $eq->getQte();} else {$nb = 0;}
                                if ($type_mvt == "1"){ // Entrée en Stock
                                    $eq->setQte($nb_eq + $nb);
                                    $this->em->persist($eq);

                                    // Enregistrer le mouvement
                                    $mvt = new MouvementEquipement();
                                    $mvt->setTypeMvt($type_mvt);
                                    $mvt->setCreatedAt(new \DateTimeImmutable());
                                    $mvt->setValue($nb_eq);
                                    $mvt->setCodeEquipementType($eq);

                                    $mvt->setNumeroBon($numero_bon);
                                    if ($date_entree) { $mvt->setDateOperation(new  \DateTime($date_entree));} else {$mvt->setDateOperation(new  \DateTime());}

                                    // Enregistrement du bon
                                    if ($bon_livraison) {
                                        if ($bon_livraison->guessExtension() != "pdf") { $reponse = ['code' => 'warning', 'msg' => 'Le Bon de livraison doit être en format PDF.'];  return new JsonResponse($reponse);}

                                        $filename = uniqid().'.'.$bon_livraison->guessExtension();
                                        $bon_livraison->move($this->getParameter('bon_livraison'), $filename);
                                        $mvt->setBonLivraison($filename);
                                    }

                                    $this->em->persist($mvt);

                                } else { // Sortie
                                    if ($nb == 0) {
                                        $reponse = ['code' => 'error', 'msg' => 'Désolé! Aucun article ne peut sortir vu que ce stock est en rupture...'];
                                        return new JsonResponse($reponse);
                                    }elseif ($nb < $nb_eq) {
                                        $reponse = ['code' => 'error', 'msg' => 'Désolé! Vous ne pouvez pas sortir un nombre d\'articles supérieur au stock existant ...'];
                                        return new JsonResponse($reponse);
                                    } else {
                                        $eq->setQte($nb -$nb_eq  );
                                        $this->em->persist($eq);

                                        // Enregistrer le mouvement
                                        $mvt = new MouvementEquipement();
                                        $mvt->setTypeMvt($type_mvt);
                                        $mvt->setCreatedAt(new \DateTimeImmutable());
                                        $mvt->setValue($nb_eq);
                                        $mvt->setCodeEquipementType($eq);

                                        $mvt->setNumeroBon($numero_bon);
                                        if ($date_entree) { $mvt->setDateOperation(new  \DateTime($date_entree));} else {$mvt->setDateOperation(new  \DateTime());}

                                        // Enregistrement de l'emplacement et du service

                                        if ($service != "0"){
                                            $Serv = $this->em->getRepository(Services::class)->find(Uuid::fromString($service));
                                            if ($Serv) { $mvt->setCodeService($Serv);}
                                        } else {
                                            $reponse = ['code' => 'error', 'msg' => 'Le service est obligatoire pour cette opération ...'];
                                            return new JsonResponse($reponse);
                                        }

                                        if ($emplacement != "0"){
                                            $Empl = $this->em->getRepository(Emplacement::class)->find(Uuid::fromString($emplacement));

                                            if ($Empl) { $mvt->setEmplacement($Empl);}
                                        }
                                        $mvt->setPrecisionEmplacement($precision_emplacement);
                                        $mvt->setObservation($observation);

                                        $this->em->persist($mvt);
                                        $this->em->flush();
                                    }
                                }
                                $this->em->flush();

                            } else {
                                $reponse = ['code' => 'warning', 'msg' => 'Le fichier STOCK de l\'équipement n\'a pas été mis à jour ! !'];
                                return new JsonResponse($reponse);
                            }
                            $reponse = [
                                'code' => 'success',
                                'msg' => 'Mouvement stock effectué avec succès !'
                            ];
                        }
                    } else {  // Appareil unique avec Numéro de Série
                        if (!$numero_serie){
                            $reponse = ['code' => 'error', 'msg' => 'Merci de renseigner le N° de série !'];
                            return new JsonResponse($reponse);
                        } else {

                            // Mise à jour de l'équipement Type
                            $eq = $this->em->getRepository(EquipementType::class)->find(Uuid::fromString($id_equipement));
                            if ($eq){
                                if($eq->getQte() && $eq->getQte() > 0) { $nb = $eq->getQte();} else {$nb = 0;}
                                if ($type_mvt == "1"){ // Entrée en Stock
                                    $eq->setQte($nb_eq + 1);

                                    // Ajout du détail
                                    $new_eq = new Equipement();
                                    $new_eq->setNom($eq->getNomEquipement());
                                    $new_eq->setNumeroSerie(strtoupper($numero_serie));

                                    if ($fournisseur){
                                        $Frn = $this->em->getRepository(Fournisseur::class)->find(Uuid::fromString($fournisseur));
                                        $new_eq->setCodeFournisseur($Frn);
                                    }

                                    if ($marque){
                                        $Mrq = $this->em->getRepository(Marque::class)->find(Uuid::fromString($marque));
                                        $new_eq->setMarque($Mrq);
                                    }

                                    if ($etat){
                                        $Etat = $this->em->getRepository(Fournisseur::class)->find(Uuid::fromString($etat));
                                        $new_eq->setEtat($Etat);
                                    }

                                    $new_eq->setCode(strtoupper($functions->generateRandomCode()));
                                    $new_eq->setModele(strtoupper($modele));
                                    $new_eq->setCriticite($eq->getCriticite());
                                    $new_eq->setTypeUnicite($eq->getCategorie());
                                    $new_eq->setDateEntree(new \DateTime($date_entree));
                                    $this->em->persist($new_eq);


                                    // Enregistrer le mouvement
                                    $mvt = new MouvementEquipement();
                                    $mvt->setTypeMvt($type_mvt);
                                    $mvt->setCreatedAt(new \DateTimeImmutable());
                                    $mvt->setValue(1);
                                    $mvt->setCodeEquipementType($eq);

                                    $mvt->setNumeroBon($numero_bon);
                                    if ($date_entree) { $mvt->setDateOperation(new  \DateTime($date_entree));} else {$mvt->setDateOperation(new  \DateTime());}

                                    // Enregistrement du bon
                                    if ($bon_livraison) {
                                        if ($bon_livraison->guessExtension() != "pdf") { $reponse = ['code' => 'warning', 'msg' => 'Le Bon de livraison doit être en format PDF.'];  return new JsonResponse($reponse);}

                                        $filename = uniqid().'.'.$bon_livraison->guessExtension();
                                        $bon_livraison->move($this->getParameter('bon_livraison'), $filename);
                                        $mvt->setBonLivraison($filename);
                                    }

                                    $this->em->persist($mvt);
                                    $this->em->flush();

                                } else { // Sortie
                                    if ($nb == 0) {
                                        $reponse = ['code' => 'error', 'msg' => 'Désolé! Aucun article ne peut sortir vu que ce stock est en rupture...'];
                                        return new JsonResponse($reponse);
                                    } elseif ($nb < $nb_eq) {
                                        $reponse = ['code' => 'error', 'msg' => 'Désolé! Vous ne pouvez pas sortir un nombre d\'articles supérieur au stock existant ...'];
                                        return new JsonResponse($reponse);
                                    } else {
                                        $eq->setQte($nb - 1);
                                        $this->em->persist($eq);

                                        // Enregistrer le mouvement
                                        $mvt = new MouvementEquipement();
                                        $mvt->setTypeMvt($type_mvt);
                                        $mvt->setCreatedAt(new \DateTimeImmutable());
                                        $mvt->setValue(1);
                                        $mvt->setCodeEquipementType($eq);$mvt->setNumeroBon($numero_bon);
                                        $mvt->setNumeroBon($numero_bon);
                                        if ($date_entree) { $mvt->setDateOperation(new  \DateTime($date_entree));} else {$mvt->setDateOperation(new  \DateTime());}

                                        if ($select_equipement){
                                            $SelectEq = $this->em->getRepository(Equipement::class)->find(Uuid::fromString($select_equipement));
                                            $mvt->setCodeEquipement($SelectEq);
                                        } else {
                                            $reponse = ['code' => 'error', 'msg' => 'Merci de sélectionner SVP un équipement ...'];
                                            return new JsonResponse($reponse);
                                        }

                                        // Enregistrement de l'emplacement et du service
                                        if ($service != "0"){
                                            $Serv = $this->em->getRepository(Services::class)->find(Uuid::fromString($service));
                                            if ($Serv) { $mvt->setCodeService($Serv);}
                                        } else {
                                            $reponse = ['code' => 'error', 'msg' => 'Le service est obligatoire pour cette opération ...'];
                                            return new JsonResponse($reponse);
                                        }

                                        if ($emplacement != "0"){
                                            $Empl = $this->em->getRepository(Emplacement::class)->find(Uuid::fromString($emplacement));
                                            if ($Empl) { $mvt->setEmplacement($Empl);}
                                        }
                                        $mvt->setPrecisionEmplacement($precision_emplacement);
                                        $mvt->setObservation($observation);

                                        $this->em->persist($mvt);
                                        $this->em->flush();
                                    }
                                }

                            } else {
                                $reponse = ['code' => 'warning', 'msg' => 'Le fichier STOCK de l\'équipement n\'a pas été mis à jour ! !'];
                                return new JsonResponse($reponse);
                            }
                            $reponse = [
                                'code' => 'success',
                                'msg' => 'Mouvement stock effectué avec succès !'
                            ];
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
}
