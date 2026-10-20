<?php

namespace App\Controller\Maintenance;

use App\Entity\Maintenance\FicheIntervention;
use App\Services\FicheInterventionPdfService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FicheInterventionController extends AbstractController
{
    #[Route('/maintenance/fiche/intervention', name: 'app_maintenance_fiche_intervention')]
    public function index(): Response
    {
        return $this->render('maintenance/fiche_intervention/index.html.twig', [
            'controller_name' => 'FicheInterventionController',
        ]);
    }

    #[Route('PrintFicheMaintenance/{id}/pdf', name: 'app_maintenance_fiche_intervention_pdf', methods: ['GET'])]
    public function pdf(FicheIntervention $fiche, FicheInterventionPdfService $pdfService): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        return new Response($pdfService->generer($fiche), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="fiche_intervention.pdf"',
        ]);
    }
}
