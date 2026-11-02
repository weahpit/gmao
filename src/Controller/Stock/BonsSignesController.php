<?php

namespace App\Controller\Stock;

use App\Entity\BonSortie;
use App\Repository\BonSortieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vue récapitulative des bons de sortie signés par le Directeur Général.
 * Affiche les bons au statut VALIDE (signés, pas encore clôturés) et
 * CLOTURE (signés puis clôturés par le vigil).
 */
final class BonsSignesController extends AbstractController
{
    #[Route('/bons-signes', name: 'app_bons_signes', methods: ['GET'])]
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

        $bons = $repo->findSignes($du, $au);

        // Statistiques pour les cartes du haut.
        $nbTotal   = count($bons);
        $nbValide  = 0;
        $nbCloture = 0;
        foreach ($bons as $b) {
            if ($b->getStatut() === BonSortie::STATUT_CLOTURE) {
                $nbCloture++;
            } else {
                $nbValide++;
            }
        }

        return $this->render('mouvements_stock/bons_signes.html.twig', [
            'bons'      => $bons,
            'nbTotal'   => $nbTotal,
            'nbValide'  => $nbValide,
            'nbCloture' => $nbCloture,
            'du'        => $request->query->get('du'),
            'au'        => $request->query->get('au'),
        ]);
    }
}
