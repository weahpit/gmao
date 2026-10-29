<?php

namespace App\Controller\Stock;

use App\Entity\BonSortie;
use App\Repository\BonSortieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vue récapitulative des bons de sortie NON signés par le Directeur Général
 * (statut EN_ATTENTE), avec un bouton de signature/validation par ligne.
 */
final class BonsASignerController extends AbstractController
{
    #[Route('/bons-a-signer', name: 'app_bons_a_signer', methods: ['GET'])]
    public function index(Request $request, BonSortieRepository $repo): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        // Réservé au Directeur Général (ROLE_ADMIN en hérite).
        $this->denyAccessUnlessGranted('ROLE_DG');

        // Filtre période optionnel : ?du=2026-10-01&au=2026-10-31
        $du = null;
        $au = null;
        try {
            if ($request->query->get('du')) {
                $du = new \DateTimeImmutable($request->query->get('du') . ' 00:00:00');
            }
            if ($request->query->get('au')) {
                $au = new \DateTimeImmutable($request->query->get('au') . ' 23:59:59');
            }
        } catch (\Exception $e) {
            $du = $au = null;
        }

        $bons = $repo->findEnAttente($du, $au);

        return $this->render('mouvements_stock/bons_a_signer.html.twig', [
            'bons' => $bons,
            'nbTotal' => count($bons),
            'du' => $request->query->get('du'),
            'au' => $request->query->get('au'),
        ]);
    }
}
