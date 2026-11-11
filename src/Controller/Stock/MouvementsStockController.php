<?php

namespace App\Controller\Stock;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MouvementsStockController extends AbstractController
{
    #[Route('/OperationsEntrees/Stock', name: 'app_mouvements_stock_entree')]
    public function entree(): Response
    {
        return $this->render('mouvements_stock/entree.html.twig');
    }

    #[Route('/OperationsSorties/Stock', name: 'app_mouvements_stock_sortie')]
    public function sorties(): Response
    {
        return $this->render('mouvements_stock/sortie.html.twig');
    }
    #[Route('/OperationsJournalieres/Stock', name: 'app_mouvements_operations_jour')]
    public function operationJour(): Response
    {
        return $this->render('mouvements_stock/operations_jour.html.twig');
    }

    #[Route('/Doublons/Articles', name: 'app_mouvements_doublons_articles')]
    public function doublonsArticles(): Response
    {
        return $this->render('mouvements_stock/doublons.html.twig');
    }

    /**
     * Page d'administration : modifier / supprimer les mouvements de stock
     * des PIECES DE RECHANGE (entite MouvementEquipement).
     */
    #[Route('/Gestion/MouvementsPieces', name: 'app_gestion_mvt_pieces')]
    #[IsGranted('ROLE_ADMIN')]
    public function gestionMvtPieces(): Response
    {
        return $this->render('mouvements_stock/gestion_pieces.html.twig');
    }

    /**
     * Page d'administration : modifier / supprimer les mouvements de stock
     * CACAO (entrees SaisieStock + sorties SortieStock).
     */
    #[Route('/Gestion/MouvementsCacao', name: 'app_gestion_mvt_cacao')]
    #[IsGranted('ROLE_ADMIN')]
    public function gestionMvtCacao(): Response
    {
        return $this->render('mouvements_stock/gestion_cacao.html.twig');
    }

    /**
     * Consultation du journal d'audit des mouvements de stock
     * (lecture seule, append-only).
     */
    #[Route('/Gestion/JournalMouvements', name: 'app_gestion_journal_mvt')]
    #[IsGranted('ROLE_ADMIN')]
    public function journalMvt(): Response
    {
        return $this->render('mouvements_stock/gestion_journal.html.twig');
    }
}
