<?php

namespace App\Controller\Api\Production;

use App\Entity\Logistique\Proprietaire;
use App\Entity\Production\Lot;
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
                            'lot'=>$lot->getNumeroLot(),
                            'proprio'=>$lot->getCodeProprio(),
                            'lot'=>$lot->getCodeProprio() ? $lot->getCodeProprio()->getDenomination() : "",
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
    #[Route('api/getAllLotsByProprietaire/{id_proprio}', name: 'app_api_get_lot_by_proprietaire')]
    public function app_api_get_lot_by_proprietaire($id_proprio): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){

                try{
                    // Liste des Natures d'équipements
                    $proprio = $this->em->getRepository(Proprietaire::class)->find(Uuid::fromString($id_proprio));
                    if ($proprio){
                        $lots = $this->em->getRepository(Lot::class)->findBy(['code_proprio'=>$proprio], ['libelle'=>'ASC']);
                        foreach ($lots as $lot){
                            $data[] = array(
                                'id'=>$lot->getId(),
                                'lot'=>$lot->getLibelle()
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
    #[Route('api/getSingleLot/{$id_lot}', name: 'app_api_get_single_lot')]
    public function app_api_get_single_lot($id_lot): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $lot = $this->em->getRepository(Lot::class)->find(Uuid::fromString($id_lot));

            if ($lot){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'id'=>$lot->getId(),
                    'lot'=>$lot->getLibelle(),
                    'proprietaire'=>$lot->getCodeProprio() ? $lot->getCodeProprio()->getDenomination() : "",
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
                $libelle_lot = $request->request->get('lot');
                $code_proprio = $request->request->get('code_proprio');


                if (!$libelle_lot || !$code_proprio) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir le lot et sélectionner le propriétaire !'];
                } else {
                    $proprio = $this->em->getRepository(Proprietaire::class)->find(Uuid::fromString($code_proprio));
                    if (!$proprio){$reponse = ['code' => 'warning', 'msg' => 'Merci de  sélectionner le propriétaire !']; return new  JsonResponse($reponse);}

                    if ($id_lot){
                        $lot = $this->em->getRepository(Lot::class)->find(Uuid::fromString($id_lot));
                    } else {
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_lot);
                        $lot = $this->em->getRepository(Lot::class)->find($uuid);
                    }

                    $isNew = false;
                    if (!$lot) {
                        $lot = new Lot();
                        $isNew = true;
                    }

                    $lot->setLibelle(strtoupper($libelle_lot));
                    $lot->setCodeProprio($proprio);

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
