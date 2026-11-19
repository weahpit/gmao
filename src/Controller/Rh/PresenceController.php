<?php

namespace App\Controller\Rh;

use App\Entity\Rh\Absences;
use App\Entity\Rh\Employe;
use App\Entity\Rh\Equipe;
use App\Entity\Rh\Feries;
use App\Entity\Rh\Historique;
use App\Entity\Rh\Horaires;
use App\Entity\Rh\HorairesPersonnalises;
use App\Entity\Rh\HorairesQuarts;
use App\Entity\Rh\Options;
use App\Entity\Rh\Programmation;
use App\Service\Rh\PointageService;
use DateInterval;
use DatePeriod;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_USER')]
final class PresenceController extends AbstractController
{
    public function __construct(private ManagerRegistry $registry,  private PointageService $pointage)
    {
    }

    #[Route('/presences', name: 'rh_app_presences')]
    public function index(PointageService $pointage): Response
    {
        $donnees = $pointage->getData('SELECT BadgeNumber, Name, Gender FROM USERINFO;');
        return $this->render('rh/presence/index.html.twig', [
            'donnees' => $donnees,
            'pointage_erreur' => $pointage->estDisponible() ? null : $pointage->derniereErreur(),
        ]);
    }
    #[Route('/presencesJournalières', name: 'rh_presences_journalieres')]
    public function presences_journalieres(PointageService $pointage): Response
    {
        return $this->render('rh/presence/suivi_journalier.html.twig', [
            'pointage_erreur' => $pointage->estDisponible() ? null : $pointage->derniereErreur(),
        ]);
    }

