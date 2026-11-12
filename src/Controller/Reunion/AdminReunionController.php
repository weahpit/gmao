<?php

namespace App\Controller\Reunion;

use App\Entity\Reunion\ParticipantReunion;
use App\Entity\Reunion\Reunion;
use App\Repository\Reunion\ParticipantReunionRepository;
use App\Repository\Reunion\ReunionRepository;
use App\Service\Reunion\QrCodeReunion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des reunions par l'administrateur (session ouverte).
 *
 *  - /admin/reunions                      : liste + creation + lien public
 *  - /admin/reunions/{id}                 : detail, participants, compte rendu, cloture
 *  - /admin/reunions/{id}/compte-rendu    : chargement du PDF
 *  - /admin/reunions/{id}/cloturer        : cloture
 *  - /admin/reunions/{id}/liste-presence  : export CSV des presences
 */
#[Route('/admin/reunions')]
#[IsGranted('ROLE_ADMIN')]
final class AdminReunionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire('%reunion_comptes_rendus%')] private string $dossierComptesRendus,
    ) {}

    private function qrDe(?Reunion $reunion, int $taille = 500): ?string
    {
        if (!$reunion) {
            return null;
        }

        return (new QrCodeReunion())->dataUri(
            $this->generateUrl('app_presence_reunion', ['jeton' => $reunion->getJeton()], UrlGeneratorInterface::ABSOLUTE_URL),
            $taille
        );
    }

    #[Route('', name: 'app_admin_reunions', methods: ['GET', 'POST'])]
    public function index(Request $request, ReunionRepository $repo): Response
    {
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reunion_creation', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Session expiree, merci de reessayer.');

                return $this->redirectToRoute('app_admin_reunions');
            }

            $titre     = trim((string) $request->request->get('titre', ''));
            $objet     = trim((string) $request->request->get('objet', ''));
            $lieu      = trim((string) $request->request->get('lieu', ''));
            $dateDebut = trim((string) $request->request->get('date_debut', ''));
            $dateFin   = trim((string) $request->request->get('date_fin', ''));

            if ($titre === '') {
                $erreurs[] = 'Le titre de la reunion est obligatoire.';
            }
            if ($dateDebut === '') {
                $erreurs[] = 'La date et l\'heure de debut sont obligatoires.';
            }

            if ($erreurs === []) {
                try {
                    $reunion = (new Reunion())
                        ->setTitre($titre)
                        ->setObjet($objet !== '' ? $objet : null)
                        ->setLieu($lieu !== '' ? $lieu : null)
                        ->setDateDebut(new \DateTimeImmutable($dateDebut))
                        ->setStatut(Reunion::STATUT_OUVERTE);

                    if ($dateFin !== '') {
                        $reunion->setDateFin(new \DateTimeImmutable($dateFin));
                    }

                    $this->em->persist($reunion);
                    $this->em->flush();

                    $this->addFlash('success', 'Reunion creee et publiee : les participants peuvent s\'enregistrer.');

                    return $this->redirectToRoute('app_admin_reunions_detail', ['id' => $reunion->getId()]);
                } catch (\Throwable) {
                    $erreurs[] = 'Les dates saisies sont invalides.';
                }
            }
        }

        $active = $repo->findActive();

        return $this->render('reunion/admin_index.html.twig', [
            'reunions'      => $repo->findToutes(),
            'erreurs'       => $erreurs,
            'statuts'       => Reunion::STATUTS,
            'reunionActive' => $active,
            'qrImage'       => $this->qrDe($active, 500),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_reunions_detail', methods: ['GET'])]
    public function detail(string $id, ReunionRepository $repo, ParticipantReunionRepository $participants): Response
    {
        $reunion = $repo->find($id);
        if (!$reunion) {
            throw $this->createNotFoundException('Reunion introuvable.');
        }

        $lienRelatif = $this->generateUrl('app_presence_reunion', ['jeton' => $reunion->getJeton()]);

        return $this->render('reunion/admin_detail.html.twig', [
            'reunion'      => $reunion,
            'qrImage'      => $reunion->isOuverte() ? $this->qrDe($reunion, 400) : null,
            'participants' => $participants->findParReunion($reunion),
            'dossierComptesRendus' => $this->dossierComptesRendus,
            'statuts'      => Reunion::STATUTS,
            'lienPublic'   => $lienRelatif,
        ]);
    }

    /** Ouverture / suspension des inscriptions. */
    #[Route('/{id}/statut/{statut}', name: 'app_admin_reunions_statut', methods: ['POST'])]
    public function changerStatut(string $id, string $statut, Request $request, ReunionRepository $repo): Response
    {
        $reunion = $repo->find($id);
        if (!$reunion) {
            throw $this->createNotFoundException('Reunion introuvable.');
        }

        if (!$this->isCsrfTokenValid('reunion_statut_' . $reunion->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expiree, merci de reessayer.');

            return $this->redirectToRoute('app_admin_reunions_detail', ['id' => $reunion->getId()]);
        }

        if (!array_key_exists($statut, Reunion::STATUTS)) {
            $this->addFlash('danger', 'Statut inconnu.');

            return $this->redirectToRoute('app_admin_reunions_detail', ['id' => $reunion->getId()]);
        }

        $reunion->setStatut($statut);
        if ($statut === Reunion::STATUT_CLOTUREE) {
            $reunion->setClotureeLe(new \DateTimeImmutable());
        } else {
            $reunion->setClotureeLe(null);
        }
        $this->em->flush();

        $this->addFlash('success', 'Statut mis a jour : ' . Reunion::STATUTS[$statut] . '.');

        return $this->redirectToRoute('app_admin_reunions_detail', ['id' => $reunion->getId()]);
    }

    /** Cloture definitive de la reunion. */
    #[Route('/{id}/cloturer', name: 'app_admin_reunions_cloturer', methods: ['POST'])]
    public function cloturer(string $id, Request $request, ReunionRepository $repo): Response
    {
        $reunion = $repo->find($id);
        if (!$reunion) {
            throw $this->createNotFoundException('Reunion introuvable.');
        }

        if (!$this->isCsrfTokenValid('reunion_cloture_' . $reunion->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expiree, merci de reessayer.');

            return $this->redirectToRoute('app_admin_reunions_detail', ['id' => $reunion->getId()]);
        }

        $reunion->setStatut(Reunion::STATUT_CLOTUREE)->setClotureeLe(new \DateTimeImmutable());
        $this->em->flush();

        $this->addFlash('success', 'Reunion cloturee. Les participants peuvent consulter le compte rendu publie.');

        return $this->redirectToRoute('app_admin_reunions_detail', ['id' => $reunion->getId()]);
    }

    /** Chargement du compte rendu PDF + choix de publication aux participants. */
    #[Route('/{id}/compte-rendu', name: 'app_admin_reunions_cr', methods: ['POST'])]
    public function uploadCompteRendu(string $id, Request $request, ReunionRepository $repo): Response
    {
        $reunion = $repo->find($id);
        if (!$reunion) {
            throw $this->createNotFoundException('Reunion introuvable.');
        }

        $retour = $this->redirectToRoute('app_admin_reunions_detail', ['id' => $reunion->getId()]);

        if (!$this->isCsrfTokenValid('reunion_cr_' . $reunion->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expiree, merci de reessayer.');

            return $retour;
        }

        /** @var UploadedFile|null $fichier */
        $fichier = $request->files->get('compte_rendu');
        if (!$fichier) {
            $this->addFlash('danger', 'Aucun fichier recu.');

            return $retour;
        }

        if (strtolower((string) $fichier->getClientOriginalExtension()) !== 'pdf') {
            $this->addFlash('danger', 'Le compte rendu doit etre un fichier PDF.');

            return $retour;
        }

        if ((int) $fichier->getSize() > 15 * 1024 * 1024) {
            $this->addFlash('danger', 'Le fichier depasse 15 Mo.');

            return $retour;
        }

        $dossier = $this->getParameter('kernel.project_dir') . '/public/' . $this->dossierComptesRendus;
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0775, true);
        }

        // Remplace l'ancien fichier le cas echeant.
        if ($reunion->getCompteRendu()) {
            $ancien = $dossier . $reunion->getCompteRendu();
            if (is_file($ancien)) {
                @unlink($ancien);
            }
        }

        $nom = 'cr_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $fichier->move($dossier, $nom);

        $reunion->setCompteRendu($nom)
            ->setCompteRenduNomOriginal($fichier->getClientOriginalName())
            ->setCompteRenduAjouteLe(new \DateTimeImmutable())
            ->setCompteRenduPublie((bool) $request->request->get('publier'));

        $this->em->flush();

        $this->addFlash('success', 'Compte rendu enregistre' . ($reunion->isCompteRenduPublie() ? ' et publie aux participants.' : ' (non publie).'));

        return $retour;
    }

    /** Le compte rendu est-il visible par les participants ? */
    #[Route('/{id}/compte-rendu/publier', name: 'app_admin_reunions_cr_publier', methods: ['POST'])]
    public function publierCompteRendu(string $id, Request $request, ReunionRepository $repo): Response
    {
        $reunion = $repo->find($id);
        if (!$reunion) {
            throw $this->createNotFoundException('Reunion introuvable.');
        }

        if (!$this->isCsrfTokenValid('reunion_cr_pub_' . $reunion->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expiree, merci de reessayer.');

            return $this->redirectToRoute('app_admin_reunions_detail', ['id' => $reunion->getId()]);
        }

        $reunion->setCompteRenduPublie(!$reunion->isCompteRenduPublie());
        $this->em->flush();

        $this->addFlash('success', $reunion->isCompteRenduPublie()
            ? 'Compte rendu visible par les participants.'
            : 'Compte rendu retire de la consultation par les participants.');

        return $this->redirectToRoute('app_admin_reunions_detail', ['id' => $reunion->getId()]);
    }

    /** Telechargement du compte rendu par l'admin (meme s'il n'est pas publie). */
    #[Route('/{id}/compte-rendu/voir', name: 'app_admin_reunions_cr_voir', methods: ['GET'])]
    public function voirCompteRendu(string $id, ReunionRepository $repo): Response
    {
        $reunion = $repo->find($id);
        if (!$reunion || !$reunion->getCompteRendu()) {
            throw $this->createNotFoundException('Aucun compte rendu.');
        }

        $chemin = $this->getParameter('kernel.project_dir') . '/public/' . $this->dossierComptesRendus . $reunion->getCompteRendu();
        if (!is_file($chemin)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        return new BinaryFileResponse($chemin, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="compte-rendu.pdf"',
        ]);
    }

    /** Export CSV de la liste de presence. */
    #[Route('/{id}/liste-presence.csv', name: 'app_admin_reunions_csv', methods: ['GET'])]
    public function exportCsv(string $id, ReunionRepository $repo, ParticipantReunionRepository $participants): Response
    {
        $reunion = $repo->find($id);
        if (!$reunion) {
            throw $this->createNotFoundException('Reunion introuvable.');
        }

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['N°', 'Nom', 'Prenoms', 'Service', 'Qualite', 'Telephone', 'Email', 'Signature', 'Enregistre le'], ';');

        $i = 0;
        foreach ($participants->findParReunion($reunion) as $p) {
            /** @var ParticipantReunion $p */
            $i++;
            fputcsv($fh, [
                $i,
                $p->getNom(),
                $p->getPrenoms(),
                $p->getService() ?? '',
                $p->getQualite() ?? '',
                $p->getTelephone() ?? '',
                $p->getEmail() ?? '',
                $p->getSignature() ? 'oui' : 'non',
                $p->getCreatedAt()?->format('d/m/Y H:i'),
            ], ';');
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return new Response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="presences-' . date('Ymd-His') . '.csv"',
        ]);
    }
}
