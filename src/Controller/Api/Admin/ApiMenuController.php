<?php

namespace App\Controller\Api\Admin;

use App\Entity\Menu;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ApiMenuController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllMenus', name: 'app_api_get_menu')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $menus = $this->em->getRepository(Menu::class)->findBy([], ['nom_menu'=>'ASC']);
                    foreach ($menus as $menu){
                        $data[] = array(
                            'id'=>$menu->getId(),
                            'menu'=>$menu->getNomMenu(),
                            'classname'=>$menu->getParentM() ? $menu->getParentM()->getNomMenu() : "" ,
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

        #[Route('api/getParentMenu', name: 'get_parents_menu')]
    public function get_parents_menu(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $menus = $this->em->getRepository(Menu::class)->findBy(['parent_m'=>null], ['nom_menu'=>'ASC']);
                    foreach ($menus as $menu){
                        $data[] = array(
                            'id'=>$menu->getId(),
                            'menu'=>$menu->getNomMenu()
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

    #[Route('api/getSingleMenu/{$id_menu}', name: 'app_api_get_single_menu')]
    public function app_api_get_single_menu(int $id_menu): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $menu = $this->em->getRepository(Menu::class)->find($id_menu);

            if ($menu){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'menu'=>$menu->getNomMenu()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner un menu  dans la liste !'
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
    #[Route('api/saveMenu', name: 'app_api_save_menu')]
    public function app_api_save_menu(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_menu = $request->request->get('id_menu');
                $libelle_menu = $request->request->get('nom_menu');
                $parent_menu = $request->request->get('parent_menu');
                $classname_menu = $request->request->get('classname_menu');

                $reference = $this->em->getRepository(Menu::class)->findOneBy(['classname_menu'=>$classname_menu]);

                if (!$libelle_menu) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir la menu !'];
                } else {
                    if ($classname_menu && $reference){
                        $reponse = ['code' => 'warning', 'msg' => 'Cette reference existe déjà !'];
                    } else {
                        $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_menu);
                        $menu = $this->em->getRepository(Menu::class)->find($uuid);
                        $isNew = false;
                        if (!$menu) {
                            $menu = new Menu();
                            $isNew = true;
                        }

                        $menu->setNomMenu(strtoupper($libelle_menu));
                        if (Uuid::isValid($parent_menu)) {
                            $uuid_parent = Uuid::fromString($parent_menu);
                            $pMenu = $this->em->getRepository(Menu::class)->find($uuid_parent);
                            $menu->setParentM($pMenu);
                        }

                        $menu->setClassnameMenu($classname_menu);

                        $this->em->persist($menu);
                        $this->em->flush();

                        $reponse = [
                            'code' => 'success',
                            'msg' => $isNew ? 'Menu créé avec succès !' : 'Menu  mis à jour avec succès !'
                        ];
                    }
                }
            } catch (\Throwable $throwable) {
                $reponse = ['code' => "error", 'msg' => 'Une erreur s\'est produite !<br>' . $throwable->getMessage()];
            }
        } else {
            $reponse = array('code'=>'warning', 'msg'=>'Vous n\'êtes pas autorisé à accéder à cette ressource');
        }
        return new JsonResponse($reponse);
    }

    #[Route('api/deleteMenu/{$id_menu}', name: 'app_api_delete_menu')]
    public function app_api_delete_menu(Request $request, int $id_menu): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_menu);
                $menu = $this->em->getRepository(Menu::class)->find($uuid);

                if ($menu){
                    $this->em->remove($menu);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Menu supprimé avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner un menu !'
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
