<?php

namespace App\Controller\Api\Admin;

use App\Entity\Poste;
use App\Entity\Services;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiPosteController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllPostes', name: 'app_api_get_poste')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $postes = $this->em->getRepository(Poste::class)->findBy([], ['libelle'=>'ASC']);
                    foreach ($postes as $poste){
                        $data[] = array(
                            'id'=>$poste->getId(),
                            'poste'=>$poste->getLibelle()
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

        #[Route('api/getAllPostesByService/{id_service}', name: 'get_all_postes_by_service')]
    public function get_all_postes_by_service($id_service): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Postes
                    $UuidService = Uuid::fromString($id_service);
                    $postes = $this->em->getRepository(Poste::class)->findBy(['code_service'=>$UuidService], ['libelle'=>'ASC']);
                    foreach ($postes as $poste){
                        $data[] = array(
                            'id'=>$poste->getId(),
                            'poste'=>$poste->getLibelle()
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
    #[Route('api/getSinglePoste/{$id_poste}', name: 'app_api_get_single_poste')]
    public function app_api_get_single_poste(int $id_poste): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $poste = $this->em->getRepository(Poste::class)->find($id_poste);

            if ($poste){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'poste'=>$poste->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner un poste  dans la liste !'
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
    #[Route('api/savePoste', name: 'app_api_save_poste')]
    public function app_api_save_poste(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_poste = $request->request->get('id_poste');
                $libelle_poste = $request->request->get('poste');
                $service = $request->request->get('service');

                $uuid = Uuid::fromString($service);
                $Serv = $this->em->getRepository(Services::class)->find($uuid);

                if (!$libelle_poste || !$Serv) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir le poste et le service!'];
                } else {
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_poste);

                    $poste = $this->em->getRepository(Poste::class)->find($uuid);
                    $isNew = false;
                    if (!$poste) {
                        $poste = new Poste();
                        $isNew = true;
                    }

                    $poste->setLibelle(strtoupper($libelle_poste));
                    $poste->setCodeService($Serv);

                    $this->em->persist($poste);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Poste créé avec succès !' : 'Poste  mis à jour avec succès !'
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
    #[Route('api/deletePoste/{$id_poste}', name: 'app_api_delete_poste')]
    public function app_api_delete_poste(Request $request, int $id_poste): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $uuid = Uuid::fromString($id_poste);
                $poste = $this->em->getRepository(Poste::class)->find($uuid);

                if ($poste){
                    $this->em->remove($poste);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Poste supprimé avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner un poste !'
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