    #[Route('/presencesParPeriode/{date1}/{date2}', name: 'rh_presence_periode')]
    public function presence_periode(
        PointageService $pointage,
        string $date1,
        string $date2,
        ManagerRegistry $registry
    ): Response
    {
            $infos = array();
            // Recupérer le seuil de tolérance des minutes
                $options = $registry->getRepository(Options::class)->find(1);
                if ($options){ $tolerance = $options->getTolerance(); } else {$tolerance = 0;}
            // Recherche de l'agent
                $employes = $registry->getRepository(Employe::class)->findBy([ 'demission'=>false, 'supprimer'=>false], ['nom_prenoms'=>'ASC']);

            foreach ($employes as $employe){
                // Recherche si à cette date l'employé était demissionnaire
                if ($employe->getCodeZkteco() > 0 && !$employe->isDemission()){

                $absences = 0;
                $total_minutes = 0;
                // Vérifie qu'il est soumi au pointage
                if ($employe->isSoumisAuPointage()){

                    // Recupere le type d'horraire (QUARTS ou NORMAL)
                    $typeHorraire = $employe->getTypeHorraire();

                    // si $typeHorraire = 1 Alors l'employé est aux heures normales sinon au Quarts
                    if ($typeHorraire == 1){

                        // Jours ouvrables
                        $j_ouvrables = ['Monday', 'Tuesday', "Wednesday", "Thursday", "Friday"];

                        // Jours fériés
                        $liste_jf = array();
                        $jfs = $registry->getRepository(Feries::class)->findAll();
                        foreach ($jfs as $jf){
                            $liste_jf[] =$jf->getDateFerie()->format('Y-m-d');
                        }

                        // Recupère l'heure d'entrée normale (Ex: 7h30)
                        $heureentree = $registry->getRepository(Horaires::class)->find(1)->getHeure1();

                        // Déclaration de la période
                        $dateStart = new DateTime($date1);
                        $dateEnd = new DateTime($date2);

                        // Ajouter 1 jour à la date de fin pour qu'elle soit incluse dans la boucle
                           $dateEnd->modify('+1 day');

                        // Créer l'intervalle (1 jour)
                           $interval = new DateInterval('P1D');

                        // Créer la période
                            $period = new DatePeriod($dateStart, $interval, $dateEnd);
                            $datet = "";
                            $minutes = array();

                            foreach ($period as $date) {
                                $jour = strftime("%A", $date->getTimestamp());

                                // L'employé a t-il une permission ?
                                // Recherche les permissions payée par CITRAC pour l'employé
                                $permissions = $registry->getRepository(Absences::class)->findBy(['code_employe'=>$employe, 'paye'=>'OUI']);
                                $a_une_permission = false;
                                foreach ($permissions as $permission){
                                    if ($date >= $permission->getDateDebut() && $date <= $permission->getDateFin()){
                                        $a_une_permission = true;
                                    }
                                }


                                // Recupère uniquement les données de jours ouvrables
                                if (in_array($jour, $j_ouvrables))
                                {
                                    // Elimine les jours feries
                                    if (!in_array($date->format('Y-m-d'), $liste_jf)) {

                                        // Elimine les permisions payées
                                        if (!$a_une_permission){

                                            $datet = $datet . " " . $date->format("Y-m-d") . "\n";
                                        $entree = $date->format('Y-m-d') . " " . $heureentree->format('H:i:s');
                                        $donnees = $pointage->getData("SELECT * FROM Req_UserCheck WHERE  Req_UserCheck.CHECKTIME_DAY =  '" . $date->format('Y-m-d') . "' and  Badgenumber = '" . $employe->getCodeZkteco() . "' AND  Req_UserCheck.SENSORID =  '1';"); // Remplace par le nom réel

                                        if ($donnees) {

                                            $heure1 = strtotime($heureentree->format('H:i:s'));
                                            $heure2 = strtotime($donnees[0]['CHECKTIME_TIME']);

                                            $difference = $heure2 - $heure1; // en secondes
                                            $minute = $difference / 60;
                                            if ($minute > $tolerance) {
                                                $total_minutes = $total_minutes + $minute;
                                            }

                                            $minutes[] = array(
                                                'minutes' => $minute,
                                                'heure1' => $heureentree->format('H:i:s'),
                                                'heure2' => $donnees[0]['CHECKTIME_TIME'],
                                                'date' => $jour . " " . $date->format('d/m/Y')
                                            );
                                        } else {
                                            $absences = $absences + 1;
                                            $minutes[] = array(
                                                'minutes' => 'ABSENT',
                                                'date' => $jour . " " . $date->format('d/m/Y')
                                            );
                                        }
                                    }
                                }
                               }

                            }

                    } else {

                        // Déclaration de la période
                        $dateStart = new DateTime($date1);
                        $dateEnd = new DateTime($date2);

                        // Ajouter 1 jour à la date de fin pour qu'elle soit incluse dans la boucle
                        $dateEnd->modify('+1 day');

                        // Créer l'intervalle (1 jour)
                        $interval = new DateInterval('P1D');

                        // Créer la période
                        $period = new DatePeriod($dateStart, $interval, $dateEnd);
                        $datet = "";
                        $minutes = array();

                        foreach ($period as $date) {
                            $jour = strftime("%A", $date->getTimestamp());

                            // Recherche l'équipe e le quart de l'employé
                            $equipe = $employe->getCodeEquipe();

                            if ($equipe){
                                // Recherche le programme de quart à cette date
                                $prg = $registry->getRepository(Programmation::class)->findBy(
                                    ['code_equipe'=>$equipe], ['date_programme'=>'ASC']
                                );
                                // Recherche de la date sélectionnée dans le programme
                                $estProgramme = false;
                                $heureentree = "";
                                foreach($prg as $p){
                                    if ($p->getDateProgramme()->format('Y-m-d') == $date->format('Y-m-d')){
                                        $estProgramme = true;
                                        // Recupère l'heure d'entrée normale suivant le quart (Ex: 7h30)
                                        $heureentree = $p->getCodeQuart()->getHeure1();
                                    }
                                }

                                if($estProgramme){
                                    if ($heureentree){

                                        // L'employé a t-il une permission ?
                                        // Recherche les permissions payée par CITRAC pour l'employé
                                        $permissions = $registry->getRepository(Absences::class)->findBy(['code_employe'=>$employe, 'paye'=>'OUI']);
                                        $a_une_permission = false;
                                        foreach ($permissions as $permission){
                                            if ($date >= $permission->getDateDebut() && $date <= $permission->getDateFin()){
                                                $a_une_permission = true;
                                            }
                                        }


                                        // Elimine les permisions payées
                                        if (!$a_une_permission){

                                            $datet = $datet . " " . $date->format("Y-m-d") . "\n";
                                            $entree = $date->format('Y-m-d') . " " . $heureentree->format('H:i:s');
                                            $donnees = $pointage->getData("SELECT * FROM Req_UserCheck WHERE  Req_UserCheck.CHECKTIME_DAY =  '" . $date->format('Y-m-d') . "' and  Badgenumber = '" . $employe->getCodeZkteco() . "' AND  Req_UserCheck.SENSORID =  '1';"); // Remplace par le nom réel

                                            if ($donnees) {

                                                $heure1 = strtotime($heureentree->format('H:i:s'));
                                                $heure2 = strtotime($donnees[0]['CHECKTIME_TIME']);

                                                $difference = $heure2 - $heure1; // en secondes
                                                $minute = $difference / 60;
                                                if ($minute > $tolerance) {
                                                    $total_minutes = $total_minutes + $minute;
                                                }

                                                $minutes[] = array(
                                                    'minutes' => $minute,
                                                    'heure1' => $heureentree->format('H:i:s'),
                                                    'heure2' => $donnees[0]['CHECKTIME_TIME'],
                                                    'date' => $jour . " " . $date->format('d/m/Y')
                                                );
                                            } else {
                                                $absences = $absences + 1;
                                                $minutes[] = array(
                                                    'minutes' => 'ABSENT',
                                                    'date' => $jour . " " . $date->format('d/m/Y')
                                                );
                                            }
                                        }
                                    }
                                    }
                                    }
                                }

                            }
                        }

                    if($total_minutes > 0 or $absences > 0){
                       if ($employe->getTypeHorraire() == 1 ){$typeH = "NORMAL";} else {$typeH = "QUARTS";}
                        $infos[] = array(
                            'nom'=>$employe->getNomPrenoms(),
                            'mle'=>$employe->getMatricule(),
                            'type_horaires'=>$typeH,
                            'minutes'=>round($total_minutes / 60, 2),
                            'absences'=>$absences
                        );
                    }

            }

            }

       return new JsonResponse(json_encode($infos));
    }
    #[Route('/RechercheAbsencesParPeriode/{date1}/{date2}', name: 'rh_absences_periode')]
    public function absences_periode(
        PointageService $pointage,
        string $date1,
        string $date2,
        ManagerRegistry $registry
    ): Response
    {
        $infos = array();
        // Recupérer le seuil de tolérance des minutes
        $options = $registry->getRepository(Options::class)->find(1);
        if ($options){ $tolerance = $options->getTolerance(); } else {$tolerance = 0;}
        // Recherche de l'agent
        $employes = $registry->getRepository(Employe::class)->findBy(['supprimer'=>false], ['nom_prenoms'=>'ASC']);

        foreach ($employes as $employe) {

           // Déclaration de la période
            $dateStart = new DateTime($date1);
            $dateEnd = new DateTime($date2);

            // Ajouter 1 jour à la date de fin pour qu'elle soit incluse dans la boucle
            $dateEnd->modify('+1 day');

            // Créer l'intervalle (1 jour)
            $interval = new DateInterval('P1D');

            // Créer la période
            $period = new DatePeriod($dateStart, $interval, $dateEnd);
            $datet = "";
            $minutes = array();
            $absences = 0;
            $total_minutes = 0;
            $datet = "";
            foreach ($period as $date) {

            $soumi = $this->chercheSoumis($employe, $date);
            $demission = $this->chercheDemission($employe, $date);
            $permission = $this->cherchePermission($employe, $date);

            if (!$permission) {
                if (!$demission) {
                    if ($soumi) {
                        $heure_entree = "-";
                        // Recupere le type d'horraire (QUARTS ou NORMAL)
                        // $typeHorraire = $employe->getTypeHorraire();
                        $typeHorraire = $this->chercheTypeHoraire($employe, new DateTime($date1));

                        // si $typeHorraire = 1 Alors l'employé est aux heures normales sinon au Quarts
                        if ($typeHorraire == 1) {
                            // Recupère l'heure d'entrée normale (Ex: 7h30)
                            $heureentree = $registry->getRepository(Horaires::class)->find(1)->getHeure1();

                            // Déclaration de la période
                            $dateStart = $date;
                            $jour = strftime("%A", $dateStart->getTimestamp());

                            $datet = $datet . " " . $dateStart->format("Y-m-d") . "\n";
                            $entree = $dateStart->format('Y-m-d') . " " . $heureentree->format('H:i:s');
                            $donnees = $pointage->getData("SELECT * FROM Req_UserCheck WHERE  Req_UserCheck.CHECKTIME_DAY =  '" . $dateStart->format('Y-m-d') . "' and  Badgenumber = '" . $employe->getCodeZkteco() . "' AND  Req_UserCheck.SENSORID =  '1';"); // Remplace par le nom réel

                            if ($donnees) {

                                $heure1 = strtotime($heureentree->format('H:i:s'));
                                $heure2 = strtotime($donnees[0]['CHECKTIME_TIME']);


                                $difference = $heure1 - $heure2; // en secondes
                                $minute = $difference / 60;

                                $total_minutes = $total_minutes + $minute;


                                $minutes[] = array(
                                    'minutes' => $minute,
                                    'heure1' => $heureentree->format('H:i:s'),
                                    'heure2' => $donnees[0]['CHECKTIME_TIME'],
                                    'date' => $jour . " " . $dateStart->format('d/m/Y')
                                );
                                $heure_entree = $donnees[0]['CHECKTIME_TIME'];

                            } else {
                                $absences = $absences + 1;
                                $minutes[] = array(
                                    'minutes' => 'ABSENT',
                                    'date' => $jour . " " . $dateStart->format('d/m/Y')
                                );

                            }
                        } elseif ($typeHorraire == 3) { // Heures personnalisée


                            // Recupère l'heure d'entrée personnalisée
                            $heureentree = $employe->getHoraireP()->getHeure1();

                            // Déclaration de la période
                            $dateStart = $date;

                            $jour = strftime("%A", $dateStart->getTimestamp());
                            $datet = $datet . " " . $dateStart->format("Y-m-d") . "\n";
                            $entree = $dateStart->format('Y-m-d') . " " . $heureentree->format('H:i:s');
                            $donnees = $pointage->getData("SELECT * FROM Req_UserCheck WHERE  Req_UserCheck.CHECKTIME_DAY =  '" . $dateStart->format('Y-m-d') . "' and  Badgenumber = '" . $employe->getCodeZkteco() . "' AND  Req_UserCheck.SENSORID =  '1';"); // Remplace par le nom réel
                            //dd($donnees);
                            if ($donnees) {

                                $heure1 = strtotime($heureentree->format('H:i:s'));
                                $heure2 = strtotime($donnees[0]['CHECKTIME_TIME']);


                                $difference = $heure1 - $heure2; // en secondes
                                $minute = $difference / 60;
                                //if ($minute > $tolerance) {
                                $total_minutes = $total_minutes + $minute;
                                //}

                                $minutes[] = array(
                                    'minutes' => $minute,
                                    'heure1' => $heureentree->format('H:i:s'),
                                    'heure2' => $donnees[0]['CHECKTIME_TIME'],
                                    'date' => $jour . " " . $dateStart->format('d/m/Y')
                                );
                                $heure_entree = $donnees[0]['CHECKTIME_TIME'];

                            } else {
                                $absences = $absences + 1;
                                $minutes[] = array(
                                    'minutes' => 'ABSENT',
                                    'date' => $jour . " " . $dateStart->format('d/m/Y')
                                );

                            }
                        } else {

                            // Déclaration de la période
                            $dateStart = $date;
                            $jour = strftime("%A", $dateStart->getTimestamp());

                            // Recherche l'équipe e le quart de l'employé
                            // $equipe = $employe->getCodeEquipe();
                            $equipeInt = $this->chercheEquipe($employe, $date);
                            if ($equipeInt) {
                                $equipe = $this->registry->getRepository(Equipe::class)->find($equipeInt);
                                if ($equipe) {
                                    // Recherche le programme de quart à cette date
                                    $prg = $registry->getRepository(Programmation::class)->findBy(
                                        ['code_equipe' => $equipe], ['date_programme' => 'ASC']
                                    );
                                    // Recherche de la date sélectionnée dans le programme
                                    $estProgramme = false;
                                    $heureentree = "";
                                    foreach ($prg as $p) {
                                        if ($p->getDateProgramme()->format('Y-m-d') == $dateStart->format('Y-m-d')) {
                                            $estProgramme = true;
                                            // Recupère l'heure d'entrée normale suivant le quart (Ex: 7h30)
                                            $heureentree = $p->getCodeQuart()->getHeure1();
                                        }
                                    }

                                    if ($estProgramme) {
                                        if ($heureentree) {

                                            $datet = $datet . " " . $dateStart->format("Y-m-d") . "\n";
                                            $entree = $dateStart->format('Y-m-d') . " " . $heureentree->format('H:i:s');
                                            $donnees = $pointage->getData("SELECT * FROM Req_UserCheck WHERE  Req_UserCheck.CHECKTIME_DAY =  '" . $dateStart->format('Y-m-d') . "' and  Badgenumber = '" . $employe->getCodeZkteco() . "' AND  Req_UserCheck.SENSORID =  '1';"); // Remplace par le nom réel

                                            if ($donnees) {

                                                $heure1 = strtotime($heureentree->format('H:i:s'));
                                                $heure2 = strtotime($donnees[0]['CHECKTIME_TIME']);

                                                $difference = $heure1 - $heure2; // en secondes
                                                $minute = $difference / 60;
                                                //if ($minute > $tolerance) {
                                                $total_minutes = $total_minutes + $minute;
                                                //}

                                                $minutes[] = array(
                                                    'minutes' => $minute,
                                                    'heure1' => $heureentree->format('H:i:s'),
                                                    'heure2' => $donnees[0]['CHECKTIME_TIME'],
                                                    'date' => $jour . " " . $dateStart->format('d/m/Y')
                                                );
                                                $heure_entree = $donnees[0]['CHECKTIME_TIME'];
                                            } else {
                                                $absences = $absences + 1;
                                                $minutes[] = array(
                                                    'minutes' => 'ABSENT',
                                                    'date' => $jour . " " . $dateStart->format('d/m/Y')
                                                );
                                            }
                                        }
                                    }
                                }
                            }


                        }

                        if ($employe->getTypeHorraire() == 1) {
                            $typeH = "NORMAL";
                        } elseif ($employe->getTypeHorraire() == 3) {
                            $typeH = "PERSONNALISE";
                        } else {
                            $typeH = "QUARTS";
                        }
                        if ($employe->getCodeEquipe()) {
                            $Nomequipe = $equipe->getNomEquipe();
                        } else {
                            $Nomequipe = "";
                        }
                        if ($employe->getPoste()) {
                            $poste = $employe->getPoste()->getLibelle();
                        } else {
                            $poste = "";
                        }
                        if ($heureentree) {
                            $he = $heureentree->format('H:i:s');
                        } else {
                            $he = "";
                        }
                        $infos[] = array(
                            'heure_entree' => $heure_entree,
                            'heure1' => $he,
                            'nom' => $employe->getNomPrenoms(),
                            'mle' => $employe->getMatricule(),
                            'photo' => $employe->getPhoto(),
                            'type_horaires' => $typeH,
                            'absences' => $absences,
                            'minutes' => round($total_minutes, 0),
                            'equipe' => $Nomequipe,
                            'poste' => $poste,
                            'tolerance' => abs($tolerance),
                            'h_personnalise' => $employe->getHoraireP() ? $employe->getHoraireP()->getLibelle() : ""
                        );

                    }
                }
            }
        }
        }


        rsort($infos);
        return new JsonResponse(json_encode($infos));
    }

