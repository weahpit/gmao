<?php

namespace App\Controller;

use App\Entity\Logistique\Pays;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PaysController extends AbstractController
{
    #[Route('/generatePays', name: 'generate_pays')]
    public function index(EntityManagerInterface $manager): Response
    {
        try {
            $pays1 = new Pays();
            $pays1->setLibelle("ETATS UNIS");
            $manager->persist($pays1);


            $pays2 = new Pays();
            $pays2->setLibelle("PORTUGAL");
            $manager->persist($pays2);



            $pays3 = new Pays();
            $pays3->setLibelle("BELGIQUE");
            $manager->persist($pays3);



            $pays4 = new Pays();
            $pays4->setLibelle("ITALIE");
            $manager->persist($pays4);



            $pays5 = new Pays();
            $pays5->setLibelle("RUSSIE");
            $manager->persist($pays5);



            $pays6 = new Pays();
            $pays6->setLibelle("AFRIQUE DU SUD");
            $manager->persist($pays6);



            $pays7 = new Pays();
            $pays7->setLibelle("CANADA");
            $manager->persist($pays7);



            $pays8 = new Pays();
            $pays8->setLibelle("NORVEGE");
            $manager->persist($pays8);



            $pays9 = new Pays();
            $pays9->setLibelle("BRESIL");
            $manager->persist($pays9);



            $pays10 = new Pays();
            $pays10->setLibelle("ARGENTINE");
            $manager->persist($pays10);

            $manager->flush();
            return new JsonResponse(['code'=>'sucess', 'msg'=>'Pays ajoutés avec succès !']);
        } catch (\Throwable $throwable){
            return new JsonResponse(['code'=>'sucess', 'msg'=>'Erreur !'. $throwable->getTraceAsString()]);
        }

    }
}
