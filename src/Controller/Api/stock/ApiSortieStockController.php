<?php

namespace App\Controller\Api\stock;

use App\Entity\Emplacement;
use App\Entity\Services;
use App\Entity\Stock\SaisieStock;
use App\Entity\Stock\SortieStock;
use App\Repository\Stock\SortieStockRepository;
use App\Service\Stock\BonSortieStockService;
use App\Service\Stock\ReferentielStock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API de sortie de stocks (consommee par l'application mobile).
 *
 * Calquee sur ApiSaisieStockController : meme format maison {code, msg, data}
 * (code : success | warning | error) et meme idempotence via client_uuid.
 */
final class ApiSortieStockController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReferentielStock $referentiel,
        private BonSortieStockService $bonsSortie,
    ) {}

    /**
     * Referentiel necessaire a l'ecran mobile :
     * rubriques, unites, motifs suggeres, services, emplacements,
     * agent connecte (grise) et date serveur.
     */
    #[Route('api/sortie-stock/referentiel', name: 'api_sortie_stock_referentiel', methods: ['GET'])]
    public function referentiel(): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $services = [];
        foreach ($this->em->getRepository(Services::class)->findAll() as $s) {
            $services[] = ['id' => (string) $s->getId(), 'libelle' => $s->getLibelle()];
        }

        $emplacements = [];
        foreach ($this->em->getRepository(Emplacement::class)->findAll() as $e) {
            $emplacements[] = ['id' => (string) $e->getId(), 'libelle' => $e->getLibelle()];
        }

        $serviceAgent = $user->getCodeService();
        $agentLibelle = trim(($user->getPrenoms() ?? '') . ' ' . ($user->getNom() ?? ''));

        return new JsonResponse([
            'code' => 'success',
            'msg'  => 'OK',
            'data' => [
                // Listes partagees web + mobile : rubriques depuis la table
                // stock_rubrique, natures depuis le referentiel Produit.
                'rubriques'    => $this->referentiel->rubriques(),
                // Unites lues dans la table stock_unite (liste partagee web + mobile).
                'unites'       => $this->referentiel->unites(),
                'natures'      => $this->referentiel->natures(),
                'uniteDefaut'  => $this->referentiel->uniteDefaut(),
                'motifs'       => SortieStock::MOTIFS,
                'services'     => $services,
                'emplacements' => $emplacements,
                'agent'        => [
                    'id'        => (string) $user->getId(),
                    'libelle'   => $agentLibelle,
                    'matricule' => $user->getMatricule(),
                    'serviceId' => $serviceAgent ? (string) $serviceAgent->getId() : null,
                    'service'   => $serviceAgent ? $serviceAgent->getLibelle() : null,
                    // Nom du fichier photo (servi depuis public/data/users/photos).
                    'photo'     => $user->getPhoto(),
                ],
                // Date et heure SERVEUR pour le champ "date de sortie" (grise).
                'date' => (new \DateTime())->format('Y-m-d H:i:s'),
                'dateOperation' => [
                    'grise'   => true,
                    'origine' => 'serveur',
                    'libelle' => 'Date et heure de la sortie',
                ],
                // Le bon de sortie (signature DG) s'applique a TOUTE sortie.
                'bonSortie' => [
                    'actif'          => true,
                    'signatureRole'  => 'ROLE_DG',
                    'signatureRoute' => 'POST api/sortie-stock/bon/{token}/signer',
                    'listeRoute'     => 'GET api/sortie-stock/bons-a-signer',
                    'detailRoute'    => 'GET api/sortie-stock/bon/{token}',
                    'toutRubrique'   => true,
                ],
            ],
        ]);
    }

    /**
     * Enregistre une sortie de stock (multipart : champs + photo facultative).
     * Idempotent via client_uuid : un renvoi hors-ligne ne cree pas de doublon.
     */
    #[Route('api/sortie-stock', name: 'api_sortie_stock_create', methods: ['POST'])]
    public function create(Request $request, SortieStockRepository $repo): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $rubrique   = trim((string) $request->request->get('rubrique'));
        $quantite   = $request->request->get('quantite');
        $unite      = trim((string) $request->request->get('unite')) ?: $this->referentiel->uniteDefaut();
        $motif      = trim((string) $request->request->get('motif'));
        $nature     = trim((string) $request->request->get('nature'));
        $serviceId  = $request->request->get('service');
        $emplacementId = $request->request->get('emplacement');
        $dateSortie = $request->request->get('date_sortie');
        $clientUuid = trim((string) $request->request->get('client_uuid')) ?: null;

        // --- Idempotence : si deja recu, on renvoie la ressource existante.
        if ($clientUuid && ($existante = $repo->findOneByClientUuid($clientUuid))) {
            return new JsonResponse([
                'code' => 'success',
                'msg'  => 'Sortie deja enregistree',
                'data' => ['id' => (string) $existante->getId(), 'duplicate' => true],
            ]);
        }

        // --- Validations.
        if (!$this->referentiel->rubriqueValide($rubrique)) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Rubrique invalide.'], 200);
        }
        // Unite validee contre la table stock_unite (et non plus une constante).
        if (!$this->referentiel->uniteValide($unite)) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Unite invalide.'], 200);
        }
        if (!$this->referentiel->natureValide($nature)) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Nature du produit invalide.'], 200);
        }
        if ($quantite === null || $quantite === '' || !is_numeric($quantite) || (float) $quantite <= 0) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Quantite invalide.'], 200);
        }
        if ($motif === '') {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Le motif est obligatoire.'], 200);
        }
        if (mb_strlen($motif) > 255) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Le motif est trop long (255 caracteres maximum).'], 200);
        }

        // --- Emplacement : obligatoire (cf. entite).
        $emplacement = null;
        if ($emplacementId) {
            try {
                $emplacement = $this->em->getRepository(Emplacement::class)->find(Uuid::fromString($emplacementId));
            } catch (\Throwable) {
                $emplacement = null;
            }
        }
        if (!$emplacement) {
            return new JsonResponse(['code' => 'warning', 'msg' => "Choisissez l'emplacement."], 200);
        }

        $sortie = new SortieStock();
        $sortie->setAgent($user);
        $sortie->setAgentLibelle(trim(($user->getPrenoms() ?? '') . ' ' . ($user->getNom() ?? '')));
        $sortie->setRubrique($rubrique);
        $sortie->setQuantite((float) $quantite);
        $sortie->setUnite($unite);
        $sortie->setMotif($motif);
        $sortie->setNature($nature);
        $sortie->setClientUuid($clientUuid);
        $sortie->setEmplacement($emplacement);
        $sortie->setEmplacementLibelle($emplacement->getLibelle());

        // Service : fourni par le mobile sinon celui de l'agent.
        $service = null;
        if ($serviceId) {
            try {
                $service = $this->em->getRepository(Services::class)->find(Uuid::fromString($serviceId));
            } catch (\Throwable) {
                $service = null;
            }
        }
        if (!$service) {
            $service = $user->getCodeService();
        }
        if ($service) {
            $sortie->setService($service);
            $sortie->setServiceLibelle($service->getLibelle());
        }

        // Date operation : le champ est GRISE cote application, la valeur qui
        // fait foi est celle du SERVEUR. Une date envoyee par l'appareil n'est
        // acceptee que si elle reste dans une tolerance de +/- 3 minutes
        // (simple decalage de fuseau / d'horloge) ; sinon on l'ignore.
        $dateServeur = new \DateTime();
        if ($dateSortie) {
            try {
                $dateAppareil = new \DateTime($dateSortie);
                $ecart = abs($dateAppareil->getTimestamp() - $dateServeur->getTimestamp());
                if ($ecart <= 180) {
                    $dateServeur = $dateAppareil;
                }
            } catch (\Throwable) {
                // date illisible : on garde la date serveur
            }
        }
        $sortie->setDateSortie($dateServeur);

        // Photo facultative.
        $photo = $request->files->get('photo');
        if ($photo) {
            $ext = strtolower((string) $photo->guessExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                return new JsonResponse(['code' => 'warning', 'msg' => 'La photo doit etre une image (jpg, png, webp).'], 200);
            }
            $filename = uniqid('sortie_') . '.' . $ext;
            try {
                $photo->move($this->getParameter('photo_sortie_stock'), $filename);
                $sortie->setPhoto($filename);
            } catch (\Throwable $e) {
                return new JsonResponse(['code' => 'error', 'msg' => 'Echec enregistrement photo.'], 200);
            }
        }

        try {
            // TOUTE sortie de stock émet un bon de sortie à signer par le DG
            // (même circuit que les bons PDR, toutes rubriques confondues).
            // La sortie reste enregistrée, mais le bon doit être signé
            // avant la sortie physique.
            $this->bonsSortie->emettreBon($sortie);

            $this->em->persist($sortie);
            $this->em->flush();
        } catch (\Throwable $e) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Erreur serveur : ' . $e->getMessage()], 200);
        }

        return new JsonResponse([
            'code' => 'success',
            'msg'  => $sortie->isEnAttente()
                ? 'Sortie enregistree. Bon de sortie ' . $sortie->getReference() . ' emis, en attente de la signature du Directeur General.'
                : 'Sortie enregistree.',
            'data' => [
                'id'            => (string) $sortie->getId(),
                'duplicate'     => false,
                'bon_reference' => $sortie->getReference(),
                'bon_statut'    => $sortie->getStatut(),
                'bon_token'     => $sortie->getToken(),
            ],
        ]);
    }

    /** Historique des dernieres sorties de l'agent connecte. */
    #[Route('api/sortie-stock', name: 'api_sortie_stock_list', methods: ['GET'])]
    public function list(SortieStockRepository $repo, Request $request): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }

        $limite = (int) ($request->query->get('limit') ?: 50);
        $items = [];
        foreach ($repo->findRecentesParAgent($this->getUser(), $limite) as $s) {
            /** @var SortieStock $s */
            $items[] = [
                'id'          => (string) $s->getId(),
                'rubrique'    => $s->getRubrique(),
                'quantite'    => $s->getQuantite(),
                'unite'       => $s->getUnite(),
                'motif'       => $s->getMotif(),
                'nature'      => $s->getNature(),
                'emplacement' => $s->getEmplacementLibelle(),
                'service'     => $s->getServiceLibelle(),
                'agent'       => $s->getAgentLibelle(),
                'photo'       => $s->getPhoto(),
                'dateSortie'  => $s->getDateSortie()?->format('Y-m-d H:i:s'),
                'createdAt'   => $s->getCreatedAt()?->format('Y-m-d H:i:s'),
                // Bon de sortie de stock (null pour une sortie anterieure au circuit)
                'bon_reference' => $s->getReference(),
                'bon_statut'    => $s->getStatut(),
                'bon_statut_libelle' => $s->getStatutLibelle(),
                'signe_at'      => $s->getSigneAt()?->format('Y-m-d H:i:s'),
                'signe_par'     => $s->getSignePar(),
            ];
        }

        return new JsonResponse(['code' => 'success', 'msg' => 'OK', 'data' => $items]);
    }

    /**
     * Signature du bon de sortie de stock par le Directeur General (app mobile).
     * Le bon existe pour TOUTE sortie de stock, quel que soit le type.
     * POST api/sortie-stock/bon/{token}/signer   (ROLE_DG / ROLE_ADMIN)
     * Même principe que api/bonSortie/signer/{token} pour les bons PDR.
     */
    #[Route('api/sortie-stock/bon/{token}/signer', name: 'api_sortie_stock_bon_signer', methods: ['POST'])]
    public function signerBon(string $token, Request $request, SortieStockRepository $repo): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }
        if (!$this->isGranted('ROLE_DG')) {
            return new JsonResponse([
                'code' => 'error',
                'msg'  => 'Acces refuse : signature reservee au Directeur General.',
            ], 403);
        }

        $bon = $repo->findOneByToken($token);
        if (!$bon instanceof SortieStock) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Bon de sortie introuvable.'], 404);
        }

        if ($this->bonsSortie->signer($bon, $this->getUser(), (string) $request->request->get('observation')) === null) {
            return new JsonResponse([
                'code' => 'error',
                'msg'  => 'Ce bon ne peut pas etre signe dans son etat actuel (' . $bon->getStatutLibelle() . ').',
            ], 409);
        }
        $this->em->flush();

        return new JsonResponse([
            'code' => 'success',
            'msg'  => 'Bon ' . $bon->getReference() . ' signe. La sortie est autorisee.',
            'data' => [
                'bon_reference' => $bon->getReference(),
                'bon_statut'    => $bon->getStatut(),
                'signe_par'     => $bon->getSignePar(),
                'signe_at'      => $bon->getSigneAt()?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Bons de sortie de stock restant a signer (ecran « validations » mobile).
     * Toutes rubriques confondues : le circuit n'est plus reserve au cacao.
     * GET api/sortie-stock/bons-a-signer        (ROLE_DG / ROLE_ADMIN)
     */
    #[Route('api/sortie-stock/bons-a-signer', name: 'api_sortie_stock_bons_a_signer', methods: ['GET'])]
    public function bonsASigner(SortieStockRepository $repo): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }
        if (!$this->isGranted('ROLE_DG')) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Acces refuse : reserve au Directeur General.'], 403);
        }

        $items = [];
        foreach ($repo->findBonsEnAttente() as $s) {
            $items[] = [
                'id'          => (string) $s->getId(),
                'reference'   => $s->getReference(),
                'token'       => $s->getToken(),
                'statut'      => $s->getStatut(),
                'rubrique'    => $s->getRubrique(),
                'nature'      => $s->getNature(),
                'quantite'    => $s->getQuantite(),
                'unite'       => $s->getUnite(),
                'motif'       => $s->getMotif(),
                'service'     => $s->getServiceLibelle(),
                'emplacement' => $s->getEmplacementLibelle(),
                'agent'       => $s->getAgentLibelle(),
                'dateSortie'  => $s->getDateSortie()?->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse(['code' => 'success', 'msg' => 'OK', 'data' => $items]);
    }
    /**
     * Detail d'un bon de sortie (ecran mobile : fiche + QR + statut).
     * GET api/sortie-stock/bon/{token}
     */
    #[Route('api/sortie-stock/bon/{token}', name: 'api_sortie_stock_bon_detail', methods: ['GET'])]
    public function detailBon(string $token, SortieStockRepository $repo): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }

        $bon = $repo->findOneByToken($token);
        if (!$bon instanceof SortieStock) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Bon de sortie introuvable.'], 404);
        }

        return new JsonResponse([
            'code' => 'success',
            'msg'  => 'OK',
            'data' => [
                'reference'   => $bon->getReference(),
                'token'       => $bon->getToken(),
                'statut'      => $bon->getStatut(),
                'statut_libelle' => $bon->getStatutLibelle(),
                'rubrique'    => $bon->getRubrique(),
                'nature'      => $bon->getNature(),
                'quantite'    => $bon->getQuantite(),
                'unite'       => $bon->getUnite(),
                'motif'       => $bon->getMotif(),
                'service'     => $bon->getServiceLibelle(),
                'emplacement' => $bon->getEmplacementLibelle(),
                'agent'       => $bon->getAgentLibelle(),
                'dateSortie'  => $bon->getDateSortie()?->format('Y-m-d H:i:s'),
                'signe_par'   => $bon->getSignePar(),
                'signe_at'    => $bon->getSigneAt()?->format('Y-m-d H:i:s'),
                'observation' => $bon->getObservationSignature(),
                'type'        => 'SORTIE_STOCK',
                'actions'     => [
                    'peut_signer'   => $this->isGranted('ROLE_DG') && $bon->getStatut() === SortieStock::STATUT_EN_ATTENTE,
                    'peut_annuler'  => $this->isGranted('ROLE_DG') && $bon->getStatut() === SortieStock::STATUT_EN_ATTENTE,
                ],
            ],
        ]);
    }

    /**
     * Annulation / refus du bon de sortie par le Directeur General (app mobile).
     * POST api/sortie-stock/bon/{token}/annuler   (ROLE_DG)
     */
    #[Route('api/sortie-stock/bon/{token}/annuler', name: 'api_sortie_stock_bon_annuler', methods: ['POST'])]
    public function annulerBon(string $token, Request $request, SortieStockRepository $repo): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }
        if (!$this->isGranted('ROLE_DG')) {
            return new JsonResponse([
                'code' => 'error',
                'msg'  => 'Acces refuse : annulation reservee au Directeur General.',
            ], 403);
        }

        $bon = $repo->findOneByToken($token);
        if (!$bon instanceof SortieStock) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Bon de sortie introuvable.'], 404);
        }

        if ($this->bonsSortie->annuler($bon, $this->getUser(), (string) $request->request->get('observation')) === null) {
            return new JsonResponse([
                'code' => 'error',
                'msg'  => 'Ce bon ne peut pas etre annule dans son etat actuel (' . $bon->getStatutLibelle() . ').',
            ], 409);
        }
        $this->em->flush();

        return new JsonResponse([
            'code' => 'success',
            'msg'  => 'Bon ' . $bon->getReference() . ' annule.',
            'data' => [
                'bon_reference' => $bon->getReference(),
                'bon_statut'    => $bon->getStatut(),
                'signe_par'     => $bon->getSignePar(),
                'signe_at'      => $bon->getSigneAt()?->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
