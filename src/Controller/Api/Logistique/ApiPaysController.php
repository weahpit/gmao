<?php

namespace App\Controller\Api\Logistique;

use App\Entity\Logistique\Pays;
use App\Entity\ZoneExploitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiPaysController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllPays', name: 'app_api_get_pays')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $payss = $this->em->getRepository(Pays::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($payss as $pays){
                        $data[] = array(
                            'id'=>$pays->getId(),
                            'pays'=>$pays->getLibelle(),
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
    #[Route('api/getSinglePays/{$id_pays}', name: 'app_api_get_single_pays')]
    public function app_api_get_single_pays($id_pays): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $pays = $this->em->getRepository(Pays::class)->find(Uuid::fromString($id_pays));

            if ($pays){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'pays'=>$pays->getLibelle(),
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
    #[Route('api/savePays', name: 'app_api_save_pays')]
    public function app_api_save_pays(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG")) {
            try {
                $id_pays = $request->request->get('id_pays');
                $libelle_pays = $request->request->get('pays');

                if (!$libelle_pays) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir le propriétaire !'];
                } else {
                    if ($id_pays){
                        $pays = $this->em->getRepository(Pays::class)->find(Uuid::fromString($id_pays));
                    } else {
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_pays);
                        $pays = $this->em->getRepository(Pays::class)->find($uuid);
                    }

                    $isNew = false;
                    if (!$pays) {
                        $pays = new Pays();
                        $isNew = true;
                    }

                    $pays->setLibelle(strtoupper($libelle_pays));

                    $this->em->persist($pays);
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
    #[Route('api/deletePays/{$id_pays}', name: 'app_api_delete_pays')]
    public function app_api_delete_pays(Request $request,  $id_pays): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $pays = $this->em->getRepository(Pays::class)->find(Uuid::fromString($id_pays));

                if ($pays){
                    $this->em->remove($pays);
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
