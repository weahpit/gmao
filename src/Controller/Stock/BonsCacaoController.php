<?php

namespace App\Controller\Stock;

use App\Entity\Stock\SortieStock;
use App\Repository\Stock\SortieStockRepository;
use App\Service\Stock\BonSortieCacaoService;
use App\Services\pdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Bons de sortie CACAO — même circuit que les bons de sortie PDR :
 *   - vue « à signer »  (ROLE_DG / ROLE_ADMIN) : signature ou annulation
 *   - vue « signés »    (ROLE_PDR / ROLE_DG / ROLE_ADMIN)
 *   - signature AJAX POST /bons-cacao-a-signer/{token}/signer
 *   - annulation AJAX POST /bons-cacao-a-signer/{token}/annuler
 *   - impression du bon en PDF (QR de contrôle)
 */
#[Route('/bons-cacao')]
final class BonsCacaoController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private BonSortieCacaoService $bonsCacao,
    ) {}

    /** Bons cacao en attente de la signature du Directeur Général. */
    #[Route('/a-signer', name: 'app_bons_cacao_a_signer', methods: ['GET'])]
    public function aSigner(Request $request, SortieStockRepository $repo): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        // Réservé au Directeur Général (ROLE_ADMIN en hérite).
        $this->denyAccessUnlessGranted('ROLE_DG');

        [$du, $au] = $this->periode($request);
        $bons = $repo->findBonsCacaoEnAttente($du, $au);

        return $this->render('mouvements_stock/bons_cacao_a_signer.html.twig', [
            'bons'    => $bons,
            'nbTotal' => count($bons),
            'du'      => $request->query->get('du'),
            'au'      => $request->query->get('au'),
        ]);
    }

    /** Bons cacao déjà signés par la Direction Générale. */
    #[Route('/signes', name: 'app_bons_cacao_signes', methods: ['GET'])]
    public function signes(Request $request, SortieStockRepository $repo): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        $this->denyAccessUnlessGranted('ROLE_PDR');

        [$du, $au] = $this->periode($request);
        $bons = $repo->findBonsCacaoSignes($du, $au);

        return $this->render('mouvements_stock/bons_cacao_signes.html.twig', [
            'bons'    => $bons,
            'nbTotal' => count($bons),
            'du'      => $request->query->get('du'),
            'au'      => $request->query->get('au'),
        ]);
    }

    /** Signature du bon (AJAX, attendu par public/js/api/stock/BonSortie.js). */
    #[Route('/a-signer/{token}/signer', name: 'app_bon_cacao_signer', methods: ['POST'])]
    public function signer(string $token, Request $request, SortieStockRepository $repo): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['code' => 'error', 'msg' => 'Session expirée, reconnectez-vous.'], 401);
        }
        if (!$this->isGranted('ROLE_DG')) {
            return $this->json([
                'code' => 'error',
                'msg'  => 'Accès refusé : la signature du bon de sortie cacao est réservée au Directeur Général.',
            ], 403);
        }

        $bon = $repo->findOneByToken($token);
        if (!$bon instanceof SortieStock) {
            return $this->json(['code' => 'error', 'msg' => 'Bon de sortie cacao introuvable.'], 404);
        }

        if ($this->bonsCacao->signer($bon, $this->getUser(), (string) $request->request->get('observation')) === null) {
            return $this->json([
                'code' => 'error',
                'msg'  => 'Ce bon ne peut pas être signé dans son état actuel (' . $bon->getStatutLibelle() . ').',
            ], 409);
        }

        $this->em->flush();

        return $this->json([
            'code' => 'success',
            'msg'  => 'Bon ' . $bon->getReference() . ' signé. La sortie de cacao est désormais autorisée.',
            'data' => [
                'reference' => $bon->getReference(),
                'statut'    => $bon->getStatut(),
                'signe_par' => $bon->getSignePar(),
                'signe_at'  => $bon->getSigneAt()?->format('d/m/Y H:i'),
            ],
        ]);
    }

    /** Annulation / refus du bon (AJAX). */
    #[Route('/a-signer/{token}/annuler', name: 'app_bon_cacao_annuler', methods: ['POST'])]
    public function annuler(string $token, Request $request, SortieStockRepository $repo): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['code' => 'error', 'msg' => 'Session expirée, reconnectez-vous.'], 401);
        }
        if (!$this->isGranted('ROLE_DG')) {
            return $this->json(['code' => 'error', 'msg' => 'Accès refusé : réservé au Directeur Général.'], 403);
        }

        $bon = $repo->findOneByToken($token);
        if (!$bon instanceof SortieStock) {
            return $this->json(['code' => 'error', 'msg' => 'Bon de sortie cacao introuvable.'], 404);
        }

        if ($this->bonsCacao->annuler($bon, $this->getUser(), (string) $request->request->get('observation')) === null) {
            return $this->json([
                'code' => 'error',
                'msg'  => 'Ce bon ne peut pas être annulé dans son état actuel (' . $bon->getStatutLibelle() . ').',
            ], 409);
        }

        $this->em->flush();

        return $this->json([
            'code' => 'success',
            'msg'  => 'Bon ' . $bon->getReference() . ' annulé.',
            'data' => ['reference' => $bon->getReference(), 'statut' => $bon->getStatut()],
        ]);
    }

    /** Impression du bon de sortie cacao (PDF avec QR). */
    #[Route('/{reference}/pdf', name: 'app_bon_cacao_pdf', methods: ['GET'])]
    public function pdf(string $reference, SortieStockRepository $repo, pdfService $servicePdf): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        $this->denyAccessUnlessGranted('ROLE_USER');

        $bon = $repo->findOneByReference($reference);
        if (!$bon instanceof SortieStock) {
            return new Response('Bon de sortie cacao introuvable.', 404);
        }

        try {
            $contenu = $servicePdf->genererBonSortie(
                $this->bonsCacao->infosPdf($bon),
                $this->bonsCacao->lignesPdf($bon),
                $this->bonsCacao->qrData($bon)
            );

            return new Response($contenu, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="bon-sortie-cacao-' . $reference . '.pdf"',
            ]);
        } catch (\Throwable $e) {
            return new Response("Une erreur s'est produite ! => " . $e->getMessage(), 500);
        }
    }

    /** Filtre période ?du=AAAA-MM-JJ&au=AAAA-MM-JJ. */
    private function periode(Request $request): array
    {
        $du = $au = null;
        try {
            if ($request->query->get('du')) {
                $du = new \DateTimeImmutable($request->query->get('du') . ' 00:00:00');
            }
            if ($request->query->get('au')) {
                $au = new \DateTimeImmutable($request->query->get('au') . ' 23:59:59');
            }
        } catch (\Exception) {
            $du = $au = null;
        }

        return [$du, $au];
    }
}
