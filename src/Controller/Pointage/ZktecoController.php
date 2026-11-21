<?php

namespace App\Controller\Pointage;

use App\Security\RhAcces;
use App\Service\Pointage\ZktecoAccess;
use App\Service\Pointage\ZktecoParametres;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Connexion a la pointeuse ZKTECO et automatisation des imports.
 *
 * Page reservee au service RH et a l'administrateur : elle permet de saisir
 * le chemin du fichier Access, de tester la connexion, de lancer un import
 * et de preparer la tache planifiee qui alimentera les pointages chaque jour.
 */
#[Route('/pointage/zkteco', name: 'pointage_zkteco')]
class ZktecoController extends AbstractController
{
    public function __construct(
        private RhAcces $acces,
        private ZktecoParametres $parametres,
        private ZktecoAccess $zkteco,
        private KernelInterface $kernel,
    ) {}

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->exigerGestion();

        $session = $request->getSession();
        $diagnostic = $session->get('zkteco_diagnostic');
        $session->remove('zkteco_diagnostic');

        return $this->render('pointage/zkteco.html.twig', $this->contexte($diagnostic));
    }

    #[Route('/enregistrer', name: '_enregistrer', methods: ['POST'])]
    public function enregistrer(Request $request): Response
    {
        $this->exigerGestion();

        if (!$this->isCsrfTokenValid('zkteco_parametres', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expire. Rechargez la page puis recommencez.');

            return $this->redirectToRoute('pointage_zkteco');
        }

        $fichier = (string) $request->request->get('fichier', '');
        $dsn = trim((string) $request->request->get('dsn', ''));
        $jours = (int) $request->request->get('jours_reprise', 3);
        $tolerance = (int) $request->request->get('tolerance', 5);
        $heure = trim((string) $request->request->get('heure', '07:30'));
        $auto = (string) $request->request->get('auto_actif', '') === '1';

        if ($fichier === '' && $dsn === '') {
            $this->addFlash('danger', 'Indiquez au moins le chemin du fichier Access (\\serveur\\partage\\att2000.mdb) ou le nom d\'un DSN ODBC.');

            return $this->redirectToRoute('pointage_zkteco');
        }

        if ($heure !== '' && !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $heure)) {
            $this->addFlash('danger', 'Heure invalide : « ' . $heure . ' ». Utilisez le format HH:MM, par exemple 07:30.');

            return $this->redirectToRoute('pointage_zkteco');
        }

        try {
            $this->parametres->enregistrer([
                'fichier' => $fichier,
                'dsn' => $dsn,
                'jours_reprise' => $jours,
                'tolerance' => $tolerance,
                'heure' => $heure,
                'auto_actif' => $auto,
            ], (string) ($this->getUser()?->getUserIdentifier() ?? ''));
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', 'Parametres non enregistres : ' . $e->getMessage());

            return $this->redirectToRoute('pointage_zkteco');
        }

        $this->addFlash('success', 'Parametres ZKTECO enregistres' . ($auto ? ' et import automatique active.' : '.'));

        return $this->redirectToRoute('pointage_zkteco');
    }

    #[Route('/tester', name: '_tester', methods: ['POST'])]
    public function tester(Request $request): Response
    {
        $this->exigerGestion();

        if (!$this->isCsrfTokenValid('zkteco_test', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expire. Rechargez la page puis recommencez.');

            return $this->redirectToRoute('pointage_zkteco');
        }

        try {
            $diagnostic = $this->zkteco->verifierSource();
        } catch (\Throwable $e) {
            $diagnostic = [
                'erreur' => 'Le test a echoue : ' . $e->getMessage(),
                'fichier' => $this->zkteco->etatFichier(),
            ];
        }

        $request->getSession()->set('zkteco_diagnostic', $diagnostic);

        return $this->redirectToRoute('pointage_zkteco');
    }

    #[Route('/importer', name: '_importer', methods: ['POST'])]
    public function importer(Request $request): Response
    {
        $this->exigerGestion();

        if (!$this->isCsrfTokenValid('zkteco_import', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expire. Rechargez la page puis recommencez.');

            return $this->redirectToRoute('pointage_zkteco');
        }

        $jours = max(1, min(90, (int) $request->request->get('jours', 3)));

        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $sortie = new BufferedOutput();
        try {
            $code = $application->run(new ArrayInput([
                'command' => 'app:pointage:zkteco:auto',
                '--jours' => $jours,
                '--force' => true,
                '--silence' => true,
            ]), $sortie);
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Import interrompu : ' . $e->getMessage());

            return $this->redirectToRoute('pointage_zkteco');
        }

        $texte = trim($sortie->fetch());
        $this->addFlash($code === 0 ? 'success' : 'danger', $code === 0
            ? 'Import lance sur les ' . $jours . ' dernier(s) jour(s). Le detail figure dans l\'historique ci-dessous.'
            : 'Import en echec. ' . $texte);

        return $this->redirectToRoute('pointage_zkteco');
    }

    /** @param array<string, mixed>|null $diagnostic */
    private function contexte(?array $diagnostic): array
    {
        $config = $this->parametres->tous();

        return [
            'config' => $config,
            'etatFichier' => $this->zkteco->etatFichier((string) ($config['fichier'] ?: null)),
            'drivers' => $this->zkteco->driversDisponibles(),
            'historique' => $this->parametres->historique(),
            'derniere' => $this->parametres->derniereExecution(),
            'diagnostic' => $diagnostic,
            'dossierInscriptible' => $this->parametres->dossierInscriptible(),
            'cheminConfiguration' => $this->parametres->fichierConfiguration(),
            'commandeAuto' => 'php bin/console app:pointage:zkteco:auto',
        ];
    }

    private function exigerGestion(): void
    {
        if (!$this->acces->aRole('ROLE_RH', 'ROLE_DRH')) {
            throw new AccessDeniedException('La connexion ZKTECO est reservee au service RH et a l\'administrateur.');
        }
    }
}
