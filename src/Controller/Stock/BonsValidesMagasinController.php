<?php

namespace App\Controller\Stock;

use App\Entity\BonSortie;
use App\Repository\BonSortieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vue magasinier (ROLE_PDR) : liste des bons validés par la Direction
 * Générale (statut VALIDE, et CLOTURE déjà sortis), avec impression du bon
 * portant la mention « Validé ».
 */
final class BonsValidesMagasinController extends AbstractController
{
    #[Route('/magasin/bons-valides', name: 'app_magasin_bons_valides', methods: ['GET'])]
    public function index(Request $request, BonSortieRepository $repo): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        // Réservé au magasinier (ROLE_PDR) ; ROLE_ADMIN en hérite.
        $this->denyAccessUnlessGranted('ROLE_PDR');

        // Filtre période optionnel : ?du=2026-10-01&au=2026-10-31 (date de signature)
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

        // findSignes renvoie les bons VALIDE + CLOTURE (tous validés par le DG).
        $bons = $repo->findSignes($du, $au);

        $nbValide  = 0;
        $nbCloture = 0;
        foreach ($bons as $b) {
            if ($b->getStatut() === BonSortie::STATUT_CLOTURE) {
                $nbCloture++;
            } else {
                $nbValide++;
            }
        }

        return $this->render('mouvements_stock/bons_valides_magasin.html.twig', [
            'bons'      => $bons,
            'nbTotal'   => count($bons),
            'nbValide'  => $nbValide,
            'nbCloture' => $nbCloture,
            'du'        => $request->query->get('du'),
            'au'        => $request->query->get('au'),
        ]);
    }
}
