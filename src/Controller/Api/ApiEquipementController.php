<?php

namespace App\Controller\Api;

use App\Entity\Criticite;
use App\Entity\Equipement;
use App\Entity\EtatEquipement;
use App\Entity\Fournisseur;
use App\Entity\Marque;
use App\Entity\NatureEquipement;
use App\Entity\TypeEquipement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use function Symfony\Component\String\s;

final class ApiEquipementController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em){}

    #[Route('api/getAllEquipements', name: 'app_api_get_equipement')]
    public function index(): Response
        {
            if (!$this->getUser()){return $this->redirectToRoute("app_login");}
            $reponse = array();
            $data = array();
            if ($this->isGranted("ROLE_USER")){
                try{
                    // Liste des Natures d'équipements
                    $equipements = $this->em->getRepository(Equipement::class)->findBy([], ['nom'=>'ASC']);
                    foreach ($equipements as $equipement){
                        $data[] = array(
                            'id'=>$equipement->getId(),
                            'nom'=>strtoupper($equipement->getNom()),
                            'marque'=>$equipement->getMarque()? $equipement->getMarque()->getNom() : "" ,
                            'modele'=>$equipement->getModele(),
                            'code'=>$equipement->getCode(),
                            'etat'=>$equipement->getEtat()?$equipement->getEtat()->getLibelle() : "" ,
                            'criticite'=>$equipement->getCriticite()? $equipement->getCriticite()->getLibelle() : "",
                            'nature'=>$equipement->getNature()?$equipement->getNature()->getLibelle() : "",
                            'adresse'=>$equipement->getCodeFournisseur() ? $equipement->getCodeFournisseur()->getSigle() : "",
                            'categorie'=>$equipement->getTypeUnicite() ? $equipement->getTypeUnicite()->getLibelle() : "",
                            'stockFinal'=>$equipement->getStockFinal()
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
    #[Route('api/getSingleEquipement/{$id_equipement}', name: 'app_api_get_single_equipement')]
    public function app_api_get_single_equipement(int $id_equipement): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_USER")) {
            try {
                $equipement = $this->em->getRepository(Equipement::class)->find($id_equipement);

            if ($equipement){
                $reponse = array(
                    'code'=>'success',
                    'msg'=>'Success',
                    'libelle'=>$equipement->getLibelle()
                );
            } else {
                $reponse = array(
                    'code'=>'warning',
                    'msg'=>'Merci de sélectionner une nature d\'équipements dans la liste !'
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
    #[Route('api/saveEquipement', name: 'app_api_save_equipement')]
    public function app_api_save_equipement(Request $request): Response
    {
        $reponse = [];
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $id_equipement = $request->request->get('idEquipement');
                $CodeEquipement = $request->request->get('CodeEquipement');
                $nomEquipement = $request->request->get('nomEquipement');
                $codeFournisseur  = $request->request->get('codeFournisseur');
                $marque= $request->request->get('marque');
                $modele = $request->request->get('modele');
                $date_entree = $request->request->get('date_entree');
                $etat_equipement = $request->request->get('etat_equipement');
                $categorie = $request->request->get('categorie');
                $criticite_id = $request->request->get('criticite_id');
                $type_unicite_id = $request->request->get('type_unicite_id');
                $numero_serie = $request->request->get('numero_serie');
                $stock_initial = $request->request->get('stock_initial');
                $stock_actuel = $request->request->get('stock_actuel');
                $stock_final = $request->request->get('stock_final');
                $photo = $request->request->get('photo');

                $fournisseur = $this->em->getRepository(Fournisseur::class)->find($codeFournisseur);
                $criticite = $this->em->getRepository(Criticite::class)->find($criticite_id);
                $nature = $this->em->getRepository(NatureEquipement::class)->find($type_unicite_id);
                $etat = $this->em->getRepository(EtatEquipement::class)->find($etat_equipement);
                $mrq = $this->em->getRepository(Marque::class)->find($marque);
                $typeEq = $this->em->getRepository(TypeEquipement::class)->find($categorie);


                if (!$nomEquipement || !$CodeEquipement || !$marque) {
                    $reponse = ['code' => 'warning', 'msg' => 'Merci de saisir tous les champs obligatoires !'];
                } else {
                    $uuid = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), (string)$id_equipement);
                    $equipement = $this->em->getRepository(Equipement::class)->find($uuid);
                    $isNew = false;
                    if (!$equipement) {
                        $equipement = new Equipement();
                        $isNew = true;
                    }

                    $equipement->setCode(strtoupper($CodeEquipement));
                    $equipement->setNom(strtoupper($nomEquipement));

                    if ($fournisseur) { $equipement->setCodeFournisseur($fournisseur);}
                    if ($criticite) { $equipement->setCriticite($criticite);}
                    if ($nature) { $equipement->setNature($nature);;}
                    if ($etat) { $equipement->setEtat($etat);;}
                    if ($mrq) { $equipement->setMarque($mrq);;}
                    if ($typeEq) { $equipement->setTypeUnicite($typeEq);;}

                    $equipement->setModele(strtoupper($modele));
                    $equipement->setDateEntree(new \DateTime($date_entree));
                    $equipement->setNumeroSerie(strtoupper($numero_serie));
                    $equipement->setStockActuel(floatval($stock_actuel));
                    $equipement->setStockInitial(floatval($stock_initial));
                    $equipement->setStockFinal(floatval($stock_final));

                    // Charger la photo de l'équipement
                   /* $photo = $request->files->get('photo');*/
                    if ($photo) {
                        $filename = $equipement->getId().$photo->guessExtension();
                        $photo->move($this->getParameter('photo_equipement'), $filename);
                    }

                    $this->em->persist($equipement);
                    $this->em->flush();

                    $reponse = [
                        'code' => "success",
                        'msg' => $isNew ? 'Equipement créé avec succès !' : 'Equipement mis à jour avec succès !'
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
    #[Route('api/deleteEquipement/{$id_equipement}', name: 'app_api_delete_equipement')]
    public function app_api_delete_equipement(Request $request, int $id_equipement): Response
    {
        $reponse = array();
        if (!$this->getUser()){return $this->redirectToRoute("app_login");}
        if ($this->isGranted("ROLE_ADMIN")) {
            try {
                $equipement = $this->em->getRepository(Equipement::class)->find($id_equipement);

                if ($equipement){
                    $this->em->remove($equipement);
                    $this->em->flush();
                    $reponse = array(
                        'code'=>'success',
                        'msg'=>'Nature Equipement supprimée avec succès !'
                    );
                } else {
                    $reponse = array(
                        'code'=>'warning',
                        'msg'=>'Merci de sélectionner une nature d\'équipements !'
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
