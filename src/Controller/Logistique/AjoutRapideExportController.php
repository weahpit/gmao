<?php

declare(strict_types=1);

namespace App\Controller\Logistique;

use App\Entity\Logistique\{Client, Destination, Pays, Navire, Proprietaire, DocType};
use App\Entity\Production\Produit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, JsonResponse};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
final class AjoutRapideExportController extends AbstractController
{
    #[Route('/logistique/ajout-rapide', name: 'logistique_ajout_rapide', methods: ['POST'])]
    public function ajouter(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $autorise = false;
        foreach (['ROLE_ADMIN','ROLE_DG','AGENT_SHIPPING','ROLE_AGENT_SHIPPING','ROLE_LOGISTIQUE','ROLE_LOGISTIQU','ROLE_SHIPPING','ROLE_DL'] as $role) if ($this->isGranted($role)) $autorise = true;
        if (!$autorise) return $this->json(['message'=>'Acces reserve aux operations logistiques.'],403);
        if (!$this->isCsrfTokenValid('operations_logistiques', (string) $request->request->get('_token'))) return $this->json(['message'=>'Formulaire expire : rechargez la page.'],403);
        $type = (string) $request->request->get('type');
        $libelle = trim((string) $request->request->get('libelle'));
        $choix = [
            'client'=>[Client::class,'nom_client','setNomClient',255],
            'pays'=>[Destination::class,'denomination','setDenomination',100],
            'navire'=>[Navire::class,'nom','setNom',255],
            'proprietaire'=>[Proprietaire::class,'denomination','setDenomination',100],
            'produit'=>[Produit::class,'libelle','setLibelle',100],
            'document'=>[DocType::class,'libelle','setLibelle',255],
        ];
        if (!isset($choix[$type])) return $this->json(['message'=>'Type d’ajout non autorise.'],400);
        [$classe,$champ,$setter,$maximum] = $choix[$type];
        if ($libelle === '' || mb_strlen($libelle) > $maximum) return $this->json(['message'=>'Libelle obligatoire, avec au maximum '.$maximum.' caracteres.'],422);
        $proprio = null;
        if ($type === 'produit') {
            $id = (string) $request->request->get('proprietaire');
            if (!Uuid::isValid($id) || !($proprio=$em->find(Proprietaire::class,Uuid::fromString($id)))) return $this->json(['message'=>'Selectionnez d’abord le proprietaire du produit.'],422);
        }
        try {
            $objet = $em->wrapInTransaction(function () use ($em,$type,$classe,$champ,$setter,$libelle,$proprio): object {
                $qb=$em->createQueryBuilder()->select('r')->from($classe,'r')->where('LOWER(r.'.$champ.') = :nom')->setParameter('nom',mb_strtolower($libelle));
                if ($type === 'produit') $qb->andWhere('r.code_proprio = :proprio')->setParameter('proprio',$proprio);
                $objet=$qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
                if (!$objet) {
                    $objet=new $classe(); $objet->$setter($libelle);
                    if ($type === 'produit') $objet->setCodeProprio($proprio);
                    $em->persist($objet);
                }
                // Le pays est aussi disponible dans le referentiel des fiches clients.
                if ($type === 'pays') {
                    $pays=$em->createQueryBuilder()->select('p')->from(Pays::class,'p')->where('LOWER(p.libelle) = :nom')->setParameter('nom',mb_strtolower($libelle))->setMaxResults(1)->getQuery()->getOneOrNullResult();
                    if (!$pays) { $pays=(new Pays())->setLibelle($libelle); $em->persist($pays); }
                }
                $em->flush(); return $objet;
            });
            return $this->json(['id'=>(string) $objet->getId(),'libelle'=>$libelle,'type'=>$type,'proprietaire'=>$proprio ? (string) $proprio->getId() : null]);
        } catch (\Throwable $e) {
            return $this->json(['message'=>'L’ajout n’a pas ete enregistre : '.$e->getMessage()],422);
        }
    }
}
