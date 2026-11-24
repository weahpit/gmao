<?php

namespace App\Controller\Api\Logistique;

use App\Entity\Logistique\CvDeblocage;
use App\Entity\Logistique\CvExecution;
use App\Entity\Logistique\Proprietaire;
use App\Entity\ZoneExploitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiCvController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em, private \App\Service\Logistique\ReglesCvs $regles){}

    #[Route('api/getAllCvsDeblocage', name: 'app_api_get_cv_deblocage')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $cvs = $this->em->getRepository(CvDeblocage::class)->findBy([], ['numero_cv'=>'ASC']);
                    foreach ($cvs as $cv){
                        $data[] = array(
                            'id'=>$cv->getId(),
                            'contrat'=>$cv->getNumeroCv(),
                            'tonnage'=>$cv->getPoids()
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
    #[Route('api/getAllCvsDeblocageByProprio/{id_proprio}', name: 'app_api_get_cv_deblocage_par_proprio')]
    public function getAllCvsDeblocageByProprio($id_proprio): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}


            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    $uuid_proprio = Uuid::fromString($id_proprio);
                    $proprio = $this->em->getRepository(Proprietaire::class)->find($uuid_proprio);
                    if ($proprio){
                    // Liste des Natures d'équipements
                        $cvs = $this->em->getRepository(CvDeblocage::class)->findBy(['code_proprietaire'=>$proprio], ['numero_cv'=>'ASC']);
                        foreach ($cvs as $cv){
                            $data[] = array(
                                'id'=>$cv->getId(),
                                'contrat'=>$cv->getNumeroCv(),
                                'tonnage'=>$cv->getPoids()
                            );
                        }
                        $reponse = array('code'=>'success', 'msg'=>'Succès', 'data'=>$data);
                    }

                } catch (\Throwable $throwable){
                    $reponse = array('code'=>'error', 'msg'=>'Erreur ! <br>'. $throwable->getMessage());
                }
            } else {
                $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
            }
            return new JsonResponse(json_encode($reponse));
        }

    #[Route('api/saveCvDeblocage', name: 'app_api_save_cv_deblocage', methods: ['POST'])]
    public function app_api_save_cv_deblocage(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('operations_logistiques', (string) $request->request->get('_token'))) return new JsonResponse(['code'=>'warning', 'msg'=>'Rechargez la page de gestion CV et formules.'], 403);
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG") or $this->isGranted("AGENT_SHIPPING")) {
            try {
                $numero_cv = $request->request->get('numero_cv');
                $tonnage = $request->request->get('tonnage');
                $code_proprietaire = $request->request->get('code_proprietaire');

                $proprio = $this->em->getRepository(Proprietaire::class)->find(Uuid::fromString($code_proprietaire));
                if (!$numero_cv || !$tonnage || !$proprio) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir le numéro de contrat, le tonnage en Kg  et sélectionner le propriétaire!'];
                } else {
                    $cv = $this->regles->creerDeblocage((string) $numero_cv, $tonnage, $proprio);

                    $reponse = [
                        'code' => 'success',
                        'msg' =>'Contrat créé avec succès !'
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

    /*
***************************************************************************************************************************
***************************************************************************************************************************
*********************                                          CV D'EXECUTION                                             *********************
***************************************************************************************************************************
***************************************************************************************************************************
*/
    #[Route('api/getAllCvsExecution', name: 'app_api_get_cv_execution')]
    public function app_api_get_cv_execution(): Response
    {
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        $reponse = array();
        $data = array();
        if ($this->isGranted("ROLE_USER")){
            try{
                // Liste des Natures d'équipements
                $cvs = $this->em->getRepository(CvExecution::class)->findBy([], ['numero_cv_execution'=>'ASC']);
                foreach ($cvs as $cv){
                    $data[] = array(
                        'id'=>$cv->getId(),
                        'contrat'=>$cv->getNumeroCvExecution(),
                        'cv_deb'=>$cv->getCodeCvDeblocage() ? $cv->getCodeCvDeblocage()->getNumeroCv() : "",
                        'tonnage'=>$cv->getPoids()
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
    #[Route('api/getAllCvsExecutionByDeb/{cv_deb}', name: 'app_api_get_cv_execution_par_cv_deb')]
    public function app_api_get_cv_execution_par_cv_deb($cv_deb): Response
    {
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        $reponse = array();
        $data = array();
        if ($this->isGranted("ROLE_USER")){
            try{


                $uuid_cv_deb = Uuid::fromString($cv_deb);
                $cvDeb= $this->em->getRepository(CvDeblocage::class)->find($uuid_cv_deb);

                if ($cvDeb){

                    $cvs = $this->em->getRepository(CvExecution::class)->findBy(['code_cv_deblocage'=>$cvDeb], ['numero_cv_execution'=>'ASC']);
                    foreach ($cvs as $cv){
                        $data[] = array(
                            'id'=>$cv->getId(),
                            'contrat'=>$cv->getNumeroCvExecution(),
                            'cv_deb'=>$cv->getCodeCvDeblocage() ? $cv->getCodeCvDeblocage()->getNumeroCv() : "",
                            'tonnage'=>$cv->getPoids()
                        );
                    }
                    $reponse = array('code'=>'success', 'msg'=>'Succès', 'data'=>$data);
                }

            } catch (\Throwable $throwable){
                $reponse = array('code'=>'error', 'msg'=>'Erreur ! <br>'. $throwable->getMessage());
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('api/saveCvExecution', name: 'app_api_save_cv_execution', methods: ['POST'])]
    public function app_api_save_cv_execution(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('operations_logistiques', (string) $request->request->get('_token'))) return new JsonResponse(['code'=>'warning', 'msg'=>'Rechargez la page de gestion CV et formules.'], 403);
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN") or $this->isGranted("ROLE_DG") or $this->isGranted("AGENT_SHIPPING")) {
            try {
                $numero_cv = $request->request->get('numero_cv');
                $code_cv_deblocage = $request->request->get('code_cv_deblocage');

                $cvDeb = $this->em->getRepository(CvDeblocage::class)->find(Uuid::fromString($code_cv_deblocage));
                if (!$numero_cv ||!$cvDeb) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir le numéro de contrat d\'exécution, le tonnage en Kg  et sélectionner le CV Déblocage!'];
                } else {
                    $cv = $this->regles->creerExecution($cvDeb, (string) $numero_cv, $request->request->get('tonnage'));

                    $reponse = [
                        'code' => 'success',
                        'msg' =>'Contrat créé avec succès !'
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

}
