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
                    // (Si OUI recupere les widgets en cours. Si NON, On les recréé)
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

                            $this->em->persist($customW);
                        }

                        $this->em->flush();
                        $reponse = array('code'=>'success', 'msg'=>'La liste des gadgets a été mise à jour !');
                    }
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
