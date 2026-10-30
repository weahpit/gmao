<?php

namespace App\Controller;

use App\Entity\DemandeAchat;
use App\Entity\Equipement;
use App\Entity\Fournisseur;
use App\Entity\Services;
use App\Repository\DemandeAchatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Module Demande d'Achat (DA).
 *
 * Vues web :
 *   - /demande-achat/nouvelle         (tout utilisateur authentifie) : creer une DA
 *   - /demande-achat/mes-demandes     (tout utilisateur authentifie) : suivi de SES DA
 *   - /demande-achat/suivi/{token}    (suivi par jeton, timeline detaillee)
 *   - /magasin/demandes-achat         (ROLE_PDR) : file du magasinier
 *   - /direction/demandes-achat       (ROLE_DG)  : file de la direction
 *
 * API (POST, JSON) :
 *   - /api/demande-achat/soumettre/{id} (ROLE_PDR) : enregistrer + soumettre a la DG
 *   - /api/demande-achat/valider/{id}   (ROLE_DG)  : valider
 *   - /api/demande-achat/refuser/{id}   (ROLE_DG)  : refuser (motif)
 *   - /api/demande-achat/commander/{id} (ROLE_PDR) : passer commande fournisseur
 *   - /api/demande-achat/servir/{id}    (ROLE_PDR) : sortir les articles (SERVIE)
 */
