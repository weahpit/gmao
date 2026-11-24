<?php

namespace App\Controller\Api\Production;

use App\Entity\Logistique\Magasin;
use App\Entity\Logistique\Proprietaire;
use App\Entity\Production\Lot;
use App\Entity\Production\Produit;
use App\Service\Production\GestionLotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiLotController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private GestionLotService $gestion,
    ){}

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
                    'pn_disponible'=>$lot->getPoidsNetDispo() ?? $lot->getPnTh() ?? 0,
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
                    'pn_disponible'=>$lot->getPoidsNetDispo() ?? $lot->getPnTh() ?? 0,
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
                    'pn_disponible'=>$lot->getPoidsNetDispo() ?? $lot->getPnTh() ?? 0,
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
                // L'emplacement n'est plus obligatoire : il est deduit du produit
                // (feves / produits finis) par le service partage.
                $magasin = $code_magasin
                    ? $this->em->getRepository(Magasin::class)->find(Uuid::fromString($code_magasin))
                    : null;

                if (!$numero_lot || !$date_prod || !$pn_th || !$proprio || !$produit) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir toutes les informations du lot !'];
                } else {

                    $lot = $id_lot
                        ? $this->em->getRepository(Lot::class)->find(Uuid::fromString($id_lot))
                        : null;

                    // Toute la validation (doublon de numero, poids, qualite) et
                    // l'ecriture passent par le service partage avec l'ecran web.
                    $resultat = $this->gestion->enregistrer([
                        'numero_lot' => $numero_lot,
                        'proprietaire' => $proprio,
                        'produit' => $produit,
                        'magasin' => $magasin,
                        'date_prod' => $date_prod,
                        'pn_th' => $pn_th,
                        'qualite' => 'C',
                    ], $lot instanceof Lot ? $lot : null, $this->utilisateur());

                    if (!$resultat['ok']) {
                        $reponse = [
                            'code' => 'error',
                            'msg' => implode('<br>', $resultat['erreurs']),
                        ];
                    } else {
                        $reponse = [
                            'code' => 'success',
                            'msg' => $resultat['creation'] ? 'Lot créé avec succès !' : 'Lot  mis à jour avec succès !'
                        ];
                    }
                }
            } catch (\Throwable $throwable) {
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est lote !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }
    #[Route('api/deleteLot/{id_lot}', name: 'app_api_delete_lot')]
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
            $lot = $id_lot ? $this->em->getRepository(Lot::class)->find(Uuid::fromString($id_lot)) : null;

            if (!$lot instanceof Lot) {
                return new JsonResponse(['code' => 'error', 'msg' => 'Erreur lors de la scission !']);
            }

            // La scission (numerotation -1 / -2, sacs, poids brut, magasin NC)
            // est centralisee dans le service partage avec l'ecran web.
            $resultat = $this->gestion->scinder($lot, (float) $poidsDemande, $this->utilisateur());

            $reponse = [
                'code' => $resultat['ok'] ? 'success' : 'warning',
                'msg' => $resultat['ok']
                    ? 'Scission effectuée avec succès'
                    : implode('<br>', $resultat['erreurs']),
            ];
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

    /**
     * Identifiant de l'utilisateur courant, tolerant aux comptes sans e-mail.
     */
    private function utilisateur(): string
    {
        $u = $this->getUser();
        if (!$u) {
            return 'systeme';
        }
        if (method_exists($u, 'getUserIdentifier')) {
            $id = (string) $u->getUserIdentifier();
            if ('' !== $id) {
                return $id;
            }
        }
        $id = method_exists($u, '__toString') ? (string) $u : '';
        return '' !== $id ? $id : 'systeme';
    }
}
