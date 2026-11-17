<?php

namespace App\Controller\Stock;

use App\Repository\Stock\DechargementCacaoRepository;
use App\Service\Stock\DechargementCacaoImportService as ImportService;
use App\Service\Stock\LecteurTabulaireService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Import de dechargement de cacao (fichier Excel / CSV) avec rapport de
 * comparaison a la base et confirmation explicite avant enregistrement.
 *
 * Parcours :
 *   1. GET  /Stock/Dechargement/Import        page + modele vierge
 *   2. POST .../analyser                      lecture du fichier -> rapport (aucune ecriture)
 *   3. POST .../enregistrer                   ecriture UNIQUEMENT apres validation utilisateur
 *   4. GET  .../modele.xlsx                   modele officiel a telecharger
 *   5. GET  /Stock/Dechargements              consultation des dechargements enregistres
 */
#[Route('/Stock')]
#[IsGranted('ROLE_USER')]
final class DechargementCacaoController extends AbstractController
{
    /** Jeton d'etape : seules les analyses emises par CE serveur peuvent etre confirmees. */
    private const SESSION_JETON = 'dechargement_cacao_analyse';

    public function __construct(
        private readonly LecteurTabulaireService $lecteur,
        private readonly ImportService $import,
        private readonly DechargementCacaoRepository $repo,
        private readonly LoggerInterface $logger,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%modele_dechargement_cacao%')]
        private readonly string $modeleXlsx,
    ) {}