    #[Route('/presencesJour/{date1}', name: 'rh_presence_jour')]
    public function presence_jour(
        PointageService $pointage,
        string $date1,
        ManagerRegistry $registry
    ): Response
    {
        $infos = array();
        // Recupérer le seuil de tolérance des minutes
        $options = $registry->getRepository(Options::class)->find(1);
        if ($options){ $tolerance = $options->getTolerance(); } else {$tolerance = 0;}
        // Recherche de l'agent
        $employes = $registry->getRepository(Employe::class)->findBy(['supprimer'=>false], ['nom_prenoms'=>'ASC']);

        foreach ($employes as $employe){

                $absences = 0;
                $total_minutes = 0;
                $datet = "";
                $minutes = array();
                $soumi = $this->chercheSoumis($employe, new DateTime($date1));
                $demission = $this->chercheDemission($employe, new DateTime($date1));
                $permission = $this->cherchePermission($employe, new DateTime($date1));

                if (!$permission){
                  if (!$demission){
                    if ($soumi){
                        $heure_entree = "-";
                        // Recupere le type d'horraire (QUARTS ou NORMAL)
                        // $typeHorraire = $employe->getTypeHorraire();
                        $typeHorraire= $this->chercheTypeHoraire($employe, new DateTime($date1));

                        // si $typeHorraire = 1 Alors l'employé est aux heures normales sinon au Quarts
                        if ($typeHorraire == 1){
                            // Recupère l'heure d'entrée normale (Ex: 7h30)
                            $heureentree = $registry->getRepository(Horaires::class)->find(1)->getHeure1();

                            // Déclaration de la période
                            $dateStart = new DateTime($date1);




                            $jour = strftime("%A", $dateStart->getTimestamp());

                            $datet = $datet . " " . $dateStart->format("Y-m-d") . "\n";
                            $entree = $dateStart->format('Y-m-d') . " " . $heureentree->format('H:i:s');
                            $donnees = $pointage->getData("SELECT * FROM Req_UserCheck WHERE  Req_UserCheck.CHECKTIME_DAY =  '" . $dateStart->format('Y-m-d') . "' and  Badgenumber = '" . $employe->getCodeZkteco() . "' AND  Req_UserCheck.SENSORID =  '1';"); // Remplace par le nom réel
                            //dd($donnees);
                            if ($donnees) {

                                $heure1 = strtotime($heureentree->format('H:i:s'));
                                $heure2 = strtotime($donnees[0]['CHECKTIME_TIME']);


                                $difference = $heure1 - $heure2; // en secondes
                                $minute = $difference / 60;
                                //if ($minute > $tolerance) {
                                $total_minutes = $total_minutes + $minute;
                                //}

                                $minutes[] = array(
                                    'minutes' => $minute,
                                    'heure1' => $heureentree->format('H:i:s'),
                                    'heure2' => $donnees[0]['CHECKTIME_TIME'],
                                    'date' => $jour . " " . $dateStart->format('d/m/Y')
                                );
                                $heure_entree = $donnees[0]['CHECKTIME_TIME'];

                            } else {
                                $absences = $absences + 1;
                                $minutes[] = array(
                                    'minutes' => 'ABSENT',
                                    'date' => $jour . " " . $dateStart->format('d/m/Y')
                                );

                            }
                        }elseif ($typeHorraire == 3){ // Heures personnalisée


                            // Recupère l'heure d'entrée personnalisée
                            $heureentree = $employe->getHoraireP()->getHeure1();

                            // Déclaration de la période
                            $dateStart = new DateTime($date1);

                            $jour = strftime("%A", $dateStart->getTimestamp());

                            $datet = $datet . " " . $dateStart->format("Y-m-d") . "\n";
                            $entree = $dateStart->format('Y-m-d') . " " . $heureentree->format('H:i:s');
                            $donnees = $pointage->getData("SELECT * FROM Req_UserCheck WHERE  Req_UserCheck.CHECKTIME_DAY =  '" . $dateStart->format('Y-m-d') . "' and  Badgenumber = '" . $employe->getCodeZkteco() . "' AND  Req_UserCheck.SENSORID =  '1';"); // Remplace par le nom réel
                            //dd($donnees);
                            if ($donnees) {

                                $heure1 = strtotime($heureentree->format('H:i:s'));
                                $heure2 = strtotime($donnees[0]['CHECKTIME_TIME']);


                                $difference = $heure1 - $heure2; // en secondes
                                $minute = $difference / 60;
                                //if ($minute > $tolerance) {
                                $total_minutes = $total_minutes + $minute;
                                //}

                                $minutes[] = array(
                                    'minutes' => $minute,
                                    'heure1' => $heureentree->format('H:i:s'),
                                    'heure2' => $donnees[0]['CHECKTIME_TIME'],
                                    'date' => $jour . " " . $dateStart->format('d/m/Y')
                                );
                                $heure_entree = $donnees[0]['CHECKTIME_TIME'];

                            } else {
                                $absences = $absences + 1;
                                $minutes[] = array(
                                    'minutes' => 'ABSENT',
                                    'date' => $jour . " " . $dateStart->format('d/m/Y')
                                );

                            }
                        } else {

                            // Déclaration de la période
                            $dateStart = new DateTime($date1);

                            $jour = strftime("%A", $dateStart->getTimestamp());

                            // Recherche l'équipe e le quart de l'employé
                            // $equipe = $employe->getCodeEquipe();
                            $equipeInt = $this->chercheEquipe($employe, new DateTime($date1));
                            if ($equipeInt){
                                $equipe = $this->registry->getRepository(Equipe::class)->find($equipeInt);
                                if ($equipe){
                                    // Recherche le programme de quart à cette date
                                    $prg = $registry->getRepository(Programmation::class)->findBy(
                                        ['code_equipe'=>$equipe], ['date_programme'=>'ASC']
                                    );
                                    // Recherche de la date sélectionnée dans le programme
                                    $estProgramme = false;
                                    $heureentree = "";
                                    foreach($prg as $p){
                                        if ($p->getDateProgramme()->format('Y-m-d') == $dateStart->format('Y-m-d')){
                                            $estProgramme = true;
                                            // Recupère l'heure d'entrée normale suivant le quart (Ex: 7h30)
                                            $heureentree = $p->getCodeQuart()->getHeure1();
                                        }
                                    }

                                    if($estProgramme){
                                        if ($heureentree){

                                            $datet = $datet . " " . $dateStart->format("Y-m-d") . "\n";
                                            $entree = $dateStart->format('Y-m-d') . " " . $heureentree->format('H:i:s');
                                            $donnees = $pointage->getData("SELECT * FROM Req_UserCheck WHERE  Req_UserCheck.CHECKTIME_DAY =  '" . $dateStart->format('Y-m-d') . "' and  Badgenumber = '" . $employe->getCodeZkteco() . "' AND  Req_UserCheck.SENSORID =  '1';"); // Remplace par le nom réel

                                            if ($donnees) {

                                                $heure1 = strtotime($heureentree->format('H:i:s'));
                                                $heure2 = strtotime($donnees[0]['CHECKTIME_TIME']);

                                                $difference = $heure1 - $heure2; // en secondes
                                                $minute = $difference / 60;
                                                //if ($minute > $tolerance) {
                                                $total_minutes = $total_minutes + $minute;
                                                //}

                                                $minutes[] = array(
                                                    'minutes' => $minute,
                                                    'heure1' => $heureentree->format('H:i:s'),
                                                    'heure2' => $donnees[0]['CHECKTIME_TIME'],
                                                    'date' => $jour . " " . $dateStart->format('d/m/Y')
                                                );
                                                $heure_entree = $donnees[0]['CHECKTIME_TIME'];
                                            } else {
                                                $absences = $absences + 1;
                                                $minutes[] = array(
                                                    'minutes' => 'ABSENT',
                                                    'date' => $jour . " " . $dateStart->format('d/m/Y')
                                                );
                                            }
                                        }
                                    }
                                }
                            }


                        }

                        if ($employe->getTypeHorraire() == 1 ){$typeH = "NORMAL";}elseif ($employe->getTypeHorraire() == 3 ){$typeH = "PERSONNALISE";} else {$typeH = "QUARTS";}
                        if ($employe->getCodeEquipe()){ $Nomequipe = $equipe->getNomEquipe();} else {$Nomequipe = "";}
                        if ($employe->getPoste()){$poste = $employe->getPoste()->getLibelle();} else {$poste = "";}
                        if ($heureentree){$he = $heureentree->format('H:i:s'); } else {$he = "";}
                        $infos[] = array(
                            'heure_entree'=>$heure_entree,
                            'heure1'=>$he,
                            'nom'=>$employe->getNomPrenoms(),
                            'id'=>$employe->getId(),
                            'mle'=>$employe->getMatricule(),
                            'photo'=>$employe->getPhoto(),
                            'type_horaires'=>$typeH,
                            'absences'=>$absences,
                            'minutes'=>round($total_minutes, 0),
                            'equipe'=>$Nomequipe,
                            'poste'=>$poste,
                            'tolerance'=>abs($tolerance),
                            'h_personnalise'=>$employe->getHoraireP() ? $employe->getHoraireP()->getLibelle() : ""
                        );

                    }
                }
             }

        }
        rsort($infos);
        return new JsonResponse(json_encode($infos));
    }

