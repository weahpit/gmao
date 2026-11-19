<?php

namespace App\Controller\Rh;

use App\Entity\Rh\Employe;
use App\Entity\Rh\Equipe;
use App\Entity\Rh\Poste;
use App\Entity\Rh\Programmation;
use Doctrine\Persistence\ManagerRegistry;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_USER')]
final class EquipeController extends AbstractController
{

    public function __construct(private PresenceController $presence)
    {
    }

    #[Route('/equipes', name: 'rh_app_equipes')]
    public function index(): Response
    {
        return $this->render('rh/equipe/index.html.twig', [

        ]);
    }

    #[Route('/getEquipes', name: 'rh_get_equipes')]
    public function get_equipes(
        ManagerRegistry $registry
    ): Response
    {
        $reponse = array();

        $equipes = $registry->getRepository(Equipe::class)->findAll();

        foreach ($equipes as $equipe){
            $reponse[] = array(
                'code'=>'SUCCESS',
                'id'=>$equipe->getId(),
                'libelle'=>$equipe->getNomEquipe()
            );
        }
        sort($reponse);
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/AddEquipe/{libelle}', name: 'rh_add_equipe')]
    public function add_equipe(
        ManagerRegistry $registry,
        string $libelle

    ): Response
    {
        $reponse = array();
        $equipe = $registry->getRepository(Equipe::class)->findBy(['nom_equipe'=>$libelle]);

        if ($equipe){
            $reponse[] = array(
                'code'=>'DOUBLON'
            );
        } else {
            $departement = new Equipe();
            $departement->setNomEquipe(strtoupper($libelle));
            $registry->getManager()->persist($departement);
            $registry->getManager()->flush();
            $reponse[] = array(
                'code'=>'SUCCESS'
            );
        }
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/AddUser/{id_user}/{id_equipe}', name: 'rh_add_user')]
    public function add_user(
        ManagerRegistry $registry,
        int $id_user,
        int $id_equipe,

    ): Response
    {
        $reponse = array();
        $employe = $registry->getRepository(Employe::class)->find($id_user);
        $equipe = $registry->getRepository(Equipe::class)->find($id_equipe);

        if ($employe && $equipe){
            $employe->setCodeEquipe($equipe);
            $registry->getManager()->persist($employe);
            $registry->getManager()->flush();
            $reponse[] = array(
                'code'=>'SUCCESS'
            );
        }

        return new JsonResponse(json_encode($reponse));
    }

    #[Route('getUsers/{id_equipe}/{date_jour}', name: 'rh_get_users_equipe')]
    public function get_users_equipe(
        ManagerRegistry $registry,
        int $id_equipe,
        string $date_jour,
    ): Response
    {
        $reponse = array();
        $ma_date = new DateTime($date_jour);
        $equipe = $registry->getRepository(Equipe::class)->find($id_equipe);
        if ($equipe){
            $users = $registry->getRepository(Employe::class)->findBy(['code_equipe'=>$equipe, 'supprimer'=>false, 'demission'=>false]);
            foreach ($users as $user){
                $presence_jour = $this->presence->details_presence_no_json($ma_date->format("Y-m-d"), $user->getId());
                $permission = $this->presence->cherchePermission($user, $ma_date);
                if ($presence_jour) {$present  = true;} else {$present  = false;}
                if ($permission) {$perm  = true;} else {$perm  = false;}
                $reponse[] = array(
                    'concat'=> $user->isChefQuart(). $user->getNomPrenoms(),
                    'chef_quart'=>$user->isChefQuart(),
                    'nom'=>$user->getNomPrenoms(),
                    'code'=>'SUCCESS',
                    'id'=>$user->getId(),
                    'mle'=>$user->getMatricule(),
                    'departement'=>$user->getCodeDepartement()->getLibelle(),
                    'poste'=>$user->getPoste()->getLibelle(),
                    'equipe'=>$equipe->getNomEquipe(),
                    'photo'=>$user->getPhoto(),
                    'present'=>$present,
                    'perm'=>$perm,
                );
            }
            sort($reponse);
        }
        return new JsonResponse(json_encode($reponse));
    }
    #[Route('changeChefQuart/{id_user}', name: 'rh_change_chef_quart')]
    public function changeChefQuart(
        ManagerRegistry $registry,
        int $id_user,
    ): Response
    {
        $reponse = array();
        try {
            $user = $registry->getRepository(Employe::class)->find($id_user);
            if ($user){
                if ($user->getCodeEquipe()){
                    foreach ($user->getCodeEquipe()->getEmployes() as $employe){
                        $employe->setChefQuart(false);
                        $registry->getManager()->persist($employe);
                    }
                    $user->setChefQuart(true);
                    $registry->getManager()->flush();
                    $reponse = array(
                        'msg'=>'Chef de quart mis à jour avec succès !',
                        'code'=>1
                    );
                } else {
                    $reponse = array(
                        'msg'=>'Aucune équipe pour cet employé !',
                        'code'=>2
                    );
                }
            } else {
                $reponse = array(
                    'msg'=>'Merci de sélectionner l\'utilisateur',
                    'code'=>2
                );
            }
        } catch (\Throwable $throwable){
            $reponse = array(
                'msg'=>'Une erreur s\'est produite : ' . $throwable->getMessage(),
                'code'=>0
            );
        }

        return new JsonResponse(json_encode($reponse));
    }
    #[Route('getUsersFromPrg/{id_programme}', name: 'rh_get_users_from_prg')]
    public function get_users_from_prg(
        ManagerRegistry $registry,
        int $id_programme,
    ): Response
    {
        $reponse = array();

        $prg = $registry->getRepository(Programmation::class)->find($id_programme);
        if ($prg->getCodeEquipe()){
            $users = $registry->getRepository(Employe::class)->findBy(['code_equipe'=>$prg->getCodeEquipe(), 'demission'=>false, 'supprimer'=>false]);
            foreach ($users as $user){
                $reponse[] = array(
                    'nom'=>$user->getNomPrenoms(),
                    'code'=>'SUCCESS',
                    'id'=>$user->getId(),
                    'mle'=>$user->getMatricule(),
                    'departement'=>$user->getCodeDepartement()->getLibelle(),
                    'poste'=>$user->getPoste()->getLibelle(),
                    'equipe'=>$prg->getCodeEquipe()->getNomEquipe(),
                    'photo'=>$user->getPhoto()
                );
            }
            sort($reponse);
        }
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/getAgentsQuartsSansEquipe/', name: 'rh_get_agents_quarts_se')]
    public function get_agents_quarts_se(
        ManagerRegistry $registry
    ): Response
    {
        $reponse = array();

        $employes = $registry->getRepository(Employe::class)->findBy(['type_horraire'=>2, 'demission'=>false, 'supprimer'=>false], ['nom_prenoms'=>'ASC']);

        foreach ($employes as $employe){
            if (!$employe->getCodeEquipe()){
                if ($employe->getTypeHorraire() == 1){ $type_horaire = "Normal";} else { $type_horaire = "Quarts";}
                if ($employe->getSexe() == 2){ $sexe = "Homme";} else { $sexe = "Femme";}
                if ($employe->getDateNaissance()){ $datenaiss = $employe->getDateNaissance()->format('d/m/Y');} else { $datenaiss = "-";}
                $reponse[] = array(
                    'nom'=>$employe->getNomPrenoms(),
                    'code'=>'SUCCESS',
                    'id'=>$employe->getId(),
                    'mle'=>$employe->getMatricule(),
                    'datenaiss'=>$datenaiss,
                    'sexe'=>$sexe,
                    'code_zkteco'=>$employe->getCodeZkteco(),
                    'type_horaire'=>$type_horaire,
                    'departement'=>$employe->getCodeDepartement()->getLibelle(),
                    'fonction'=>$employe->getPoste()->getLibelle()
                );
            }

        }
        sort($reponse);
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/removeUserFromEquipe/{id_user}', name: 'rh_remove_user')]
    public function delete_poste(
        ManagerRegistry $registry,
        int $id_user
    ): Response
    {
        $reponse = array();
        $employe = $registry->getRepository(Employe::class)->find($id_user);
        if (!$employe){
            $reponse[] = array(
                'code'=>'ERREUR'
            );
        } else {
            $employe->setCodeEquipe(null);
            $registry->getManager()->persist($employe);
            $registry->getManager()->flush();
            $reponse[] = array(
                'code'=>'SUCCESS'
            );
        }

        return new JsonResponse(json_encode($reponse));
    }
}