    #[Route('/Dechargement/Import', name: 'app_dechargement_cacao_import', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('dechargement_cacao/index.html.twig', [
            'totaux'       => $this->repo->totaux(),
            'cooperatives' => $this->repo->cooperatives(),
            'tailleMax'    => LecteurTabulaireService::TAILLE_MAX,
            'extensions'   => implode(',', LecteurTabulaireService::EXTENSIONS),
        ]);
    }

    #[Route('/Dechargement/Import/analyser', name: 'app_dechargement_cacao_analyser', methods: ['POST'])]
    public function analyser(Request $request): JsonResponse
    {
        try {
            if (!$this->isCsrfTokenValid('dechargement_cacao_import', (string) $request->request->get('_token'))) {
                return $this->erreur('Session expiree. Rechargez la page puis relancez l\'import.', 419);
            }

            $fichier = $request->files->get('fichier');
            if (!$fichier instanceof UploadedFile) {
                return $this->erreur('Aucun fichier recu. Selectionnez un fichier .xlsx, .xls ou .csv.');
            }

            $feuille = trim((string) $request->request->get('feuille', ''));
            $lecture = $this->lecteur->lire($fichier, $feuille !== '' ? $feuille : null);
            $rapport = $this->import->analyser($lecture);

            // Le server calcule lui-meme l'empreinte du contenu : impossible de rejouer
            // un enregistrement avec un fichier different de celui qui a ete montre.
            $empreinte = hash('sha256', (string) $fichier->getClientOriginalName() . '|' . json_encode($rapport['lignes'], JSON_UNESCAPED_UNICODE));
            $jeton = (string) Uuid::v4();

            $request->getSession()->set(self::SESSION_JETON, [
                'empreinte' => $empreinte,
                'jeton'     => $jeton,
                'at'        => time(),
                'fichier'   => $fichier->getClientOriginalName(),
            ]);

            foreach ($rapport['lignes'] as $i => $ligne) {
                $rapport['lignes'][$i]['empreinte'] = $empreinte;
            }

            return $this->json([
                'code'      => 'success',
                'msg'       => 'Analyse terminee.',
                'empreinte' => $empreinte,
                'jeton'     => $jeton,
                'rapport'   => $rapport,
            ]);
        } catch (\RuntimeException $e) {
            return $this->erreur($e->getMessage());
        } catch (\Throwable $e) {
            $this->journaliser('analyse', $e);

            return $this->erreur('Lecture impossible du fichier : ' . $e->getMessage());
        }
    }

    #[Route('/Dechargement/Import/enregistrer', name: 'app_dechargement_cacao_enregistrer', methods: ['POST'])]
    public function enregistrer(Request $request): JsonResponse
    {
        try {
            if (!$this->isCsrfTokenValid('dechargement_cacao_import', (string) $request->request->get('_token'))) {
                return $this->erreur('Session expiree. Rechargez la page puis relancez l\'import.', 419);
            }

            $session = $request->getSession()->get(self::SESSION_JETON);
            $jeton = (string) $request->request->get('jeton', '');
            $empreinte = (string) $request->request->get('empreinte', '');

            if (!is_array($session) || ($session['jeton'] ?? null) !== $jeton || $jeton === '') {
                return $this->erreur('Aucune analyse en cours (ou page rechargee). Relancez l\'analyse du fichier.', 409);
            }
            if (($session['empreinte'] ?? null) !== $empreinte) {
                return $this->erreur('Le fichier analyse ne correspond plus a celui affiche. Relancez l\'analyse.', 409);
            }
            if (time() - (int) ($session['at'] ?? 0) > 3600) {
                $request->getSession()->remove(self::SESSION_JETON);

                return $this->erreur('L\'analyse date de plus d\'une heure. Relancez-la pour enregistrer.', 409);
            }

            $codeLignes = $request->request->all('lignes');
            $aEnregistrer = [];
            $aRetirer = [];
            foreach ($codeLignes as $donnees) {
                if (!is_array($donnees)) {
                    continue;
                }
                if (($donnees['action'] ?? 'enregistrer') === 'retirer') {
                    $aRetirer[] = (string) ($donnees['code'] ?? '');
                    continue;
                }
                $aEnregistrer[] = $donnees;
            }

            if ($aEnregistrer === []) {
                return $this->erreur('Aucune ligne a enregistrer. Cochez au moins une ligne du rapport.');
            }

            /** @var \App\Entity\User|null $user */
            $user = $this->getUser();
            $utilisateur = trim(((string) ($user?->getPrenoms() ?? '')) . ' ' . ((string) ($user?->getNom() ?? ''))) ?: (string) ($user?->getUserIdentifier() ?? 'inconnu');

            $resultat = $this->import->enregistrer($aEnregistrer, $empreinte, $utilisateur, 'IMPORT_XLS');
            $request->getSession()->remove(self::SESSION_JETON);

            return $this->json([
                'code'     => 'success',
                'msg'      => sprintf('%d bon(s) cree(s), %d mis a jour.', $resultat['creees'], $resultat['mises_a_jour']),
                'resultat' => $resultat,
                'retires'  => $aRetirer,
                'totaux'   => $this->repo->totaux(),
            ]);
        } catch (\RuntimeException $e) {
            return $this->erreur($e->getMessage(), 409);
        } catch (\Throwable $e) {
            $this->journaliser('enregistrement', $e);

            return $this->erreur('Enregistrement impossible : ' . $e->getMessage(), 500);
        }
    }

    /** Modele CSV a telecharger (ouvrable directement dans Excel). */
    #[Route('/Dechargement/Import/modele.csv', name: 'app_dechargement_cacao_modele_csv', methods: ['GET'])]
    public function modeleCsv(): Response
    {
        $entetes = [
            'Code', 'Date', 'Site', 'Produit', 'Cooperative', 'Immat', 'Transporteur',
            'Nbre Sacs', 'PoidsNet', 'Statut', 'Date de Déchargement', 'Obersavation',
            'PNP', 'Traitant', 'Categorie',
        ];

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $entetes, ';');
        fputcsv($fh, [
            'SC101389-2025-2-15', '05/12/2025 11:52', 'US28', 'CACAO(Ordinaire)', 'COOP-CA ZRANOUWE',
            'AA-056-BJ01', 'CITRAC', 262, '17413', 'Accepté(05/12/2025)', '08/12/2025',
            'accepté', '17966', 'moussa man', 'COOP EURD',
        ], ';');
        rewind($fh);
        $contenu = (string) stream_get_contents($fh);
        fclose($fh);

        // BOM UTF-8 pour qu'Excel affiche correctement les accents.
        return new Response("\xEF\xBB\xBF" . $contenu, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele_dechargement_cacao.csv"',
        ]);
    }

    /** Modele .xlsx officiel (recopie du fichier fourni). */
    #[Route('/Dechargement/Import/modele.xlsx', name: 'app_dechargement_cacao_modele_xlsx', methods: ['GET'])]
    public function modeleXlsx(): Response
    {
        if (!is_file($this->modeleXlsx)) {
            return new Response('Modele indisponible. Utilisez le modele CSV.', 404);
        }

        return $this->file($this->modeleXlsx, 'template_dechargement_cacao.xlsx');
    }

    #[Route('/Dechargements', name: 'app_dechargements_cacao', methods: ['GET'])]
    public function consultation(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $terme = trim((string) $request->query->get('q', ''));
        $cooperative = trim((string) $request->query->get('cooperative', ''));

        $resultat = $this->repo->rechercher($terme, $cooperative, $page, 25);

        return $this->render('dechargement_cacao/consultation.html.twig', [
            'items'        => $resultat['items'],
            'total'        => $resultat['total'],
            'page'         => $page,
            'parPage'      => 25,
            'terme'        => $terme,
            'cooperative'  => $cooperative,
            'cooperatives' => $this->repo->cooperatives(),
            'totaux'       => $this->repo->totaux(),
        ]);
    }

    private function erreur(string $message, int $statut = 422): JsonResponse
    {
        return $this->json(['code' => 'error', 'msg' => $message], $statut);
    }

    private function journaliser(string $etape, \Throwable $e): void
    {
        // Trace dans var/log/ : sans cela, un echec d'import serait totalement muet.
        $this->logger->error('Import dechargement cacao (' . $etape . ') : ' . $e->getMessage(), [
            'exception' => $e,
        ]);
    }
}
