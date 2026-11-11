<?php

namespace App\Controller\Api\stock;

use App\Entity\Emplacement;
use App\Entity\Services;
use App\Entity\Stock\SaisieStock;
use App\Entity\Stock\SortieStock;
use App\Repository\Stock\SortieStockRepository;
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
                'unites'       => SaisieStock::UNITES,
                'natures'      => $this->referentiel->natures(),
                'uniteDefaut'  => 'Kg',
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
                'date' => (new \DateTime())->format('Y-m-d H:i:s'),
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
        $unite      = trim((string) $request->request->get('unite')) ?: 'Kg';
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
        if (!in_array($unite, SaisieStock::UNITES, true)) {
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

        // Date de sortie declaree par l'appareil (sinon maintenant).
        if ($dateSortie) {
            try {
                $sortie->setDateSortie(new \DateTime($dateSortie));
            } catch (\Throwable) {
                // on garde la date par defaut du constructeur
            }
        }

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
            $this->em->persist($sortie);
            $this->em->flush();
        } catch (\Throwable $e) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Erreur serveur : ' . $e->getMessage()], 200);
        }

        return new JsonResponse([
            'code' => 'success',
            'msg'  => 'Sortie enregistree.',
            'data' => ['id' => (string) $sortie->getId(), 'duplicate' => false],
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
            ];
        }

        return new JsonResponse(['code' => 'success', 'msg' => 'OK', 'data' => $items]);
    }
}
