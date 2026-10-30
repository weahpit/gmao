<?php

namespace App\Controller\Api\Admin;

use App\Entity\Groupe;
use App\Entity\Poste;
use App\Entity\Services;
use App\Entity\User;
use App\Services\Functions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiUsersController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em, private Functions $functions){}

    #[Route('api/getAllUsers', name: 'app_api_get_users')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $users = $this->em->getRepository(User::class)->findBy([], ['nom'=>'ASC','prenoms'=>'ASC' ]);
                    foreach ($users as $user){
                        $data[] = array(
                            'id'=>$user->getId(),
                            'nom'=>$user->getNom(),
                            'prenoms'=>$user->getPrenoms(),
                            'email'=>$user->getEmail(),
                            'mobile'=>$user->getMobile(),
                            'matricule'=>$user->getMatricule(),
                            'photo'=>$user->getPhoto(),
                            'groupe'=>$user->getCodeGroupe() ?$user->getCodeGroupe()->getNomGroupe() : "",
                            'poste'=>$user->getCodePoste() ?$user->getCodePoste()->getLibelle() : "",
                            'service'=>$user->getCodeService() ?$user->getCodeService()->getLibelle() : "",
                            'direction'=>$user->getCodeDirection() ?$user->getCodeDirection()->getLibelle() : "",
                            'actif'=>$user->isActive() ? "OUI" : "NON",
                            'admin'=>$user->isAdmin() ? "OUI" : "NON",
                            'bloque'=>$user->isBloque() ? "OUI" : "NON",
                            'signataire'=>$user->isSignataire() ? "OUI" : "NON",
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

    #[Route('api/getSingleUser', name: 'app_api_get_single_user')]
    public function app_api_get_single_user(Request $request): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {


                $id_user = $request->request->get('id_user');
                $uuid = Uuid::fromString($id_user);
                $user = $this->em->getRepository(User::class)->find($uuid);

            if ($user){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'id'=>$user->getId(),
                    'nom'=>$user->getNom(),
                    'prenoms'=>$user->getPrenoms(),
                    'email'=>$user->getEmail(),
                    'mobile'=>$user->getMobile(),
                    'matricule'=>$user->getMatricule(),
                    'photo'=>$user->getPhoto(),
                    'groupe'=>$user->getCodeGroupe() ?$user->getCodeGroupe()->getId() : "",
                    'poste'=>$user->getCodePoste() ?$user->getCodePoste()->getId() : "",
                    'service'=>$user->getCodeService() ?$user->getCodeService()->getId() : "",
                    'direction'=>$user->getCodeDirection() ?$user->getCodeDirection()->getId() : "",
                    'actif'=>$user->isActive() ? "OUI" : "NON",
                    'admin'=>$user->isAdmin() ? "OUI" : "NON",
                    'bloque'=>$user->isBloque() ? "OUI" : "NON",
                    'signataire'=>$user->isSignataire() ? "OUI" : "NON",
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une user  dans la liste !'
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

    #[Route('api/saveUser', name: 'app_api_save_users')]
    public function app_api_save_users(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_user = $request->request->get('id_user');
                $nom = $request->request->get('nom');
                $prenoms = $request->request->get('prenoms');
                $email = $request->request->get('email');
                $matricule = $request->request->get('matricule');
                $mobile = $request->request->get('mobile');
                $groupe = $request->request->get('groupe');
                $service = $request->request->get('service');
                $poste = $request->request->get('poste');
                $admin = $request->request->get('admin');
                $bloque = $request->request->get('bloque');
                $signataire = $request->request->get('signataire');
                $mdp = $request->request->get('mdp');
                $confirm_mdp = $request->request->get('confirm_mdp');
                $photo = $request->files->get('photo');

                    if (!$nom || !$prenoms || !$email || !$mobile){
                        $reponse = ['code' => 'warning', 'msg' => 'Merci de renseigner toutes les valeurs obligatoires !'];
                    } else {

                        $isNew = false;

                        if ($id_user === "0") {
                            if (($mdp != $confirm_mdp) || $confirm_mdp == "" || $mdp == ""){
                                $reponse = ['code' => 'warning', 'msg' => 'Les mots de passe ne sont pas conformes !'];
                                return new JsonResponse($reponse);
                            } else {
                                $users = new User();
                                $isNew = true;
                                $users->setPassword(password_hash($mdp, PASSWORD_BCRYPT));
                                $users->setActive(true);
                            }

                        } else {
                            $uuid = Uuid::fromString($id_user);
                            $users = $this->em->getRepository(User::class)->find($uuid);

                        }

                        $users->setMatricule(strtoupper($matricule));
                        $users->setNom(strtoupper($nom));
                        $users->setPrenoms(strtoupper($prenoms));
                        $users->setEmail(strtolower($email));
                        $users->setMobile(strtoupper($mobile));

                            if ($groupe){
                                $Groupe = $this->em->getRepository(Groupe::class)->find(Uuid::fromString($groupe));
                                if ($Groupe){
                                    $users->setCodeGroupe($Groupe);
                                }
                            }

                            if ($service){
                                $Service = $this->em->getRepository(Services::class)->find(Uuid::fromString($service));
                                if ($Service){
                                    $users->setCodeService($Service);
                                }
                            }

                            if ($poste){
                                $Poste = $this->em->getRepository(Poste::class)->find(Uuid::fromString($poste));
                                if ($Poste){
                                    $users->setCodePoste($Poste);
                                }
                            }

                        if ($photo) {
                            $filename = uniqid().'.'.$photo->guessExtension();
                            $photo->move($this->getParameter('photo_users'), $filename);
                            $users->setPhoto($filename);
                        }

                        $users->setRoles(['ROLE_USER']);
                        $users->setAdmin(false);

                        // dd($admin);
                        if ($admin == "OUI") {
                            $users->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
                            $users->setAdmin(true);
                        }

                        // L'utilisateur est-il signataire ?
                        if ($signataire == "OUI"){
                            $users->setRoles(['ROLE_USER','ROLE_DG']);
                            $users->setSignataire(true);
                        }

                            // L'utilisateur est-il bloque ?
                            if ($bloque == "OUI"){
                                $users->setBloque(true);
                                $users->setActive(false);

                            }else {
                                $users->setBloque(false);
                                $users->setActive(true);
                            }

                        $this->em->persist($users);
                        $this->em->flush();

                        $reponse = [
                            'code' => 'success',
                            'msg' => $isNew ? 'Utilisateur créé avec succès !' : 'Utilisateur  mise à jour avec succès !'
                        ];
                    }
            } catch (\Throwable $throwable) {
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getTraceAsString()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }

    #[Route('api/deleteUser/{$id_users}', name: 'app_api_delete_users')]
    public function app_api_delete_users(Request $request,  $id_users): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_users);
                $users = $this->em->getRepository(User::class)->find($uuid);

                if ($users){
                    $this->em->remove($users);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'User supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner un user !'
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
