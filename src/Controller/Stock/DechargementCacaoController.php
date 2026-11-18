<?php

namespace App\Controller\Stock;

use App\Entity\Stock\DechargementCacao;
use App\Repository\Stock\DechargementCacaoRepository;
use App\Repository\Stock\LotDechargementRepository;
use App\Service\Stock\DechargementCacaoImportService as ImportService;
use Doctrine\ORM\EntityManagerInterface;
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
 *   1. GET  /Stock/Dechargement/Import        formulaire
 *   2. POST .../structure                     DIAGNOSTIC : lecture seule du fichier,
 *                                             renvoie l'en-tete lue et la
 *                                             correspondance des colonnes
 *   3. POST .../analyser                      comparaison complete avec la base
 *   4. POST .../enregistrer                   ecriture UNIQUEMENT apres validation
 *   5. GET  .../modele.xlsx                   modele officiel a telecharger
 *   6. GET  /Stock/Dechargements              consultation des dechargements
 *
 * Le fichier est LU UNE SEULE FOIS par le serveur ; le navigateur ne renvoie
 * ensuite que le jeton + les renommages de colonnes eventuels (aucun re-envoi
 * du fichier, donc pas de limite post_max_size).
 *
 * Correctif v2 : les colonnes sont identifiees par leur libelle et non plus par
 * une lettre fixe. Un fichier dont "Cooperative" est en colonne P est lu
 * correctement (plus de faux "Code du bon manquant").
 */
#[Route('/Stock')]
#[IsGranted('ROLE_USER')]
final class DechargementCacaoController extends AbstractController
{
    /** Session : fichier deja lu par le serveur (structure ou rapport en attente). */
    private const SESSION_LECTURE = 'dechargement_cacao_lecture';
    private const SESSION_JETON = 'dechargement_cacao_analyse';

    private const CSRF_ID = 'dechargement_cacao_import';

    /** Champs pour lesquels un renommage manuel de colonne est propose. */
    private const CHAMPS_LIBELLES = [
        'code' => 'Code du bon',
        'date' => 'Date',
        'site' => 'Site',
        'produit' => 'Produit',
        'cooperative' => 'Coopérative',
        'immat' => 'Immatriculation',
        'transporteur' => 'Transporteur',
        'nbreSacs' => 'Nombre de sacs',
        'poidsNet' => 'PoidsNet',
        'statut' => 'Statut',
        'dateDechargement' => 'Date de déchargement',
        'observation' => 'Observation',
        'pnp' => 'PNP',
        'traitant' => 'Traitant',
        'categorie' => 'Catégorie',
    ];

