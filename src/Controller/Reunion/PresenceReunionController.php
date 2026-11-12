<?php

namespace App\Controller\Reunion;

use App\Entity\Reunion\ParticipantReunion;
use App\Entity\Reunion\Reunion;
use App\Entity\Services;
use App\Repository\Reunion\ParticipantReunionRepository;
use App\Repository\Reunion\ReunionRepository;
use App\Service\Reunion\QrCodeReunion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Page PUBLIQUE (hors session / sans connexion) d'enregistrement de presence
 * a une reunion ouverte.
 *
 * Acces par lien direct contenant un jeton : /presence/reunion/{jeton}
 * Aucune sidebar : la page utilise le gabarit autonome presence_base.html.twig.
 *
 *  - GET  /presence/reunion/{jeton}          -> formulaire d'inscription (ou compte rendu si cloturee)
 *  - POST /presence/reunion/{jeton}/signer   -> enregistrement de la presence
 *  - GET  /presence/reunion/{jeton}/recu/{id}-> recu individuel de presence
 *  - GET  /presence/reunion                  -> accueil public : QR code de la reunion active
 *  - GET  /presence/reunion/qr/{jeton}       -> affiche plein ecran du QR code
 */
final class PresenceReunionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire('%reunion_signatures%')] private string $dossierSignatures,
        #[Autowire('%reunion_comptes_rendus%')] private string $dossierComptesRendus,
    ) {}

    private function genererQr(?Reunion $reunion): ?string
    {
        if (!$reunion) {
            return null;
        }

        return (new QrCodeReunion())->dataUri(
            $this->generateUrl('app_presence_reunion', ['jeton' => $reunion->getJeton()], UrlGeneratorInterface::ABSOLUTE_URL),
            620
        );
    }

    private function trouver(string $jeton): ?Reunion
    {
        return $this->em->getRepository(Reunion::class)->findParJeton($jeton);
    }

    /** Contexte commun envoye a la vue. */
    private function contexte(Reunion $reunion): array
    {
        $services = $this->em->getRepository(Services::class)->findBy([], ['libelle' => 'ASC']);

        return [
            'reunion'          => $reunion,
            'services'         => $services,
            'dossierSignatures' => $this->dossierSignatures,
        ];
    }

    /**
     * Accueil public : affiche le QR code du lien d'enregistrement de la
     * reunion ACTIVE (la plus recente ouverte aux inscriptions).
     * Le participant scanne le QR code avec son telephone pour s'enregistrer.
     */
    #[Route('/presence/reunion', name: 'app_presence_reunion_accueil', methods: ['GET'])]
    public function accueil(ReunionRepository $reunions): Response
    {
        $active = $reunions->findActive();

        $lien = $active
            ? $this->generateUrl('app_presence_reunion', ['jeton' => $active->getJeton()], UrlGeneratorInterface::ABSOLUTE_URL)
            : null;

        return $this->render('reunion/presence_accueil.html.twig', [
            'reunion' => $active,
            'lien'    => $lien,
            'qrImage' => $this->genererQr($active),
        ]);
    }

    /** Affichage plein ecran du QR code d'une reunion (a projeter ou imprimer). */
    #[Route('/presence/reunion/qr/{jeton}', name: 'app_presence_reunion_qr', methods: ['GET'])]
    public function qr(string $jeton): Response
    {
        $reunion = $this->trouver($jeton);
        if (!$reunion) {
            return new Response('Reunion introuvable', 404);
        }

        return $this->render('reunion/presence_qr.html.twig', [
            'reunion' => $reunion,
            'lien'    => $this->generateUrl('app_presence_reunion', ['jeton' => $reunion->getJeton()], UrlGeneratorInterface::ABSOLUTE_URL),
            'qrImage' => $this->genererQr($reunion),
        ]);
    }

    #[Route('/presence/reunion/{jeton}', name: 'app_presence_reunion', methods: ['GET'], priority: -10)]
    public function formulaire(string $jeton, ParticipantReunionRepository $participants): Response
    {
        $reunion = $this->trouver($jeton);
        if (!$reunion) {
            return $this->render('reunion/presence.html.twig', [
                'reunion' => null,
                'introuvable' => true,
            ], new Response('', 404));
        }

        return $this->render('reunion/presence.html.twig', array_merge($this->contexte($reunion), [
            'participants' => $participants->findParReunion($reunion),
            'envoye'       => false,
            'erreurs'      => [],
            'ancien'       => [],
        ]));
    }

    #[Route('/presence/reunion/{jeton}/signer', name: 'app_presence_reunion_signer', methods: ['POST'])]
    public function signer(
        string $jeton,
        Request $request,
        ParticipantReunionRepository $participants
    ): Response {
        $reunion = $this->trouver($jeton);
        if (!$reunion) {
            return $this->render('reunion/presence.html.twig', [
                'reunion' => null,
                'introuvable' => true,
            ], new Response('', 404));
        }

        $ancien = [
            'nom'         => trim((string) $request->request->get('nom', '')),
            'prenoms'     => trim((string) $request->request->get('prenoms', '')),
            'serviceId'   => trim((string) $request->request->get('service', '')),
            'serviceAuto' => trim((string) $request->request->get('service_libre', '')),
            'qualite'     => trim((string) $request->request->get('qualite', '')),
            'telephone'   => trim((string) $request->request->get('telephone', '')),
            'email'       => trim((string) $request->request->get('email', '')),
        ];

        // Seule une reunion ouverte accepte de nouvelles presences.
        if (!$reunion->isOuverte()) {
            return $this->render('reunion/presence.html.twig', array_merge($this->contexte($reunion), [
                'participants' => $participants->findParReunion($reunion),
                'envoye'       => false,
                'erreurs'      => ['La reunion n\'est plus ouverte aux inscriptions.'],
                'ancien'       => $ancien,
            ]), new Response('', 403));
        }

        $erreurs = [];

        if (!$this->isCsrfTokenValid('presence_' . $reunion->getJeton(), (string) $request->request->get('_token'))) {
            $erreurs[] = 'Votre session a expire, merci de renvoyer le formulaire.';
        }

        if ($ancien['nom'] === '') {
            $erreurs[] = 'Le nom est obligatoire.';
        }
        if ($ancien['prenoms'] === '') {
            $erreurs[] = 'Les prenoms sont obligatoires.';
        }

        $tel = preg_replace('/[^0-9+]/', '', $ancien['telephone']);
        if (strlen(preg_replace('/[^0-9]/', '', $tel)) < 8) {
            $erreurs[] = 'Le numero de telephone doit contenir au moins 8 chiffres.';
        }

        // Adresse email (facultative, mais verifiee si elle est saisie).
        $email = mb_strtolower($ancien['email']);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = 'L\'adresse email saisie n\'est pas valide.';
        }

        // Service : choisi dans la liste, ou saisi librement s'il n'existe pas.
        $serviceLibelle = $ancien['serviceAuto'];
        if ($ancien['serviceId'] !== '') {
            $service = $this->em->getRepository(Services::class)->find($ancien['serviceId']);
            if ($service) {
                $serviceLibelle = (string) $service->getLibelle();
            }
        }
        if ($ancien['qualite'] === '') {
            $erreurs[] = 'La qualite (fonction) est obligatoire.';
        }

        // Signature manuscrite : image PNG transmise en base64 par le canvas.
        $signatureB64 = (string) $request->request->get('signature_data', '');
        if (!preg_match('#^data:image/png;base64,#', $signatureB64)) {
            $erreurs[] = 'La signature est obligatoire : tracez-la dans le cadre prevu.';
        }

        if ($erreurs === []) {
            if ($participants->existeDeja($reunion, $ancien['nom'], $tel, $email)) {
                $erreurs[] = 'Ce participant semble deja enregistre pour cette reunion (meme nom et meme telephone).';
            }
        }

        $nomFichierSignature = null;
        if ($erreurs === []) {
            $binaire = base64_decode(substr($signatureB64, strlen('data:image/png;base64,')), true);
            if ($binaire === false || strlen($binaire) < 100) {
                $erreurs[] = 'La signature est vide ou illisible.';
            } else {
                $dossier = $this->getParameter('kernel.project_dir') . '/public/' . $this->dossierSignatures;
                if (!is_dir($dossier)) {
                    @mkdir($dossier, 0775, true);
                }
                $nomFichierSignature = 'sig_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
                if (@file_put_contents($dossier . $nomFichierSignature, $binaire) === false) {
                    $erreurs[] = 'Impossible d\'enregistrer la signature, merci de reessayer.';
                    $nomFichierSignature = null;
                }
            }
        }

        if ($erreurs !== []) {
            return $this->render('reunion/presence.html.twig', array_merge($this->contexte($reunion), [
                'participants' => $participants->findParReunion($reunion),
                'envoye'       => false,
                'erreurs'      => $erreurs,
                'ancien'       => $ancien,
            ]), new Response('', 422));
        }

        $participant = new ParticipantReunion();
        $participant->setReunion($reunion)
            ->setNom(mb_strtoupper($ancien['nom']))
            ->setPrenoms($ancien['prenoms'])
            ->setService($serviceLibelle !== '' ? $serviceLibelle : null)
            ->setQualite($ancien['qualite'])
            ->setTelephone($tel)
            ->setEmail($email !== '' ? $email : null)
            ->setSignature($nomFichierSignature)
            ->setIp($request->getClientIp());

        $this->em->persist($participant);
        $this->em->flush();

        return $this->render('reunion/presence.html.twig', array_merge($this->contexte($reunion), [
            'participants' => $participants->findParReunion($reunion),
            'envoye'       => true,
            'erreurs'      => [],
            'ancien'       => [],
            'dernier'      => $participant,
        ]));
    }

    /** Recu individuel imprimable (preuve de presence). */
    #[Route('/presence/reunion/{jeton}/recu/{id}', name: 'app_presence_reunion_recu', methods: ['GET'])]
    public function recu(string $jeton, string $id): Response
    {
        $reunion = $this->trouver($jeton);
        if (!$reunion) {
            return new Response('Reunion introuvable', 404);
        }

        $participant = $this->em->getRepository(ParticipantReunion::class)->findOneBy([
            'id'      => $id,
            'reunion' => $reunion,
        ]);

        if (!$participant) {
            return new Response('Participant introuvable', 404);
        }

        return $this->render('reunion/presence_recu.html.twig', [
            'reunion'     => $reunion,
            'participant' => $participant,
            'dossierSignatures' => $this->dossierSignatures,
        ]);
    }

    /** Compte rendu PDF, consultable par les participants quand l'admin l'a publie. */
    #[Route('/presence/reunion/{jeton}/compte-rendu', name: 'app_presence_reunion_cr', methods: ['GET'])]
    public function compteRendu(string $jeton): Response
    {
        $reunion = $this->trouver($jeton);
        if (!$reunion || !$reunion->isCompteRenduConsultable()) {
            return new Response('Compte rendu non disponible', 404);
        }

        $chemin = $this->getParameter('kernel.project_dir') . '/public/' . $this->dossierComptesRendus . $reunion->getCompteRendu();
        if (!is_file($chemin)) {
            return new Response('Compte rendu non disponible', 404);
        }

        $nom = 'CR-' . preg_replace('/[^a-z0-9]+/i', '-', (string) $reunion->getTitre()) . '.pdf';

        return new BinaryFileResponse($chemin, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nom . '"',
        ]);
    }
}
