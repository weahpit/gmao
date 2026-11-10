<?php

namespace App\Controller\Api\Logistique;

use App\Entity\Logistique\Client;
use App\Entity\Logistique\Pays;
use App\Entity\ZoneExploitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiClientController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllClients', name: 'app_api_get_client')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{

                    $clients = $this->em->getRepository(Client::class)->findBy([], ['nom_client'=>'ASC']);
                    foreach ($clients as $client){
                        $data[] = array(
                            'id'=>$client->getId(),
                            'client'=>$client->getNomClient(),
                            'pays'=>$client->getCodePays() ? $client->getCodePays()->getLibelle() : '',
                            'ville'=>$client->getVille(),
                            'adresse'=>$client->getAdresseClient(),
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
    #[Route('api/getSingleClient/{$id_client}', name: 'app_api_get_single_client')]
    public function app_api_get_single_client($id_client): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $client = $this->em->getRepository(Client::class)->find(Uuid::fromString($id_client));

            if ($client){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'id'=>$client->getId(),
                    'client'=>$client->getNomClient(),
                    'pays'=>$client->getCodePays() ? $client->getCodePays()->getLibelle() : '',
                    'ville'=>$client->getVille(),
                    'adresse'=>$client->getAdresseClient(),
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
    #[Route('api/saveClient', name: 'app_api_save_client')]
    public function app_api_save_client(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG")) {
            try {
                $id_client = $request->request->get('id_client');
                $nom_client = $request->request->get('client');
                $code_pays = $request->request->get('pays');
                $ville= $request->request->get('ville');
                $adresse = $request->request->get('adresse');

                $pays = $this->em->getRepository(Pays::class)->find(Uuid::fromString($code_pays));

                if (!$nom_client || !$pays) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir le client et son pays !'];
                } else {
                    $rech_client = $this->em->getRepository(Client::class)->findBy(['nom_client'=>$nom_client]);
                    if ($rech_client){
                        $reponse = [
                            'code' => 'error',
                            'msg' => 'Ce client existe déjà en base !'
                        ];
                    } else {
                        if ($id_client){
                            $client = $this->em->getRepository(Client::class)->find(Uuid::fromString($id_client));
                        } else {
                            $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_client);
                            $client = $this->em->getRepository(Client::class)->find($uuid);
                        }

                        $isNew = false;
                        if (!$client) {
                            $client = new Client();
                            $isNew = true;
                        }

                        $client->setNomClient(strtoupper($nom_client));
                        $client->setCodePays($pays);
                        $client->setVille(strtoupper($ville));
                        $client->setAdresseClient(strtoupper($adresse));

                        $this->em->persist($client);
                        $this->em->flush();

                        $reponse = [
                            'code' => 'success',
                            'msg' => $isNew ? 'Client créé avec succès !' : 'Client  mis à jour avec succès !'
                        ];
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
    #[Route('api/deleteClient/{$id_client}', name: 'app_api_delete_client')]
    public function app_api_delete_client(Request $request,  $id_client): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $client = $this->em->getRepository(Client::class)->find(Uuid::fromString($id_client));

                if ($client){
                    $this->em->remove($client);
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
