<?php

namespace App\Controller\Api\Production;

use App\Entity\Logistique\Magasin;
use App\Entity\Logistique\Proprietaire;
use App\Entity\Production\Lot;
use App\Entity\Production\Produit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiLotController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllLots', name: 'app_api_get_lot')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $lots = $this->em->getRepository(Lot::class)->findBy([], ['numero_lot'=>'ASC']);
                    foreach ($lots as $lot){
                        $data[] = array(
                            'id'=>$lot->getId(),
                            'numero_lot'=>$lot->getNumeroLot(),
                            'proprio'=>$lot->getCodeProprio()?$lot->getCodeProprio()->getDenomination() : "" ,
                            'produit'=>$lot->getCodeProduit() ? $lot->getCodeProduit()->getLibelle() : "",
                            'date_production'=>$lot->getDateProd() ? $lot->getDateProd()->format('d/m/Y') : "",
                            'pn_th'=>$lot->getPnTh() ? $lot->getPnTh() :0,
                            'nbSacs'=>$lot->getNbSacs() ? $lot->getNbSacs() :0,
                            'pb_th'=>$lot->getPbTht() ? $lot->getPbTht() :0,
                            'etat'=>"-",
                            'situation'=>$lot->getCodeMagasin() ? $lot->getCodeMagasin()->getLibelle() : "",
                            'ot'=>"-",
                            'qualite'=>$lot->getQualite(),
                            'unite'=>$lot->getCodeProduit() ? $lot->getCodeProduit() ->getUnite()->getLibelle() : ""
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

    #[Route('api/getAllLotsByTypeProduits/{type_produit}', name: 'getAllLotsByTypeProduits')]
    public function getAllLotsByTypeProduits($type_produit): Response
    {
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        $reponse = array();
        $data = array();
        if ($this->isGranted("ROLE_USER")){
            try{
                // Liste des Natures d'équipements
                $typeProduit = $this->em->getRepository(Produit::class)->find($type_produit);
                if ($typeProduit){
                    $lots = $this->em->getRepository(Lot::class)->findBy(['code_produit'=>$typeProduit], ['numero_lot'=>'ASC']);
                    foreach ($lots as $lot){
                        $data[] = array(
                            'id'=>$lot->getId(),
                            'numero_lot'=>$lot->getNumeroLot(),
                            'proprio'=>$lot->getCodeProprio()?$lot->getCodeProprio()->getDenomination() : "" ,
                            'produit'=>$lot->getCodeProduit() ? $lot->getCodeProduit()->getLibelle() : "",
                            'date_production'=>$lot->getDateProd() ? $lot->getDateProd()->format('d/m/Y') : "",
                            'pn_th'=>$lot->getPnTh() ? $lot->getPnTh() :0,
                            'nbSacs'=>$lot->getNbSacs() ? $lot->getNbSacs() :0,
                            'pb_th'=>$lot->getPbTht() ? $lot->getPbTht() :0,
                            'etat'=>"-",
                            'situation'=>$lot->getCodeMagasin() ? $lot->getCodeMagasin()->getLibelle() : "",
                            'ot'=>"-",
                            'qualite'=>$lot->getQualite(),
                            'unite'=>$lot->getCodeProduit() ? $lot->getCodeProduit() ->getUnite()->getLibelle() : ""
                        );
                    }
                    $reponse = array('code'=>'success', 'msg'=>'Succès', 'data'=>$data);
                } else {
                    $reponse = array('code'=>'warning', 'msg'=>'Merci de sélectionner un produit');
                }

            } catch (\Throwable $throwable){
                $reponse = array('code'=>'error', 'msg'=>'Erreur ! <br>'. $throwable->getMessage());
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse(json_encode($reponse));
    }
    #[Route('api/getSingleLot', name: 'app_api_get_single_lot')]
    public function app_api_get_single_lot(Request $request): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $id_lot = $request->request->get('id_lot');
                $lot = $this->em->getRepository(Lot::class)->find(Uuid::fromString($id_lot));

            if ($lot){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'id'=>$lot->getId(),
                    'numero_lot'=>$lot->getNumeroLot(),
                    'proprio'=>$lot->getCodeProprio()?$lot->getCodeProprio()->getId() : "" ,
                    'produit'=>$lot->getCodeProduit() ? $lot->getCodeProduit()->getId() : "",
                    'date_production'=>$lot->getDateProd() ? $lot->getDateProd()->format('Y-m-d') : "",
                    'pn_th'=>$lot->getPnTh() ? $lot->getPnTh() :0,
                    'nbSacs'=>$lot->getNbSacs() ? $lot->getNbSacs() :0,
                    'pb_th'=>$lot->getPbTht() ? $lot->getPbTht() :0,
                    'etat'=>"-",
                    'situation'=>$lot->getCodeMagasin() ? $lot->getCodeMagasin()->getId() : "",
                    'ot'=>"-",
                    'qualite'=>$lot->getQualite(),
                    'unite'=>$lot->getCodeProduit() ? $lot->getCodeProduit() ->getUnite()->getLibelle() : ""
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner un propriétaire dans la liste !'
                );
            }

            } catch (\Throwable $throwable) {
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est lote !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }

    #[Route('api/saveLot', name: 'app_api_save_lot')]
    public function app_api_save_lot(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG")) {
            try {
                $id_lot = $request->request->get('id_lot');
                $numero_lot = $request->request->get('numero_lot');
                $code_proprio = $request->request->get('code_proprio');
                $code_produit = $request->request->get('code_produit');
                $code_magasin = $request->request->get('magasin');
                $pn_th = $request->request->get('pn_th');
                $date_prod = $request->request->get('date_prod');

                $proprio = $this->em->getRepository(Proprietaire::class)->find(Uuid::fromString($code_proprio));
                $produit = $this->em->getRepository(Produit::class)->find(Uuid::fromString($code_produit));
                $magasin = $this->em->getRepository(Magasin::class)->find(Uuid::fromString($code_magasin));

                if (!$numero_lot || !$date_prod || !$pn_th || !$proprio || !$produit || !$magasin ) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir toutes les informations du lot !'];
                } else {

                    if ($id_lot){
                        $lot = $this->em->getRepository(Lot::class)->find(Uuid::fromString($id_lot));
                    } else {
                        // Recherche de doublons
                        $rech_dbl = $this->em->getRepository(Lot::class)->findBy(['origine'=>strtoupper($numero_lot)]);
                        if ($rech_dbl){
                            $reponse = [
                                'code' => 'error',
                                'msg' =>  'CE NUMERO DE LOT EXISTE DEJA  !'
                            ];
                            return new JsonResponse($reponse);
                        }
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_lot);
                        $lot = $this->em->getRepository(Lot::class)->find($uuid);
                    }

                    $isNew = false;
                    if (!$lot) {
                        $lot = new Lot();
                        $isNew = true;
                        $lot->setPnTh(floatval($lot->getCodeProduit()?$lot->getCodeProduit()->getPoidsNetDefault() : 0 ));
                        $lot->setPbTht(floatval($lot->getCodeProduit()?$lot->getCodeProduit()->getPoidsNetDefault() : 0 ));
                        $lot->setNbSacs(floatval($lot->getCodeProduit()?$lot->getCodeProduit()->getPoidsNetDefault() : 0 ));

                        $lot->setQualite('C');
                        $lot->setCreatedAt(new \DateTimeImmutable());
                        $lot->setCreatedBy($this->getUser());
                        $lot->setCreatedBy($this->getUser());
                    }

                    $lot->setNumeroLot(strtoupper($numero_lot));
                    $lot->setOrigine(strtoupper($numero_lot));
                    $lot->setCodeProprio($proprio);
                    $lot->setCodeProduit($produit);
                    $lot->setCodeMagasin($magasin);
                    $lot->setDateProd(new \DateTime($date_prod));

                    $this->em->persist($lot);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Lot créé avec succès !' : 'Lot  mis à jour avec succès !'
                    ];
                }
            } catch (\Throwable $throwable) {
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est lote !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }
    #[Route('api/deleteLot/{$id_lot}', name: 'app_api_delete_lot')]
    public function app_api_delete_lot(Request $request,  $id_lot): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $lot = $this->em->getRepository(Lot::class)->find(Uuid::fromString($id_lot));

                if ($lot){
                    $this->em->remove($lot);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Lot supprimé avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner un Lot !'
                    );
                }

            } catch (\Throwable $throwable) {
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est lote !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }

#[Route('api/Prod/ScissionLot', name: 'get_scission_lot')]
    public function get_scission_lot(Request $request): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG")) {
            try {
            $id_lot = $request->request->get('id_lot');
            $poidsDemande = $request->request->get('poids_demande');
            $lot = $this->em->getRepository(Lot::class)->find($id_lot);
            if($lot){
                $pb = $lot->getPbTht() ?  $lot->getPbTht()  : $lot->getCodeProduit()->getPoidsBrutDefault();
                $pn = $lot->getPnTh();
                $tare = $lot->getCodeProduit()->getTareUniteDefault() ? $lot->getCodeProduit()->getTareUniteDefault() : 0 ;

                if ((float) $poidsDemande > $pn or (float) $poidsDemande <= 0) {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de renseigner un Poids Net Correct !'
                    );
                } else {
                    $nb_sacs_nc = 0;
                    $nb_sacs_c = 0;

                    //Création du lolt NC
                    $lot_nc = new Lot();
                    $lot_nc->setNumeroLot($lot->getNumeroLot()."-2");
                    $lot_nc->setOrigine(strtoupper($lot->getOrigine()));
                    $lot_nc->setScission(true);
                    $lot_nc->setQualite("NC");
                    $lot_nc->setPnTh((float) $pn);

                    //Calcule le nb de sacs ou cartons ou big bag
                        $nb_sacs_nc = ($lot->getNbSacs() * (float) $poidsDemande) / $lot->getPnTh();
                    // Calcule du poids Brut
                        $tare_nc =  $nb_sacs_nc * ($tare);
                        $pb_nc = (float) $pn + $tare_nc;

                    $lot_nc->setPbTht($pb_nc);
                    $lot_nc->setNbSacs($nb_sacs_nc);
                    $lot_nc->setPoidsNetDispo((float) $pn);
                    $lot_nc->setUpdatedAt(new \DateTimeImmutable());
                    $lot_nc->setCreatedAt(new \DateTimeImmutable());
                    $lot_nc->setUpdatedBy($this->getUser());
                    $lot_nc->setCreatedBy($this->getUser());
                    $lot_nc->setCodeMagasin($this->em->getRepository(Magasin::class)->findOneBy(['libelle'=>'MAGASIN NON CONFORME']));
                    $lot_nc->setCodeProduit($lot->getCodeProduit());
                    $lot_nc->setCodeProprio($lot->getCodeProprio());
                    $lot_nc->setDateProd($lot->getDateProd());
                    $this->em->persist($lot_nc);

                    // Mise à jour du lot principal


                    $lot->setNumeroLot($lot->getNumeroLot()."-1");
                    $lot->setScission(true);
                    $lot->setQualite("C");
                    $lot->setPnTh($lot->getPnTh() - (int) $pn);
                    $lot->setPoidsNetDispo($lot->getPnTh());

                    $nb_sacs_c = $lot->getNbSacs() - $nb_sacs_c;
                    $pb_c = $pb - $pb_nc;
                    $lot->setPbTht($pb_c);
                    $lot->setNbSacs($nb_sacs_c);

                    $lot->setUpdatedAt(new \DateTimeImmutable());
                    $lot->setUpdatedBy($this->getUser());
                    $this->em->persist($lot);
                    $this->em->flush();

                    $reponse = array(
                        'code'=>"success",
                        'msg'=>'Scission effectuée avec succès'
                    );
                }
            } else {
                $reponse = array(
                    'code'=>"error",
                    'msg'=>'Erreur lors de la scission !'
                );
            }
        } catch (\Throwable $throwable){
            $reponse = array(
                'code'=>"error",
                'msg'=>$throwable->getTraceAsString()
            );
        }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }
}