    #[Route('/detailsPresence/{date1}/{id_employe}', name: 'rh_details_presence')]
    public function details_presence(
        string $date1,
        int $id_employe
    ): Response
    {
        $infos = array();
        // Recupérer le seuil de tolérance des minutes

        $employe = $this->registry->getRepository(Employe::class)->find($id_employe);
        if ($employe){
            $dateStart = new DateTime($date1);
            $jour = strftime("%A", $dateStart->getTimestamp());
            $donnees = $this->odbc_cnn->getData("SELECT * FROM Req_UserCheck WHERE  Req_UserCheck.CHECKTIME_DAY =  '" . $dateStart->format('Y-m-d') . "' and  Badgenumber = '" . $employe->getCodeZkteco() . "';"); // Remplace par le nom réel

            foreach ($donnees as $donnee){
                $infos[] = array(
                    'NAME'=>$employe->getNomPrenoms(),
                    'CHECKTIME_DAY'=>$donnee['CHECKTIME_DAY'],
                    'CHECKTIME_TIME'=>$donnee['CHECKTIME_TIME'],
                    'CHECKTYPE'=>$donnee['CHECKTYPE'],
                );
            }
        }

        rsort($infos);
        return new JsonResponse(json_encode($infos));
    }
    #[Route('/detailsPresence_no_json/{date1}/{id_employe}', name: 'rh_details_presence_no_json')]
    public function details_presence_no_json(
        string $date1,
        int $id_employe
    ): array
    {
        $infos = array();
        // Recupérer le seuil de tolérance des minutes

        $employe = $this->registry->getRepository(Employe::class)->find($id_employe);
        if ($employe){
            $dateStart = new DateTime($date1);
            $jour = strftime("%A", $dateStart->getTimestamp());
            $donnees = $this->odbc_cnn->getData("SELECT * FROM Req_UserCheck WHERE  Req_UserCheck.CHECKTIME_DAY =  '" . $dateStart->format('Y-m-d') . "' and  Badgenumber = '" . $employe->getCodeZkteco() . "' AND  Req_UserCheck.SENSORID =  '1';"); // Remplace par le nom réel

            foreach ($donnees as $donnee){
                $infos[] = array(
                    'NAME'=>$employe->getNomPrenoms(),
                    'CHECKTIME_DAY'=>$donnee['CHECKTIME_DAY'],
                    'CHECKTIME_TIME'=>$donnee['CHECKTIME_TIME'],
                    'CHECKTYPE'=>$donnee['CHECKTYPE'],
                );
            }
        }
        return  $infos;
    }

