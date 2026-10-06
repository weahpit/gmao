<?php

namespace App\Controller\Stock;

use App\Entity\EquipementType;
use App\Entity\MouvementEquipement;
use App\Services\pdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class InventairesController extends AbstractController
{
    #[Route('/inventaires', name: 'app_inventaires')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('inventaires/index.html.twig');
    }

    #[Route('api/getAllData', name: 'get_all_data')]
    public function get_all_data(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        try {
            $date_inventaire = $request->request->get('date_inventaire');
            $dateMvt = new  \DateTime($date_inventaire);

            // Recherche de tous les équipements Types
            $eqs = $entityManager->getRepository(EquipementType::class)->findBy([], ['nom_equipement'=>'ASC']);

            $reponse = array();
            $data = array();
            foreach ($eqs as $eq){
                $stock_actuelle = 0;
                $entree = 0;
                $sorties = 0;

                // Recherche les movements et compte suivant la nature de l'équipement ['Quantite' ou 'Serial']
                $mvts = $eq->getMouvementEquipements();
                // Parcours les moyuvements
                foreach ($mvts as $mvt){
                    $date_mvt = $mvt->getDateOperation() ? $mvt->getDateOperation()->format('Y-m-d'): $mvt->getCreatedAt()->format('Y-m-d');
                    if (strtotime($date_mvt) <= strtotime($dateMvt->format('Y-m-d'))){
                        if ($mvt->getTypeMvt() == "1"){ // Entrée
                            $entree = $entree + $mvt->getValue();
                        } else {
                            $sorties = $sorties + $mvt->getValue();
                        }
                    }
                }

                $stock_actuelle = $entree - $sorties;

                $data[] = array(
                    'id'=>$eq->getId(),
                    'nom'=>$eq->getNomEquipement(),
                    'actuel'=>$stock_actuelle? $stock_actuelle : 0,
                    'seuil'=>$eq->getSeuil() ? $eq->getSeuil() : 0,
                    'entree'=>$entree,
                    'sortie'=>$sorties
                );
            }
            $reponse = array(
                'code'=>'success',
                'msg'=>'Inventaire extrait avec succès !',
                'data'=>$data
            );
        } catch (\Throwable $throwable){
            $reponse = array(
                'code'=>'error',
                'msg'=>"Une erreur s'est produite!  => ". $throwable->getMessage()
            );
        }
        return new JsonResponse($reponse);
    }

    #[Route('api/getEquipementDetails', name: 'get_equipement_details')]
    public function get_equipement_details(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        try {
            $reponse = array();
            $data = array();

            $date_inventaire = $request->request->get('date_inventaire');
            $critere = $request->request->get('critere');
            $id_equipement = $request->request->get('id_equipement');

            $dateMvt = new  \DateTime($date_inventaire);

            // Recherche de tous les équipements Types
            $eq = $entityManager->getRepository(EquipementType::class)->find(Uuid::fromString($id_equipement));
            if ($eq){

                // Recherche les movements et compte suivant la nature de l'équipement ['Quantite' ou 'Serial']
                $mvts = $entityManager->getRepository(MouvementEquipement::class)->findBy(['code_equipement_type'=>$eq], ['created_at'=>'DESC']);
                // Parcours les moyuvements
                foreach ($mvts as $mvt){
                    $entree = "";
                    $sorties = "";
                    if (strtotime($mvt->getCreatedAt()->format('Y-m-d')) <= strtotime($dateMvt->format('Y-m-d'))){
                        if ($mvt->getTypeMvt() == "1"){ // Entrée
                            $entree = $mvt->getValue();
                        } else {
                            $sorties = $mvt->getValue();
                        }
                        $data[] = array(
                            'id'=>$mvt->getId(),
                            'service'=>$mvt->getCodeService() ? $mvt->getCodeService() ->getLibelle() : "",
                            'entree'=>$entree,
                            'sortie'=>$sorties,
                            'date_mvt'=>$mvt->getCreatedAt() ? $mvt->getCreatedAt()->format("d/m/Y") : ""
                        );
                    }
                }

            }
            $reponse = array(
                'code'=>'success',
                'msg'=>'Détails Mouvements extrait avec succès !',
                'equipement'=>$eq->getNomEquipement(),
                'photo'=>$eq->getPhoto() ? $eq->getPhoto() : "",
                'qte'=>$eq->getQte() ?$eq->getQte() : 0 ,
                'data'=>$data
            );
        } catch (\Throwable $throwable){
            $reponse = array(
                'code'=>'error',
                'msg'=>"Une erreur s'est produite!  => ". $throwable->getMessage()
            );
        }
        return new JsonResponse($reponse);
    }
    #[Route('api/getEquipementDetails/print', name: 'get_equipement_details_print')]
    public function get_equipement_details_print(
        Request $request,
        EntityManagerInterface $entityManager,
        pdfService $servicePdf
    ): Response {
        if (!$this->getUser()) {
            return $this->redirectToRoute("app_login");
        }

        try {
            $data = [];

            $date_inventaire = $request->request->get('date_inventaire');
            $id_equipement   = $request->request->get('id_equipement');

            $dateMvt = new \DateTime($date_inventaire);

            $eq = $entityManager->getRepository(EquipementType::class)
                ->find(Uuid::fromString($id_equipement));

            // Si l'équipement n'existe pas : on retourne quand même une réponse.
            if (!$eq) {
                return new Response('Équipement introuvable.', 404);
            }

            $mvts = $entityManager->getRepository(MouvementEquipement::class)
                ->findBy(['code_equipement_type' => $eq], ['created_at' => 'DESC']);

            foreach ($mvts as $mvt) {
                if (strtotime($mvt->getCreatedAt()->format('Y-m-d')) <= strtotime($dateMvt->format('Y-m-d'))) {
                    $entree  = ($mvt->getTypeMvt() == "1") ? $mvt->getValue() : "";
                    $sorties = ($mvt->getTypeMvt() != "1") ? $mvt->getValue() : "";

                    $data[] = [
                        'date_mvt' => $mvt->getCreatedAt() ? $mvt->getCreatedAt()->format("d/m/Y") : "",
                        'entree'   => $entree,
                        'sortie'   => $sorties,
                        'service'  => $mvt->getCodeService() ? $mvt->getCodeService()->getLibelle() : "",
                    ];
                }
            }

            $entetes  = ['Date', 'Entrée', 'Sortie', 'Service'];
            $largeurs = [45, 45, 45, 55]; // total ≈ 190 mm (portrait A4)

            $contenu = $servicePdf->genererTableau(
                'Détails Mouvements',
                $entetes,
                $largeurs,
                $data,
                'P',
                $eq->getNomEquipement()
            );

            return new Response($contenu, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="mouvements.pdf"',
            ]);

        } catch (\Throwable $throwable) {
            // On retourne une CHAÎNE, pas un tableau.
            return new Response(
                "Une erreur s'est produite ! => " . $throwable->getMessage(),
                500
            );
        }
    }
}
