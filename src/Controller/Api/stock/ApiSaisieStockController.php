<?php

namespace App\Controller\Api\stock;

use App\Entity\Services;
use App\Entity\Stock\SaisieStock;
use App\Repository\Stock\SaisieStockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API de saisie de stocks (consommee par l'application mobile).
 *
 * Toutes les reponses suivent le format maison {code, msg, data}.
 * code : success | warning | error.
 */
final class ApiSaisieStockController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Referentiel necessaire a l'ecran mobile :
     * rubriques, unites, liste des services, agent connecte (grise) et date serveur.
     */
    #[Route('api/saisie-stock/referentiel', name: 'api_saisie_stock_referentiel', methods: ['GET'])]
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

        $serviceAgent = $user->getCodeService();
        $agentLibelle = trim(($user->getPrenoms() ?? '') . ' ' . ($user->getNom() ?? ''));

        return new JsonResponse([
            'code' => 'success',
            'msg'  => 'OK',
            'data' => [
                'rubriques' => SaisieStock::RUBRIQUES,
                'unites'    => SaisieStock::UNITES,
                'uniteDefaut' => 'Kg',
                'services'  => $services,
                'agent'     => [
                    'id'        => (string) $user->getId(),
                    'libelle'   => $agentLibelle,
                    'matricule' => $user->getMatricule(),
                    'serviceId' => $serviceAgent ? (string) $serviceAgent->getId() : null,
                    'service'   => $serviceAgent ? $serviceAgent->getLibelle() : null,
                ],
                'date' => (new \DateTime())->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Enregistre une saisie de stock (multipart : champs + photo facultative).
     * Idempotent via client_uuid : un renvoi hors-ligne ne cree pas de doublon.
     */
    #[Route('api/saisie-stock', name: 'api_saisie_stock_create', methods: ['POST'])]
    public function create(Request $request, SaisieStockRepository $repo): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $rubrique   = trim((string) $request->request->get('rubrique'));
        $quantite   = $request->request->get('quantite');
        $unite      = trim((string) $request->request->get('unite')) ?: 'Kg';
        $serviceId  = $request->request->get('service');
        $dateSaisie = $request->request->get('date_saisie');
        $clientUuid = trim((string) $request->request->get('client_uuid')) ?: null;

        // --- Idempotence : si deja recu, on renvoie la ressource existante.
        if ($clientUuid && ($existante = $repo->findOneByClientUuid($clientUuid))) {
            return new JsonResponse([
                'code' => 'success',
                'msg'  => 'Saisie deja enregistree',
                'data' => ['id' => (string) $existante->getId(), 'duplicate' => true],
            ]);
        }

        // --- Validations.
        if (!in_array($rubrique, SaisieStock::RUBRIQUES, true)) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Rubrique invalide.'], 200);
        }
        if (!in_array($unite, SaisieStock::UNITES, true)) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Unite invalide.'], 200);
        }
        if ($quantite === null || $quantite === '' || !is_numeric($quantite) || (float) $quantite <= 0) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Quantite invalide.'], 200);
        }

        $saisie = new SaisieStock();
        $saisie->setAgent($user);
        $saisie->setAgentLibelle(trim(($user->getPrenoms() ?? '') . ' ' . ($user->getNom() ?? '')));
        $saisie->setRubrique($rubrique);
        $saisie->setQuantite((float) $quantite);
        $saisie->setUnite($unite);
        $saisie->setClientUuid($clientUuid);

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
            $saisie->setService($service);
            $saisie->setServiceLibelle($service->getLibelle());
        }

        // Date de saisie declaree par l'appareil (sinon maintenant).
        if ($dateSaisie) {
            try {
                $saisie->setDateSaisie(new \DateTime($dateSaisie));
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
            $filename = uniqid('saisie_') . '.' . $ext;
            try {
                $photo->move($this->getParameter('photo_saisie_stock'), $filename);
                $saisie->setPhoto($filename);
            } catch (\Throwable $e) {
                return new JsonResponse(['code' => 'error', 'msg' => 'Echec enregistrement photo.'], 200);
            }
        }

        try {
            $this->em->persist($saisie);
            $this->em->flush();
        } catch (\Throwable $e) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Erreur serveur : ' . $e->getMessage()], 200);
        }

        return new JsonResponse([
            'code' => 'success',
            'msg'  => 'Saisie enregistree.',
            'data' => ['id' => (string) $saisie->getId(), 'duplicate' => false],
        ]);
    }

    /** Historique des dernieres saisies de l'agent connecte. */
    #[Route('api/saisie-stock', name: 'api_saisie_stock_list', methods: ['GET'])]
    public function list(SaisieStockRepository $repo, Request $request): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }

        $limite = (int) ($request->query->get('limit') ?: 50);
        $items = [];
        foreach ($repo->findRecentesParAgent($this->getUser(), $limite) as $s) {
            /** @var SaisieStock $s */
            $items[] = [
                'id'         => (string) $s->getId(),
                'rubrique'   => $s->getRubrique(),
                'quantite'   => $s->getQuantite(),
                'unite'      => $s->getUnite(),
                'service'    => $s->getServiceLibelle(),
                'agent'      => $s->getAgentLibelle(),
                'photo'      => $s->getPhoto(),
                'dateSaisie' => $s->getDateSaisie()?->format('Y-m-d H:i:s'),
                'createdAt'  => $s->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse(['code' => 'success', 'msg' => 'OK', 'data' => $items]);
    }
}