    function chercheSoumis(Employe $employe, DateTime $datepointage) : bool
    {
        $soumi = $employe->isSoumisAuPointage();
        // Vérifie qu'il est soumi au pointage à la date indiquée ou après
        // Recherche le dernier changement justre avant la date indiquée

            $historiqueSoumis = $this->registry->getRepository(Historique::class)->findBy(['type_operation'=>'Changement Type Etat', 'code_employe'=>$employe],['date_operation'=>'DESC']);
            foreach ($historiqueSoumis as $hsoumis){
                if ($datepointage->format('Y-m-d') >= $hsoumis->getDateOperation()->format('Y-m-d') ){
                      if ($hsoumis->getValeur() == "1")  {
                          return  true;
                      } else {
                          return false;
                      }
                }
            }
        return $soumi;
    }

    function chercheTypeHoraire(Employe $employe, DateTime $datepointage) : int
    {
        $typeHoraire = $employe->getTypeHorraire();
        // Vérifie qu'il est soumi au pointage à la date indiquée ou après
        // Recherche le dernier changement justre avant la date indiquée

            $historiqueTypeH = $this->registry->getRepository(Historique::class)->findBy(['type_operation'=>'Changement Type Horaire', 'code_employe'=>$employe],['date_operation'=>'DESC']);
            foreach ($historiqueTypeH as $typeH){
                if ($datepointage->format('Y-m-d') >= $typeH->getDateOperation()->format('Y-m-d') ){
                    $typeHoraire =(int) $typeH->getValeur();
                    return  $typeHoraire;
                }
            }
        return $typeHoraire;
    }

