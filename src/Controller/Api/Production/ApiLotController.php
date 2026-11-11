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
                            'etat'=>"-",
                            'situation'=>$lot->getCodeMagasin() ? $lot->getCodeMagasin()->getLibelle() : "",
                            'ot'=>"-",
                            'qualite'=>$lot->getQualite()
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
                    'etat'=>"-",
                    'situation'=>$lot->getCodeMagasin() ? $lot->getCodeMagasin()->getId() : "",
                    'ot'=>"-",
                    'qualite'=>$lot->getQualite()
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
                        $rech_dbl = $this->em->getRepository(Lot::class)->findBy(['numero_lot'=>strtoupper($numero_lot)]);
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
                    }

                    $lot->setNumeroLot(strtoupper($numero_lot));
                    $lot->setCodeProprio($proprio);
                    $lot->setCodeProduit($produit);
                    $lot->setCodeMagasin($magasin);
                    $lot->setDateProd(new \DateTime($date_prod));
                    $lot->setQualite('C');
                    $lot->setCreatedAt(new \DateTimeImmutable());
                    $lot->setCreatedBy($this->getUser());
                    $lot->setScission(false);
                    $lot->setPnTh(floatval($pn_th));

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
}
