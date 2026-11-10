<?php

namespace App\Controller\Api\Logistique;

use App\Entity\Logistique\Banque;
use App\Entity\ZoneExploitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiBanqueController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllBanques', name: 'app_api_get_banque')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $banques = $this->em->getRepository(Banque::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($banques as $banque){
                        $data[] = array(
                            'id'=>$banque->getId(),
                            'banque'=>$banque->getLibelle(),
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
    #[Route('api/getSingleBanque/{$id_banque}', name: 'app_api_get_single_banque')]
    public function app_api_get_single_banque($id_banque): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $banque = $this->em->getRepository(Banque::class)->find(Uuid::fromString($id_banque));

            if ($banque){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'banque'=>$banque->getLibelle(),
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
    #[Route('api/saveBanque', name: 'app_api_save_banque')]
    public function app_api_save_banque(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG")) {
            try {
                $id_banque = $request->request->get('id_banque');
                $libelle_banque = $request->request->get('banque');

                if (!$libelle_banque) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir le propriétaire !'];
                } else {
                    if ($id_banque){
                        $banque = $this->em->getRepository(Banque::class)->find(Uuid::fromString($id_banque));
                    } else {
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_banque);
                        $banque = $this->em->getRepository(Banque::class)->find($uuid);
                    }

                    $isNew = false;
                    if (!$banque) {
                        $banque = new Banque();
                        $isNew = true;
                    }

                    $banque->setLibelle(strtoupper($libelle_banque));

                    $this->em->persist($banque);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Propriétaire créé avec succès !' : 'Propriétaire  mis à jour avec succès !'
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
    #[Route('api/deleteBanque/{$id_banque}', name: 'app_api_delete_banque')]
    public function app_api_delete_banque(Request $request,  $id_banque): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $banque = $this->em->getRepository(Banque::class)->find(Uuid::fromString($id_banque));

                if ($banque){
                    $this->em->remove($banque);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Propriétaire supprimé avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner un Propriétaire !'
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
