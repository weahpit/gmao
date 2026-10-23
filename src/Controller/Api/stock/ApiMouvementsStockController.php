<?php

namespace App\Controller\Api\stock;

use App\Entity\Emplacement;
use App\Entity\Equipement;
use App\Entity\EquipementType;
use App\Entity\Famille;
use App\Entity\Fournisseur;
use App\Entity\Marque;
use App\Entity\MouvementEquipement;
use App\Entity\NatureEquipement;
use App\Entity\Services;
use App\Entity\TypeEquipement;
use App\Repository\EquipementRepository;
use App\Repository\EquipementTypeRepository;
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

    #[Route('api/getArticlesFromStock', name: 'app_get_articles_from_stock')]
    public function app_get_articles_from_stock(): Response
    {
        $reponse = array();
        $data = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_PDR")) {
            try {
                $equipementsType = $this->em->getRepository(EquipementType::class)->findAll();
                foreach ($equipementsType as$equipement){
                    $occurences = $equipement->getEquipements();
                    if ($occurences){
                        foreach ($occurences as $occurence){
                            $data[] = array(
                                'id'=>$occurence->getId(),
                                'nom'=>$occurence->getNom(),
                                'reference'=>$occurence->getModele(),
                                'qte'=>$occurence->getQte() ? $occurence->getQte() : 0,
                                'categorie'=>$equipement->getCategorie() ? $equipement->getCategorie()->getLibelle() : ""
                            );
                        }

                    }
                }
                $reponse = ['code' => "success", 'msg' => 'Success', 'data'=>$data];
            }catch (\Throwable  $throwable){
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }

    #[Route('/api/saveStockOperation', name: 'api_save_stock_operation', methods: ['POST'])]
    public function saveStockOperation(
        Request $request,
        EntityManagerInterface $em,
        EquipementRepository $equipementRepository
    ): JsonResponse {

        $articles = json_decode(
            $request->request->get('articles', '[]'),
            true
        );

        $typeOperation =
            $request->request->get('type_operation');

        if (empty($articles)) {

            return $this->json([
                'code' => 'error',
                'msg'  => 'Aucun article sélectionné.'
            ], 400);
        }

        $connection = $em->getConnection();
        $connection->beginTransaction();

        try {

            foreach ($articles as $item) {

                $equipement =
                    $equipementRepository->find(
                        $item['article_id']
                    );

                if (!$equipement) {

                    throw new \Exception(
                        sprintf(
                            'Equipement introuvable (%s)',
                            $item['article_id']
                        )
                    );
                }

                $quantite = (float)$item['quantite'];

                if ($quantite <= 0) {
                    throw new \Exception(
                        'Quantité invalide.'
                    );
                }

                /*
                 * Stock actuel
                 */
                $stockAvant = (float)$equipement->getQte();

                /*
                 * ENTREE
                 */
                if ($typeOperation === '1') {

                    $stockApres =
                        $stockAvant + $quantite;

                    $typeMvt = '1';
                }
                /*
                 * SORTIE
                 */
                else {

                    if ($quantite > $stockAvant) {

                        throw new \Exception(
                            sprintf(
                                'Stock insuffisant pour %s (Disponible : %.2f)',
                                $equipement->getNom(),
                                $stockAvant
                            )
                        );
                    }

                    $stockApres =
                        $stockAvant - $quantite;

                    $typeMvt = '2';
                }

                /*
                 * Mise à jour stock équipement
                 */
                $equipement->setQte(
                    $stockApres
                );

                /*
                 * Mise à jour stock équipement Type
                 */
                if ($typeOperation === '1') {
                    $equipement->getCodeEquipementType()->setQte($equipement->getCodeEquipementType()->getQte() + $quantite);
                } else {
                    $equipement->getCodeEquipementType()->setQte($equipement->getCodeEquipementType()->getQte() - $quantite);
                }
                /*
                 * Création mouvement
                 */
                $mouvement = new MouvementEquipement();

                $mouvement
                    /*->setCodeEquipement(
                        $equipement
                    )*/
                    ->setCodeEquipement(
                        $equipement
                    )->setCodeEquipementType(
                        $equipement->getCodeEquipementType()
                    )
                    ->setTypeMvt(
                        $typeMvt
                    )
                    ->setQte(
                        $quantite
                    )
                    ->setValue(
                        $quantite
                    )
                    ->setStockAvant(
                        $stockAvant
                    )
                    ->setStockApres(
                        $stockApres
                    )
                    ->setObservation(
                        $item['mention'] ?? null
                    )
                    ->setCreatedAt(
                        new \DateTimeImmutable()
                    )
                    ->setDateOperation(
                        new \DateTime()
                    );


                if ($typeOperation === '2') {
                    $serviceDemandeur = $item['service_demandeur'];
                    $demandeur = $item['demandeur'];
                    if ($serviceDemandeur && $demandeur){
                        $Service = $em->getRepository(Services::class)->find(Uuid::fromString($serviceDemandeur));
                        if ($Service){
                            $mouvement->setCodeService($Service);
                        } else {
                            return $this->json([
                                'code' => 'error',
                                'msg'  => 'Le service demandeur est obligatoire'
                            ], 500);
                        }
                    $mouvement->setDemandeur(strtoupper($demandeur));
                    } else {
                        return $this->json([
                            'code' => 'error',
                            'msg'  => 'Le service et le demandeur sont obligatoires !'
                        ], 500);
                    }
                }
                /*
                 * Service (optionnel)
                 */
                if (
                    isset($item['code_service'])
                    && $item['code_service']
                ) {

                    $service =
                        $em->getReference(
                            Services::class,
                            $item['code_service']
                        );

                    $mouvement
                        ->setCodeService(
                            $service
                        );
                }
                if ($this->getUser()){ $mouvement->setCreatedBy($this->getUser()); } else {
                    return $this->json([
                        'code' => 'error',
                        'msg'  => 'Vous n\'êtes pas connecté au système !'
                    ], 500);
                }
                /*
                 * Emplacement (optionnel)
                 */
                if (
                    isset($item['emplacement'])
                    && $item['emplacement']
                ) {

                    $emplacement =
                        $em->getReference(
                            Emplacement::class,
                            $item['emplacement']
                        );

                    $mouvement
                        ->setEmplacement(
                            $emplacement
                        );
                }

                /*
                 * Numéro de bon
                 */
                if (
                    !empty(
                    $item['numero_bon']
                    )
                ) {

                    $mouvement
                        ->setNumeroBon(
                            $item['numero_bon']
                        );
                }

                /*
                 * Upload document
                 */
                $fileKey =
                    'file_' .
                    $item['article_id'];

                if (
                    $request
                        ->files
                        ->has($fileKey)
                ) {

                    $file =
                        $request
                            ->files
                            ->get($fileKey);

                    $extension =
                        $file->guessExtension();

                    $fileName =
                        uniqid(
                            'mvt_'
                        ) .
                        '.' .$extension;

                    $file->move(
                        $this->getParameter(
                            'bon_livraison'
                        ),
                        $fileName
                    );

                    $mouvement
                        ->setBonLivraison(
                            $fileName
                        );
                }

                /*
                 * Valeur financière
                 * (facultatif)
                 */
                if (
                    method_exists(
                        $equipement,
                        'getPrixUnitaire'
                    )
                ) {

                    $mouvement
                        ->setValue(
                            $quantite *
                            $equipement
                                ->getPrixUnitaire()
                        );
                }

                $em->persist(
                    $mouvement
                );
            }

            $em->flush();

            $connection->commit();

            return $this->json([
                'code' => 'success',
                'msg'  => 'Mouvement enregistré avec succès.'
            ]);

        } catch (\Exception $e) {

            $connection->rollBack();

            return $this->json([
                'code' => 'error',
                'msg'  => $e->getMessage()
            ], 500);
        }
    }

    #[Route('api/getMouvementsByDate', name: 'getMouvementsByDate')]
    public function getMouvementsByDate(Request $request): Response
    {
        $reponse = array();
        $data = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_PDR")) {
            try {
                $date_inventaire = $request->request->get('date_operation');
                $dateMvt = new  \DateTime($date_inventaire);

                $Mvts = $this->em->getRepository(MouvementEquipement::class)->findBy(['date_operation'=>$dateMvt], ['created_at'=>'DESC']);
                foreach ($Mvts as$Mvt){
                    $data[] = array(
                        'id'=>$Mvt->getId(),
                        'type_mvt'=>$Mvt->getTypeMvt(),
                        'qte'=>$Mvt->getQte() ? $Mvt->getQte() : 0,
                        'eqType'=>$Mvt->getCodeEquipementType()?$Mvt->getCodeEquipementType()->getNomEquipement() : "" ,
                        'photo'=>$Mvt->getCodeEquipementType()?$Mvt->getCodeEquipementType()->getPhoto() : "" ,
                        'service'=>$Mvt->getCodeService()?$Mvt->getCodeService()->getLibelle() : "" ,
                        'emplacement'=>$Mvt->getEmplacement() ?$Mvt->getEmplacement()->getLibelle() : "" ,
                        'emplacement2'=>$Mvt->getPrecisionEmplacement() ? $Mvt->getPrecisionEmplacement() : "",
                        'bl'=>$Mvt->getBonLivraison()? $Mvt->getBonLivraison() : "",
                        'numero_bon'=>$Mvt->getNumeroBon() ? $Mvt->getNumeroBon() : "" ,
                        'stock_avant'=>$Mvt->getStockAvant() ? $Mvt->getStockAvant() : "",
                        'stock_apres'=>$Mvt->getStockApres() ? $Mvt->getStockApres() : "",
                        'created_at'=>$Mvt->getCreatedAt()->format('H:i:s'),
                        'mention'=>$Mvt->getObservation()
                    );
                }
                $reponse = ['code' => "success", 'msg' => 'Success', 'data'=>$data];
            }catch (\Throwable  $throwable){
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }

    #[Route('api/getCodeArticle', name: 'getCodeArticle')]
    public function getCodeArticle(Request $request): Response
    {
        $reponse = array();
        $data = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_PDR")) {
            try {
                $code_famille = $request->request->get('code_famille');
                $code_categorie = $request->request->get('code_categorie');

                $famille = $this->em->getRepository(Famille::class)->find(Uuid::fromString($code_famille));
                $categorie = $this->em->getRepository(TypeEquipement::class)->find(Uuid::fromString($code_categorie));

                $valeur = "";
                if ($famille && $categorie){
                    $valeur = $famille->getInitial() . "-" . $categorie->getInitial();
                    /*recherche les equipements repondant a la famille et a la categorie*/
                    $eqs = $this->em->getRepository(EquipementType::class)->findBy(['categorie'=>$categorie]);
                    $count_eqs = $this->em->getRepository(EquipementType::class)->count(['categorie'=>$categorie]);

                    if ($eqs){
                        $valeur = $valeur . "001";
                    } else {
                        if (strlen($count_eqs + 1) == 1){
                            $valeur = $valeur . "00" . $count_eqs + 1;
                        } elseif (strlen($count_eqs + 1) == 2){
                            $valeur = $valeur . "0" . $count_eqs + 1;
                        } else {
                            $valeur = $valeur . $count_eqs + 1;
                        }
                    }
                    $reponse = ['code' => "success", 'msg' => 'Success', 'valeur'=>$valeur];
                } else {
                    $reponse = ['code' => "error", 'msg' => 'Error', 'valeur'=>$valeur];
                }

            }catch (\Throwable  $throwable){
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }

}
