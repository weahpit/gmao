<?php

namespace App\Controller\Api;

use App\Entity\Fournisseur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiFournisseurController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllFournisseurs', name: 'app_api_get_fournisseur')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $fournisseurs = $this->em->getRepository(Fournisseur::class)->findBy([], ['sigle'=>'ASC']);
                    foreach ($fournisseurs as $fournisseur){
                        $data[] = array(
                            'id'=>$fournisseur->getId(),
                            'sigle'=>$fournisseur->getSigle(),
                            'rs'=>$fournisseur->getRaisonSociale(),
                            'email'=>$fournisseur->getEmail(),
                            'site'=>$fournisseur->getSiteweb(),
                            'tel'=>$fournisseur->getTel(),
                            'mobile'=>$fournisseur->getMobile(),
                            'adresse'=>$fournisseur->getAdresse(),
                            'cc'=>$fournisseur->getCc(),
                            'rccim'=>$fournisseur->getRccim(),
                            'agree'=>$fournisseur->isAgree()? "OUI" : 'NON',
                            'code'=>$fournisseur->getCode(),
                            'personne'=>$fournisseur->getPersonneRessource(),
                            'mobile_personne'=>$fournisseur->getContactPersonne(),
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
    #[Route('api/getSingleFournisseur/{$id_fournisseur}', name: 'app_api_get_single_fournisseur')]
    public function app_api_get_single_fournisseur(int $id_fournisseur): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $fournisseur = $this->em->getRepository(Fournisseur::class)->find($id_fournisseur);

            if ($fournisseur){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$fournisseur->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une nature d\'équipements dans la liste !'
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
    #[Route('api/saveFournisseur', name: 'app_api_save_fournisseur')]
    public function app_api_save_fournisseur(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_fournisseur = $request->request->get('id_fournisseur');
                $code_fournisseur = $request->request->get('code_fournisseur');
                $rs_fournisseur  = $request->request->get('rs_fournisseur');
                $sigle_fournisseur= $request->request->get('sigle_fournisseur');
                $email_fournisseur= $request->request->get('email_fournisseur');
                $site_fournisseur= $request->request->get('site_fournisseur');
                $tel_fournisseur = $request->request->get('tel_fournisseur');
                $mobile_fournisseur = $request->request->get('mobile_fournisseur');
                $adresse_fournisseur = $request->request->get('adresse_fournisseur');
                $bp_fournisseur = $request->request->get('bp_fournisseur');
                $rccim_fournisseur = $request->request->get('rccim_fournisseur');
                $cc_fournisseur = $request->request->get('cc_fournisseur');
                $nom_contact_fournisseur = $request->request->get('nom_contact_fournisseur');
                $mobile_contact_fournisseur = $request->request->get('mobile_contact_fournisseur');
                $notes_fournisseur = $request->request->get('notes_fournisseur');
                $agree = $request->request->get('agree_fournisseur');

                // dd($agree);
                if (!$rs_fournisseur || !$sigle_fournisseur || !$email_fournisseur ||
                    !$tel_fournisseur) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_fournisseur);
                    $fournisseur = $this->em->getRepository(Fournisseur::class)->find($uuid);
                    $isNew = false;
                    if (!$fournisseur) {
                        $fournisseur = new Fournisseur();
                        $isNew = true;
                    }

                    $fournisseur->setRaisonSociale(strtoupper($rs_fournisseur));
                    $fournisseur->setSigle(strtoupper($sigle_fournisseur));
                    $fournisseur->setEmail(strtolower($email_fournisseur));
                    $fournisseur->setSiteweb($site_fournisseur);
                    $fournisseur->setMobile(strtoupper($mobile_fournisseur));
                    $fournisseur->setTel(strtoupper($tel_fournisseur));
                    $fournisseur->setCc(strtoupper($cc_fournisseur));
                    $fournisseur->setBp($bp_fournisseur);
                    $fournisseur->setRccim(strtoupper($rccim_fournisseur));
                    $fournisseur->setAdresse($adresse_fournisseur);
                    $fournisseur->setPersonneRessource(strtoupper($nom_contact_fournisseur));
                    $fournisseur->setContactPersonne(strtoupper($mobile_contact_fournisseur));
                    $fournisseur->setCode(strtoupper($code_fournisseur));
                    $fournisseur->setNotes($notes_fournisseur);
                    if ($agree == "1"){$fournisseur->setAgree(true);} else {$fournisseur->setAgree(false);}

                    $this->em->persist($fournisseur);
                    $this->em->flush();

                    $reponse = [
                        'code' => "success",
                        'msg' => $isNew ? 'Fournisseur créé avec succès !' : 'Fournisseur créé mise à jour avec succès !'
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
    #[Route('api/deleteFournisseur/{$id_fournisseur}', name: 'app_api_delete_fournisseur')]
    public function app_api_delete_fournisseur(Request $request, int $id_fournisseur): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $fournisseur = $this->em->getRepository(Fournisseur::class)->find($id_fournisseur);

                if ($fournisseur){
                    $this->em->remove($fournisseur);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Nature Equipement supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une nature d\'équipements !'
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
