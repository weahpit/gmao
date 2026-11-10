<?php

namespace App\Controller\Api\Production;

use App\Entity\Logistique\Proprietaire;
use App\Entity\Production\Produit;
use App\Entity\ZoneExploitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiProduitController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllProduits', name: 'app_api_get_produit')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $produits = $this->em->getRepository(Produit::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($produits as $produit){
                        $data[] = array(
                            'id'=>$produit->getId(),
                            'produit'=>$produit->getLibelle(),
                            'proprietaire'=>$produit->getCodeProprio() ? $produit->getCodeProprio()->getDenomination() : "",
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
    #[Route('api/getAllProduitsByProprietaire/{id_proprio}', name: 'app_api_get_produit_by_proprietaire')]
    public function app_api_get_produit_by_proprietaire($id_proprio): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){

                try{
                    // Liste des Natures d'équipements
                    $proprio = $this->em->getRepository(Proprietaire::class)->find(Uuid::fromString($id_proprio));
                    if ($proprio){
                        $produits = $this->em->getRepository(Produit::class)->findBy(['code_proprio'=>$proprio], ['libelle'=>'ASC']);
                        foreach ($produits as $produit){
                            $data[] = array(
                                'id'=>$produit->getId(),
                                'produit'=>$produit->getLibelle()
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
    #[Route('api/getSingleProduit/{$id_produit}', name: 'app_api_get_single_produit')]
    public function app_api_get_single_produit($id_produit): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $produit = $this->em->getRepository(Produit::class)->find(Uuid::fromString($id_produit));

            if ($produit){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'id'=>$produit->getId(),
                    'produit'=>$produit->getLibelle(),
                    'proprietaire'=>$produit->getCodeProprio() ? $produit->getCodeProprio()->getDenomination() : "",
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner un propriétaire dans la liste !'
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
    #[Route('api/saveProduit', name: 'app_api_save_produit')]
    public function app_api_save_produit(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG")) {
            try {
                $id_produit = $request->request->get('id_produit');
                $libelle_produit = $request->request->get('produit');
                $code_proprio = $request->request->get('code_proprio');


                if (!$libelle_produit || !$code_proprio) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir le produit et sélectionner le propriétaire !'];
                } else {
                    $proprio = $this->em->getRepository(Proprietaire::class)->find(Uuid::fromString($code_proprio));
                    if (!$proprio){$reponse = ['code' => 'warning', 'msg' => 'Merci de  sélectionner le propriétaire !']; return new  JsonResponse($reponse);}

                    if ($id_produit){
                        $produit = $this->em->getRepository(Produit::class)->find(Uuid::fromString($id_produit));
                    } else {
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_produit);
                        $produit = $this->em->getRepository(Produit::class)->find($uuid);
                    }

                    $isNew = false;
                    if (!$produit) {
                        $produit = new Produit();
                        $isNew = true;
                    }

                    $produit->setLibelle(strtoupper($libelle_produit));
                    $produit->setCodeProprio($proprio);

                    $this->em->persist($produit);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Produit créé avec succès !' : 'Produit  mis à jour avec succès !'
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
    #[Route('api/deleteProduit/{$id_produit}', name: 'app_api_delete_produit')]
    public function app_api_delete_produit(Request $request,  $id_produit): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $produit = $this->em->getRepository(Produit::class)->find(Uuid::fromString($id_produit));

                if ($produit){
                    $this->em->remove($produit);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Produit supprimé avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner un Produit !'
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
