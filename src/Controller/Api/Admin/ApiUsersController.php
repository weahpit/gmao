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
                            'actif'=>$user->isActive() ? "OUI" : "NON"
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

    #[Route('api/getSingleUser/{$id_user}', name: 'app_api_get_single_users')]
    public function app_api_get_single_users(int $id_user): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
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
                    'groupe'=>$user->getCodeGroupe() ?$user->getCodeGroupe()->getNomGroupe() : "",
                    'poste'=>$user->getCodePoste() ?$user->getCodePoste()->getLibelle() : "",
                    'service'=>$user->getCodeService() ?$user->getCodeService()->getLibelle() : "",
                    'direction'=>$user->getCodeDirection() ?$user->getCodeDirection()->getLibelle() : "",
                    'actif'=>$user->isActive() ? "OUI" : "NON",
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
                $mdp = $request->request->get('mdp');
                $confirm_mdp = $request->request->get('confirm_mdp');
                $photo = $request->files->get('photo');

                $Groupe = $this->em->getRepository(Groupe::class)->find(Uuid::fromString($groupe));
                $Service = $this->em->getRepository(Services::class)->find(Uuid::fromString($service));
                $Poste = $this->em->getRepository(Poste::class)->find(Uuid::fromString($poste));

                    if (!$nom || !$prenoms || !$email || !$mobile || !$Groupe){
                        $reponse = ['code' => 'warning', 'msg' => 'Merci de renseigner toutes les valeurs obligatoires !'];
                    }elseif (($mdp != $confirm_mdp) || $confirm_mdp == "" || $mdp == ""){
                        $reponse = ['code' => 'warning', 'msg' => 'Les mots de passe ne sont pas conformes !'];
                    } else {
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_user);
                        $users = $this->em->getRepository(User::class)->find($uuid);
                        $isNew = false;
                        if (!$users) {
                            $users = new User();
                            $isNew = true;
                        }

                        $users->setMatricule(strtoupper($matricule));
                        $users->setNom(strtoupper($nom));
                        $users->setPrenoms(strtoupper($prenoms));
                        $users->setEmail(strtolower($email));
                        $users->setMobile(strtoupper($mobile));
                        $users->setActive(true);
                        $users->setCodeGroupe($Groupe);

                        if ($Service){$users->setCodeService($Service);}
                        if ($Poste){$users->setCodePoste($Poste);}
                        if ($photo) {
                            $filename = uniqid().'.'.$photo->guessExtension();
                            $photo->move($this->getParameter('photo_users'), $filename);
                            $users->setPhoto($filename);
                        }
                        $users->setPassword(password_hash($mdp, PASSWORD_BCRYPT));

                        if ($admin == "OUI"){ $users->setRoles(['ROLE_USER','ROLE_ADMIN']); } else { $users->setRoles(['ROLE_USER']); }

                        $this->em->persist($users);
                        $this->em->flush();

                        $reponse = [
                            'code' => 'success',
                            'msg' => $isNew ? 'Utilisateur créé avec succès !' : 'Utilisateur  mise à jour avec succès !'
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
