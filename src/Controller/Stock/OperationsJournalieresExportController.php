<?php

namespace App\Controller\Stock;

use App\Entity\MouvementEquipement;
use App\Services\pdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Impression PDF et envoi par email des opérations journalières
 * (mouvements de stock d'une date donnée).
 */
final class OperationsJournalieresExportController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    // =====================================================================
    //  1) IMPRESSION PDF
    // =====================================================================
    #[Route('/api/operations-journalieres/pdf', name: 'app_operations_jour_pdf', methods: ['POST', 'GET'])]
    public function pdf(Request $request, pdfService $pdfService): Response
    {
        // Garde utilisateur (l'app n'a pas de pare-feu complet : à sécuriser)
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        if (!($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_PDR'))) {
            return new Response('Vous n\'êtes pas autorisé à accéder à cette ressource', 403);
        }

        $dateStr = $request->request->get('date_operation') ?: date('Y-m-d');
        [$entetes, $largeurs, $lignes, $titre, $sousTitre] = $this->construireTableau($dateStr);

        $contenu = $pdfService->genererTableau($titre, $entetes, $largeurs, $lignes, 'L', $sousTitre);

        return new Response($contenu, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="operations-journalieres-' . $dateStr . '.pdf"',
        ]);

    }

    // =====================================================================
    //  2) ENVOI PAR EMAIL
    // =====================================================================
    #[Route('/api/operations-journalieres/email', name: 'app_operations_jour_email', methods: ['POST'])]
    public function email(Request $request, pdfService $pdfService, MailerInterface $mailer): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        if (!($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_PDR'))) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Vous n\'êtes pas autorisé à accéder à cette ressource']);
        }

        try {
            $dateStr     = $request->request->get('date_operation') ?: date('Y-m-d');
            $destinataire = trim((string) $request->request->get('destinataire'));
            $messageLibre = trim((string) $request->request->get('message'));

            if ($destinataire === '' || !filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
                return new JsonResponse(['code' => 'error', 'msg' => 'Adresse email du destinataire invalide.']);
            }

            [$entetes, $largeurs, $lignes, $titre, $sousTitre] = $this->construireTableau($dateStr);

            if (count($lignes) === 0) {
                return new JsonResponse(['code' => 'warning', 'msg' => 'Aucune opération pour cette date : rien à envoyer.']);
            }

            $pdfBinaire = $pdfService->genererTableau($titre, $entetes, $largeurs, $lignes, 'L', $sousTitre);
            $nomFichier = 'operations-journalieres-' . $dateStr . '.pdf';

            $dateAffichee = (new \DateTime($dateStr))->format('d/m/Y');
            $corps = ($messageLibre !== '' ? $messageLibre . "\n\n" : '')
                . "Bonjour,\n\n"
                . "Veuillez trouver ci-joint le relevé des opérations journalières sur mouvements de stock "
                . "du " . $dateAffichee . " (" . count($lignes) . " opération(s)).\n\n"
                . "Cordialement,\nCITRAC — GMAO";

            $mail = (new Email())
                ->from('infos@citrac.ci')
                ->to($destinataire)
                ->subject('CITRAC GMAO — Opérations journalières du ' . $dateAffichee)
                ->text($corps)
                ->attach($pdfBinaire, $nomFichier, 'application/pdf');

            $mailer->send($mail);

            return new JsonResponse(['code' => 'success', 'msg' => 'Email envoyé à ' . $destinataire . '.']);
        } catch (\Throwable $e) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Envoi impossible : ' . $e->getMessage()]);
        }
    }

    // =====================================================================
    //  Construction du tableau (partagé PDF / Email)
    // =====================================================================
    private function construireTableau(string $dateStr): array
    {
        $dateMvt = new \DateTime($dateStr);

        $mvts = $this->em->getRepository(MouvementEquipement::class)
            ->findBy(['date_operation' => $dateMvt], ['created_at' => 'DESC']);

        // En-têtes + largeurs (mm) pour une page A4 paysage (zone utile ~ 277 mm)
        $entetes  = ['Article', 'Nature', 'Qté', 'Service', 'Emplacement', 'N° Bon', 'Stock avant', 'Stock après', 'Heure', 'Observations'];
        $largeurs = [45,        18,       12,    32,        40,            22,       20,            20,           16,      52];

        $lignes = [];
        foreach ($mvts as $mvt) {
            $nature = ($mvt->getTypeMvt() === '1' || $mvt->getTypeMvt() === 1) ? 'ENTREE' : 'SORTIE';

            $emplacement = $mvt->getEmplacement() ? $mvt->getEmplacement()->getLibelle() : '';
            $precision   = $mvt->getPrecisionEmplacement() ? ' [' . $mvt->getPrecisionEmplacement() . ']' : '';

            $lignes[] = [
                $mvt->getCodeEquipementType() ? $mvt->getCodeEquipementType()->getNomEquipement() : '',
                $nature,
                $mvt->getQte() ?: 0,
                $mvt->getCodeService() ? $mvt->getCodeService()->getLibelle() : '',
                trim($emplacement . $precision),
                $mvt->getNumeroBon() ?: '',
                $mvt->getStockAvant() ?: '',
                $mvt->getStockApres() ?: '',
                $mvt->getCreatedAt() ? $mvt->getCreatedAt()->format('H:i') : '',
                $mvt->getObservation() ?: '',
            ];
        }

        $titre     = 'Opérations Journalières - Mouvements de Stock';
        $sousTitre = 'Date : ' . $dateMvt->format('d/m/Y');

        return [$entetes, $largeurs, $lignes, $titre, $sousTitre];
    }
}