class DemandeAchatController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DemandeAchatRepository $repo,
    ) {
    }

    private function username(): string
    {
        $u = $this->getUser();
        return $u ? (method_exists($u, 'getUserIdentifier') ? $u->getUserIdentifier() : (string) $u) : 'inconnu';
    }

    // ---------------------------------------------------------------------
    //  DEMANDEUR : creation + suivi
    // ---------------------------------------------------------------------

    #[Route('/demande-achat/nouvelle', name: 'da_nouvelle', methods: ['GET', 'POST'])]
    public function nouvelle(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($request->isMethod('POST')) {
            $motif     = trim((string) $request->request->get('motif', ''));
            $serviceId = $request->request->get('code_service');
            $demandeur = trim((string) $request->request->get('demandeur', $this->username()));
            $lignesRaw = $request->request->all('lignes');

            $lignes = [];
            $equipRepo = $this->em->getRepository(Equipement::class);
            foreach ((array) $lignesRaw as $l) {
                $des = trim((string) ($l['designation'] ?? ''));
                $qte = (float) ($l['quantite'] ?? 0);
                if ($des === '' && $qte <= 0) {
                    continue;
                }
                $equipId = trim((string) ($l['equipement_id'] ?? ''));
                $code    = trim((string) ($l['code'] ?? ''));
                $qteStock = null;

                if ($equipId !== '') {
                    $eq = $equipRepo->find($equipId);
                    if ($eq) {
                        $qteStock = (float) ($eq->getQte() ?? 0);
                        if ($code === '') { $code = (string) $eq->getCode(); }
                        if ($des === '')  { $des  = (string) $eq->getNom(); }
                    } else {
                        $equipId = '';
                    }
                }

                // Disponible seulement si l'article est connu en stock et suffisant.
                $disponible = ($qteStock !== null) && ($qteStock >= $qte);

                $lignes[] = [
                    'code'             => $code,
                    'equipement_id'    => $equipId !== '' ? $equipId : null,
                    'designation'      => $des,
                    'quantite'         => $qte,
                    'qte_stock'        => $qteStock,          // null = hors catalogue
                    'disponible'       => $disponible,
                    'observation'      => trim((string) ($l['observation'] ?? '')),
                    // Decisions direction (valeurs par defaut)
                    'valide'           => true,
                    'quantite_validee' => $qte,
                    // A commander par defaut si introuvable en stock ou stock insuffisant
                    'a_commander'      => !$disponible,
                ];
            }

            if (count($lignes) === 0) {
                $this->addFlash('danger', 'Ajoutez au moins un article a la demande.');
                return $this->redirectToRoute('da_nouvelle');
            }

            $da = new DemandeAchat();
            $da->setNumero($this->repo->genererNumero());
            $da->setToken(bin2hex(random_bytes(16)));
            $da->setStatut(DemandeAchat::STATUT_NOUVELLE);
            $da->setCreatedBy($this->username());
            $da->setDemandeur($demandeur !== '' ? $demandeur : $this->username());
            $da->setMotif($motif !== '' ? $motif : null);
            $da->setLignes($lignes);

            if ($serviceId) {
                $service = $this->em->getRepository(Services::class)->find($serviceId);
                if ($service) {
                    $da->setCodeService($service);
                    $da->setService(method_exists($service, '__toString') ? (string) $service : null);
                }
            }

            $da->addHistorique(DemandeAchat::STATUT_NOUVELLE, $this->username(), 'Demande creee par le service demandeur.');
            $this->repo->save($da);

            $this->addFlash('success', 'Demande ' . $da->getNumero() . ' enregistree. Suivez son avancement ci-dessous.');
            return $this->redirectToRoute('da_suivi', ['token' => $da->getToken()]);
        }

        $services = [];
        try {
            $services = $this->em->getRepository(Services::class)->findBy([], ['libelle' => 'ASC']);
        } catch (\Throwable) {
            try { $services = $this->em->getRepository(Services::class)->findAll(); } catch (\Throwable) {}
        }

        return $this->render('demande_achat/nouvelle.html.twig', [
            'services' => $services,
            'moi'      => $this->username(),
        ]);
    }

    #[Route('/demande-achat/mes-demandes', name: 'da_mes_demandes', methods: ['GET'])]
    public function mesDemandes(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        return $this->render('demande_achat/mes_demandes.html.twig', [
            'demandes' => $this->repo->findForDemandeur($this->username()),
            'LIBELLES' => DemandeAchat::LIBELLES,
        ]);
    }

    #[Route('/demande-achat/suivi/{token}', name: 'da_suivi', methods: ['GET'])]
    public function suivi(string $token): Response
    {
        $da = $this->repo->findOneByToken($token);
        if (!$da) {
            throw $this->createNotFoundException('Demande introuvable.');
        }
        return $this->render('demande_achat/suivi.html.twig', [
            'da' => $da,
        ]);
    }

    // ---------------------------------------------------------------------
    //  Recherche d'articles (liste deroulante filtrable + stock)
    // ---------------------------------------------------------------------

    #[Route('/api/demande-achat/articles', name: 'api_da_articles', methods: ['GET'])]
    public function apiArticles(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $q = trim((string) $request->query->get('q', ''));

        $qb = $this->em->getRepository(Equipement::class)->createQueryBuilder('e');
        if ($q !== '') {
            $qb->andWhere('LOWER(e.nom) LIKE :q OR LOWER(e.code) LIKE :q')
               ->setParameter('q', '%' . mb_strtolower($q) . '%');
        }
        $qb->orderBy('e.nom', 'ASC')->setMaxResults(200);

        $out = [];
        foreach ($qb->getQuery()->getResult() as $e) {
            /** @var Equipement $e */
            $out[] = [
                'id'   => (string) $e->getId(),
                'code' => (string) $e->getCode(),
                'nom'  => (string) $e->getNom(),
                'qte'  => (float) ($e->getQte() ?? 0),
            ];
        }
        return new JsonResponse($out);
    }

    // ---------------------------------------------------------------------
    //  MAGASINIER (ROLE_PDR)
    // ---------------------------------------------------------------------

    #[Route('/magasin/demandes-achat', name: 'da_magasin', methods: ['GET'])]
    public function magasin(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PDR');
        return $this->render('demande_achat/magasin.html.twig', [
            'a_traiter' => $this->repo->findPourMagasin(),
            'toutes'    => $this->repo->findAllForMagasin(),
            'counts'    => $this->repo->counts(),
            'LIBELLES'  => DemandeAchat::LIBELLES,
        ]);
    }

    // ---------------------------------------------------------------------
    //  DIRECTION (ROLE_DG)
    // ---------------------------------------------------------------------

    #[Route('/direction/demandes-achat', name: 'da_direction', methods: ['GET'])]
    public function direction(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_DG');
        return $this->render('demande_achat/direction.html.twig', [
            'a_valider' => $this->repo->findPourDG(),
            'toutes'    => $this->repo->findAllForDG(),
            'counts'    => $this->repo->counts(),
            'LIBELLES'  => DemandeAchat::LIBELLES,
        ]);
    }

    // ---------------------------------------------------------------------
    //  API (POST / JSON) - transitions de statut
    // ---------------------------------------------------------------------

    private function find(string $id): ?DemandeAchat
    {
        return $this->repo->find($id);
    }

    /** Magasinier : enregistre la demande et la soumet a la direction. */
    #[Route('/api/demande-achat/soumettre/{id}', name: 'api_da_soumettre', methods: ['POST'])]
    public function apiSoumettre(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_PDR');
        $da = $this->find($id);
        if (!$da) {
            return new JsonResponse(['ok' => false, 'message' => 'Demande introuvable.'], 404);
        }
        if (!$da->isNouvelle()) {
            return new JsonResponse(['ok' => false, 'message' => 'Cette demande a deja ete traitee.'], 409);
        }
        $da->setStatut(DemandeAchat::STATUT_SOUMISE);
        $da->setEnregistrePar($this->username());
        $da->setSoumiseAt(new \DateTimeImmutable());
        $da->addHistorique(DemandeAchat::STATUT_SOUMISE, $this->username(), 'Enregistree par le magasin et soumise a la direction.');
        $this->em->flush();
        return new JsonResponse(['ok' => true, 'statut' => $da->getStatut(), 'libelle' => $da->getStatutLabel()]);
    }

    /** Direction : valide la demande. */
    #[Route('/api/demande-achat/valider/{id}', name: 'api_da_valider', methods: ['POST'])]
    public function apiValider(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_DG');
        $da = $this->find($id);
        if (!$da) {
            return new JsonResponse(['ok' => false, 'message' => 'Demande introuvable.'], 404);
        }
        if (!$da->isSoumise()) {
            return new JsonResponse(['ok' => false, 'message' => 'Seule une demande soumise peut etre validee.'], 409);
        }
        $commentaire = trim((string) $request->request->get('commentaire', ''));

        // Decisions par ligne (quantites ajustees, articles invalides, a commander).
        // Format attendu : JSON { "<index>": {valide:bool, quantite_validee:number, a_commander:bool} }
        $decisionsRaw = (string) $request->request->get('decisions', '');
        if ($decisionsRaw !== '') {
            $decisions = json_decode($decisionsRaw, true);
            if (is_array($decisions)) {
                $lignes = $da->getLignes() ?? [];
                foreach ($lignes as $i => $l) {
                    $key = (string) $i;
                    if (!array_key_exists($key, $decisions)) {
                        continue;
                    }
                    $d = $decisions[$key];
                    $valide = (bool) ($d['valide'] ?? true);
                    $qv = isset($d['quantite_validee']) ? (float) $d['quantite_validee'] : (float) ($l['quantite'] ?? 0);
                    $stock = $l['qte_stock'] ?? null;
                    // Recalcul disponibilite sur la quantite validee.
                    $dispo = ($stock !== null) && ((float) $stock >= $qv);
                    $aCommander = array_key_exists('a_commander', $d) ? (bool) $d['a_commander'] : !$dispo;

                    $lignes[$i]['valide'] = $valide;
                    $lignes[$i]['quantite_validee'] = $qv;
                    $lignes[$i]['disponible'] = $dispo;
                    $lignes[$i]['a_commander'] = $valide ? $aCommander : false;
                }
                $da->setLignes($lignes);
            }
        }

        if (count($da->getLignesValidees()) === 0) {
            return new JsonResponse(['ok' => false, 'message' => 'Aucun article valide : utilisez plutot le bouton Refuser.'], 422);
        }

        $da->setStatut(DemandeAchat::STATUT_VALIDEE);
        $da->setValidePar($this->username());
        $da->setValideAt(new \DateTimeImmutable());
        $da->setCommentaireDg($commentaire !== '' ? $commentaire : null);
        $da->addHistorique(DemandeAchat::STATUT_VALIDEE, $this->username(), $commentaire !== '' ? $commentaire : 'Demande validee par la direction.');
        $this->em->flush();
        return new JsonResponse(['ok' => true, 'statut' => $da->getStatut(), 'libelle' => $da->getStatutLabel()]);
    }

    /** Direction : refuse la demande (motif obligatoire). */
    #[Route('/api/demande-achat/refuser/{id}', name: 'api_da_refuser', methods: ['POST'])]
    public function apiRefuser(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_DG');
        $da = $this->find($id);
        if (!$da) {
            return new JsonResponse(['ok' => false, 'message' => 'Demande introuvable.'], 404);
        }
        if (!$da->isSoumise()) {
            return new JsonResponse(['ok' => false, 'message' => 'Seule une demande soumise peut etre refusee.'], 409);
        }
        $motif = trim((string) $request->request->get('motif', ''));
        if ($motif === '') {
            return new JsonResponse(['ok' => false, 'message' => 'Le motif de refus est obligatoire.'], 422);
        }
        $da->setStatut(DemandeAchat::STATUT_REFUSEE);
        $da->setValidePar($this->username());
        $da->setValideAt(new \DateTimeImmutable());
        $da->setCommentaireDg($motif);
        $da->addHistorique(DemandeAchat::STATUT_REFUSEE, $this->username(), $motif);
        $this->em->flush();
        return new JsonResponse(['ok' => true, 'statut' => $da->getStatut(), 'libelle' => $da->getStatutLabel()]);
    }

    /** Magasinier : stock insuffisant -> passe commande chez un fournisseur. */
    #[Route('/api/demande-achat/commander/{id}', name: 'api_da_commander', methods: ['POST'])]
    public function apiCommander(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_PDR');
        $da = $this->find($id);
        if (!$da) {
            return new JsonResponse(['ok' => false, 'message' => 'Demande introuvable.'], 404);
        }
        if (!$da->isValidee()) {
            return new JsonResponse(['ok' => false, 'message' => 'La demande doit etre validee avant toute commande.'], 409);
        }
        if (!$da->hasLignesACommander()) {
            return new JsonResponse(['ok' => false, 'message' => 'Aucun article n\'est marque a commander sur cette demande.'], 422);
        }
        $fournisseurId  = $request->request->get('fournisseur');
        $fournisseurNom = trim((string) $request->request->get('fournisseur_nom', ''));
        if ($fournisseurId) {
            $f = $this->em->getRepository(Fournisseur::class)->find($fournisseurId);
            if ($f) {
                $da->setFournisseur($f);
                if ($fournisseurNom === '') {
                    $fournisseurNom = method_exists($f, '__toString') ? (string) $f : '';
                }
            }
        }
        if ($fournisseurNom === '') {
            return new JsonResponse(['ok' => false, 'message' => 'Indiquez le fournisseur de la commande.'], 422);
        }
        $da->setStatut(DemandeAchat::STATUT_EN_COMMANDE);
        $da->setTypeTraitement(DemandeAchat::TRAITEMENT_COMMANDE);
        $da->setFournisseurNom($fournisseurNom);
        $da->setCommandeAt(new \DateTimeImmutable());
        $da->addHistorique(DemandeAchat::STATUT_EN_COMMANDE, $this->username(), count($da->getLignesACommander()) . ' article(s) commande(s) chez ' . $fournisseurNom . ' (stock insuffisant).');
        $this->em->flush();
        return new JsonResponse(['ok' => true, 'statut' => $da->getStatut(), 'libelle' => $da->getStatutLabel()]);
    }

    /** Magasinier : sort les articles -> demande SERVIE. */
    #[Route('/api/demande-achat/servir/{id}', name: 'api_da_servir', methods: ['POST'])]
    public function apiServir(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_PDR');
        $da = $this->find($id);
        if (!$da) {
            return new JsonResponse(['ok' => false, 'message' => 'Demande introuvable.'], 404);
        }
        if (!$da->isValidee() && !$da->isEnCommande()) {
            return new JsonResponse(['ok' => false, 'message' => 'La demande doit etre validee (ou commande recue) pour etre servie.'], 409);
        }
        $bonRef = trim((string) $request->request->get('bon_sortie_ref', ''));
        if ($da->getTypeTraitement() === null) {
            $da->setTypeTraitement(DemandeAchat::TRAITEMENT_SORTIE);
        }
        $da->setStatut(DemandeAchat::STATUT_SERVIE);
        $da->setServiePar($this->username());
        $da->setServieAt(new \DateTimeImmutable());
        if ($bonRef !== '') {
            $da->setBonSortieRef($bonRef);
        }
        $da->addHistorique(DemandeAchat::STATUT_SERVIE, $this->username(), $bonRef !== '' ? ('Articles sortis - bon ' . $bonRef . '.') : 'Articles sortis au service demandeur.');
        $this->em->flush();
        return new JsonResponse(['ok' => true, 'statut' => $da->getStatut(), 'libelle' => $da->getStatutLabel()]);
    }

    // ---------------------------------------------------------------------
    //  Bon de commande fournisseur (articles coches a commander)
    // ---------------------------------------------------------------------

    #[Route('/magasin/demandes-achat/{id}/bon-commande', name: 'da_bon_commande', methods: ['GET'])]
    public function bonCommande(string $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_PDR');
        $da = $this->find($id);
        if (!$da) {
            throw $this->createNotFoundException('Demande introuvable.');
        }
        return $this->render('demande_achat/bon_commande.html.twig', [
            'da'     => $da,
            'lignes' => $da->getLignesACommander(),
        ]);
    }
}