    function chercheEquipe(Employe $employe, DateTime $datepointage) : int
    {
        $equipe = $employe->getCodeEquipe() ? $employe->getCodeEquipe()->getId() : 0;
        if ($employe->getTypeHorraire() == 2 ){
                $historiqueEquipe = $this->registry->getRepository(Historique::class)->findBy(['type_operation'=>'Changement Equipe', 'code_employe'=>$employe],['date_operation'=>'DESC']);
                foreach ($historiqueEquipe as $eqp){
                    if ($datepointage->format('Y-m-d') >= $eqp->getDateOperation()->format('Y-m-d') ){
                        $equipe = $this->registry->getRepository(Equipe::class)->find((int) $eqp->getValeur());
                        return  $equipe->getId();
                    }
                }
        }
     return $equipe;
    }

    function chercheDemission(Employe $employe, DateTime $datepointage) : bool
    {
        $demission = $employe->isDemission();
        // Vérifie qu'il est soumi au pointage à la date indiquée ou après
        // Recherche le dernier changement justre avant la date indiquée

        $historiqueDemission = $this->registry->getRepository(Historique::class)->findBy(['type_operation'=>'DEMISSION', 'code_employe'=>$employe],['date_operation'=>'DESC']);
        foreach ($historiqueDemission as $dem){
            if ($datepointage->format('Y-m-d') >= $dem->getDateOperation()->format('Y-m-d') ){
                if ($dem->getValeur() == "1")  {
                    return  true;
                } else {
                    return false;
                }
            }
        }
        return $demission;
    }

