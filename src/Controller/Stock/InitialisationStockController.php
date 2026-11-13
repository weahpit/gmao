<?php

namespace App\Controller\Stock;

use App\Entity\Stock\InitialisationStock;
use App\Repository\Stock\InitialisationStockRepository;
use App\Service\Stock\ReferentielStock;
use App\Service\Stock\StockInitialService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Initialisation des types de stock a une date d'inventaire physique.
 *
 * L'administrateur declare, pour un type de stock (rubrique), la quantite
 * reellement comptee lors de l'inventaire physique a la date D.
 * Le stock final = quantite inventoriee + mouvements POSTERIEURS a D.
 *
 * Ecran : templates/saisie_stock/initialisation.html.twig
 * API JSON mobile : /api/stock/initialisation/*
 */
#[Route('/stock')]
final class InitialisationStockController extends AbstractController
{
    public function __construct(
        private readonly StockInitialService $service,
        private readonly InitialisationStockRepository $initRepo,
        private readonly ReferentielStock $referentiel,
    ) {}

    /** Controle d'acces commun a toutes les actions. */
    private function garder(): ?Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        return null;
    }

    // ------------------------------------------------------------------ WEB

    #[Route('/initialisation', name: 'app_stock_initialisation', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($redirection = $this->garder()) {
            return $redirection;
        }
        $this->denyAccessUnlessGranted('ROLE_USER');
        $peutModifier = $this->isGranted('ROLE_ADMIN');

        $erreur = null;
        $succes = null;
        $saisie = [
            'rubrique'       => '',
            'nature'         => '',
            'quantite'       => '',
            'unite'          => $this->referentiel->uniteDefaut(),
            'dateInventaire' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'observation'    => '',
            'remplacer'      => false,
        ];

        if ($request->isMethod('POST')) {
            if (!$peutModifier) {
                $erreur = "Seul un administrateur peut initialiser les stocks.";
            } elseif (!$this->isCsrfTokenValid('initialisation_stock', (string) $request->request->get('_token'))) {
                $erreur = 'Votre session a expire, merci de recharger la page.';
            } else {
                $saisie = [
                    'rubrique'       => trim((string) $request->request->get('rubrique', '')),
                    'nature'         => trim((string) $request->request->get('nature', '')),
                    'quantite'       => trim((string) $request->request->get('quantite', '')),
                    'unite'          => trim((string) $request->request->get('unite', '')),
                    'dateInventaire' => trim((string) $request->request->get('dateInventaire', '')),
                    'observation'    => trim((string) $request->request->get('observation', '')),
                    'remplacer'      => (bool) $request->request->get('remplacer', false),
                ];

                $resultat = $this->service->initialiser($saisie, $this->getUser());
                if ($resultat['succes']) {
                    $this->addFlash('success', $resultat['message']);
                    return $this->redirectToRoute('app_stock_initialisation');
                }
                $erreur = $resultat['message'];
            }
        }

        return $this->render('saisie_stock/initialisation.html.twig', [
            'apercu'       => $this->service->apercu(),
            'initialisations' => $this->initRepo->actives(),
            'historique'   => $this->initRepo->historique(50),
            'rubriques'    => $this->referentiel->rubriques(),
            'natures'      => $this->referentiel->natures(),
            'unites'       => $this->referentiel->unites(),
            'uniteDefaut'  => $this->referentiel->uniteDefaut(),
            'saisie'       => $saisie,
            'erreur'       => $erreur,
            'peutModifier' => $peutModifier,
        ]);
    }

    #[Route('/initialisation/{id}/desactiver', name: 'app_stock_initialisation_desactiver', methods: ['POST'])]
    public function desactiver(InitialisationStock $initialisation, Request $request): Response
    {
        if ($redirection = $this->garder()) {
            return $redirection;
        }
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('desactiver_initialisation_' . $initialisation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Votre session a expire, merci de recharger la page.');

            return $this->redirectToRoute('app_stock_initialisation');
        }

        $this->service->desactiver($initialisation);
        $this->addFlash('success', sprintf(
            'Initialisation de « %s » desactivee : le stock repart de l\'historique complet des mouvements.',
            $initialisation->getRubrique()
        ));

        return $this->redirectToRoute('app_stock_initialisation');
    }

    // ------------------------------------------------------------------ API

    /** Cree (ou remplace) une initialisation. */
    #[Route('/api/initialisation', name: 'api_stock_initialisation_creer', methods: ['POST'])]
    public function apiCreer(Request $request): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['code' => 401, 'msg' => 'Authentification requise.', 'data' => null], 401);
        }
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['code' => 403, 'msg' => 'Seul un administrateur peut initialiser les stocks.', 'data' => null], 403);
        }

        $donnees = json_decode((string) $request->getContent(), true);
        if (!is_array($donnees)) {
            $donnees = $request->request->all();
        }

        $resultat = $this->service->initialiser($donnees, $this->getUser());
        if (!$resultat['succes']) {
            return $this->json(['code' => 400, 'msg' => $resultat['message'], 'data' => null], 400);
        }

        return $this->json([
            'code' => 200,
            'msg'  => $resultat['message'],
            'data' => $this->service->versApi($resultat['initialisation']),
        ]);
    }

    /** Desactive une initialisation. */
    #[Route('/api/initialisation/desactiver', name: 'api_stock_initialisation_desactiver', methods: ['POST'])]
    public function apiDesactiver(Request $request): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['code' => 401, 'msg' => 'Authentification requise.', 'data' => null], 401);
        }
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['code' => 403, 'msg' => 'Seul un administrateur peut modifier les initialisations.', 'data' => null], 403);
        }

        $donnees = json_decode((string) $request->getContent(), true);
        $id = is_array($donnees) ? ($donnees['id'] ?? null) : $request->request->get('id');
        $init = null;
        if ($id !== null && $id !== '') {
            try {
                $init = $this->initRepo->find(\Symfony\Component\Uid\Uuid::fromString((string) $id));
            } catch (\InvalidArgumentException) {
                $init = null;
            }
        }
        if ($init === null) {
            return $this->json(['code' => 404, 'msg' => 'Initialisation introuvable.', 'data' => null], 404);
        }

        $this->service->desactiver($init);

        return $this->json(['code' => 200, 'msg' => 'Initialisation desactivee.', 'data' => $this->service->versApi($init)]);
    }

    /** Apercu du calcul (stock initial, mouvements posterieurs, stock final). */
    #[Route('/api/initialisation/apercu', name: 'api_stock_initialisation_apercu', methods: ['GET'])]
    public function apiApercu(): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['code' => 401, 'msg' => 'Authentification requise.', 'data' => null], 401);
        }

        return $this->json([
            'code' => 200,
            'msg'  => 'OK',
            'data' => ['regle' => 'Stock final = quantite inventoriee + entrees posterieures - sorties posterieures'] + $this->service->apercu(),
        ]);
    }

    /**
     * Meme appel que ci-dessus, sans le prefixe /stock : l'application mobile
     * peut demander api/stock/initialisation/stock-final ou
     * stock/api/initialisation/stock-final, les deux repondent la meme chose.
     */
    #[Route('/api/stock/initialisation/stock-final', name: 'api_stock_final_court', methods: ['GET'], priority: 20)]
    public function apiStockFinalCourt(): JsonResponse
    {
        return $this->apiStockFinal();
    }

    /** Stock final par type de stock (consommation mobile). */
    #[Route('/api/initialisation/stock-final', name: 'api_stock_final', methods: ['GET'])]
    public function apiStockFinal(): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['code' => 401, 'msg' => 'Authentification requise.', 'data' => null], 401);
        }

        return $this->json(['code' => 200, 'msg' => 'OK', 'data' => $this->service->stockFinal()]);
    }
}
