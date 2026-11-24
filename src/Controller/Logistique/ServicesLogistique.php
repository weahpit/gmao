<?php

namespace App\Controller\Logistique;

use App\Entity\Logistique\Approbation;
use App\Entity\Logistique\Client;
use App\Entity\Logistique\Destination;
use App\Entity\Logistique\DetailsModeleDoc;
use App\Entity\Logistique\DocsDossier;
use App\Entity\Logistique\DocType;
use App\Entity\Logistique\Dossier;
use App\Entity\Logistique\Magasin;
use App\Entity\Logistique\ModeleDoc;
use App\Entity\Logistique\Navire;
use App\Entity\Logistique\PhotosDocs;
use App\Entity\Logistique\Proprietaire;
use App\Entity\Production\Lot;
use App\Entity\Production\Produit;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\Logistique\EmbarquementManager;
use Symfony\Component\Routing\Attribute\Route;

final class ServicesLogistique extends  AbstractController
{public function __construct(
    private ManagerRegistry $em,
    private EmbarquementManager $embarquements,
    private \App\Service\Documents\OptimiseurDocuments $optimiseurDocuments,
)
{
}

    #[Route('/api/getListOT', name: 'app_api_listOT')]
    public function getListOT(ManagerRegistry $registry): Response
    {
        $reponse = array();
        $listeOT = $registry->getRepository(Dossier::class)->findBy([], ['id'=>'ASC']);
        foreach ($listeOT as $ot){
            // Recherche de docs
            $mes_docs = $ot->getDocsDossiers();
            $i = 0;
            $j=0;
            foreach ($mes_docs as $doc){
                $i++;
                if ($doc->getFilename()) { $j++;}
            }
            $reponse[] = array(
                'id_dossier'=>$ot->getId(),
                'ot'=>$ot->getNumeroOt(),
                'destination'=>$ot->getDestination() ? $ot->getDestination()->getDenomination() : "-",
                'tonnage'=>$ot->getTonnage(),
                'nb_doc'=>$i,
                'nb_charges'=>$j,
                'suivi'=>round($j/$i, 2) * 100,
            );
        }
        rsort($reponse);
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/api/getListDocsByOT/{id_dossier}', name: 'app_api_listDocsByOT')]
    public function getListDocsByOT(string $id_dossier, ManagerRegistry $registry): Response
    {
        $data = array();
        $reponse = array();
        $i = 0;
        $j=0;
        $ot = $registry->getRepository(Dossier::class)->find($id_dossier);
        if ($ot){
            $listeDocs = $registry->getRepository(DocsDossier::class)->findBy(['code_dossier'=>$ot]);

            foreach ($listeDocs as $doc){
                $i++;
                if ($doc->getFilename()){
                    $j++;
                }
                $data[] = array(
                    'doc'=>$doc->getLibelle(),
                    'id_doc'=>$doc->getId(),
                    'filename'=>$doc->getFilename()? $doc->getFilename() : ""
                );
            }
        }
        sort($data);
        $reponse = array(
            'nb_doc'=>$i,
            'nb_charges'=>$j,
            'suivi'=>round($j/$i, 2) * 100,
            'data'=>$data
        );
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/api/getApprobations', name: 'app_api_approbations')]
    public function getListApprobations(ManagerRegistry $registry): Response
    {
        $reponse = array();
        $listeAp = $registry->getRepository(Approbation::class)->findBy([], ['valeur'=>'ASC']);
        foreach ($listeAp as $ap){
            $reponse[] = array(
                'id_approbation'=>$ap->getId(),
                'valeur'=>$ap->getValeur()
            );
        }
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/api/getProduits', name: 'app_api_get_produits')]
    public function getProduits(ManagerRegistry $registry): Response
    {
        $reponse = array();
        $listeProduits = $registry->getRepository(Produit::class)->findAll();
        foreach ($listeProduits as $produit){
            $reponse[] = array(
                'id_produit'=>$produit->getId(),
                'produit'=>$produit->getLibelle()
            );
        }
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/api/getProprietaires', name: 'app_api_get_proprietaires')]
    public function getProprietaires(ManagerRegistry $registry): Response
    {
        $reponse = array();
        $listeProprios = $registry->getRepository(Proprietaire::class)->findAll();
        foreach ($listeProprios as $proprio){
            $reponse[] = array(
                'id_proprio'=>$proprio->getId(),
                'proprio'=>$proprio->getDenomination()
            );
        }
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/api/getMagasins', name: 'app_api_get_magasins')]
    public function getMagasins(ManagerRegistry $registry): Response
    {
        $reponse = array();
        $listeMagasins = $registry->getRepository(Magasin::class)->findAll();
        foreach ($listeMagasins as $magasin){
            $reponse[] = array(
                'id_magasin'=>$magasin->getId(),
                'magasin'=>$magasin->getLibelle(),
                'nb'=>$magasin->getLots()? $magasin->getLots()->count() : 0,
            );
        }
        sort($reponse);
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/api/getDocs/NotInModeleDoc/{code_modele}', name: 'app_api_get_docs_not_in_modele_doc')]
    public function getDocsNotInModel(string $code_modele, ManagerRegistry $registry): Response
    {
        $reponse = array();
        $modele = $registry->getRepository(ModeleDoc::class)->find($code_modele);
        if ($modele){
            $listeDocs = $registry->getRepository(DocType::class)->findAll();
            foreach ($listeDocs as $doc){
                // Recherche le doc dans la liste docs du modele
                $rech_doc = $registry->getRepository(DetailsModeleDoc::class)->findOneBy(['code_doc'=>$doc, 'code_modele'=>$modele]);
                if (!$rech_doc){
                    $reponse[] = array(
                        'doc'=>$doc->getLibelle(),
                        'id_doc'=>$doc->getId()
                    );
                }

            }
        }
        sort($reponse);
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/api/getProduits/getProduitsByProprio/{id_proprio}', name: 'app_api_get_produits_by_proprio')]
    public function getProduitsByProprio(string $id_proprio, ManagerRegistry $registry): Response
    {
        $reponse = array();
        $proprio = $registry->getRepository(Proprietaire::class)->find($id_proprio);
        if ($proprio){
            $listeProduits = $registry->getRepository(Produit::class)->findBy(['code_proprio'=>$proprio], ['libelle'=>'ASC']);
            foreach ($listeProduits as $produit){
                $reponse[] = array(
                    'id_produit'=>$produit->getId(),
                    'produit'=>$produit->getLibelle()
                );
            }
        }
        return new JsonResponse(json_encode($reponse));
    }
    #[Route('/api/getClients', name: 'app_api_get_clients')]
    public function getClients(ManagerRegistry $registry): Response
    {
        $reponse = array();
        $listeClients = $registry->getRepository(Client::class)->findAll();
        foreach ($listeClients as $client){
            $reponse[] = array(
                'client'=>$client->getNomClient(),
                'id_client'=>$client->getId()
            );
        }
        sort($reponse);
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/api/getNavires', name: 'app_api_get_navires')]
    public function getNavires(ManagerRegistry $registry): Response
    {
        $reponse = array();
        $listeNavires= $registry->getRepository(Navire::class)->findAll();
        foreach ($listeNavires as $navire){
            $reponse[] = array(
                'navire'=>$navire->getNom(),
                'id_navire'=>$navire->getId()
            );
        }
        sort($reponse);
        return new JsonResponse(json_encode($reponse));
    }


    #[Route('/api/getLotsProduit/{id_pdt}', name: 'app_api_get_lots_pdt')]
    public function getLotsProduits(ManagerRegistry $registry, string $id_pdt): Response
    {
        $reponse = array();
        $produit= $registry->getRepository(Produit::class)->find($id_pdt);
        if ($produit){
            $listeLots = $registry->getRepository(Lot::class)->findBy(['code_produit'=>$produit]);
            foreach ($listeLots as $lot){
                $reponse[] = array(
                    'numero_lot'=>$lot->getNumeroLot(),
                    'id_lot'=>$lot->getId(),
                    'pn_dispo'=>$lot->getPoidsNetDispo()
                );
            }
        }

        sort($reponse);
        return new JsonResponse(json_encode($reponse));
    }
    #[Route('/api/getInfosLot/{id_lot}', name: 'app_api_get_lots_infos')]
    public function getLotInfos(ManagerRegistry $registry, string $id_lot): Response
    {
        $reponse = array();
        $lot= $registry->getRepository(Lot::class)->find($id_lot);
        if ($lot){
            $reponse[] = array(
                'numero_lot'=>$lot->getNumeroLot(),
                'id_lot'=>$lot->getId(),
                'pn_dispo'=>$lot->getPoidsNetDispo()
            );
        }
        return new JsonResponse(json_encode($reponse));
    }
    #[Route('/api/addLot/{data}', name: 'app_api_add_lot')]
    public function addLot(ManagerRegistry $registry,string $data): Response
    {
        try {
            $reponse = array();
            $arraydata = array();
            $arraydata = json_decode($data);
            if ($arraydata){
                //proprio
                $proprio = $registry->getRepository(Proprietaire::class)->find($arraydata->proprio);
                $pdt = $registry->getRepository(Produit::class)->find($arraydata->pdt);
                $magasin = $registry->getRepository(Magasin::class)->find($arraydata->proprio);
                if ($arraydata->numero_lot &&
                    $arraydata->date_prod &&
                    $arraydata->pn_th &&
                    $proprio &&
                    $pdt &&
                    $magasin
                ){
                    if ($arraydata->id_lot == 0){ // Nouveau lot
                        $lot = new Lot();
                        $lot->setCreatedAt(new \DateTimeImmutable());
                        $lot->setCreatedBy($registry->getRepository(User::class)->find($this->getUser()));
                        $lot->setScission(false);
                        $lot->setQualite("C");
                    } else {
                        $lot = $registry->getRepository(Lot::class)->find($arraydata->id_lot);
                        if (!$lot){
                            $reponse = array('code'=>0, 'msg'=>'Le lot n\'existe pas', 'status'=>'error');
                            return new JsonResponse(json_encode($reponse));
                        }

                    }
                    // Recherche du numero Lot
                    if ($registry->getRepository(Lot::class)->findOneBy(['numero_lot'=>strtoupper($arraydata->numero_lot)])){
                        $reponse = array('code'=>2, 'msg'=>'Le lot existe déjà !', 'status'=>'doublon');
                        return new JsonResponse(json_encode($reponse));
                    }

                    $lot->setNumeroLot(strtoupper($arraydata->numero_lot));
                    /*$lot->setPoidsNet($arraydata->pn_reel ? $arraydata->pn_reel : 0);*/
                    $lot->setPnTh($arraydata->pn_th);
                    $lot->setPoidsNetDispo($arraydata->pn_th);
                    $lot->setCodeMagasin($magasin);
                    $lot->setDateProd(new \DateTime($arraydata->date_prod));
                    $lot->setCodeProduit($pdt);
                    $lot->setCodeProprio($proprio);
                    $lot->setUpdatedAt(new \DateTimeImmutable());
                    $lot->setUpdatedBy($registry->getRepository(User::class)->find($this->getUser()));


                    $registry->getManager()->persist($lot);
                    $registry->getManager()->flush();
                    $reponse = array('code'=>1, 'msg'=>'Le lot N° ' . $lot->getNumeroLot() . ' a été enregistré avec succès', 'status'=>'success');
                } else {
                    $reponse = array('code'=>2, 'msg'=>'Merci de renseigner tous les champs', 'status'=>'error');
                }
            } else {
                $reponse = array('code'=>0, 'msg'=>'Données incorrectes !', 'status'=>'error');
            }
            return new JsonResponse(json_encode($reponse));
        } catch (\Throwable $throwable){
            $reponse = array('code'=>0, 'msg'=>$throwable->getMessage(), 'status'=>'error');
            return new JsonResponse(json_encode($reponse));
        }
    }

    #[Route('/api/getLotInfos/{id_lot}', name: 'getInfosLot')]
    public function getInfosLot(ManagerRegistry $registry, string $id_lot): Response
    {
        $reponse = array();
        $lot = $registry->getRepository(Lot::class)->find($id_lot);
        if($lot){
            $reponse = array(
                'id_lot'=>$lot->getId(),
                'numero_lot'=>$lot->getNumeroLot(),
                'date_prod'=>$lot->getDateProd() ? $lot->getDateProd()->format('Y-m-d') : "-",
                'pn_th'=>$lot->getPnTh(),
                'pn_reel'=>$lot->getPoidsNet()? $lot->getPoidsNet() : 0,
                'etat'=>"-",
                'situation'=>$lot->getCodeMagasin()? $lot->getCodeMagasin()->getId() : 0,
                'ot'=>"-",
                'proprio'=>$lot->getCodeProprio()? $lot->getCodeProprio()->getId() : 0,
                'pdt'=>$lot->getCodeProduit()? $lot->getCodeProduit()->getId() : 0,
                'libelle_pdt'=>$lot->getCodeProduit()? $lot->getCodeProduit()->getLibelle() : "",
                'updated_at'=>$lot->getUpdatedAt() ? $lot->getUpdatedAt()->format('d/m/Y') : "",
                'scission'=>$lot->isScission(),
                'qualite'=>$lot->getQualite()
            );
        }
        return new JsonResponse(json_encode($reponse));
    }
    #[Route('/api/scinderLot/{data}', name: 'getScission')]
    public function getScission(ManagerRegistry $registry, string $data): Response
    {
        return new JsonResponse(['code'=>'warning', 'msg'=>'Utilisez la scission controlee dans Production de lots.'], 409);
    }

    #[Route('/api/getListLots', name: 'app_api_get_lots')]
    public function getListLots(ManagerRegistry $registry): Response
    {
        $reponse = array();
        $listeLots = $registry->getRepository(Lot::class)->findBy([], ['id'=>'ASC']);
        foreach ($listeLots as $lot){
            $reponse[] = array(
                'updated_at'=>$lot->getUpdatedAt() ? $lot->getUpdatedAt()->format('d/m/Y H:i:s') : "-",
                'id_lot'=>$lot->getId(),
                'numero_lot'=>$lot->getNumeroLot(),
                'date_prod'=>$lot->getDateProd() ? $lot->getDateProd()->format('d/m/Y') : "-",
                'pn_th'=>$lot->getPnTh(),
                'pn_reel'=>$lot->getPoidsNet(),
                'etat'=>"-",
                'situation'=>$lot->getCodeMagasin()? $lot->getCodeMagasin()->getLibelle() : "-",
                'ot'=>"-",
                'proprio'=>$lot->getCodeProprio()? $lot->getCodeProprio()->getDenomination() : "-",
                'pdt'=>$lot->getCodeProduit()? $lot->getCodeProduit()->getLibelle() : "-",
                'qualite'=>$lot->getQualite(),
                'scission'=>$lot->isScission()
            );
        }
        rsort($reponse);
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('api/addNavires/{nomNavire}', name: 'app_api_add_navires')]
    public function addNavires(string $nomNavire, ManagerRegistry $registry): Response
    {
        $reponse = array();
        if ($nomNavire){
            // Recherche Nom Client
            $navire= $registry->getRepository(Navire::class)->findOneBy(['nom'=>strtoupper($nomNavire)]);
            if (!$navire){
                $navire = new Navire();
                $navire->setNom(strtoupper($nomNavire));
                $registry->getManager()->persist($navire);
                $registry->getManager()->flush();
                $reponse = array('code'=>'SUCCESS');
            } else {
                $reponse = array('code'=>'EXISTS');
            }
        } else {
            $reponse = array('code'=>'BAD_DATA');
        }
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('api/addClients/{nomClient}', name: 'app_api_add_clients')]
    public function addClients(string $nomClient, ManagerRegistry $registry): Response
    {
        $reponse = array();
        if ($nomClient){
            // Recherche Nom Client
            $client= $registry->getRepository(Client::class)->findOneBy(['nom_client'=>strtoupper($nomClient)]);
            if (!$client){
                $client = new Client();
                $client->setNomClient(strtoupper($nomClient));
                $registry->getManager()->persist($client);
                $registry->getManager()->flush();
                $reponse = array('code'=>'SUCCESS');
            } else {
                $reponse = array('code'=>'EXISTS');
            }
        } else {
            $reponse = array('code'=>'BAD_DATA');
        }
        return new JsonResponse(json_encode($reponse));
    }
    #[Route('api/addDossier/{data}', name: 'app_api_add_dossier')]
    public function addDossier(string $data, ManagerRegistry $registry): Response
    {
        return new JsonResponse(['code'=>'warning', 'msg'=>'Cette ancienne action est desactivee. Utilisez la gestion du dossier et son suivi documentaire.'], 409);
    }

    /** Nom de l'utilisateur connecte (ou « Admin » par defaut). */
    private function auteurCourant(): string
    {
        $utilisateur = $this->getUser();
        if ($utilisateur && method_exists($utilisateur, 'getNom')) {
            $nom = trim((string) $utilisateur->getNom());
            if ($nom !== '') {
                return $nom;
            }
        }

        return 'Admin';
    }

    #[Route('api/editSingleDossier/{data}', name: 'app_api_edit_dossier')]
    public function editDossier(string $data, ManagerRegistry $registry, Request $request): Response
    {
        return new JsonResponse(['code'=>'warning', 'msg'=>'Cette ancienne action est desactivee. Utilisez la gestion du dossier et son suivi documentaire.'], 409);
    }

    #[Route('api/addDocByDossier/{data}', name: 'app_api_add_doc_by_dossier')]
    public function addDocByDossier(string $data, ManagerRegistry $registry, Request $request): Response
    {
        return new JsonResponse(['code'=>'warning', 'msg'=>'Cette ancienne action est desactivee. Utilisez la gestion du dossier et son suivi documentaire.'], 409);
    }


    #[Route('api/addPhotosByDossier/{code_dossier}', name: 'app_api_add_photos_by_dossier')]
    public function addPhotosByDossier(string $code_dossier, ManagerRegistry $registry, Request $request): Response
    {
        $reponse[] = array();
        $ot = $registry->getRepository(Dossier::class)->find($code_dossier);
        if ($ot){

            $files = $request->files->get("fichiers");
            //dd($files);

            foreach ($files as $file) {

                $filename = uniqid() . '.' . $file->guessExtension();



                $photo = new PhotosDocs();
                $originalName = $file->getClientOriginalName();
                $fichier = pathinfo($originalName, PATHINFO_FILENAME);
                $fichier = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fichier);
                $extension = $file->guessExtension();

                $file->move(
                    $this->getParameter('dossier_photos_directory'),
                    $filename
                );
                $this->optimiseurDocuments->optimiser(rtrim((string) $this->getParameter('dossier_photos_directory'), '/\\') . '/' . $filename);




                $photo->setFilename($filename);
                $photo->setFichier($fichier);
                $photo->setCodeDossier($ot);
                $photo->setExtension($extension);

                $registry->getManager()->persist($photo);
                $registry->getManager()->flush();


            }
            $reponse[] = array('code' => 'SUCCESS', 'status' => "success");
        } else {
            $reponse[] = array('code'=>'ERROR', 'status' => "error");
        }
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('api/getSingleDossier/{id_dossier}', name: 'app_api_get_single_dossier')]
    public function getSingleDossier(string $id_dossier, ManagerRegistry $registry): Response
    {
        $reponse = array();
        $dossier = $registry->getRepository(Dossier::class)->find($id_dossier);

        if ($dossier) {
            // Affecte les valeurs
            $reponse = array(
                'code' => 'SUCCESS',
                'numero_ot' => $dossier->getNumeroOt(),
                'proprio' => $dossier->getProprietaire() ? $dossier->getProprietaire()->getId() : 0,
                'pdt' => $dossier->getProduit() ? $dossier->getProduit()->getId() : 0,
                'clt' => $dossier->getClient() ? $dossier->getClient()->getId() : 0,
                'txt_proprio' => $dossier->getProprietaire() ? $dossier->getProprietaire()->getDenomination() : 0,
                'txt_pdt' => $dossier->getProduit() ? $dossier->getProduit()->getLibelle() : "",
                'txt_clt' => $dossier->getClient() ? $dossier->getClient()->getNomClient() : "",
                'dest' => $dossier->getDestination() ? $dossier->getDestination()->getId() : "",
                'navire' => $dossier->getCodeNavire() ? $dossier->getCodeNavire()->getNom() : 0,
                'tonnage' => $dossier->getTonnage(),
                'nb_tc' => $dossier->getNbTc(),
                'cv_deb' => $dossier->getCvDeblocage(),
                'cv_exe' => $dossier->getCvExecution(),
                'eta' => $dossier->getEta() ? $dossier->getEta()->format("d/m/Y") : "",
                'date_emb' => $dossier->getDateEmbarquement() ? $dossier->getDateEmbarquement()->format("d/m/Y") : "",
                'date_emp' => $dossier->getDateEmpotage() ? $dossier->getDateEmpotage()->format("d/m/Y") : "",
                'date_relev' => $dossier->getDateRelevage() ? $dossier->getDateRelevage()->format("d/m/Y") : "",
                'remise' => $dossier->getRemiseDoc() ? $dossier->getRemiseDoc()->format("d/m/Y") : "",
                'formule' => $dossier->isFormule(),
                'cheque' => $dossier->isCheque(),
                'fumigue' => $dossier->isTcFumigue(),
                'poids_vgm' => $dossier->getPoidsVgm() ? $dossier->getPoidsVgm() : 0,
                'poids_reel' => $dossier->getPoidsSigam() ? $dossier->getPoidsSigam() : 0,
                'etiquette' => $dossier->isEtiquette(),
                'draft' => $dossier->getDraft() ? $dossier->getDraft() : "0",
                'pl' => $dossier->getPackingList() ?  $dossier->getPackingList() : "0",
                'cert_poids' => $dossier->getCertificatPoids() ? $dossier->getCertificatPoids() : "0",
                'co' => $dossier->getCo() ? $dossier->getCo() : "0",
                'coa' => $dossier->getCoa() ? $dossier->getCoa() : "0",
                'conformite' => $dossier->getCertificatConformite() ? $dossier->getCertificatConformite() : "0",
                'hygienne' => $dossier->getCertificatHygienne() ? $dossier->getCertificatHygienne() : "0",
                'cert_phyto' => $dossier->getCertificatPhyto() ? $dossier->getCertificatPhyto() : "0",
                'cert_fumigation' => $dossier->getCertificatFumigation() ? $dossier->getCertificatFumigation() : "0",
                'halal' => $dossier->getCertificatHalah() ? $dossier->getCertificatHalah() : "0",
                'ville' => $dossier->getAdresse(),
                'fichier'=>$dossier->getFilename()? $dossier->getFilename() : ""
            );
        }
        return new JsonResponse(json_encode($reponse));
    }
    public function searchOT(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $query = $request->query->get('q', '');

        $results = [];
        if (!empty($query)) {
            $qb = $em->createQueryBuilder();
            $qb->select('d')
                ->from(Dossier::class, 'd')
                ->where($qb->expr()->like('LOWER(d.numero_ot)', ':search'))
                ->setParameter('search', '%' . strtolower($query) . '%')
                ->setMaxResults(10);

            $dossiers = $qb->getQuery()->getResult();

            foreach ($dossiers as $dossier) {
                $results[] = $dossier->getNumeroOt();
            }
        }
        return new JsonResponse($results);
    }


    #[Route('api/addTcAndLots/{id_dossier}/{data}', name: 'app_api_add_tc_lots')]
    public function addTcAndLots(string $id_dossier, string $data, ManagerRegistry $registry): Response
    {
        return new JsonResponse(['code'=>'warning', 'msg'=>'Cette ancienne action est desactivee. Utilisez la gestion du dossier et son suivi documentaire.'], 409);
    }

    #[Route('api/getTcAndLotsByDossier/{id_dossier}', name: 'app_api_get_tc_and_lots_by__dossier')]
    public function getTcAndLotsByDossier(string $id_dossier, ManagerRegistry $registry): Response
    {
        $reponse = array();
        $dossier = $registry->getRepository(Dossier::class)->find($id_dossier);

        if ($dossier) {
            $data_tcs = array();
            $data_lots = array();
            foreach ($dossier->getTcs() as $tc){
                $data_tcs[] = array(
                    'numero_tc'=>$tc->getNumeroTc()
                );
            }

            foreach ($dossier->getLots() as $lot){
                $data_lots[] = array(
                    'numero_lot'=>$lot->getNumeroLot()
                );
            }

            $reponse[] = array(
                'tcs' => $data_tcs,
                'lots' => $data_lots,
                'pn_th' => $dossier->getTonnage()
            );
        }
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('api/supprimePhoto/{id_photo}', name: 'app_api_delete_photo_by__dossier')]
    public function supprimePhoto(string $id_photo, ManagerRegistry $registry): Response
    {
        $reponse = array();
        $photo = $registry->getRepository(PhotosDocs::class)->find($id_photo);

        if ($photo) {
            /*Suppression du fichier en base*/
            $chemin = $this->getParameter('dossier_photos_directory')."/".$photo->getFilename();
            if (file_exists($photo->getFilename())) {
                if (unlink($photo->getFilename())) {
                    $registry->getManager()->remove($photo);
                    $registry->getManager()->flush();

                    $reponse[] = array(
                        'code' => "SUCCESS"
                    );
                } else {
                    $reponse[] = array(
                        'code' => "SUCCESS"
                    );
                }
            } else {
                $reponse[] = array(
                    'code' => "SUCCESS"
                );
            }

        } else {
            $reponse[] = array(
                'code' => "ERROR"
            );
        }
        return new JsonResponse(json_encode($reponse));
    }
    #[Route('api/supprimeDocRemise/{id_doc}', name: 'app_api_delete_remise_by__dossier')]
    public function supprimeRemise(string $id_doc, ManagerRegistry $registry): Response
    {
        return new JsonResponse(['code'=>'warning', 'msg'=>'Cette ancienne action est desactivee. Utilisez la gestion du dossier et son suivi documentaire.'], 409);
    }

    #[Route('api/getDossiersFiles/{id_dossier}', name: 'app_api_get_files_by__dossier')]
    public function getDossierFiles(string $id_dossier, ManagerRegistry $registry): Response
    {
        $reponse = array();
        $data = array();
        $dossier = $registry->getRepository(Dossier::class)->find($id_dossier);

        if ($dossier) {
            $fichiers = $dossier->getPhotosDocs();
            foreach ($fichiers as $fichier){
                $data[] = array(
                    'id'=>$fichier->getId(),
                    'fichier'=>$fichier->getFichier(),
                    'extension'=>$fichier->getExtension(),
                    'filename'=>$fichier->getFilename()
                );
            }
            $reponse = array(
                'code' => "SUCCESS",
                'data'=>$data
            );

        } else {
            $reponse = array(
                'code' => "ERROR"
            );
        }
        return new JsonResponse(json_encode($reponse));
    }

    public function generateDossierDocs(Dossier $dossier) : void
    {

        if ($dossier->getClient() && $dossier->getProduit()){

            // Recherche si le modele CLIENT / PRODUIT existe
            $rech_docs_client = $this->em->getRepository(ModeleDoc::class)->findOneBy([
                'code_produit'=>$dossier->getProduit(),
                'code_client'=>$dossier->getClient()
            ]);
            if ($rech_docs_client){
                // Le modele Client existe
                // Affectation
                $modele = $rech_docs_client;
            } else {
                // Vérifie que le produit contient un modele
                $rech_docs_pdt = $this->em->getRepository(ModeleDoc::class)->findOneBy([
                    'code_produit'=>$dossier->getProduit(),
                    'standard'=>true
                ]);
                if  ($rech_docs_pdt){
                    $modele = $rech_docs_pdt;
                }
            }

            foreach ($modele->getDetailsModeleDocs() as $detailsModeleDoc){
                $doc = new DocsDossier();
                $doc->setLibelle($detailsModeleDoc->getCodeDoc()->getLibelle());
                $doc->setCodeDossier($dossier);
                $doc->setCodeModele($modele);

                $this->em->getManager()->persist($doc);
                $this->em->getManager()->flush();
            }
        }

    }}