    function cherchePermission(Employe $employe, DateTime $datepointage) : bool
    {
        $historiquePermissions = $this->registry->getRepository(Absences::class)->findBy(['code_employe'=>$employe]);
        foreach ($historiquePermissions as $permission){
            if ($datepointage->format('Y-m-d') >= $permission->getDateDebut()->format('Y-m-d')  and  $datepointage->format('Y-m-d') <= $permission->getDateFin()->format('Y-m-d')){
                return  true;
            }
        }
        return false;
    }

    #[Route('/getDetailsPresenceEmploye/{date1}/{id_employe}', name: 'rh_details_presence_employe')]
    function detailsEmploye(int $id_employe, string $date1, PointageService $pointage,) : Response
    {
        $infos = array();
        $data = array();
        $employe = $this->registry->getRepository(Employe::class)->find($id_employe);
        if ($employe){
                $dateStart = new DateTime($date1);
                $jour = strftime("%A", $dateStart->getTimestamp());
                $donnees = $pointage->getData("SELECT * FROM Req_UserCheck WHERE  Req_UserCheck.CHECKTIME_DAY =  '" . $dateStart->format('Y-m-d') . "' and  Badgenumber = '" . $employe->getCodeZkteco() . "';"); // Remplace par le nom réel
                //dd($donnees);
                foreach ($donnees as $donnee){
                    if ($donnee['SENSORID'] == "1"){ $type_sensor = "ENTREE";} else {  $type_sensor = "SORTIE";}
                    $data[] = array(
                        'CHECKTIME_DAY'=>$donnee['CHECKTIME_DAY'],
                        'CHECKTIME_TIME'=>$donnee['CHECKTIME_TIME'],
                        'SENSORID'=>$donnee['SENSORID'],
                        'TYPE_ACCES'=>$type_sensor,
                    );
                }

            $infos = array(
                'NAME'=>$employe->getNomPrenoms(),
                'POSTE'=>$employe->getPoste()->getLibelle(),
                'PHOTO'=>$employe->getPhoto(),
                'DATA'=>$data
            );
        }

        return $this->render('rh/presence/details.html.twig', [
            'infos' => $infos
        ]);
    }

