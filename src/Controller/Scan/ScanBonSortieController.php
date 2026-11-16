<?php

declare(strict_types=1);

namespace App\Controller\Scan;

use App\Entity\BonSortie;
use App\Entity\Stock\SortieStock;
use App\Service\Reunion\QrCodeReunion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * PAGE PUBLIQUE DE SCAN QR DES BONS DE SORTIE (guerite / magasin).
 *
 * Le bon imprime porte un QR code qui encode un JSON :
 *   {app: CITRAC, typ: BON_SORTIE | BON_SORTIE_STOCK, ref: ..., tok: ...}
 * Cette page lit ce contenu et affiche la fiche du bon (lignes, signatures,
 * cloture) pour le controle a la sortie des marchandises.
 *
 * Routes (declarees PUBLIQUES dans security.yaml) :
 *   - GET /scan              : page de scan (camera) ou saisie de la reference
 *   - GET /scan/{code}       : meme page, avec un bon deja cible (lien direct)
 *   - GET /api/scan/{code}   : fiche JSON d'un bon (utilisee par la page)
 *
 * Deux familles de bons sont reconnues :
 *   - BON_SORTIE       : App\Entity\BonSortie (mouvements de materiel / PDR)
 *   - BON_SORTIE_STOCK : App\Entity\Stock\SortieStock (sorties de stock par rubrique)
 * Le jeton est cherche dans les deux tables : l'agent n'a donc jamais a
 * preciser de quel type de bon il s'agit.
 *
 * Aucune donnee sensible n'est exposee (pas de prix, pas de photo, pas
 * d'identifiant de connexion) et les routes sont en LECTURE SEULE : la
 * cloture se fait toujours depuis l'application mobile, avec un compte
 * authentifie. Le code du bon sert de cle : il est long et aleatoire.
 */
