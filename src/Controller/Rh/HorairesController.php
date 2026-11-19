<?php

namespace App\Controller\Rh;

use App\Entity\Rh\Horaires;
use App\Entity\Rh\HorairesPersonnalises;
use App\Entity\Rh\HorairesQuarts;
use App\Entity\Rh\TypeHoraire;
use DateTime;
use DateTimeZone;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_USER')]
final class HorairesController extends AbstractController
{
    #[Route('/horaires', name: 'rh_app_horaires')]
    public function index(ManagerRegistry $registry): Response
    {
        $horaires = $registry->getRepository(Horaires::class)->findOneBy(['id'=>1]);
        //dd($horaires);
        return $this->render('rh/horaires/index.html.twig', [
            'horaires'=>$horaires
        ]);
    }

    #[Route('/AddHours/{time1}/{time2}/{time3}/{time4}', name: 'rh_add_hours')]
    public function add_hours(
        ManagerRegistry $registry,
        \DateTime $time1,
        \DateTime $time2,
        \DateTime $time3,
        \DateTime $time4

    ): Response
    {
       $reponse = array();
       $horraire = $registry->getRepository(Horaires::class)->find(1);
       if (!$horraire){
           $horraire = new Horaires();
       }

           $horraire->setHeure2(\DateTime::createFromInterface($time2));
           $horraire->setHeure1(\DateTime::createFromInterface( $time1));
           $horraire->setHeure3(\DateTime::createFromInterface($time3));
           $horraire->setHeure4(\DateTime::createFromInterface($time4));

           $registry->getManager()->persist($horraire);
           $registry->getManager()->flush();

        $reponse[] = array(
            'code'=>'SUCCESS',
            'time1'=>$horraire->getHeure1()->format('H:i'),
            'time2'=>$horraire->getHeure2()->format('H:i'),
            'time3'=>$horraire->getHeure3()->format('H:i'),
            'time4'=>$horraire->getHeure4()->format('H:i')
        );
        return new JsonResponse(json_encode($reponse));
    }
    #[Route('/AddQuarts/{quart11}/{quart12}/{quart21}/{quart22}/{quart31}/{quart32}', name: 'rh_add_quarts')]
        public function add_quarts(
            ManagerRegistry $registry,
            \DateTime $quart11,
            \DateTime $quart12,
            \DateTime $quart21,
            \DateTime $quart22,
            \DateTime $quart31,
            \DateTime $quart32

        ): Response
        {
           $reponse = array();
           $manager = $registry->getManager();
           $valeurs = [
               1 => ['Quart 1', $quart11, $quart12],
               2 => ['Quart 2', $quart21, $quart22],
               3 => ['Quart 3', $quart31, $quart32],
           ];

           foreach ($valeurs as $id => [$libelle, $debut, $fin]) {
               $quart = $registry->getRepository(HorairesQuarts::class)->find($id);
               if (!$quart) {
                   $quart = new HorairesQuarts();
                   $manager->persist($quart);
               }
               $quart->setLibelle($libelle);
               $quart->setHeure1($debut);
               $quart->setHeure2($fin);
           }

           $manager->flush();

           foreach ($registry->getRepository(HorairesQuarts::class)->findAll() as $quart) {
               $reponse[] = array(
                   'code' => 'SUCCESS',
                   'libelle' => $quart->getLibelle(),
                   'time1' => $quart->getHeure1()?->format('H:i'),
                   'time2' => $quart->getHeure2()?->format('H:i'),
               );
           }

           return new JsonResponse(json_encode($reponse));
        }
        #[Route('/AddPersonalises/{libelle}/{heure1}/{heure2}/{heure3}/{heure4}', name: 'rh_add_personalisees')]
        public function add_personalisees(
            ManagerRegistry $registry,
            string $libelle,
            \DateTime $heure1,
            \DateTime $heure2,
            \DateTime $heure3,
            \DateTime $heure4

        ): Response
        {
           $reponse = array();

                // Ajout de l'Horaire Personalisée
                    $horraire_p = new HorairesPersonnalises();
                    $horraire_p->setLibelle(strtoupper($libelle));
                    $horraire_p->setHeure1($heure1);
                    $horraire_p->setHeure2($heure2);
                    $horraire_p->setHeure3($heure3);
                    $horraire_p->setHeure4($heure4);

                    $registry->getManager()->persist($horraire_p);
                    $registry->getManager()->flush();
                    $reponse = array(
                        'code'=>'SUCCESS'
                    );
                    return new JsonResponse(json_encode($reponse));
        }

    /**
     * @throws \Exception
     */
    #[Route('/getHoraires', name: 'rh_get_horaires')]
    public function get_horaires(
        ManagerRegistry $registry
    ): Response
    {
        $reponse = array();
        date_default_timezone_set('Africa/Abidjan');
        $horraire = $registry->getRepository(Horaires::class)->find(1);

            if ($horraire){
                $reponse[] = array(
                    'code'=>'SUCCESS',
                    'time1'=>$horraire->getHeure1()->format('H:i'),
                    'time2'=>$horraire->getHeure2()->format('H:i'),
                    'time3'=>$horraire->getHeure3()->format('H:i'),
                    'time4'=>$horraire->getHeure4()->format('H:i')
                );
            }
        return new JsonResponse(json_encode($reponse));
    }
    #[Route('/getTypesHoraires', name: 'rh_get_types_horaires')]
    public function get_equipes(
        ManagerRegistry $registry
    ): Response
    {
        $reponse = array();

        $typeHoraires = $registry->getRepository(TypeHoraire::class)->findAll();

        foreach ($typeHoraires as $typeHoraire){
            $reponse[] = array(
                'code'=>'SUCCESS',
                'id'=>$typeHoraire->getId(),
                'libelle'=>$typeHoraire->getLibelle()
            );
        }
        //sort($reponse);
        return new JsonResponse(json_encode($reponse));
    }
    /**
     * @throws \Exception
     */
    #[Route('/getQuarts', name: 'rh_get_quarts')]
    public function get_quarts(
        ManagerRegistry $registry
    ): Response
    {
        $reponse = array();
        date_default_timezone_set('Africa/Abidjan');

        $horraire_quarts = $registry->getRepository(HorairesQuarts::class)->findAll();
        foreach ($horraire_quarts as $quart){
            $reponse[] = array(
                'code'=>'SUCCESS',
                'libelle'=>$quart->getLibelle(),
                'time1'=>$quart->getHeure1()->format('H:i'),
                'time2'=>$quart->getHeure2()->format('H:i')
            );
        }
        return new JsonResponse(json_encode($reponse));
    }

    #[Route('/getPersonalise', name: 'rh_get_personalise')]
    public function get_personalise(
        ManagerRegistry $registry
    ): Response
    {
        $reponse = array();

        $horraire_personalise = $registry->getRepository(HorairesPersonnalises::class)->findAll();
        foreach ($horraire_personalise as $horaires){
            $reponse[] = array(
                'code'=>'SUCCESS',
                'id'=>$horaires->getId(),
                'libelle'=>$horaires->getLibelle(),
                'time1'=>$horaires->getHeure1()->format('H:i'),
                'time2'=>$horaires->getHeure2()->format('H:i'),
                'time3'=>$horaires->getHeure3()->format('H:i'),
                'time4'=>$horaires->getHeure4()->format('H:i'),
            );
        }
        return new JsonResponse(json_encode($reponse));
    }
}
