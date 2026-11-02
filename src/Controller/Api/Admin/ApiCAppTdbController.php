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

final class ApiCAppTdbController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

   #[Route('api/getDisplayedTdbByGroupe', name: 'getDisplayedTdbByGroupe')]
    public function getDisplayedTdbByGroupe(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();

                try{
                    // Liste des Widgets

                    $widgets = $this->em->getRepository(CAppTdb::class)->findBy(['code_user'=>$this->getUser(), 'visible'=>true], ['ordre'=>'ASC']);
                    //dd($widgets);
                    foreach ($widgets as $widget){
                        // vérifie si le groupe est presents dans les goupes du widget
                            $data[] = array(
                                'id'=>$widget->getId(),
                                'ordre'=>$widget->getOrdre(),
                                'widget'=>$widget->getCodeGadget()?$widget->getCodeGadget()->getLibelle() : "",
                                'visible'=>$widget->isVisible() ? 'OUI' : 'NON'
                            );
                    }

                    $reponse = array('code'=>'success', 'msg'=>'Succès', 'data'=>$data);
                } catch (\Throwable $throwable){
                    $reponse = array('code'=>'error', 'msg'=>'Erreur ! <br>'. $throwable->getMessage());
                }

            return new JsonResponse(json_encode($reponse));
        }
    #[Route('api/getCustomWidgetsTdb', name: 'getCustomWidgetsTdb')]
    public function getCustomWidgetsTdb(): Response
    {
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        $reponse = array();
        $data = array();

        try{
            // Liste des Widgets

            $widgets = $this->em->getRepository(CAppTdb::class)->findBy(['code_user'=>$this->getUser()], ['ordre'=>'ASC']);
            //dd($widgets);
            foreach ($widgets as $widget){
                // vérifie si le groupe est presents dans les goupes du widget
                $data[] = array(
                    'id'=>$widget->getId(),
                    'ordre'=>$widget->getOrdre(),
                    'widget'=>$widget->getCodeGadget()?$widget->getCodeGadget()->getLibelle() : "",
                    'visible'=>$widget->isVisible() ? 'OUI' : 'NON'
                );
            }

            $reponse = array('code'=>'success', 'msg'=>'Succès', 'data'=>$data);
        } catch (\Throwable $throwable){
            $reponse = array('code'=>'error', 'msg'=>'Erreur ! <br>'. $throwable->getMessage());
        }

        return new JsonResponse(json_encode($reponse));
    }

    #[Route('api/setOrderedDown/{id_gadget}', name: 'setOrderedDown')]
    public function setOrderedDown($id_gadget): Response
    {
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        $reponse = array();
        try{
            // Liste des Widgets
            $gadget = $this->em->getRepository(CAppTdb::class)->find(Uuid::fromString($id_gadget));
            if ($gadget){
                $ordre = (int)$gadget->getOrdre();
               /* dd($gadget->getOrdre());*/
                // Recherche le gadget suivant
                $gadgetSuivant = $this->em->getRepository(CAppTdb::class)->findOneBy(['code_user'=>$this->getUser(), 'ordre'=>$ordre + 1]);

                if ($gadgetSuivant){
                    $gadgetSuivant->setOrdre($ordre);
                    $gadget->setOrdre($ordre + 1);
                    /*dd($gadgetSuivant->getOrdre());
                    dd($gadget->getOrdre());*/
                    $this->em->persist($gadget);
                    $this->em->persist($gadgetSuivant);
                    $this->em->flush();
                    $reponse = array('code'=>'success', 'msg'=>'Succès');
                }
            }
        } catch (\Throwable $throwable){
            $reponse = array('code'=>'error', 'msg'=>'Erreur ! <br>'. $throwable->getMessage());
        }
        return new JsonResponse(json_encode($reponse));
    }
    #[Route('api/setOrderedUp/{id_gadget}', name: 'setOrderedUp')]
    public function setOrderedUp($id_gadget): Response
    {
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        $reponse = array();
        try{
            // Liste des Widgets
            $gadget = $this->em->getRepository(CAppTdb::class)->find(Uuid::fromString($id_gadget));
            if ($gadget){
                $ordre = (int)$gadget->getOrdre();
                /* dd($gadget->getOrdre());*/
                // Recherche le gadget suivant
                $gadgetPrecedent= $this->em->getRepository(CAppTdb::class)->findOneBy(['code_user'=>$this->getUser(), 'ordre'=>$ordre - 1]);

                if ($gadgetPrecedent){
                    $gadgetPrecedent->setOrdre($ordre);
                    $gadget->setOrdre($ordre - 1);
                    /*dd($gadgetSuivant->getOrdre());
                    dd($gadget->getOrdre());*/
                    $this->em->persist($gadget);
                    $this->em->persist($gadgetPrecedent);
                    $this->em->flush();
                    $reponse = array('code'=>'success', 'msg'=>'Succès');
                }
            }
        } catch (\Throwable $throwable){
            $reponse = array('code'=>'error', 'msg'=>'Erreur ! <br>'. $throwable->getMessage());
        }
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('api/setGadgetVisible/{id_gadget}/{value}', name: 'setGadgetVisible')]
    public function setGadgetVisible($id_gadget, string $value): Response
    {
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        $reponse = array();
        try{
            // Liste des Widgets
            $gadget = $this->em->getRepository(CAppTdb::class)->find(Uuid::fromString($id_gadget));

            if ($gadget){
                if ($value == "1"){ $visible = true;} else {$visible = false;}

                $gadget->setVisible($visible);
                $this->em->persist($gadget);
                $this->em->flush();

                $reponse = array('code'=>'error', 'msg'=>'Gadget mis à jour avec succès !');
            }
        } catch (\Throwable $throwable){
            $reponse = array('code'=>'error', 'msg'=>'Erreur ! <br>'. $throwable->getMessage());
        }
        return new JsonResponse(json_encode($reponse));
    }
}
