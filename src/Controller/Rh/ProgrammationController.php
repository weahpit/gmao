<?php

namespace App\Controller\Rh;

use App\Entity\Rh\Annee;
use App\Entity\Rh\Employe;
use App\Entity\Rh\Equipe;
use App\Entity\Rh\Horaires;
use App\Entity\Rh\HorairesQuarts;
use App\Entity\Rh\Mois;
use App\Entity\Rh\Programmation;
use App\Repository\EquipeRepository;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use function Symfony\Component\Clock\now;

#[Route('/rh')]
#[IsGranted('ROLE_USER')]
final class ProgrammationController extends AbstractController
{
    public function __construct(private PresenceController $presenceController)
    {
    }

    #[Route('/programmation', name: 'rh_app_programmation')]
    public function index(EquipeRepository $equipeRepository): Response
    {
        return $this->render('rh/programmation/index.html.twig', [
            'equipes'=>$equipeRepository->findAll()
        ]);
    }
    #[Route('/genererMois/{mois}/{libelle_mois?""}', name: 'rh_generation_mois')]
    public function generer_mois(
        ManagerRegistry $registry,
        int $mois,
        string $libelle_mois
    ): Response
    {
        $reponse = array();
        $annee = new DateTime();
        $annee = $registry->getRepository(Annee::class)->findOneBy(['valeur'=>(int) $annee->format('Y')]);
        $valeur_mois = $registry->getRepository(Mois::class)->findOneBy(['index'=>$mois, 'code_annee'=>$annee]);
        if (!$valeur_mois){
            $valeur_mois = new Mois();
            $valeur_mois->setLibelle(strtoupper($libelle_mois));
            $valeur_mois->setIndex($mois);
            $valeur_mois->setCodeAnnee($annee);
            $registry->getManager()->persist($valeur_mois);
//            --------------------- -------------- --------------------------
            $year = $annee->getValeur();
            $month = $valeur_mois->getIndex();


// Créer la date de départ
            $startDate = new DateTime("$year-$month-01");

// Cloner la date de départ pour créer la date de fin
            $endDate = clone $startDate;
            $endDate->modify('last day of this month');
//            --------------------- -------------- --------------------------
// Boucle sur chaque jour
            $i = 0;
            while ($startDate <= $endDate) {
                $i++;
                $quarts = $registry->getRepository(HorairesQuarts::class)->findAll();
                $startDate->format('Y-m-d');
                $startDate->modify('+1 day');
                //echo $startDate->format('Y-m-d') . "\n";
                foreach ($quarts as $quart){
                    $programme = new Programmation();
                    if (strlen($i) == 1){$jour = "0". strval($i); } else {$jour = strval($i);}
                    $programme->setDateProgramme(new DateTime("$year-$month-". $jour));
                    $programme->setCodeMois($valeur_mois);
                    $programme->setCodeQuart($quart);
                    $registry->getManager()->persist($programme);
                }
            }

            $registry->getManager()->flush();
            $reponse[] = array(
                'code'=>'SUCCESS'
            );
        } else {
            $reponse[] = array(
                'code'=>'FAILED'
            );
        }
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/getMois', name: 'rh_get_mois')]
    public function get_mois(
        ManagerRegistry $registry
    ): Response
    {
        $reponse = array();

        $mois = $registry->getRepository(Mois::class)->findAll();

        foreach ($mois as $m){
            $reponse[] = array(
                'id'=>$m->getId(),
                'libelle'=>$m->getLibelle()
            );
        }
        sort($reponse);
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/getQuartsJours', name: 'rh_get_quart_jours')]
    public function get_quart_jours(
        ManagerRegistry $registry
    ): Response
    {
        $reponse = array();

        $mois = $registry->getRepository(Mois::class)->findOneBy([], ['id'=>'DESC']);
        if($mois){
            $programe = $registry->getRepository(Programmation::class)->findAll();
            foreach ($programe as $value){
                if ($value->getCodeEquipe()) {$nomequipe = $value->getCodeEquipe()->getNomEquipe();} else { $nomequipe = "-";}
                if ($value->getCodeEquipe()){
                    if ($value->getCodeEquipe()->getId() == 1){
                        $reponse[] = array(
                            'id'=>$value->getId(),
                            'title'=>$nomequipe,
                            'start'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure1()->format('H:i:s'),
                            'end'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure2()->format('H:i:s'),
                            "classNames"=> ["equipeA"]
                        );
                    } elseif ($value->getCodeEquipe()->getId() == 2){
                        $reponse[] = array(
                            'id'=>$value->getId(),
                            'title'=>$nomequipe,
                            'start'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure1()->format('H:i:s'),
                            'end'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure2()->format('H:i:s'),
                            "classNames"=> ["equipeB"]
                        );
                    } elseif ($value->getCodeEquipe()->getId() == 3) {
                        $reponse[] = array(
                            'id'=>$value->getId(),
                            'title'=>$nomequipe,
                            'start'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure1()->format('H:i:s'),
                            'end'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure2()->format('H:i:s'),
                            "classNames"=> ["equipeC"]
                        );
                   } elseif ($value->getCodeEquipe()->getId() == 4) {
                        $reponse[] = array(
                            'id'=>$value->getId(),
                            'title'=>$nomequipe,
                            'start'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure1()->format('H:i:s'),
                            'end'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure2()->format('H:i:s'),
                            "classNames"=> ["equipeD"]
                        );
                   }else  {
                        $reponse[] = array(
                            'id'=>$value->getId(),
                            'title'=>$nomequipe,
                            'start'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure1()->format('H:i:s'),
                            'end'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure2()->format('H:i:s'),
                            "classNames"=> ["sansEquipe"]
                        );
                   }
                } else {
                    $reponse[] = array(
                        'id'=>$value->getId(),
                        'title'=>$nomequipe,
                        'start'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure1()->format('H:i:s'),
                        'end'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure2()->format('H:i:s'),
                        "classNames"=> ["sansEquipe"]
                    );
                }
            }
        }

        return new JsonResponse($reponse);
    }
    #[Route('getQuartsDuJour/{date_jour}', name: 'rh_get_quart_du_jour')]
    public function get_quart_du_jour(
        ManagerRegistry $registry,
        string $date_jour
    ): Response
    {
        $reponse = array();

        $ma_date = new DateTime($date_jour);
            $programe = $registry->getRepository(Programmation::class)->findBy(['date_programme'=>$ma_date]);
            foreach ($programe as $value){
                if ($value->getCodeEquipe()) {$nomequipe = $value->getCodeEquipe()->getNomEquipe();$id_equipe = $value->getCodeEquipe()->getId();} else { $id_equipe = 0;$nomequipe = "-";}
                if ($value->getCodeQuart()) {$quart = $value->getCodeQuart()->getHeure1()->format("H:i:s") . " - ". $value->getCodeQuart()->getHeure2()->format("H:i:s");} else { $quart = "-";}
                if ($value->getCodeEquipe()){
                    if ($value->getCodeEquipe()->getId() == 1){
                        $reponse[] = array(
                            'id'=>$value->getId(),
                            'title'=>$nomequipe,
                            'id_equipe'=>$id_equipe,
                            'start'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure1()->format('H:i:s'),
                            'end'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure2()->format('H:i:s'),
                            "quart"=> $quart
                        );
                    } elseif ($value->getCodeEquipe()->getId() == 2){
                        $reponse[] = array(
                            'id'=>$value->getId(),
                            'title'=>$nomequipe,
                            'id_equipe'=>$id_equipe,
                            'start'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure1()->format('H:i:s'),
                            'end'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure2()->format('H:i:s'),
                            "quart"=> $quart
                        );
                    } elseif ($value->getCodeEquipe()->getId() == 3) {
                        $reponse[] = array(
                            'id'=>$value->getId(),
                            'title'=>$nomequipe,
                            'id_equipe'=>$id_equipe,
                            'start'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure1()->format('H:i:s'),
                            'end'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure2()->format('H:i:s'),
                            "quart"=> $quart
                        );
                    } elseif ($value->getCodeEquipe()->getId() == 4) {
                        $reponse[] = array(
                            'id'=>$value->getId(),
                            'title'=>$nomequipe,
                            'id_equipe'=>$id_equipe,
                            'start'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure1()->format('H:i:s'),
                            'end'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure2()->format('H:i:s'),
                            "quart"=> $quart
                        );
                    }else  {
                        $reponse[] = array(
                            'id'=>$value->getId(),
                            'title'=>$nomequipe,
                            'id_equipe'=>$id_equipe,
                            'start'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure1()->format('H:i:s'),
                            'end'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure2()->format('H:i:s'),
                            "quart"=> "AUCUN"
                        );
                    }
                } else {
                    $reponse[] = array(
                        'id'=>$value->getId(),
                        'title'=>$nomequipe,
                        'id_equipe'=>$id_equipe,
                        'start'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure1()->format('H:i:s'),
                        'end'=>$value->getDateProgramme()->format('Y-m-d')."T".$value->getCodeQuart()->getHeure2()->format('H:i:s'),
                        "quart"=> "AUCUN"
                    );
                }
            }
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/getHnDuJour/{date_jour}', name: 'rh_get_hn_du_jour')]
    public function get_hn_du_jour(
        ManagerRegistry $registry,
        string $date_jour,
        PresenceController $presences
    ): Response
    {
        $reponse = array();
        date_default_timezone_set('Africa/Abidjan');
        $ma_date = new DateTime($date_jour);
        $employesHn = $registry->getRepository(Employe::class)->findBy(['soumis_au_pointage'=>true,'supprimer'=>false], ['nom_prenoms'=>"ASC"]);
           foreach ($employesHn as $employe){
               $permission = $this->presenceController->cherchePermission($employe, $ma_date);
               if (!$employe->isDemission() && !$permission){
                   if ($employe->getTypeHorraire() != 2){
                       $retard = false ;
                       $attente = false ;
                       $presence_jour = $presences->details_presence_no_json($ma_date->format("Y-m-d"), $employe->getId());
                       $h_travail = $employe->getHoraireP() ? $employe->getHoraireP()->getHeure1()->format("H:i") : $registry->getRepository(Horaires::class)->find($employe->getTypeHorraire())->getHeure1()->format("H:i");
                       $h_time = $employe->getHoraireP() ? $employe->getHoraireP()->getHeure1() : $registry->getRepository(Horaires::class)->find($employe->getTypeHorraire())->getHeure1();

                           if ($presence_jour) {
                               $present  = true;
                               $heure_format  = new DateTime($presence_jour[0]['CHECKTIME_TIME']) ;
                               if ($heure_format->format("H:i") > $h_time->format("H:i")){ $retard = true ;}
                               } else {
                                   $present  = false;$heure_arrivee = "";
                                   if (now()->format("H:i") < $h_travail) {
                                       $attente = true ;
                                   }
                               }

                       $reponse[] = array(
                           'id'=>$employe->getId(),
                           'nom'=>$employe->getNomPrenoms(),
                           'present'=>$present,
                           'poste'=>$employe->getPoste() ? $employe->getPoste()->getLibelle() : "Poste non défini",
                           'photo'=>$employe->getPhoto(),
                           'heure_travail'=>$h_travail,
                           'heure_arrivee'=>$present ? $heure_format->format("H:i") : $heure_arrivee,
                           'retard'=>$retard,
                           'attente'=>$attente,
                       );
                   }
               }

           }

        return new JsonResponse(json_encode($reponse));
    }


    #[Route('/addEquipeQuart/{id_programme}/{id_equipe}', name: 'rh_add_equipe_quart')]
    public function add_equipe_quart(
        ManagerRegistry $registry,
        int $id_programme,
        int $id_equipe

    ): Response
    {
        $reponse = array();

        $programme = $registry->getRepository(Programmation::class)->find($id_programme);
        $equipe = $registry->getRepository(Equipe::class)->find($id_equipe);
        if($programme && $equipe){
            $doublonPrg = $registry->getRepository(Programmation::class)->findBy([
                'date_programme'=>$programme->getDateProgramme(),
                'code_equipe'=>$equipe
            ]);
            if ($doublonPrg){
                $reponse[] = array(
                    'code'=>'DOUBLON'
                );
            } else {
                $programme->setCodeEquipe($equipe);
                $registry->getManager()->persist($programme);
                $registry->getManager()->flush();

                $reponse[] = array(
                    'code'=>'SUCCESS'
                );
            }


        } else {
            $reponse[] = array(
                'code'=>'ERROR'
            );
        }
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/deleteEquipeFromQuart/{id_programme}', name: 'rh_delete_equipe_quart')]
    public function delete_equipe_quart(
        ManagerRegistry $registry,
        int $id_programme

    ): Response
    {
        $reponse = array();

        $programme = $registry->getRepository(Programmation::class)->find($id_programme);

        if($programme){
            $programme->setCodeEquipe(null);
            $registry->getManager()->persist($programme);
            $registry->getManager()->flush();

            $reponse[] = array(
                'code'=>'SUCCESS'
            );

        } else {
            $reponse[] = array(
                'code'=>'ERROR'
            );
        }
        return new JsonResponse(json_encode($reponse));
    }


}