#[Route('/scan')]
final class ScanBonSortieController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private QrCodeReunion $qr,
    ) {}

    /** Page de scan (camera) + saisie manuelle de la reference du bon. */
    #[Route('', name: 'app_scan_bon', methods: ['GET'], priority: 20)]
    public function page(): Response
    {
        return $this->rendre(null);
    }

    /**
     * Lien direct /scan/<code> (QR imprime sur le bon, ou lien partage) :
     * le bon est affiche tout de suite, la camera reste disponible dessous
     * pour enchainer sur les bons suivants.
     */
    #[Route('/{code}', name: 'app_scan_bon_code', methods: ['GET'], requirements: ['code' => '[A-Za-z0-9_\-]{3,80}'])]
    public function pageCode(string $code): Response
    {
        return $this->rendre($code);
    }

    /** Fiche JSON d'un bon (par jeton, ou a defaut par reference). */
    #[Route('/api/scan/{code}', name: 'app_scan_bon_api', methods: ['GET'], priority: 60, requirements: ['code' => '[^/]{1,80}'])]
    public function api(string $code): JsonResponse
    {
        [$bon, $constat] = $this->lireBonAvecDiagnostic($code);
        if ($bon === null) {
            return $this->json([
                'code' => 'warning',
                'msg'  => 'Bon introuvable : le code lu ne correspond a aucun bon de sortie enregistre.'
                    . ' Verifiez qu\'il s\'agit bien d\'un bon CITRAC, puis saisissez sa reference imprimee (ex. BS-2026-0001).',
                'diagnostic' => $constat,
            ], 404);
        }

        return $this->json([
            'code' => 'success',
            'msg'  => 'Bon trouve.',
            'data' => $this->serialiser($bon),
        ]);
    }

    /** Rendu commun de la page de scan. */
    private function rendre(?string $code): Response
    {
        $cible = ($code !== null && $code !== '') ? $this->lireBon($code) : null;

        return $this->render('scan/bon_sortie.html.twig', [
            'cible_initiale' => $cible !== null ? $this->serialiser($cible) : null,
            'url_api'        => $this->generateUrl('app_scan_bon_api', ['code' => '__CODE__']),
            // Actions du vigil : la page les attend (sinon "Faire sortir" / "Signaler" ne marchent pas).
            'url_faire_sortir' => $this->generateUrl('app_scan_bon_faire_sortir'),
            'url_signaler'     => $this->generateUrl('app_scan_bon_signaler'),
            'url_page_bon'   => $this->generateUrl('app_scan_bon_code', ['code' => '__CODE__']),
            'qr_page'        => $this->qr->dataUri(
                $this->generateUrl('app_scan_bon', [], UrlGeneratorInterface::ABSOLUTE_URL),
                420
            ),
        ]);
    }

    // ------------------------------------------------------------------
    //  ACTIONS DU VIGIL (guerite) : FAIRE SORTIR / SIGNALER
    // ------------------------------------------------------------------
    /**
     * Le vigil confirme la sortie : le bon est CLOTURE et la marchandise
     * peut quitter le site.
     *
     *   POST /scan/api/faire-sortir   { code: "<token|reference>", poste_controle: "..." }
     */
    #[Route('/api/faire-sortir', name: 'app_scan_bon_faire_sortir', methods: ['POST'], priority: 60)]
    public function faireSortir(Request $request): JsonResponse
    {
        $bon = $this->lireBon($this->codeDepuisRequete($request));
        if ($bon === null) {
            return $this->json([
                'code' => 'warning',
                'msg'  => 'Aucun bon de sortie ne correspond a ce code.',
            ], 404);
        }

        $etat = $this->etatControle($bon);
        if ($etat['deja_cloture']) {
            return $this->json([
                'code' => 'info',
                'msg'  => 'Ce bon a deja ete cloture' . ($etat['cloture_at'] ? ' le ' . $etat['cloture_at'] : '') . '.',
                'data' => $this->serialiser($bon),
            ], 200);
        }
        if ($etat['deja_refuse']) {
            return $this->json([
                'code' => 'error',
                'msg'  => 'Ce bon a ete signale et refuse' . ($etat['refus_motif'] ? ' : ' . $etat['refus_motif'] : '') . '. La sortie n\'est pas autorisee.',
                'data' => $this->serialiser($bon),
            ], 409);
        }
        if (!$etat['peut_sortir']) {
            return $this->json([
                'code' => 'warning',
                'msg'  => $etat['raison'],
                'data' => $this->serialiser($bon),
            ], 409);
        }

        $poste = trim((string) ($this->valeurRequete($request, 'poste_controle') ?? ''));
        $libre = trim((string) ($this->valeurRequete($request, 'agent') ?? ''));
        $par   = $this->nomVigil($libre);
        $quand = new \DateTimeImmutable();

        if ($bon instanceof BonSortie) {
            $bon->setStatut(BonSortie::STATUT_CLOTURE)
                ->setClotureAt($quand)
                ->setCloturePar($par)
                ->setPosteControle($poste !== '' ? $poste : 'Guerite');
        } else {
            $bon->setClotureAt($quand)
                ->setCloturePar($par)
                ->setPosteControle($poste !== '' ? $poste : 'Guerite');
        }
        $this->em->flush();

        return $this->json([
            'code' => 'success',
            'msg'  => 'Sortie confirmee : le bon ' . $bon->getReference() . ' est cloture.',
            'data' => $this->serialiser($bon),
        ]);
    }

    /**
     * Le vigil signale un probleme : la sortie est REFUSEE avec un motif
     * obligatoire, et le bon est annule.
     *
     *   POST /scan/api/signaler   { code: "...", motif: "...", poste_controle: "..." }
     */
    #[Route('/api/signaler', name: 'app_scan_bon_signaler', methods: ['POST'], priority: 60)]
    public function signaler(Request $request): JsonResponse
    {
        $bon = $this->lireBon($this->codeDepuisRequete($request));
        if ($bon === null) {
            return $this->json([
                'code' => 'warning',
                'msg'  => 'Aucun bon de sortie ne correspond a ce code.',
            ], 404);
        }

        $motif = trim((string) ($this->valeurRequete($request, 'motif') ?? ''));
        if (mb_strlen($motif) < 5) {
            return $this->json([
                'code' => 'warning',
                'msg'  => 'Merci d\'indiquer le motif du signalement (au moins 5 caracteres).',
            ], 422);
        }
        if (mb_strlen($motif) > 255) {
            $motif = mb_substr($motif, 0, 255);
        }

        $libre = trim((string) ($this->valeurRequete($request, 'agent') ?? ''));
        $etat = $this->etatControle($bon);
        if ($etat['deja_cloture']) {
            return $this->json([
                'code' => 'error',
                'msg'  => 'Ce bon a deja ete cloture : la sortie est deja terminee, signalez-le a la Direction Generale.',
                'data' => $this->serialiser($bon),
            ], 409);
        }
        if ($etat['deja_refuse']) {
            return $this->json([
                'code' => 'info',
                'msg'  => 'Ce bon est deja signale : ' . (string) $etat['refus_motif'],
                'data' => $this->serialiser($bon),
            ], 200);
        }

        $poste = trim((string) ($this->valeurRequete($request, 'poste_controle') ?? ''));
        $par   = $this->nomVigil($libre);
        $quand = new \DateTimeImmutable();

        if ($bon instanceof BonSortie) {
            $bon->setStatut(BonSortie::STATUT_ANNULE)
                ->setRefusAt($quand)
                ->setRefusPar($par)
                ->setRefusMotif($motif)
                ->setPosteControle($poste !== '' ? $poste : 'Guerite');
        } else {
            $bon->setStatut(SortieStock::STATUT_ANNULE)
                ->setRefusAt($quand)
                ->setRefusPar($par)
                ->setRefusMotif($motif)
                ->setObservationSignature($motif)
                ->setPosteControle($poste !== '' ? $poste : 'Guerite');
        }
        $this->em->flush();

        return $this->json([
            'code' => 'success',
            'msg'  => 'Bon ' . $bon->getReference() . ' signale et refuse. Motif enregistre : ' . $motif,
            'data' => $this->serialiser($bon),
        ]);
    }

    /** Le code du bon, lu dans le corps JSON ou dans le formulaire. */
    private function codeDepuisRequete(Request $request): string
    {
        return trim((string) ($this->valeurRequete($request, 'code') ?? ''));
    }

    /** Valeur d'un champ, acceptee en JSON brut ou en formulaire. */
    private function valeurRequete(Request $request, string $champ): ?string
    {
        $valeur = $request->request->get($champ);
        if ($valeur === null && $request->getContent()) {
            $payload = json_decode((string) $request->getContent(), true);
            if (is_array($payload) && array_key_exists($champ, $payload)) {
                $valeur = $payload[$champ];
            }
        }

        return $valeur === null ? null : (string) $valeur;
    }

    /** Nom affiche du vigil (prenoms + nom, ou e-mail, ou « Guerite »). */
    private function nomVigil(string $libre = ''): string
    {
        $u = $this->getUser();
        if ($u === null) {
            return $libre !== '' ? mb_substr($libre, 0, 180) : 'Guerite (page scan)';
        }

        $nom = '';
        foreach (['getPrenoms', 'getNom'] as $methode) {
            if (method_exists($u, $methode)) {
                $nom .= ' ' . (string) $u->{$methode}();
            }
        }
        $nom = trim($nom);
        if ($nom === '' && method_exists($u, 'getUserIdentifier')) {
            $nom = (string) $u->getUserIdentifier();
        }

        return $nom !== '' ? mb_substr($nom, 0, 180) : 'Guerite (page scan)';
    }

    /**
     * Ce que la page de scan peut proposer pour ce bon : faire sortir
     * (le vigil cloture) ou signaler (refus motive). Calcule cote serveur,
     * la page ne fait qu'afficher les boutons.
     */
    private function actionsBon(BonSortie|SortieStock $bon): array
    {
        $etat = $this->etatControle($bon);

        return [
            'peut_sortir'  => $etat['peut_sortir'],
            'deja_cloture' => $etat['deja_cloture'],
            'deja_refuse'  => $etat['deja_refuse'],
            'raison'       => $etat['raison'],
        ];
    }

    /**
     * Etat de controle d'un bon pour la guerite : peut-on faire sortir,
     * est-ce deja cloture, est-ce deja signale, et sinon pourquoi.
     *
     * @return array{peut_sortir: bool, deja_cloture: bool, deja_refuse: bool, motif_invalide: bool, raison: string, refus_motif: string, cloture_at: string}
     */
    private function etatControle(BonSortie|SortieStock $bon): array
    {
        $etat = [
            'peut_sortir'   => false,
            'deja_cloture'  => false,
            'deja_refuse'   => false,
            'motif_invalide'=> false,
            'raison'        => '',
            'refus_motif'   => '',
            'cloture_at'    => '',
        ];

        if ($bon instanceof BonSortie) {
            $etat['deja_cloture'] = $bon->isCloture();
            $etat['cloture_at']   = $bon->getClotureAt() ? $bon->getClotureAt()->format('d/m/Y \a H:i') : '';
            $etat['deja_refuse']  = $bon->getStatut() === BonSortie::STATUT_ANNULE;
            // NB : BonSortie n'a pas de getObservationSignature() (seul SortieStock en a un) :
            // l'appeler faisait planter la fiche de tout bon materiel / PDR (erreur 500).
            $etat['refus_motif']  = (string) $bon->getRefusMotif();

            if ($etat['deja_refuse']) {
                $etat['raison'] = 'Ce bon a ete annule par la Direction Generale.';
            } elseif ($bon->isEnAttente()) {
                $etat['raison'] = 'Ce bon attend encore la signature du Directeur General : la sortie n\'est pas autorisee.';
            } elseif ($bon->isValide()) {
                $etat['peut_sortir'] = true;
            } else {
                $etat['raison'] = 'Ce bon n\'est pas dans un etat permettant une sortie (' . $bon->getStatutLabel() . ').';
            }

            return $etat;
        }

        $etat['deja_cloture'] = $bon->isCloturee();
        $etat['cloture_at']   = $bon->getClotureAt() ? $bon->getClotureAt()->format('d/m/Y \a H:i') : '';
        $etat['deja_refuse']  = $bon->isRefusee();
        $etat['refus_motif']  = (string) $bon->getRefusMotif();

        if ($etat['deja_refuse']) {
            $etat['raison'] = 'Cette sortie a ete signalee.';
        } elseif ($bon->isValide()) {
            $etat['peut_sortir'] = true;
        } elseif ($bon->isEnAttente()) {
            $etat['raison'] = 'Ce bon attend encore la signature du Directeur General : la sortie n\'est pas autorisee.';
        } else {
            $etat['raison'] = 'Ce bon n\'est pas dans un etat permettant une sortie (' . $bon->getStatutLibelle() . ').';
        }

        return $etat;
    }

    // ------------------------------------------------------------------
    //  Recherche du bon
    // ------------------------------------------------------------------
    /**
     * Recherche par jeton dans les deux tables de bons, puis, en secours,
     * par reference (bons imprimes avant l'ajout de la colonne token).
     */
    private function lireBon(string $code): BonSortie|SortieStock|null
    {
        return $this->lireBonAvecDiagnostic($code)[0];
    }

    /**
     * Normalise un code lu (QR, saisie manuelle, URL) :
     *   - JSON CITRAC imprimes sur le bon -> on garde ref (reference lisible)
     *     et tok (jeton) ; les deux sont essayes ensuite ;
     *   - URL /scan/<code> -> on garde <code> ;
     *   - code brut -> on retire espaces, guillemets et « # » de fin.
     *
     * @return array{0: string, 1: string} [reference, jeton] (l'un des deux peut etre vide)
     */
    private function normaliserCode(string $brut): array
    {
        $txt = trim($brut);
        if ($txt === '') {
            return ['', ''];
        }

        if ($txt[0] === '{') {
            $obj = json_decode($txt, true);
            if (is_array($obj)) {
                $ref = trim((string) ($obj['ref'] ?? $obj['reference'] ?? ''));
                $tok = trim((string) ($obj['tok'] ?? $obj['token'] ?? ''));
                if ($ref !== '' || $tok !== '') {
                    return [$ref, $tok];
                }
            }
        }

        if (preg_match('~/scan/([A-Za-z0-9_\-]{3,80})\s*$~', $txt, $m) === 1) {
            return [$m[1], ''];
        }

        // Caracteres parasites frequentes en fin de lecture (retour chariot, « # »
        // de fin d'URL, guillemets autour du code).
        $txt = trim($txt, " \t\n\r\0\x0B\"'<>.,;:");

        return [$txt, $txt];
    }

    /**
     * Recherche du bon + constat lisible quand il n'est pas trouve.
     *
     * @return array{0: BonSortie|SortieStock|null, 1: array<string, mixed>}
     */
    private function lireBonAvecDiagnostic(string $code): array
    {
        [$ref, $tok] = $this->normaliserCode($code);
        $constat = [
            'code_lu'    => $code,
            'reference'  => $ref,
            'jeton'      => $tok !== '' ? (strlen($tok) > 10 ? substr($tok, 0, 10) . '...' : $tok) : '',
            'essais'     => [],
            'indice'     => '',
        ];

        $principal = $ref !== '' ? $ref : $tok;
        if ($principal === '' || mb_strlen($principal) > 80) {
            $constat['indice'] = 'Le code lu est vide ou trop long : le QR n\'a pas ete reconnu.';
            return [null, $constat];
        }

        $candidats = array_values(array_unique(array_filter([$tok, $ref])));
        if ($candidats === []) {
            return [null, $constat];
        }
        // Reference sans le prefixe « # » eventuel (anciens QR).
        $candidats[] = ltrim((string) $ref, '#');
        $candidats = array_values(array_unique(array_filter($candidats)));

        $erreurs = [];
        foreach ($candidats as $cle) {
            // 1) Bons PDR / materiel (table bon_sortie)
            try {
                $bon = $this->em->getRepository(BonSortie::class)->findOneBy(['token' => $cle]);
                if (!$bon instanceof BonSortie) {
                    $bon = $this->em->getRepository(BonSortie::class)->findOneBy(['reference' => $cle]);
                }
                if ($bon instanceof BonSortie) {
                    $constat['essais'][] = ['cle' => $cle, 'table' => 'bon_sortie', 'resultat' => 'trouve'];
                    return [$bon, $constat];
                }
                $constat['essais'][] = ['cle' => $cle, 'table' => 'bon_sortie', 'resultat' => 'aucun'];
            } catch (\Throwable $e) {
                $constat['essais'][] = ['cle' => $cle, 'table' => 'bon_sortie', 'resultat' => 'erreur : ' . $e->getMessage()];
                $erreurs[] = $e->getMessage();
            }

            // 2) Bons de sortie de stock (table sortie_stock, colonnes bon_token / bon_reference)
            try {
                $repo = $this->em->getRepository(SortieStock::class);
                $bon = $repo->findOneBy(['token' => $cle]);
                if (!$bon instanceof SortieStock) {
                    $bon = $repo->findOneBy(['reference' => $cle]);
                }
                if ($bon instanceof SortieStock) {
                    $constat['essais'][] = ['cle' => $cle, 'table' => 'sortie_stock', 'resultat' => 'trouve'];
                    return [$bon, $constat];
                }
                $constat['essais'][] = ['cle' => $cle, 'table' => 'sortie_stock', 'resultat' => 'aucun'];
            } catch (\Throwable $e) {
                $constat['essais'][] = ['cle' => $cle, 'table' => 'sortie_stock', 'resultat' => 'erreur : ' . $e->getMessage()];
                $erreurs[] = $e->getMessage();
            }
        }

        // Indice utile pour l'agent : un bon tres proche existe-t-il ?
        try {
            $semblables = $this->em->createQueryBuilder()
                ->select('b.reference')
                ->from(BonSortie::class, 'b')
                ->orderBy('b.createdAt', 'DESC')
                ->setMaxResults(3)
                ->getQuery()
                ->getScalarResult();
            $refs = array_map(static fn ($r) => (string) $r['reference'], $semblables);
            if ($refs !== []) {
                $constat['derniers_bons'] = $refs;
            }
        } catch (\Throwable) {
        }

        if ($erreurs !== []) {
            $constat['indice'] = 'La base a renvoye une erreur : une colonne du bon est probablement absente.'
                . ' Lancez php bin/console doctrine:schema:update --force (ou la migration) puis reessayez.';
        } elseif (isset($constat['derniers_bons'])) {
            $constat['indice'] = 'Aucun bon ne porte ce code. Derniers bons enregistres : '
                . implode(', ', $constat['derniers_bons']) . '.';
        }

        return [null, $constat];
    }

    // ------------------------------------------------------------------
    //  Mise en forme pour la page
    // ------------------------------------------------------------------
    /** @return array<string, mixed> */
    private function serialiser(BonSortie|SortieStock $bon): array
    {
        return $bon instanceof SortieStock
            ? $this->serialiserStock($bon)
            : $this->serialiserPdr($bon);
    }

    /** Bon de sortie « stock » (sortie par rubrique : cacao, PDR...). */
    private function serialiserStock(SortieStock $bon): array
    {
        $statut   = (string) ($bon->getStatut() ?: SortieStock::STATUT_EN_ATTENTE);
        $qte      = $bon->getQuantite();
        $quantite = $qte !== null
            ? rtrim(rtrim(number_format((float) $qte, 2, '.', ' '), '0'), '.')
            : '';

        return [
            'type'       => 'BON_SORTIE_STOCK',
            'type_label' => 'Bon de sortie de stock',
            'token'      => (string) $bon->getToken(),
            'reference'  => (string) $bon->getReference(),
            'statut'       => $statut,
            'statut_label' => $bon->getStatutLibelle(),
            'date'       => $bon->getDateSortie()
                ? $bon->getDateSortie()->format('d/m/Y \\a H:i')
                : ($bon->getCreatedAt() ? $bon->getCreatedAt()->format('d/m/Y \\a H:i') : ''),
            'service'    => (string) $bon->getServiceLibelle(),
            'demandeur'  => (string) $bon->getAgentLibelle(),
            'emplacement'=> (string) $bon->getEmplacementLibelle(),
            'etabli_par' => (string) $bon->getAgentLibelle(),
            'signe_par'  => (string) $bon->getSignePar(),
            'signe_at'   => $bon->getSigneAt() ? $bon->getSigneAt()->format('d/m/Y \\a H:i') : '',
            'observation_signature' => (string) $bon->getObservationSignature(),
            'cloture_par' => (string) $bon->getCloturePar(),
            'cloture_at'  => $bon->getClotureAt() ? $bon->getClotureAt()->format('d/m/Y \\a H:i') : '',
            'poste_controle' => (string) $bon->getPosteControle(),
            'refus_par'   => (string) $bon->getRefusPar(),
            'refus_at'    => $bon->getRefusAt() ? $bon->getRefusAt()->format('d/m/Y \\a H:i') : '',
            'refus_motif' => (string) $bon->getRefusMotif(),
            'actions'     => $this->actionsBon($bon),
            'lignes'     => [[
                'code'        => '',
                'designation' => (string) $bon->getRubrique(),
                'qte'         => trim($quantite . ' ' . (string) $bon->getUnite()),
                'stock_avant' => '',
                'stock_apres' => '',
                'mention'     => (string) $bon->getMotif(),
            ]],
        ];
    }

    /** Bon de sortie « PDR / materiel » (App\Entity\BonSortie). */
    private function serialiserPdr(BonSortie $bon): array
    {
        $lignes = [];
        foreach (($bon->getLignes() ?: []) as $ligne) {
            if (!is_array($ligne)) {
                continue;
            }
            $lignes[] = [
                'code'        => (string) ($ligne['code'] ?? ''),
                'designation' => (string) ($ligne['designation'] ?? ($ligne['libelle'] ?? '')),
                'qte'         => (string) ($ligne['qte'] ?? ''),
                'stock_avant' => (string) ($ligne['stock_avant'] ?? ''),
                'stock_apres' => (string) ($ligne['stock_apres'] ?? ''),
                'mention'     => (string) ($ligne['mention'] ?? ''),
            ];
        }

        return [
            'type'       => 'BON_SORTIE',
            'type_label' => 'Bon de sortie (materiel / PDR)',
            'token'      => (string) $bon->getToken(),
            'reference'  => (string) $bon->getReference(),
            'statut'       => $bon->getStatut(),
            'statut_label' => $bon->getStatutLabel(),
            'date'       => $bon->getCreatedAt() ? $bon->getCreatedAt()->format('d/m/Y \\a H:i') : '',
            'service'    => (string) $bon->getService(),
            'demandeur'  => (string) $bon->getDemandeur(),
            'emplacement'=> '',
            'etabli_par' => (string) $bon->getEtabliPar(),
            'signe_par'  => (string) $bon->getSignePar(),
            'signe_at'   => $bon->getSigneAt() ? $bon->getSigneAt()->format('d/m/Y \\a H:i') : '',
            'observation_signature' => '',
            'cloture_par' => (string) $bon->getCloturePar(),
            'cloture_at'  => $bon->getClotureAt() ? $bon->getClotureAt()->format('d/m/Y \\a H:i') : '',
            'poste_controle' => (string) $bon->getPosteControle(),
            'refus_par'   => (string) $bon->getRefusPar(),
            'refus_at'    => $bon->getRefusAt() ? $bon->getRefusAt()->format('d/m/Y \\a H:i') : '',
            'refus_motif' => (string) $bon->getRefusMotif(),
            'actions'     => $this->actionsBon($bon),
            'lignes'     => $lignes,
        ];
    }
}
