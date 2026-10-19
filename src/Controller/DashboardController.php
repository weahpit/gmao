<?php

namespace App\Controller;

use App\Entity\CAppTdb;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}
    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        $this->checkAndGetUserWidget();
        return $this->render('dashboard/index.html.twig');
    }

    #[Route('/checkAndGetUserWidget', name: 'checkAndGetUserWidget')]
    public function checkAndGetUserWidget(): Response
    {
        $reponse = array();

        if (!$this->getUser()){
            return  $this->redirectToRoute("app_login");}

        try {
            $user = $this->em->getRepository(User::class)->find($this->getUser());
            $groupe = $user->getCodeGroupe();
            if ($groupe){
                $widgets = $groupe->getWidgets();

                if ($widgets){
                    // Vérifie que l'utilisateur a une personnalisation de widgets
                    // (Si OUI récupère les widgets en cours. Si NON, On les recréé)

                    $customWidgets = $user->getCAppTdbs();
                    // dd($customWidgets->count());
                    if ($customWidgets->count() == 0){
                        $i=0;

                        foreach ($widgets as $widget){
                            $i++;
                            $customW = new CAppTdb();
                            $customW->setOrdre($i);
                            $customW->setCodeGadget($widget);
                            $customW->setCodeUser($user);
                            $customW->setVisible(true);
                            $customW->setWidth($widget->getWidth());
                            $customW->setRenderType($widget->getRenderType());

                            $this->em->persist($customW);
                        }

                        $reponse = array('code'=>'success', 'msg'=>'La liste des gadgets a été mise à jour !');
                    } else {
                        // S'assurer des nouveaux gadgets
                        foreach ($widgets as $widget){ // Parcours les widgets du groupe
                           /*recherche si le widget appartient aux customs widgets*/
                            $customW = $this->em->getRepository(CAppTdb::class)->findOneBy(['code_user'=>$user, 'code_gadget'=>$widget]);
                            if (!$customW){
                                $customW = new CAppTdb();
                                $customW->setOrdre($customWidgets->count() + 1);
                                $customW->setCodeGadget($widget);
                                $customW->setCodeUser($user);
                                $customW->setVisible(true);
                                $customW->setWidth($widget->getWidth());
                                $customW->setRenderType($widget->getRenderType());
                                $this->em->persist($customW);
                            }
                        }

                        // Vérifie  si le gadget customisé a été retiré de votre liste personnalisée
                            foreach ($customWidgets as $customWidget){
                                // Vérifie si CustomWidget appartient aux widget du groupe
                                if (! $widgets->contains($customWidget->getCodeGadget())){
                                    $this->em->remove($customWidget);
                                }
                            }

                        }

                    // Réorganisation générale des gadgets dans votre liste

                    $customW = $this->em->getRepository(CAppTdb::class)->findBy(['code_user'=>$user], ['visible'=>'DESC','ordre'=>'ASC']);
                    $j = 1;
                    foreach ($customW as $value){
                        $counter = (int) $j+1;
                        $value->setOrdre($j++);
                        $this->em->persist($value);
                    }

                    $this->em->flush();
                }

            } else {
                $reponse = array('code'=>'warning', 'msg'=>'Vous n\'appartenez à aucun groupe. Vous ne bénéfiez cependant d\'aucun gadget !');
            }
        }catch (\Throwable $throwable){
            $reponse = array('code'=>'error', 'msg'=>'Erreur !' . $throwable->getMessage());
        }

        return new JsonResponse($reponse);
    }
}
