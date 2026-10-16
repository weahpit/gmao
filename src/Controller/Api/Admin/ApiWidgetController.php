<?php

namespace App\Controller\Api\Admin;

use App\Controller\GroupeController;
use App\Entity\CAppTdb;
use App\Entity\Groupe;
use App\Entity\Widget;
use App\Entity\Services;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Metadata\Group;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiWidgetController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllWidgets', name: 'app_api_get_widget')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();

            $data = array();

            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $widgets = $this->em->getRepository(Widget::class)->findBy([], ['id'=>'ASC']);
                    foreach ($widgets as $widget){
                        $groupes = $widget->getCodeGroupe();
                        $data_groupe = array();
                        foreach ($groupes as  $groupe){
                            $data_groupe[] = array('id'=>$groupe->getId(), 'groupe'=>$groupe->getNomGroupe());
                        }
                        $data[] = array(
                            'id'=>$widget->getId(),
                            'widget'=>$widget->getLibelle(),
                            'width'=>$widget->getWidth() ? $widget->getWidth() : 0,
                            'groupes'=>$data_groupe,
                            'app_js'=>$widget->getAppJs(),
                            'render_type'=>$widget->getRenderType()
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

        #[Route('api/getAllWidgetsByGroupe/{id_groupe}', name: 'get_all_widgets_by_groupe')]
    public function getAllWidgetsByGroupe($id_groupe): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();

            /*try{
                // Liste des Widgets
                $UuidGroupe = Uuid::fromString($id_groupe);
                $groupe = $this->em->getRepository(Groupe::class)->find($UuidGroupe);
                $widgets = $this->em->getRepository(Widget::class)->findBy([], ['id'=>'ASC']);
                //dd($widgets);
                foreach ($widgets as $widget){
                    // vérifie si le groupe est presents dans les goupes du widget
                    if ($widget->getCodeGroupe()->contains($groupe)){
                        $data[] = array(
                            'id'=>$widget->getId(),
                            'widget'=>$widget->getLibelle(),
                            'reference'=>$widget->getReference(),
                            'width'=>$widget->getWidth() ? $widget->getWidth()  : 0,
                            'app_js'=>$widget->getAppJs(),
                            'render_type'=>$widget->getRenderType()
                        );
                    }
                }*/
                try{
                    // Liste des Widgets
                    $UuidGroupe = Uuid::fromString($id_groupe);
                    $groupe = $this->em->getRepository(Groupe::class)->find($UuidGroupe);
                    $widgets = $this->em->getRepository(CAppTdb::class)->findBy(['code_user'=>$this->getUser()], ['ordre'=>'ASC']);
                    //dd($widgets);
                    foreach ($widgets as $widget){
                            $data[] = array(
                                'id'=>$widget->getId(),
                                'widget'=>$widget->getCodeGadget()->getLibelle(),
                                'reference'=>$widget->getCodeGadget()->getReference(),
                                'width'=>$widget->getCodeGadget()->getWidth() ? $widget->getCodeGadget()->getWidth()  : 0,
                                'app_js'=>$widget->getCodeGadget()->getAppJs(),
                                'render_type'=>$widget->getCodeGadget()->getRenderType()
                            );
                    }

                    $reponse = array('code'=>'success', 'msg'=>'Succès', 'data'=>$data);
                } catch (\Throwable $throwable){
                    $reponse = array('code'=>'error', 'msg'=>'Erreur ! <br>'. $throwable->getMessage());
                }

            return new JsonResponse(json_encode($reponse));
        }
    #[Route('api/getSingleWidget', name: 'app_api_get_single_widget')]
    public function app_api_get_single_widget(Request $request): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $id_widget = $request->request->get('id_widget');
                $UuidWidget = Uuid::fromString($id_widget);
                $widget = $this->em->getRepository(Widget::class)->find($UuidWidget);

            if ($widget){
                $groupes = $widget->getCodeGroupe();
                $data_groupe = array();

                foreach ($groupes as  $groupe){
                    $data_groupe[] = array('id'=>$groupe->getId(), 'groupe'=>$groupe->getNomGroupe());
                }

                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'id'=>$widget->getId(),
                    'widget'=>$widget->getLibelle(),
                    'width'=>$widget->getWidth() ? $widget->getWidth() : 0,
                    'groupes'=>$data_groupe,
                    'app_js'=>$widget->getAppJs(),
                    'render_type'=>$widget->getRenderType()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner un widget  dans la liste !'
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
    #[Route('api/saveWidget', name: 'app_api_save_widget')]
    public function app_api_save_widget(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_widget = $request->request->get('id_widget');
                $libelle_widget = $request->request->get('widget');
                $width = $request->request->get('width');
                $code_groupe = $request->request->get('groupe');
                $render_type = $request->request->get('render_type');
                $app_js = $request->request->get('app_js');

                if (!$libelle_widget || !$width || !$render_type || !$app_js) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs!'];
                } else {
                        if ($id_widget == "0"){
                            $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_widget);
                        } else{
                            $uuid = Uuid::fromString($id_widget);
                        }

                    $widget = $this->em->getRepository(Widget::class)->find($uuid);
                    $isNew = false;
                    if (!$widget) {
                        $widget = new Widget();
                        $isNew = true;
                    } else {
                        $groupes = $widget->getCodeGroupe();
                        if ($groupes){
                            // Supprimer les groupes
                            foreach ($groupes as $grp){
                                $widget->removeCodeGroupe($grp);
                            }
                        }
                    }

                    $widget->setLibelle($libelle_widget);
                    $widget->setWidth((int) $width);
                    $widget->setRenderType($render_type);
                    $widget->setAppJs($app_js);

                    // Enregistrement des groupes
                    $liste_groupe = explode(",", $code_groupe);
                    foreach ($liste_groupe as $id_groupe){
                        $uuid_groupe = Uuid::fromString($id_groupe);
                        $groupe = $this->em->getRepository(Groupe::class)->find($uuid_groupe);
            // stock_alertes_pdr
            // derniers_mouvements_stocks
                        if ($groupe){
                            $widget->addCodeGroupe($groupe);
                        }
                    }

                    $this->em->persist($widget);
                    $this->em->flush();

                    $reponse = [
                        'code' => 'success',
                        'msg' => $isNew ? 'Widget créé avec succès !' : 'Widget  mis à jour avec succès !'
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
    #[Route('api/deleteWidget/{$id_widget}', name: 'app_api_delete_widget')]
    public function app_api_delete_widget(Request $request, int $id_widget): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $uuid = Uuid::fromString($id_widget);
                $widget = $this->em->getRepository(Widget::class)->find($uuid);

                if ($widget){
                    $this->em->remove($widget);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Widget supprimé avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner un widget !'
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
    #[Route('api/deleteWidgets', name: 'app_api_delete_widgets')]
    public function app_api_delete_widgets(Request $request): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $widgets = $this->em->getRepository(Widget::class)->findAll();

                foreach ($widgets as $widget){
                    foreach ($widget->getCodeGroupe() as $groupe) {
                        $groupe->removeWidget($widget);
                    }
                    $this->em->remove($widget);
                }
                $this->em->flush();

            } catch (\Throwable $throwable) {
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }
}