    #[Route('/getDetailsPresenceEmploye/json/{date1}/{id_employe}', name: 'rh_details_json_presence_employe')]
    function detailsJsonEmploye(int $id_employe, string $date1, PointageService $pointage,) : Response
    {
        $infos = array();
        $data = array();
        $employe = $this->registry->getRepository(Employe::class)->find($id_employe);
        if ($employe){
            $dateStart = new DateTime($date1);
            $jour = strftime("%A", $dateStart->getTimestamp());
            $donnees = $pointage->getData("SELECT * FROM Req_UserCheck WHERE  Req_UserCheck.CHECKTIME_DAY =  '" . $dateStart->format('Y-m-d') . "' and  Badgenumber = '" . $employe->getCodeZkteco() . "';"); // Remplace par le nom réel
            //dd($donnees);
            foreach ($donnees as $donnee){
                if ($donnee['SENSORID'] == "1"){ $type_sensor = "ENTREE";} else {  $type_sensor = "SORTIE";}
                $data[] = array(
                    'CHECKTIME_DAY'=>$donnee['CHECKTIME_DAY'],
                    'CHECKTIME_TIME'=>$donnee['CHECKTIME_TIME'],
                    'SENSORID'=>$donnee['SENSORID'],
                    'TYPE_ACCES'=>$type_sensor,
                );
            }

            $infos = array(
                'NAME'=>$employe->getNomPrenoms(),
                'POSTE'=>$employe->getPoste()->getLibelle(),
                'PHOTO'=>$employe->getPhoto(),
                'DATA'=>$data
            );
        }
        return new  JsonResponse(json_encode($infos));
    }
}
