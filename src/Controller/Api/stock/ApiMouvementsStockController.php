<?php

namespace App\Controller\Api\stock;

use App\Entity\BonSortie;
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
use App\Services\pdfService;
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
                                'code'=>$occurence->getCode(),
                                'reference'=>strtoupper($occurence->getModele()),
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
        EquipementRepository $equipementRepository,
        pdfService $servicePdf
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

        // L'utilisateur peut cocher « Générer un bon de sortie CITRAC ».
        // Si la case est décochée, aucun bon n'est généré ni enregistré.
        $genererBon = $request->request->get('generer_bon') === '1';

        // Référence unique du bon de sortie (un bon par opération de sortie)
        $referenceBon = null;
        $tokenBon     = null;
        if ($typeOperation === '2' && $genererBon) {
            $referenceBon = 'BS-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
            // Jeton aléatoire encodé dans le QR code (validation mobile par le vigil)
            $tokenBon = bin2hex(random_bytes(16));
        }
        $bonLignes = [];
        $bonInfos  = [];
        $bonService = null;

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

                    // Rattacher le bon de sortie et collecter les données pour le PDF
                    $mouvement->setNumeroBon($referenceBon);

                    $bonLignes[] = [
                        'code'        => $equipement->getCode(),
                        'designation' => strtoupper((string) $equipement->getNom()),
                        'qte'         => rtrim(rtrim(number_format($quantite, 2, '.', ''), '0'), '.'),
                        'stock_avant' => rtrim(rtrim(number_format($stockAvant, 2, '.', ''), '0'), '.'),
                        'stock_apres' => rtrim(rtrim(number_format($stockApres, 2, '.', ''), '0'), '.'),
                        'mention'     => $item['mention'] ?? '',
                    ];

                    if (empty($bonInfos)) {
                        $etabliPar = '';
                        $u = $this->getUser();
                        if ($u && method_exists($u, 'getUserIdentifier')) { $etabliPar = $u->getUserIdentifier(); }
                        $bonService = $Service;
                        $bonInfos = [
                            'reference'  => $referenceBon,
                            'date'       => (new \DateTime())->format('d/m/Y à H:i'),
                            'service'    => $Service ? $Service->getLibelle() : '',
                            'demandeur'  => strtoupper((string) $demandeur),
                            'etabli_par' => $etabliPar,
                        ];
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

            // Persistance du bon de sortie (statut EN_ATTENTE par défaut :
            // la validation se fera plus tard à la guérite via le scan QR).
            $bonSortie = null;
            if ($typeOperation === '2' && $genererBon && !empty($bonLignes)) {
                $bonSortie = new BonSortie();
                $bonSortie->setReference($referenceBon)
                    ->setToken($tokenBon)
                    ->setStatut(BonSortie::STATUT_EN_ATTENTE)
                    ->setService($bonInfos['service'] ?? '')
                    ->setDemandeur($bonInfos['demandeur'] ?? '')
                    ->setEtabliPar($bonInfos['etabli_par'] ?? '')
                    ->setLignes($bonLignes)
                    ->setCreatedAt(new \DateTimeImmutable());
                if ($bonService instanceof Services) {
                    $bonSortie->setCodeService($bonService);
                }
                $u = $this->getUser();
                if ($u && method_exists($u, 'getUserIdentifier')) {
                    $bonSortie->setCreatedBy($u->getUserIdentifier());
                }
                $em->persist($bonSortie);
            }

            $em->flush();

            $connection->commit();

            // Génération à la demande du bon de sortie (PDF) pour une opération de sortie
            $bonPdfBase64 = null;
            if ($typeOperation === '2' && $genererBon && !empty($bonLignes)) {
                try {
                    // Le QR encode un jeton : le vigil le scanne pour valider le bon.
                    $qrData = $this->construireQrData($referenceBon, $tokenBon);
                    $bonInfos['statut'] = BonSortie::STATUT_EN_ATTENTE;
                    $pdfContenu = $servicePdf->genererBonSortie($bonInfos, $bonLignes, $qrData);
                    $bonPdfBase64 = base64_encode($pdfContenu);
                } catch (\Throwable $e) {
                    // Ne pas bloquer l'enregistrement si la génération du PDF échoue
                    $bonPdfBase64 = null;
                }
            }

            return $this->json([
                'code' => 'success',
                'msg'  => 'Mouvement enregistré avec succès.',
                'bon_reference' => $referenceBon,
                'bon_statut'    => ($typeOperation === '2' && $genererBon) ? BonSortie::STATUT_EN_ATTENTE : null,
                'bon_pdf'       => $bonPdfBase64
            ]);

        } catch (\Exception $e) {

            $connection->rollBack();

            return $this->json([
                'code' => 'error',
                'msg'  => $e->getMessage()
            ], 500);
        }
    }

    #[Route('api/bonSortie/{reference}', name: 'app_bon_sortie_pdf', methods: ['GET'])]
    public function bonSortiePdf(string $reference, pdfService $servicePdf): Response
    {
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if (!($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_PDR"))) {
            return new Response('Vous n\'êtes pas autorisé à accéder à cette ressource', 403);
        }
        try {
            $bon = $this->em->getRepository(BonSortie::class)->findOneBy(['reference' => $reference]);

            $bonLignes = [];
            $bonInfos  = [];

            if ($bon) {
                // Source de vérité : le bon persisté (snapshot des lignes + statut).
                $bonLignes = $bon->getLignes() ?: [];
                $bonInfos = [
                    'reference'   => $bon->getReference(),
                    'date'        => $bon->getCreatedAt() ? $bon->getCreatedAt()->format('d/m/Y à H:i') : '',
                    'service'     => (string) $bon->getService(),
                    'demandeur'   => (string) $bon->getDemandeur(),
                    'etabli_par'  => (string) $bon->getEtabliPar(),
                    'statut'      => $bon->getStatut(),
                    // Signature du Directeur Général (étape 2)
                    'signe_par'   => (string) $bon->getSignePar(),
                    'signe_at'    => $bon->getSigneAt() ? $bon->getSigneAt()->format('d/m/Y à H:i') : '',
                    // Clôture par le vigil à la guérite (étape 3)
                    'cloture_par' => (string) $bon->getCloturePar(),
                    'cloture_at'  => $bon->getClotureAt() ? $bon->getClotureAt()->format('d/m/Y à H:i') : '',
                    // Colonnes historiques conservées pour compatibilité
                    'valide_par'  => (string) $bon->getValidePar(),
                    'valide_at'   => $bon->getValideAt() ? $bon->getValideAt()->format('d/m/Y à H:i') : '',
                ];
                $qrData = $this->construireQrData($bon->getReference(), (string) $bon->getToken());
            } else {
                // Repli pour les anciens bons créés avant la table bon_sortie.
                $Mvts = $this->em->getRepository(MouvementEquipement::class)->findBy(
                    ['numero_bon' => $reference, 'type_mvt' => '2'],
                    ['created_at' => 'ASC']
                );
                if (!$Mvts) {
                    return new Response('Bon de sortie introuvable.', 404);
                }
                foreach ($Mvts as $m) {
                    $eq = $m->getCodeEquipement();
                    $bonLignes[] = [
                        'code'        => $eq ? $eq->getCode() : '',
                        'designation' => $eq ? strtoupper((string) $eq->getNom()) : '',
                        'qte'         => $m->getQte() !== null ? rtrim(rtrim(number_format($m->getQte(), 2, '.', ''), '0'), '.') : '',
                        'stock_avant' => $m->getStockAvant() !== null ? rtrim(rtrim(number_format($m->getStockAvant(), 2, '.', ''), '0'), '.') : '',
                        'stock_apres' => $m->getStockApres() !== null ? rtrim(rtrim(number_format($m->getStockApres(), 2, '.', ''), '0'), '.') : '',
                        'mention'     => $m->getObservation() ?? '',
                    ];
                    if (empty($bonInfos)) {
                        $bonInfos = [
                            'reference'  => $reference,
                            'date'       => $m->getCreatedAt() ? $m->getCreatedAt()->format('d/m/Y à H:i') : '',
                            'service'    => $m->getCodeService() ? $m->getCodeService()->getLibelle() : '',
                            'demandeur'  => (string) $m->getDemandeur(),
                            'etabli_par' => (string) $m->getCreatedBy(),
                        ];
                    }
                }
                $qrData = $this->construireQrData($reference, '');
            }

            $contenu = $servicePdf->genererBonSortie($bonInfos, $bonLignes, $qrData);

            return new Response($contenu, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="bon-sortie-' . $reference . '.pdf"',
            ]);
        } catch (\Throwable $throwable) {
            return new Response("Une erreur s'est produite ! => " . $throwable->getMessage(), 500);
        }
    }

    /**
     * Construit le contenu encodé dans le QR code du bon de sortie.
     * L'application mobile du vigil lit ce JSON, en extrait le jeton,
     * puis appelle api/bonSortie/valider/{token} pour valider la fiche.
     */
    private function construireQrData(string $reference, string $token): string
    {
        return json_encode([
            'app' => 'CITRAC',
            'typ' => 'BON_SORTIE',
            'ref' => $reference,
            'tok' => $token,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * MOBILE (guérite) : après le scan du QR, récupère les détails du bon
     * et son statut, pour que le vigil voie ce qu'il s'apprête à valider.
     */
    #[Route('api/bonSortie/scan/{token}', name: 'app_bon_sortie_scan', methods: ['GET'])]
    public function bonSortieScan(string $token): JsonResponse
    {
        if (!$this->getUser()){
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifié.'], 401);
        }
        if (!($this->isGranted("ROLE_VIGIL") or $this->isGranted("ROLE_ADMIN"))) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Accès refusé.'], 403);
        }

        $bon = $this->em->getRepository(BonSortie::class)->findOneBy(['token' => $token]);
        if (!$bon) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Bon de sortie introuvable.'], 404);
        }

        return new JsonResponse([
            'code' => 'success',
            'msg'  => 'Bon trouvé.',
            'data' => $this->serialiserBon($bon),
        ]);
    }

    /**
     * DIRECTEUR GÉNÉRAL : signe le bon de sortie (étape 2).
     * Fait passer le statut EN_ATTENTE -> VALIDE.
     * Accessible depuis l'interface web (ROLE_DG) ou un ROLE_ADMIN.
     */
    #[Route('api/bonSortie/signer/{token}', name: 'app_bon_sortie_signer', methods: ['POST'])]
    public function bonSortieSigner(string $token): JsonResponse
    {
        if (!$this->getUser()){
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifié.'], 401);
        }
        if (!($this->isGranted("ROLE_DG") or $this->isGranted("ROLE_ADMIN"))) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Accès refusé : seul le Directeur Général peut signer.'], 403);
        }

        $bon = $this->em->getRepository(BonSortie::class)->findOneBy(['token' => $token]);
        if (!$bon) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Bon de sortie introuvable.'], 404);
        }

        if ($bon->getStatut() === BonSortie::STATUT_ANNULE) {
            return new JsonResponse([
                'code' => 'error',
                'msg'  => 'Ce bon a été annulé, signature impossible.',
                'data' => $this->serialiserBon($bon),
            ], 409);
        }

        // Déjà signé (ou clôturé) -> idempotent.
        if ($bon->isValide() || $bon->isCloture()) {
            return new JsonResponse([
                'code' => 'info',
                'msg'  => 'Ce bon a déjà été signé.',
                'data' => $this->serialiserBon($bon),
            ], 200);
        }

        $signePar = '';
        $u = $this->getUser();
        if ($u && method_exists($u, 'getUserIdentifier')) { $signePar = $u->getUserIdentifier(); }

        $bon->setStatut(BonSortie::STATUT_VALIDE)
            ->setSigneAt(new \DateTimeImmutable())
            ->setSignePar($signePar);

        $this->em->flush();

        return new JsonResponse([
            'code' => 'success',
            'msg'  => 'Bon de sortie signé avec succès. Il peut maintenant être clôturé à la guérite.',
            'data' => $this->serialiserBon($bon),
        ]);
    }

    /**
     * MOBILE (guérite) : le vigil CLÔTURE le bon après avoir scanné le QR
     * (étape 3). Un bon ne peut être clôturé QUE s'il a d'abord été signé
     * par le Directeur Général (statut VALIDE). Opération idempotente.
     *
     * NB : la route historique .../valider/{token} est conservée pour
     * compatibilité avec l'app mobile ; .../cloturer/{token} est un alias.
     */
    #[Route('api/bonSortie/valider/{token}', name: 'app_bon_sortie_valider', methods: ['POST'])]
    #[Route('api/bonSortie/cloturer/{token}', name: 'app_bon_sortie_cloturer', methods: ['POST'])]
    public function bonSortieCloturer(string $token, Request $request): JsonResponse
    {
        if (!$this->getUser()){
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifié.'], 401);
        }
        if (!($this->isGranted("ROLE_VIGIL") or $this->isGranted("ROLE_ADMIN"))) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Accès refusé.'], 403);
        }

        $bon = $this->em->getRepository(BonSortie::class)->findOneBy(['token' => $token]);
        if (!$bon) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Bon de sortie introuvable.'], 404);
        }

        if ($bon->getStatut() === BonSortie::STATUT_ANNULE) {
            return new JsonResponse([
                'code' => 'error',
                'msg'  => 'Ce bon a été annulé, clôture impossible.',
                'data' => $this->serialiserBon($bon),
            ], 409);
        }

        // Pas encore signé par le DG -> clôture interdite.
        if ($bon->isEnAttente()) {
            return new JsonResponse([
                'code' => 'warning',
                'msg'  => 'Ce bon est encore en cours de validation : il doit d\'abord être signé par le Directeur Général avant de pouvoir être clôturé.',
                'data' => $this->serialiserBon($bon),
            ], 409);
        }

        // Déjà clôturé -> idempotent.
        if ($bon->isCloture()) {
            return new JsonResponse([
                'code' => 'info',
                'msg'  => 'Ce bon a déjà été clôturé le ' . ($bon->getClotureAt() ? $bon->getClotureAt()->format('d/m/Y à H:i') : '') . '.',
                'data' => $this->serialiserBon($bon),
            ], 200);
        }

        $cloturePar = '';
        $u = $this->getUser();
        if ($u && method_exists($u, 'getUserIdentifier')) { $cloturePar = $u->getUserIdentifier(); }

        $poste = $request->request->get('poste_controle');
        if ($poste === null) {
            // Le client mobile peut aussi envoyer un corps JSON.
            $payload = json_decode($request->getContent() ?: '[]', true);
            if (is_array($payload) && isset($payload['poste_controle'])) {
                $poste = (string) $payload['poste_controle'];
            }
        }

        $bon->setStatut(BonSortie::STATUT_CLOTURE)
            ->setClotureAt(new \DateTimeImmutable())
            ->setCloturePar($cloturePar)
            ->setPosteControle($poste !== null ? (string) $poste : 'Guérite');

        $this->em->flush();

        return new JsonResponse([
            'code' => 'success',
            'msg'  => 'Bon de sortie clôturé avec succès. La sortie est autorisée.',
            'data' => $this->serialiserBon($bon),
        ]);
    }

    /**
     * Représentation JSON d'un bon de sortie pour l'application mobile.
     */
    private function serialiserBon(BonSortie $bon): array
    {
        return [
            'reference'      => $bon->getReference(),
            'statut'         => $bon->getStatut(),
            'statut_label'   => $bon->getStatutLabel(),
            'service'        => $bon->getService(),
            'demandeur'      => $bon->getDemandeur(),
            'etabli_par'     => $bon->getEtabliPar(),
            'lignes'         => $bon->getLignes() ?: [],
            'date'           => $bon->getCreatedAt() ? $bon->getCreatedAt()->format('d/m/Y à H:i') : '',
            // Signature du Directeur Général
            'signe_par'      => $bon->getSignePar(),
            'signe_at'       => $bon->getSigneAt() ? $bon->getSigneAt()->format('d/m/Y à H:i') : '',
            // Clôture par le vigil
            'cloture_par'    => $bon->getCloturePar(),
            'cloture_at'     => $bon->getClotureAt() ? $bon->getClotureAt()->format('d/m/Y à H:i') : '',
            'poste_controle' => $bon->getPosteControle(),
        ];
    }

    #[Route('api/getMouvementsByEquipement/{idEquipement}', name: 'getMouvementsByEquipement', methods: ['GET'])]
    public function getMouvementsByEquipement(string $idEquipement): Response
    {
        $reponse = array();
        $data = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_PDR")) {
            try {
                $Equipement = $this->em->getRepository(Equipement::class)->find(Uuid::fromString($idEquipement));
                if (!$Equipement){
                    return new JsonResponse(['code' => 'warning', 'msg' => 'Equipement introuvable', 'data' => []]);
                }

                $Mvts = $this->em->getRepository(MouvementEquipement::class)->findBy(
                    ['code_equipement' => $Equipement],
                    ['created_at' => 'DESC']
                );

                foreach ($Mvts as $Mvt){
                    $data[] = array(
                        'id'           => $Mvt->getId(),
                        'type_mvt'     => $Mvt->getTypeMvt(),
                        'qte'          => $Mvt->getQte() ? $Mvt->getQte() : 0,
                        'date'         => $Mvt->getDateOperation() ? $Mvt->getDateOperation()->format('d/m/Y') : "",
                        'heure'        => $Mvt->getCreatedAt() ? $Mvt->getCreatedAt()->format('H:i:s') : "",
                        'service'      => $Mvt->getCodeService() ? $Mvt->getCodeService()->getLibelle() : "",
                        'emplacement'  => $Mvt->getEmplacement() ? $Mvt->getEmplacement()->getLibelle() : "",
                        'numero_bon'   => $Mvt->getNumeroBon() ? $Mvt->getNumeroBon() : "",
                        'demandeur'    => $Mvt->getDemandeur() ? $Mvt->getDemandeur() : "",
                        'stock_avant'  => $Mvt->getStockAvant(),
                        'stock_apres'  => $Mvt->getStockApres(),
                        'mention'      => $Mvt->getObservation() ? $Mvt->getObservation() : ""
                    );
                }
                $reponse = ['code' => "success", 'msg' => 'Success', 'data' => $data];
            } catch (\Throwable $throwable){
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
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
                $code_famille = $request->request->get('code_famille_');
                $code_categorie = $request->request->get('code_categorie_');

                $famille = $this->em->getRepository(Famille::class)->find(Uuid::fromString($code_famille));
                $categorie = $this->em->getRepository(TypeEquipement::class)->find(Uuid::fromString($code_categorie));

                $valeur = "";
                if ($famille && $categorie){
                    $valeur = $famille->getInitial() . "-" . $categorie->getInitial();
                    /*recherche les equipements repondant a la famille et a la categorie*/
                    $eqs = $this->em->getRepository(Equipement::class)->findBy(['type_unicite'=>$categorie]);
                    $count_eqs = $this->em->getRepository(Equipement::class)->count(['type_unicite'=>$categorie]);
                    //dd($count_eqs);
                    if (!$eqs){
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

    #[Route('api/RechercheDoublonArticle', name: 'RechercheDoublonArticle')]
    public function RechercheDoublonArticle(Request $request): Response
    {
        $reponse = array();
        $data = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_PDR")) {
            try {
                $nom = trim(strtoupper($request->request->get('nom')));

                $valeur = $this->em->getRepository(EquipementType::class)->findOneBy(['nom_equipement'=>strtoupper($nom)]);
                if ($valeur){
                    $reponse = ['code' => "error", 'msg' => 'ce nom existe déjà'];
                } else {
                    $reponse = ['code' => "success", 'msg' => 'success'];
                }

            }catch (\Throwable  $throwable){
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }
    #[Route('api/RechercheDoublonRef', name: 'RechercheDoublonRef')]
    public function RechercheDoublonRef(Request $request): Response
    {
        $reponse = array();
        $data = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_PDR")) {
            try {
                $modele = $request->request->get('ref');
                $code_equipement_type =$request->request->get('code_equipement_type');

                $EquipementType = $this->em->getRepository(EquipementType::class)->find(Uuid::fromString($code_equipement_type));

                if($EquipementType){
                    $valeur = $this->em->getRepository(Equipement::class)->findOneBy(['code_equipement_type'=>$EquipementType, 'modele'=>strtoupper($modele)]);
                    if ($valeur){
                        $reponse = ['code' => "error", 'msg' => 'cette référence existe déjà'];
                    } else {
                        $reponse = ['code' => "success", 'msg' => 'success'];
                    }
                } else{
                    $reponse = ['code' => "error", 'msg' => 'Merci de sélectionner l\'équipemennt Type'];
                }

            }catch (\Throwable  $throwable){
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }
    #[Route('api/RechercheBonSortie', name: 'RechercheBonSortie')]
    public function RechercheBonSortie(Request $request): Response
    {
        $reponse = array();
        $data = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG")) {
            try {
                $valeur = $this->em->getRepository(BonSortie::class)->count(['signe_par'=>null]);
                    $reponse = ['code' => "error", 'msg' =>$valeur];
            }catch (\Throwable  $throwable){
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }
}
