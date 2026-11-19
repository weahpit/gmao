<?php

namespace App\Controller\Rh;

use App\Entity\Rh\Absences;
use App\Entity\Rh\Employe;
use App\Entity\Rh\Feries;
use App\Entity\Rh\Historique;
use App\Entity\Rh\Horaires;
use App\Entity\Rh\HorairesQuarts;
use DateTime;
use DateTimeZone;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/rh')]
#[IsGranted('ROLE_USER')]
final class ExceptionsController extends AbstractController
{

    public function __construct(private ManagerRegistry $registry, private SluggerInterface $slugger)
    {
    }

    #[Route('/exceptions', name: 'rh_exceptions')]
    public function index(): Response
    {

        return $this->render('rh/exceptions/index.html.twig');
    }

    #[Route('/getJF', name: 'rh_get_jf')]
    public function get_jf(

    ): Response
    {
        $reponse = array();

        $jfs = $this->registry->getRepository(Feries::class)->findAll();
        foreach ($jfs as $jf){
            $reponse[] = array(
                'id'=>$jf->getId(),
                'denomination'=>$jf->getDenomination(),
                'date_jf'=>$jf->getDateFerie()->format('d/m/Y')
            );
        }

        return new JsonResponse(json_encode($reponse));
    }
    #[Route('/getPermissions', name: 'rh_get_permissions')]
    public function get_permissions(

    ): Response
    {
        $reponse = array();

        $absences = $this->registry->getRepository(Absences::class)->findAll();
        foreach ($absences as $absence){
            $reponse[] = array(
                'id'=>$absence->getId(),
                'periode'=>"[".$absence->getDateDebut()->format('d/m/Y')." - " . $absence->getDateFin()->format('d/m/Y') ."]" ,
                'nom'=>$absence->getCodeEmploye()->getNomPrenoms(),
                'mle'=>$absence->getCodeEmploye()->getMatricule(),
                'type_absence'=>$absence->getTypeAbsence(),
                'nb_jours'=>$absence->getNbJours(),
                'paye'=>$absence->getPaye()
            );
        }
        rsort($reponse);
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/getDemissions', name: 'rh_get_demissions')]
    public function get_demissions(

    ): Response
    {
        $reponse = array();

        $employes =$this->registry->getRepository(Employe::class)->findBy(['demission'=>true], ['nom_prenoms'=>'ASC']);
        foreach ($employes as $employe){
            $reponse[] = array(
                'id'=>$employe->getId(),
                'nom'=>$employe->getNomPrenoms(),
                'mle'=>$employe->getMatricule(),
                'service'=>$employe->getPoste()->getLibelle() ? $employe->getPoste()->getLibelle() : "-",
                'departement'=>$employe->getPoste()->getCodeDepartement()->getLibelle() ? $employe->getPoste()->getCodeDepartement()->getLibelle() : "-"
            );
        }
        rsort($reponse);
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/getPermissions/details/{id_employe}', name: 'rh_get_permissions_employe')]
    public function get_permissions_employe(
        int $id_employe
    ): Response
    {
        $reponse = array();
       if  ($id_employe == 0){
           $absences = $this->registry->getRepository(Absences::class)->findAll();
           foreach ($absences as $absence){
               $reponse[] = array(
                   'id'=>$absence->getId(),
                   'periode'=>"[".$absence->getDateDebut()->format('d/m/Y')." - " . $absence->getDateFin()->format('d/m/Y') ."]" ,
                   'nom'=>$absence->getCodeEmploye()->getNomPrenoms(),
                   'mle'=>$absence->getCodeEmploye()->getMatricule(),
                   'type_absence'=>$absence->getTypeAbsence(),
                   'nb_jours'=>$absence->getNbJours(),
                   'paye'=>$absence->getPaye(),
                   'fichier'=>$absence->getFichier(),
                   'date_fin'=>$absence->getDateFin()->format('Y-m-d'),
                   'date_debut'=>$absence->getDateDebut()->format('Y-m-d')
               );
           }
            rsort($reponse);
        } else {
           $employe = $this->registry->getRepository(Employe::class)->find($id_employe);
           if ($employe){
                $absences = $this->registry->getRepository(Absences::class)->findBy(['code_employe'=>$employe]);
                foreach ($absences as $absence){
                    $reponse[] = array(
                        'id'=>$absence->getId(),
                        'periode'=>"[".$absence->getDateDebut()->format('d/m/Y')." - " . $absence->getDateFin()->format('d/m/Y') ."]" ,
                        'nom'=>$absence->getCodeEmploye()->getNomPrenoms(),
                        'mle'=>$absence->getCodeEmploye()->getMatricule(),
                        'type_absence'=>$absence->getTypeAbsence(),
                        'nb_jours'=>$absence->getNbJours(),
                        'paye'=>$absence->getPaye(),
                        'fichier'=>$absence->getFichier(),
                        'extension'=>$absence->getExtension()
                    );
                }
                rsort($reponse);

            }
        }
        return new JsonResponse(json_encode($reponse));
    }
    #[Route('/affichePermission/{id_permission}', name: 'rh_affiche_permission')]
        public function affichePermission(
            int $id_permission
        ): Response
        {
            $reponse = array();
               $absence = $this->registry->getRepository(Absences::class)->find($id_permission);
               if ($absence){
                   $reponse = array(
                       'id'=>$absence->getId(),
                       'periode'=>"[".$absence->getDateDebut()->format('d/m/Y')." - " . $absence->getDateFin()->format('d/m/Y') ."]" ,
                       'nom'=>$absence->getCodeEmploye()->getNomPrenoms(),
                       'mle'=>$absence->getCodeEmploye()->getMatricule(),
                       'type_absence'=>$absence->getTypeAbsence(),
                       'nb_jours'=>$absence->getNbJours(),
                       'paye'=>$absence->getPaye(),
                       'fichier'=>$absence->getFichier(),
                       'extension'=>$absence->getExtension(),
                       'slug'=>$this->slugger->slug($absence->getCodeEmploye()->getNomPrenoms(), $absence->getId()),
                       'date_debut'=>$absence->getDateDebut()->format('Y-m-d'),
                       'date_fin'=>$absence->getDateFin()->format('Y-m-d')
                   );
               }

            return new JsonResponse(json_encode($reponse));
        }

    #[Route('/addJF/{denomination}/{date_ferie}', name: 'rh_add_jf')]
    public function add_jf(
        string $denomination,
        string $date_ferie
    ): Response
    {
        $reponse = array();

        if ($denomination && $date_ferie){
            $jf = new Feries();
            $jf->setDateFerie(new DateTime($date_ferie));
            $jf->setDenomination(strtoupper($denomination));

            $this->registry->getManager()->persist($jf);
            $this->registry->getManager()->flush();
            $reponse[] = array(
                'code'=>'SUCCESS'
            );
        } else {
            $reponse[] = array(
                'code'=>'error'
            );
        }


        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/remJF/{id_jf}', name: 'rh_rem_jf')]
    public function rem_jf(
        int $id_jf
    ): Response
    {
        $reponse = array();
            $jf = $this->registry->getRepository(Feries::class)->find($id_jf);
            if ($jf){
                $this->registry->getManager()->remove($jf);
                $this->registry->getManager()->flush();
                $reponse[] = array(
                    'code'=>'SUCCESS'
                );
            } else {
                $reponse[] = array(
                    'code'=>'error'
                );
            }


        return new JsonResponse(json_encode($reponse));
    }

    #[Route('addPermission/{data}', name: 'rh_add_permission')]
    public function add_permission(
        Request $request,
        string $data,
    ): Response
    {

        $reponse = array();
        //$page_cp = $pages_cp->find($id_page);
        try {
            if($data){
                $arraydata = json_decode($data);

                // Recherche l'employé
                $employe = $this->registry->getRepository(Employe::class)->find($arraydata->id_employe);

                if ($employe){

                    // Vérifie si c'est une modification ou un ajout
                    if  ((int)  $arraydata->id_permission == 0){
                        $permission = new Absences();
                    } else {
                        $permission = $this->registry->getRepository(Absences::class)->find((int)  $arraydata->id_permission);
                    }

                    $permission->setCodeEmploye($employe);
                    $permission->setDateDebut(new DateTime($arraydata->date1));
                    $permission->setDateFin(new DateTime($arraydata->date2));
                    $permission->setNbJours($arraydata->nb_jours);
                    $permission->setPaye($arraydata->paye);
                    $permission->setTypeAbsence($arraydata->type_absence);

                    // Récupérer le fichier envoyé ()
                    $file = $request->files->get('fichier');

                    if ($file) {
                        // Générer un nom unique
                        $fileName = uniqid().'.'.$file->guessExtension();
                        $permission->setFichier($fileName);
                        $permission->setExtension($file->guessExtension());
                        // Déplacer le fichier vers un dossier (ex: public/uploads)
                        $file->move(
                            $this->getParameter('permissions_directory'),
                            $fileName
                        );
                    }

                    $this->registry->getManager()->persist($permission);
                    $this->registry->getManager()->flush();
                    $reponse[] = array(
                        'code'=>'SUCCESS',
                        'msg'=>"Absence enregistrée",
                        'ordre'=>1
                    );

                } else {
                    $reponse[] = array(
                        'code'=>'ERROR',
                        'msg'=>"Employé inexistant",
                        'ordre'=>2
                    );

                }

            } else {
                $reponse[] = array(
                    'code'=>'ERROR',
                    'msg'=>"Données incorrectes",
                    'ordre'=>0
                );
            }
        } catch (\Throwable $throwable){
            $reponse[] = array(
                'code'=>'ERROR',
                'msg'=>$throwable->getMessage(),
                'ordre'=>0
            );
        }

        return new JsonResponse(json_encode($reponse));
    }

    #[Route('addDemission/{data}', name: 'rh_add_demission')]
    public function add_demission(
        string $data,
    ): Response
    {
        try{
            $reponse = array();
            //$page_cp = $pages_cp->find($id_page);
            if($data){
                $arraydata = json_decode($data);

                // Recherche l'employé
                $employe = $this->registry->getRepository(Employe::class)->find($arraydata->id_employe);

                if ($employe){
                    $employe->setDemission(true);
                    $employe->setDateDemission(new DateTime($arraydata->date_demission));
                    $employe->setMotifDemission($arraydata->motif);

                    $this->registry->getManager()->persist($employe);

                    // Enregistrement dans l'historique
                    $data_historique = new Historique();
                    $data_historique->setCodeEmploye($employe);
                    $data_historique->setDateOperation(new DateTime($arraydata->date_demission));
                    $data_historique->setTypeOperation("DEMISSION");
                    $data_historique->setValeur(1);
                    $this->registry->getManager()->persist($data_historique);

                    $this->registry->getManager()->flush();
                    $reponse[] = array(
                        'code'=>'SUCCESS'
                    );

                } else {
                    $reponse[] = array(
                        'code'=>'ERROR'
                    );
                }

            } else {
                $reponse[] = array(
                    'code'=>'ERROR'
                );
            }
        } catch (\Throwable $throwable){
            $reponse[] = array(
                'code'=>'ERROR'
            );
        }



        return new JsonResponse(json_encode($reponse));
    }
}
