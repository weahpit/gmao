<?php

namespace App\Controller\Rh;

use App\Entity\Rh\Employe;
use App\Entity\Rh\Equipe;
use App\Entity\Rh\Historique;
use App\Entity\Rh\HorairesPersonnalises;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_USER')]
final class HistoriqueController extends AbstractController
{
    #[Route('/historique', name: 'rh_app_historique')]
    public function index(ManagerRegistry $registry): Response
    {
        return $this->render('rh/historique/index.html.twig', [
            'employes' => $registry->getRepository(Employe::class)->findBy(
                ['supprimer' => false],
                ['nom_prenoms' => 'ASC']
            ),
        ]);
    }

    #[Route('/SetDataHistorique/{type_operation}/{date_mvt}/{valeur}/{personalise}/{id_employe}', name: 'rh_set_data_historique')]
    public function set_data_historique(
        ManagerRegistry $registry,
        string $type_operation,
        string $date_mvt,
        string $valeur,
        int $personalise,
        int $id_employe
    ): Response
    {
        $reponse = array();
        try {
            $employe = $registry->getRepository(Employe::class)->find($id_employe);
            $date_transfert = new DateTime($date_mvt);
            if ($employe && $type_operation && $date_transfert && $valeur){
                // Vérifie que cest la premiere occurence pour ce type d'historique et enregistre la premiere affectation
                $liste_historique = $registry->getRepository(Historique::class)->count(['type_operation'=>$type_operation, 'code_employe'=>$employe]);
                if ($liste_historique == 0){
                    $first_historique = new Historique();
                    $first_historique->setCodeEmploye($employe);
                    $first_historique->setDateOperation(new DateTime("2025-03-01"));
                    $first_historique->setTypeOperation($type_operation);
                    if ($type_operation == "Changement Type Horaire"){
                            $first_historique->setValeur($employe->getTypeHorraire());
                    }elseif ($type_operation == "Changement Type Etat"){
                        if ($employe->isSoumisAuPointage()){
                            $first_historique->setValeur("1");
                        } else {
                            $first_historique->setValeur("2");
                        }
                    }elseif ($type_operation == "Changement Equipe"){
                        if ($employe->getCodeEquipe()){
                            $first_historique->setValeur($employe->getCodeEquipe()->getId());
                        }
                    }
                    $registry->getManager()->persist($first_historique);
                }

                $data_historique = new Historique();
                $data_historique->setCodeEmploye($employe);
                $data_historique->setDateOperation($date_transfert);
                $data_historique->setTypeOperation($type_operation);
                $data_historique->setValeur($valeur);

                // Mise à jour de l'employé

                if ($type_operation == "Changement Type Horaire"){
                    $employe->setTypeHorraire($valeur);
                    if ($personalise > 0){
                        $employe->setHoraireP($registry->getRepository(HorairesPersonnalises::class)->find($personalise));
                    }
                    $registry->getManager()->persist($data_historique);
                }

                if ($type_operation == "Changement Type Etat"){
                   if ($valeur == 1) {$employe->setSoumisAuPointage(true);} else{$employe->setSoumisAuPointage(false);}
                    $registry->getManager()->persist($data_historique);
                }

                if ($type_operation == "Changement Equipe" && (int) $valeur > 0){
                    $equipe = $registry->getRepository(Equipe::class)->find($valeur);
                    if ($equipe){
                        $employe->setCodeEquipe($equipe);
                        $employe->setTypeHorraire(2);
                        $registry->getManager()->persist($data_historique);
                    }
                }

                $registry->getManager()->persist($data_historique);
                $registry->getManager()->flush();
                $reponse[] = array('resultat'=>'SUCCESS', 'code'=>1);
            } else {
                $reponse[] = array('resultat'=>'Valeurs incorrectes', 'code'=>0);
            }
            return  new  JsonResponse(json_encode($reponse));
        } catch (\Throwable $exception){
            $reponse[] = array('resultat'=>'FAILED' . $exception->getMessage(), 'code'=>0);
            return  new  JsonResponse(json_encode($reponse));
        }
    }

    #[Route('/getDataHistorique/{id_employe}', name: 'rh_get_data_historique')]
    public function get_data_historique(
        ManagerRegistry $registry,
        int $id_employe
    ): Response
    {
        $reponse = array();
/*        try {*/
            $employe = $registry->getRepository(Employe::class)->find($id_employe);
            $data =  $registry->getRepository(Historique::class)->findBy(['code_employe'=>$employe], ["date_operation"=>"DESC"]);
            if ($employe){
                //dd($data);
                foreach ($data as $item){
                    $reponse[] = array(
                        'date_operation'=>$item->getDateOperation()->format("d/m/Y"),
                        'operation'=>$item->getTypeOperation(),
                        'valeur'=>$item->getValeur()
                     );
                }
            } else {
                $reponse[] = array('resultat'=>'Valeurs incorrectes', 'code'=>0);
            }
            return  new  JsonResponse(json_encode($reponse));
       /* } catch (\Throwable $exception){
            $reponse[] = array('resultat'=>'FAILED' . $exception->getMessage(), 'code'=>0);
            return  new  JsonResponse(json_encode($reponse));
        }*/
    }
    #[Route('/initializeHystory/{id_employe}', name: 'rh_initialize_history')]
    public function initializeHystory(
        ManagerRegistry $registry,
        int $id_employe
    ): Response
    {
        $reponse = array();
        try {
        $employe = $registry->getRepository(Employe::class)->find($id_employe);
        $data =  $registry->getRepository(Historique::class)->findBy(['code_employe'=>$employe], ["date_operation"=>"DESC"]);
        if ($employe){
           // Supprime toute l'historique pour cet employé
            foreach ($data as $item){
                $registry->getManager()->remove($item);
            }

            // Mise à jour du fichier employé
            $employe->setSoumisAuPointage(true);
            $employe->setCodeEquipe(null);
            $employe->setTypeHorraire(1);
            $employe->setChefQuart(null);

            $registry->getManager()->flush();

            $reponse[] = array('resultat'=>'Valeurs réinitialisées', 'code'=>1);
        } else {
            $reponse[] = array('resultat'=>'Valeurs incorrectes', 'code'=>0);
        }
        return  new  JsonResponse(json_encode($reponse));
        } catch (\Throwable $exception){
             $reponse[] = array('resultat'=>'FAILED' . $exception->getMessage(), 'code'=>0);
             return  new  JsonResponse(json_encode($reponse));
         }
    }
}