    public function __construct(
        private readonly LecteurTabulaireService $lecteur,
        private readonly ImportService $import,
        private readonly DechargementCacaoRepository $repo,
        private readonly LotDechargementRepository $liens,
        private readonly EntityManagerInterface $em,
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

    /* ==================================================================
     *  1. DIAGNOSTIC : que voit le serveur dans le fichier ?
     * ================================================================== */

    #[Route('/Dechargement/Import/structure', name: 'app_dechargement_cacao_structure', methods: ['POST'])]
    public function structure(Request $request): JsonResponse
    {
        try {
            if (!$this->jetonValide($request)) {
                return $this->erreur('Session expiree. Rechargez la page (Ctrl+F5) puis relancez l\'import.', 419);
            }

            $lecture = $this->lectureDepuisRequete($request);
            $request->getSession()->set(self::SESSION_LECTURE, $lecture);
            $request->getSession()->remove(self::SESSION_JETON);

            return $this->json([
                'code'       => 'success',
                'structure'  => $this->import->structure($lecture),
                'champs'     => self::CHAMPS_LIBELLES,
                'fichierNom' => (string) ($lecture['fichierNom'] ?? ''),
            ]);
        } catch (\RuntimeException $e) {
            return $this->erreur($e->getMessage());
        } catch (\Throwable $e) {
            $this->journaliser('structure', $e);

            return $this->erreur('Lecture impossible du fichier : ' . $e->getMessage());
        }
    }

    /* ==================================================================
     *  2. ANALYSE : comparaison complete avec la base
     * ================================================================== */

    #[Route('/Dechargement/Import/analyser', name: 'app_dechargement_cacao_analyser', methods: ['POST'])]
    public function analyser(Request $request): JsonResponse
    {
        try {
            if (!$this->jetonValide($request)) {
                return $this->erreur('Session expiree. Rechargez la page (Ctrl+F5) puis relancez l\'import.', 419);
            }

            // Deux chemins : le fichier vient d'etre envoye, ou il a deja ete lu (diagnostic).
            $nouveau = $request->files->get('fichier');
            if ($nouveau instanceof UploadedFile) {
                $lecture = $this->lectureDepuisRequete($request);
            } else {
                $lecture = $request->getSession()->get(self::SESSION_LECTURE);
                if (!is_array($lecture) || !isset($lecture['lignesBrutes'])) {
                    return $this->erreur('Le fichier n\'est plus disponible sur le serveur (session expiree). Sélectionnez-le à nouveau puis relancez l\'analyse.', 409);
                }
                $lecture = $this->appliquerCorrections($lecture, $request);
            }

            $rapport = $this->import->analyser($lecture);

            // Le serveur calcule lui-meme l'empreinte : impossible de rejouer un
            // enregistrement avec un fichier different de celui qui a ete montre.
            $empreinte = hash('sha256', (string) ($lecture['fichierNom'] ?? 'fichier') . '|' . json_encode($rapport['lignes'], JSON_UNESCAPED_UNICODE));
            $jeton = (string) Uuid::v4();

            $request->getSession()->set(self::SESSION_JETON, [
                'empreinte' => $empreinte,
                'jeton'     => $jeton,
                'at'        => time(),
                'fichier'   => (string) ($lecture['fichierNom'] ?? ''),
            ]);

            foreach ($rapport['lignes'] as $i => $ligne) {
                $rapport['lignes'][$i]['empreinte'] = $empreinte;
            }

            $manquantes = $rapport['fichier']['colonnesAbsentes'];
            $message = $manquantes === []
                ? 'Analyse terminee.'
                : 'Colonne(s) obligatoire(s) non reconnue(s) : ' . implode(', ', $manquantes)
                    . '. Corrigez la ligne d\'en-tete ou renommez les colonnes, puis relancez l\'analyse.';

            return $this->json([
                'code'      => $manquantes === [] ? 'success' : 'warning',
                'msg'       => $message,
                'empreinte' => $empreinte,
                'jeton'     => $jeton,
                'rapport'   => $rapport,
                'fichier'   => (string) ($lecture['fichierNom'] ?? ''),
            ]);
        } catch (\RuntimeException $e) {
            return $this->erreur($e->getMessage());
        } catch (\Throwable $e) {
            $this->journaliser('analyse', $e);

            return $this->erreur('Analyse impossible : ' . $e->getMessage());
        }
    }

    /* ==================================================================
     *  3. ENREGISTREMENT
     * ================================================================== */

    #[Route('/Dechargement/Import/enregistrer', name: 'app_dechargement_cacao_enregistrer', methods: ['POST'])]
    public function enregistrer(Request $request): JsonResponse
    {
        try {
            if (!$this->jetonValide($request)) {
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
            $request->getSession()->remove(self::SESSION_LECTURE);

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

    /* ==================================================================
     *  Modeles + consultation
     * ================================================================== */

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

    /* ------------------------------------------------------------------
     * Modification / suppression (ROLE_ADMIN)
     * ------------------------------------------------------------------ */

    /**
     * Formulaire de modification d'un dechargement deja enregistre.
     *
     * Reserve au ROLE_ADMIN : l'import est la voie normale, la modification
     * manuelle sert a corriger une saisie erronee (poids, date, cooperative...).
     * La date de dechargement y est OBLIGATOIRE, comme a l'import.
     */
    #[Route('/Dechargements/{id}/modifier', name: 'app_dechargement_cacao_modifier', methods: ['GET', 'POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('ROLE_ADMIN')]
    public function modifier(DechargementCacao $dechargement, Request $request): Response
    {
        $erreurs = [];
        $saisie = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('dechargement_cacao_edition', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Session expiree, merci de recommencer.');

                return $this->redirectToRoute('app_dechargement_cacao_modifier', ['id' => $dechargement->getId()]);
            }

            /** @var array<string,mixed> $saisie */
            $saisie = $request->request->all();

            $code = trim((string) ($saisie['code'] ?? ''));
            $dateBon = $this->dateSaisie($saisie['date_bon'] ?? null);
            $dateDechargement = $this->dateSaisie($saisie['date_dechargement'] ?? null);
            $poidsNet = $this->nombreSaisi($saisie['poids_net'] ?? null);
            $nbreSacs = $this->nombreSaisi($saisie['nbre_sacs'] ?? null);
            $pnp = $this->nombreSaisi($saisie['pnp'] ?? null);

            if ($code === '') {
                $erreurs[] = 'Le code du bon est obligatoire.';
            } elseif (mb_strlen($code) > 60) {
                $erreurs[] = 'Le code du bon ne doit pas depasser 60 caracteres.';
            } else {
                $existant = $this->repo->findOneByCode($code);
                if ($existant !== null && (string) $existant->getId() !== (string) $dechargement->getId()) {
                    $erreurs[] = 'Un autre dechargement porte deja le code ' . $code . '.';
                }
            }

            if ($dateDechargement === null) {
                $erreurs[] = 'La date de dechargement est obligatoire.';
            }
            if ($poidsNet === null || $poidsNet <= 0) {
                $erreurs[] = 'Le poids net doit etre un nombre superieur a zero.';
            }
            if ($nbreSacs !== null && $nbreSacs < 0) {
                $erreurs[] = 'Le nombre de sacs ne peut pas etre negatif.';
            }
            if ($dateBon !== null && $dateDechargement !== null && $dateDechargement < $dateBon) {
                $erreurs[] = 'La date de dechargement ne peut pas etre anterieure a la date du bon.';
            }

            if ($erreurs === []) {
                $dechargement->setCode($code)
                    ->setDateBon($dateBon)
                    ->setSite($this->texteSaisi($saisie['site'] ?? null))
                    ->setProduit($this->texteSaisi($saisie['produit'] ?? null))
                    ->setCooperative($this->texteSaisi($saisie['cooperative'] ?? null))
                    ->setImmatriculation($this->texteSaisi($saisie['immatriculation'] ?? null))
                    ->setTransporteur($this->texteSaisi($saisie['transporteur'] ?? null))
                    ->setNbreSacs($nbreSacs !== null ? (int) round($nbreSacs) : null)
                    ->setPoidsNet($poidsNet)
                    ->setStatut($this->texteSaisi($saisie['statut'] ?? null))
                    ->setDateDechargement($dateDechargement)
                    ->setObservation($this->texteSaisi($saisie['observation'] ?? null))
                    ->setPnp($pnp)
                    ->setTraitant($this->texteSaisi($saisie['traitant'] ?? null))
                    ->setCategorie($this->texteSaisi($saisie['categorie'] ?? null))
                    ->setUpdatedAt(new \DateTimeImmutable())
                    ->setUpdatedBy($this->identiteUtilisateur());

                try {
                    $this->em->flush();
                    $this->addFlash('success', 'Le dechargement ' . $code . ' a ete mis a jour.');

                    return $this->redirectToRoute('app_dechargements_cacao');
                } catch (\Throwable $e) {
                    $this->logger->error('Modification dechargement : echec', ['code' => $code, 'erreur' => $e->getMessage()]);
                    $erreurs[] = 'Enregistrement impossible : ' . $e->getMessage();
                }
            }
        }

        return $this->render('dechargement_cacao/edit.html.twig', [
            'dechargement' => $dechargement,
            'erreurs'      => $erreurs,
            'saisie'       => $saisie,
            'nbLots'       => $this->liens->compterPourDechargement($dechargement),
        ]);
    }

    /**
     * Suppression definitive d'un dechargement (ROLE_ADMIN).
     *
     * Refusee si le camion alimente deja un lot fabrique : la tracabilite serait
     * rompue. Il faut d'abord retirer l'association depuis la page du lot.
     */
    #[Route('/Dechargements/{id}/supprimer', name: 'app_dechargement_cacao_supprimer', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    #[IsGranted('ROLE_ADMIN')]
    public function supprimer(DechargementCacao $dechargement, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('dechargement_cacao_suppression', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expiree, merci de recommencer.');

            return $this->redirectToRoute('app_dechargements_cacao');
        }

        $code = (string) $dechargement->getCode();
        $liens = $this->liens->parDechargement($dechargement);

        if ($liens !== []) {
            $numeros = [];
            foreach (array_slice($liens, 0, 4) as $lien) {
                $numero = $lien->getLot()?->getNumeroLot();
                if ($numero !== null) {
                    $numeros[] = $numero;
                }
            }
            $this->addFlash('danger', sprintf(
                'Suppression refusee : le bon %s alimente le(s) lot(s) %s. Retirez d\'abord l\'association depuis la page du lot (Tracabilite).',
                $code,
                implode(', ', $numeros) . (count($liens) > 4 ? '...' : '')
            ));

            return $this->redirectToRoute('app_dechargements_cacao');
        }

        try {
            $this->em->remove($dechargement);
            $this->em->flush();
            $this->addFlash('success', 'Le dechargement ' . $code . ' a ete supprime.');
        } catch (\Throwable $e) {
            $this->logger->error('Suppression dechargement : echec', ['code' => $code, 'erreur' => $e->getMessage()]);
            $this->addFlash('danger', 'Suppression impossible : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_dechargements_cacao');
    }

    /* ------------------------------------------------------------------
     * Outils
     * ------------------------------------------------------------------ */

    /** Accepte 2026-11-18 comme 18/11/2026. */
    private function dateSaisie(mixed $valeur): ?\DateTimeInterface
    {
        if (!is_scalar($valeur)) {
            return null;
        }
        $texte = trim((string) $valeur);
        if ($texte === '') {
            return null;
        }

        // Les colonnes date_bon / date_dechargement sont de type "mutable" :
        // on renvoie donc un DateTime (et non un DateTimeImmutable).
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y', '!Y-m-d H:i:s', '!d/m/Y H:i'] as $format) {
            $date = \DateTime::createFromFormat($format, $texte);
            if ($date instanceof \DateTime) {
                return $date;
            }
        }

        return null;
    }

    private function nombreSaisi(mixed $valeur): ?float
    {
        if (!is_scalar($valeur)) {
            return null;
        }
        $texte = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim((string) $valeur));

        return is_numeric($texte) ? (float) $texte : null;
    }

    private function texteSaisi(mixed $valeur): ?string
    {
        $texte = is_scalar($valeur) ? trim((string) $valeur) : '';

        return $texte === '' ? null : $texte;
    }

    private function identiteUtilisateur(): string
    {
        $utilisateur = $this->getUser();

        return null !== $utilisateur && method_exists($utilisateur, 'getUserIdentifier')
            ? (string) $utilisateur->getUserIdentifier()
            : 'system';
    }

    /**
     * Lit le fichier envoye et applique la ligne d'en-tete / les renommages demandes.
     *
     * @return array<string,mixed>
     */
    private function lectureDepuisRequete(Request $request): array
    {
        $fichier = $request->files->get('fichier');
        if (!$fichier instanceof UploadedFile) {
            throw new \RuntimeException('Aucun fichier recu. Selectionnez un fichier .xlsx, .xls ou .csv.');
        }

        $feuille = trim((string) $request->request->get('feuille', ''));
        $lecture = $this->lecteur->lire($fichier, $feuille !== '' ? $feuille : null);
        $lecture['fichierNom'] = $fichier->getClientOriginalName();

        return $this->appliquerCorrections($lecture, $request);
    }

    /**
     * Applique sur une lecture deja faite : ligne d'en-tete imposee + colonnes renommees.
     *
     * @param array<string,mixed> $lecture
     *
     * @return array<string,mixed>
     */
    private function appliquerCorrections(array $lecture, Request $request): array
    {
        $enteteForce = (int) $request->request->get('entete', 0);
        if ($enteteForce > 0) {
            $lecture = $this->forcerEntete($lecture, $enteteForce);
        }

        // Colonnes renommees : { "P": "Cooperative", ... } ou { "cooperative": "Coop CA" }
        $renommages = $request->request->all('colonnes');
        $lettres = $request->request->all('lettres');

        if (is_array($renommages)) {
            foreach ($renommages as $cle => $libelle) {
                $cle = trim((string) $cle);
                $libelle = trim((string) $libelle);
                if ($cle === '' || $libelle === '') {
                    continue;
                }
                // Cle = lettre de colonne ("P") ou nom de champ ("cooperative").
                $lettre = preg_match('/^[A-Za-z]{1,3}$/', $cle) === 1
                    ? strtoupper($cle)
                    : strtoupper((string) ($lettres[$cle] ?? ''));
                if ($lettre !== '' && isset($lecture['entetes'][$lettre])) {
                    $lecture['entetes'][$lettre] = $libelle;
                }
            }
        }

        return $lecture;
    }

    /**
     * Relit la feuille en prenant une autre ligne comme en-tete.
     * Les lignes au-dessus de l'en-tete forcee sont ignorees.
     *
     * @param array<string,mixed> $lecture
     *
     * @return array<string,mixed>
     */
    private function forcerEntete(array $lecture, int $numeroLigne): array
    {
        $brutes = $lecture['lignesBrutes'] ?? [];
        if (!isset($brutes[$numeroLigne - 1])) {
            throw new \RuntimeException(sprintf('Le fichier ne contient pas de ligne %d (seulement %d lignes).', $numeroLigne, count($brutes)));
        }

        $entetes = [];
        foreach ($brutes[$numeroLigne - 1] as $adresse => $libelle) {
            $entetes[(string) $adresse] = trim((string) $libelle);
        }

        $lignes = [];
        foreach (array_slice($brutes, $numeroLigne) as $ligne) {
            $colonne = [];
            foreach ($entetes as $adresse => $libelle) {
                $colonne[$adresse] = (string) ($ligne[$adresse] ?? '');
            }
            if (trim(implode('', $colonne)) === '') {
                continue;
            }
            $lignes[] = $colonne;
        }

        $lecture['entetes'] = $entetes;
        $lecture['lignes'] = $lignes;
        $lecture['ligneEntete'] = $numeroLigne;

        return $lecture;
    }

    /**
     * Jeton anti-CSRF accepte soit dans le champ classique `_token`,
     * soit dans l'en-tete `X-CSRF-Token`.
     */
    private function jetonValide(Request $request): bool
    {
        $jeton = (string) ($request->request->get('_token') ?? '');
        if ($jeton === '') {
            $jeton = (string) $request->headers->get('X-CSRF-Token', '');
        }

        return $this->isCsrfTokenValid(self::CSRF_ID, $jeton);
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
