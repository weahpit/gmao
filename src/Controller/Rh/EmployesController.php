<?php

namespace App\Controller\Rh;

use App\Entity\Rh\Absences;
use App\Entity\Rh\Departement;
use App\Entity\Rh\Employe;
use App\Entity\Rh\Equipe;
use App\Entity\Rh\HorairesPersonnalises;
use App\Entity\Rh\Poste;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_USER')]
final class EmployesController extends AbstractController
{
    #[Route('/employes', name: 'rh_app_employes')]
    public function index(): Response
    {
        return $this->render('rh/employes/index.html.twig', [
            'controller_name' => 'EmployesController',
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route('/getEmployes', name: 'rh_get_employes')]
    public function get_employes(
        ManagerRegistry $registry
    ): Response
    {
        $reponse = array();

        $employes = $registry->getRepository(Employe::class)->findBy(['supprimer'=>false, 'demission'=>false]);
        //dd($employes);
        foreach ($employes as $employe){
            $est_absent = false;
            $h_personnaliee = "";
            if ($employe->getTypeHorraire() == 1)
            {
                $type_horaire = "Normal";
            } elseif  ($employe->getTypeHorraire() == 3){
                $type_horaire = "Personnalisé";
                $h_personnaliee  =$employe->getHoraireP()? $employe->getHoraireP()->getLibelle() : "";
            } else {
                $type_horaire = "Quarts";
            }

            if ($employe->getSexe() == 2){ $sexe = "Homme";} else { $sexe = "Femme";}
            if ($employe->getDateNaissance()){ $datenaiss = $employe->getDateNaissance()->format('d/m/Y');} else { $datenaiss = "-";}
            if ($employe->getCodeEquipe()){ $equipe = $employe->getCodeEquipe()->getNomEquipe();} else { $equipe = "";}

            // Recherche si l'utilisateur a une absence en cours
            $absences = $registry->getRepository(Absences::class)->findBy(['code_employe'=>$employe]);
            foreach ($absences as $absence){
                if ($absence->getDateFin() >= new \DateTime()){
                    $est_absent = true;
                }
            }

            $reponse[] = array(
                'nom'=>$employe->getNomPrenoms(),
                'code'=>'SUCCESS',
                'id'=>$employe->getId(),
                'mle'=>$employe->getMatricule(),
                'datenaiss'=>$datenaiss,
                'sexe'=>$sexe,
                'code_zkteco'=>$employe->getCodeZkteco(),
                'type_horaire'=>$type_horaire,
                'departement'=>$employe->getCodeDepartement()? $employe->getCodeDepartement()->getLibelle() :  "-"  ,
                'fonction'=>$employe->getPoste()?  $employe->getPoste()->getLibelle():  "-"  ,
                'soumi'=>$employe->isSoumisAuPointage(),
                'photo'=>$employe->getPhoto(),
                'equipe'=>$equipe,
                'h_personnalise'=>$h_personnaliee,
                'est_absent'=>$est_absent
            );
        }
        sort($reponse);
        return new JsonResponse(json_encode($reponse));
    }
    #[Route('/getEmployes/getByEquipe/{id_equipe}', name: 'rh_get_employes_by_equipe')]
    public function get_employes_by_equipe(
        ManagerRegistry $registry,
        int $id_equipe
    ): Response
    {
        $reponse = array();

        $single_equipe = $registry->getRepository(Equipe::class)->find($id_equipe);
        if ($single_equipe){
            $employes = $registry->getRepository(Employe::class)->findBy(['supprimer'=>false, 'demission'=>false, 'code_equipe'=>$single_equipe]);

            foreach ($employes as $employe){
                $est_absent = false;
                $h_personnaliee = "";
                if ($employe->getTypeHorraire() == 1)
                {
                    $type_horaire = "Normal";
                } elseif  ($employe->getTypeHorraire() == 3){
                    $type_horaire = "Personnalisé";
                    $h_personnaliee  =$employe->getHoraireP()? $employe->getHoraireP()->getLibelle() : "";
                } else {
                    $type_horaire = "Quarts";
                }

                if ($employe->getSexe() == 2){ $sexe = "Homme";} else { $sexe = "Femme";}
                if ($employe->getDateNaissance()){ $datenaiss = $employe->getDateNaissance()->format('d/m/Y');} else { $datenaiss = "-";}
                if ($employe->getCodeEquipe()){ $equipe = $employe->getCodeEquipe()->getNomEquipe();} else { $equipe = "";}

                // Recherche si l'utilisateur a une absence en cours
                $absences = $registry->getRepository(Absences::class)->findBy(['code_employe'=>$employe]);
                foreach ($absences as $absence){
                    if ($absence->getDateFin() >= new \DateTime()){
                        $est_absent = true;
                    }
                }

                $reponse[] = array(
                    'nom'=>$employe->getNomPrenoms(),
                    'code'=>'SUCCESS',
                    'id'=>$employe->getId(),
                    'mle'=>$employe->getMatricule(),
                    'datenaiss'=>$datenaiss,
                    'sexe'=>$sexe,
                    'code_zkteco'=>$employe->getCodeZkteco(),
                    'type_horaire'=>$type_horaire,
                    'departement'=>$employe->getCodeDepartement()? $employe->getCodeDepartement()->getLibelle() :  "-"  ,
                    'fonction'=>$employe->getPoste()?  $employe->getPoste()->getLibelle():  "-"  ,
                    'soumi'=>$employe->isSoumisAuPointage(),
                    'photo'=>$employe->getPhoto(),
                    'equipe'=>$equipe,
                    'h_personnalise'=>$h_personnaliee,
                    'est_absent'=>$est_absent
                );
            }
            sort($reponse);
        }

        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/getEmployes/getByDept/{id_dpt}', name: 'rh_get_employes_by_dept')]
    public function get_employes_by_dept(
        ManagerRegistry $registry,
        int $id_dpt
    ): Response
    {
        $reponse = array();

        $single_dpt = $registry->getRepository(Departement::class)->find($id_dpt);
        if ($single_dpt){
            $employes = $registry->getRepository(Employe::class)->findBy(['supprimer'=>false, 'demission'=>false, 'code_departement'=>$single_dpt]);

            foreach ($employes as $employe){
                $est_absent = false;
                $h_personnaliee = "";
                if ($employe->getTypeHorraire() == 1)
                {
                    $type_horaire = "Normal";
                } elseif  ($employe->getTypeHorraire() == 3){
                    $type_horaire = "Personnalisé";
                    $h_personnaliee  =$employe->getHoraireP()? $employe->getHoraireP()->getLibelle() : "";
                } else {
                    $type_horaire = "Quarts";
                }

                if ($employe->getSexe() == 2){ $sexe = "Homme";} else { $sexe = "Femme";}
                if ($employe->getDateNaissance()){ $datenaiss = $employe->getDateNaissance()->format('d/m/Y');} else { $datenaiss = "-";}
                if ($employe->getCodeEquipe()){ $equipe = $employe->getCodeEquipe()->getNomEquipe();} else { $equipe = "";}

                // Recherche si l'utilisateur a une absence en cours
                $absences = $registry->getRepository(Absences::class)->findBy(['code_employe'=>$employe]);
                foreach ($absences as $absence){
                    if ($absence->getDateFin() >= new \DateTime()){
                        $est_absent = true;
                    }
                }

                $reponse[] = array(
                    'nom'=>$employe->getNomPrenoms(),
                    'code'=>'SUCCESS',
                    'id'=>$employe->getId(),
                    'mle'=>$employe->getMatricule(),
                    'datenaiss'=>$datenaiss,
                    'sexe'=>$sexe,
                    'code_zkteco'=>$employe->getCodeZkteco(),
                    'type_horaire'=>$type_horaire,
                    'departement'=>$employe->getCodeDepartement()? $employe->getCodeDepartement()->getLibelle() :  "-"  ,
                    'fonction'=>$employe->getPoste()?  $employe->getPoste()->getLibelle():  "-"  ,
                    'soumi'=>$employe->isSoumisAuPointage(),
                    'photo'=>$employe->getPhoto(),
                    'equipe'=>$equipe,
                    'h_personnalise'=>$h_personnaliee,
                    'est_absent'=>$est_absent
                );
            }
            sort($reponse);
        }

        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/getEmployes/getByPoste/{id_poste}', name: 'rh_get_employes_by_poste')]
    public function get_employes_by_poste(
        ManagerRegistry $registry,
        int $id_poste
    ): Response
    {
        $reponse = array();

        $single_poste = $registry->getRepository(Poste::class)->find($id_poste);
        if ($single_poste){
            $employes = $registry->getRepository(Employe::class)->findBy(['supprimer'=>false, 'demission'=>false, 'poste'=>$single_poste]);

            foreach ($employes as $employe){
                $est_absent = false;
                $h_personnaliee = "";
                if ($employe->getTypeHorraire() == 1)
                {
                    $type_horaire = "Normal";
                } elseif  ($employe->getTypeHorraire() == 3){
                    $type_horaire = "Personnalisé";
                    $h_personnaliee  =$employe->getHoraireP()? $employe->getHoraireP()->getLibelle() : "";
                } else {
                    $type_horaire = "Quarts";
                }

                if ($employe->getSexe() == 2){ $sexe = "Homme";} else { $sexe = "Femme";}
                if ($employe->getDateNaissance()){ $datenaiss = $employe->getDateNaissance()->format('d/m/Y');} else { $datenaiss = "-";}
                if ($employe->getCodeEquipe()){ $equipe = $employe->getCodeEquipe()->getNomEquipe();} else { $equipe = "";}

                // Recherche si l'utilisateur a une absence en cours
                $absences = $registry->getRepository(Absences::class)->findBy(['code_employe'=>$employe]);
                foreach ($absences as $absence){
                    if ($absence->getDateFin() >= new \DateTime()){
                        $est_absent = true;
                    }
                }

                $reponse[] = array(
                    'nom'=>$employe->getNomPrenoms(),
                    'code'=>'SUCCESS',
                    'id'=>$employe->getId(),
                    'mle'=>$employe->getMatricule(),
                    'datenaiss'=>$datenaiss,
                    'sexe'=>$sexe,
                    'code_zkteco'=>$employe->getCodeZkteco(),
                    'type_horaire'=>$type_horaire,
                    'departement'=>$employe->getCodeDepartement()? $employe->getCodeDepartement()->getLibelle() :  "-"  ,
                    'fonction'=>$employe->getPoste()?  $employe->getPoste()->getLibelle():  "-"  ,
                    'soumi'=>$employe->isSoumisAuPointage(),
                    'photo'=>$employe->getPhoto(),
                    'equipe'=>$equipe,
                    'h_personnalise'=>$h_personnaliee,
                    'est_absent'=>$est_absent
                );
            }
            sort($reponse);
        }

        return new JsonResponse(json_encode($reponse));
    }
    /**
     * @throws \Exception
     */
    #[Route('/getEmployeData/{id_employe}', name: 'rh_get_employe_data')]
    public function get_employe_data(
        ManagerRegistry $registry,
        int $id_employe,
    ): Response
    {
        $reponse = array();

        $employe = $registry->getRepository(Employe::class)->find($id_employe);
        //dd($employe);
        if ($employe){

            if ($employe->getDateNaissance()){ $datenaiss = $employe->getDateNaissance()->format('Y-m-d');} else { $datenaiss = "-";}
            if ($employe->getCodeEquipe()){ $equipe = $employe->getCodeEquipe()->getNomEquipe();} else { $equipe = "";}

            $reponse[] = array(
                'nom'=>$employe->getNomPrenoms(),
                'code'=>'SUCCESS',
                'id'=>$employe->getId(),
                'mle'=>$employe->getMatricule(),
                'datenaiss'=>$datenaiss,
                'sexe'=>$employe->getSexe(),
                'code_zkteco'=>$employe->getCodeZkteco(),
                'type_horaire'=>$employe->getTypeHorraire(),
                'departement'=>$employe->getCodeDepartement()->getId(),
                'poste'=>$employe->getPoste()->getId(),
                'soumi'=>$employe->isSoumisAuPointage(),
                'photo'=>$employe->getPhoto(),
                'equipe'=>$equipe,
                'h_personnalise'=>$employe->getHoraireP() ? $employe->getHoraireP()->getLibelle() : "-"
            );
        }
        sort($reponse);
        return new JsonResponse(json_encode($reponse));
    }


    #[Route('addEmployes/{data}/{id_employe?0}', name: 'rh_add_employe')]
    public function add_employe(
        string $data,
        int $id_employe,
        Request $request,
        ManagerRegistry $registry
    ): Response
    {
        //$page_cp = $pages_cp->find($id_page);
        if($data){
            $arraydata = json_decode($data);

            $poste = $registry->getRepository(Poste::class)->find($arraydata->poste);
            $departement = $registry->getRepository(Departement::class)->find($arraydata->departement);

            // Recherche l'employé
            $employe = $registry->getRepository(Employe::class)->find($id_employe);
            if (!$employe){
                $employe = new Employe();
            }
            //dd($employe);
            $employe->setMatricule($arraydata->mle);
            $employe->setCodeZkteco((int) $arraydata->zkteco);
            $employe->setNomPrenoms(strtoupper($arraydata->nom));
            $employe->setSexe(strtoupper($arraydata->genre));
            if (\DateTime::createFromFormat('Y-m-d', $arraydata->datenaiss)) {$employe->setDateNaissance(\DateTime::createFromFormat('Y-m-d', $arraydata->datenaiss));}
            $employe->setPoste($poste);
            $employe->setCodeDepartement($departement);
            $employe->setTypeHorraire($arraydata->type_horaire);
            if ($arraydata->personnalise){
                // dd($arraydata->personnalise);
                $personnalise = $registry->getRepository(HorairesPersonnalises::class)->find((int)$arraydata->personnalise)->getId();
                if ($personnalise){
                    $employe->setTypeHorraire($personnalise);
                }
            }
            if ($arraydata->soumi == "1") {$employe->setSoumisAuPointage(true);} else {$employe->setSoumisAuPointage(false);}

            // Récupérer le fichier envoyé (par exemple via un champ "photo")
            $file = $request->files->get('photo');
            //dd($file);
            if ($file) {
                // Générer un nom unique
                $fileName = uniqid().'.'.$file->guessExtension();
                $employe->setPhoto($fileName);
                // Déplacer le fichier vers un dossier (ex: public/uploads)
                $file->move(
                    $this->getParameter('users_directory'),
                    $fileName
                );
            }
            // return new Response('Fichier uploadé : '.$fileName);
            $employe->setDemission(false);
            $employe->setSupprimer(false);

            $registry->getManager()->persist($employe);
            $registry->getManager()->flush();

            return  new JsonResponse([
                'code'=>'SUCCESS'
            ]);
        } else {
            return  new JsonResponse([
                'code'=>'ERROR'
            ]);
        }

    }

    #[Route('rmEmploye/{id_employe}', name: 'rh_rm_employe')]
    public function rm_employe(
        int $id_employe,
        Request $request,
        ManagerRegistry $registry
    ): Response
    {
        $result = array();
        $employe = $registry->getRepository(Employe::class)->find($id_employe);

        if($employe){
            $employe->setSupprimer(true);
            $registry->getManager()->persist($employe);
            $registry->getManager()->flush();
            $result = array('code'=>'SUCCESS');

        } else {
            $result = array('code'=>'ERROR');
        }
        return  new JsonResponse(json_encode($result));
    }
}
