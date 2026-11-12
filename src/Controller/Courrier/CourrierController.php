<?php

namespace App\Controller\Courrier;

use App\Entity\Courrier\Courrier;
use App\Entity\Courrier\PieceJointeCourrier;
use App\Entity\Services;
use App\Repository\Courrier\CourrierRepository;
use App\Service\Courrier\GestionnaireCourrier;
use App\Service\Courrier\StockagePiecesJointes;
use App\Services\pdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Module de gestion du courrier (entrant / sortant).
 *
 *  - /courriers                      : listing filtre + compteurs
 *  - /courriers/nouveau              : enregistrement d'un courrier
 *  - /courriers/{id}                 : fiche detail + pieces jointes
 *  - /courriers/{id}/modifier        : modification
 *  - /courriers/{id}/statut           : changement rapide de statut (AJAX)
 *  - /courriers/{id}/pieces          : ajout de pieces jointes
 *  - /courriers/piece/{id}/voir|telecharger|supprimer
 *  - /courriers/{id}/supprimer       : suppression definitive
 *  - /courriers/impression           : listing imprimable (PDF)
 *  - /courriers/{id}/fiche-pdf       : fiche de circulation imprimable
 */
#[Route('/courriers')]
#[IsGranted('ROLE_USER')]
final class CourrierController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CourrierRepository $courriers,
        private GestionnaireCourrier $gestionnaire,
        private StockagePiecesJointes $stockage,
    ) {}

    // ------------------------------------------------------------------
    //  Listing
    // ------------------------------------------------------------------
    #[Route('', name: 'app_courrier_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filtres = [
            'sens'    => (string) $request->query->get('sens', ''),
            'statut'  => (string) $request->query->get('statut', ''),
            'service' => (string) $request->query->get('service', ''),
            'q'       => (string) $request->query->get('q', ''),
            'du'      => (string) $request->query->get('du', ''),
            'au'      => (string) $request->query->get('au', ''),
        ];

        $liste = $this->courriers->rechercher($filtres);

        return $this->render('courrier/index.html.twig', [
            'courriers'    => $liste,
            'filtres'      => $filtres,
            'nombreTotal'  => count($liste),
            'compteurs'    => $this->courriers->compterParStatut(),
            'compteursEntrant' => $this->courriers->compterParStatut(Courrier::SENS_ENTRANT),
            'compteursSortant' => $this->courriers->compterParStatut(Courrier::SENS_SORTANT),
            'services'     => $this->listeServices(),
            'sens'         => Courrier::SENS,
            'statuts'      => Courrier::STATUTS,
            'couleurs'     => Courrier::STATUT_COULEURS,
        ]);
    }

    // ------------------------------------------------------------------
    //  Enregistrement
    // ------------------------------------------------------------------
    #[Route('/nouveau', name: 'app_courrier_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(Request $request): Response
    {
        $courrier = new Courrier();
        $courrier->setSens((string) $request->query->get('sens', Courrier::SENS_ENTRANT));
        if (!isset(Courrier::SENS[$courrier->getSens()])) {
            $courrier->setSens(Courrier::SENS_ENTRANT);
        }

        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('courrier_form', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Session expiree, merci de recommencer.');

                return $this->redirectToRoute('app_courrier_nouveau');
            }

            $erreurs = $this->remplir($courrier, $request);

            if ($erreurs === []) {
                $this->gestionnaire->attribuerNumero($courrier);
                $courrier->setCreatedBy($this->getUser());

                $erreurs = $this->traiterFichiers($courrier, $request);

                if ($erreurs === []) {
                    $this->em->persist($courrier);
                    $this->em->flush();

                    $this->addFlash('success', 'Courrier enregistre sous le numero ' . $courrier->getNumero() . '.');

                    return $this->redirectToRoute('app_courrier_detail', ['id' => $courrier->getId()]);
                }
            }
        }

        return $this->render('courrier/form.html.twig', [
            'courrier'  => $courrier,
            'erreurs'   => $erreurs,
            'services'  => $this->listeServices(),
            'mode'      => 'creation',
            'sens'      => Courrier::SENS,
            'statuts'   => Courrier::STATUTS,
            'priorites' => ['normale' => 'Normale', 'urgente' => 'Urgente', 'tres_urgente' => 'Tres urgente'],
        ]);
    }

    // ------------------------------------------------------------------
    //  Saisie semi-automatique (expediteur, organisme, service, responsable)
    // ------------------------------------------------------------------
    /**
     * Valeurs distinctes deja saisies, pour l'autocompletion des champs.
     *
     * La recherche est faite cote serveur sur la valeur deja tapee : le
     * navigateur envoie « q » a chaque frappe (avec un court delai) et ne
     * recoit que les 8 propositions les plus pertinentes.
     */
    #[Route('/suggestions', name: 'app_courrier_suggestions', methods: ['GET'], priority: 10)]
    public function suggestions(Request $request): JsonResponse
    {
        $champ = (string) $request->query->get('champ', 'correspondant');
        $sens  = (string) $request->query->get('sens', '');
        $texte = trim((string) $request->query->get('q', ''));

        if (!in_array($champ, CourrierRepository::CHAMPS_SUGGERES, true)) {
            return new JsonResponse(['valeurs' => []], 400);
        }

        return new JsonResponse([
            'valeurs' => $this->courriers->suggerer($champ, $texte, $sens !== '' ? $sens : null),
        ]);
    }

    // ------------------------------------------------------------------
    //  Fiche detail
    // ------------------------------------------------------------------
    #[Route('/{id}', name: 'app_courrier_detail', methods: ['GET'])]
    public function detail(string $id): Response
    {
        $courrier = $this->trouver($id);

        return $this->render('courrier/detail.html.twig', [
            'courrier' => $courrier,
            'sens'     => Courrier::SENS,
            'statuts'  => Courrier::STATUTS,
            'couleurs' => Courrier::STATUT_COULEURS,
        ]);
    }

    // ------------------------------------------------------------------
    //  Modification
    // ------------------------------------------------------------------
    #[Route('/{id}/modifier', name: 'app_courrier_modifier', methods: ['GET', 'POST'])]
    public function modifier(string $id, Request $request): Response
    {
        $courrier = $this->trouver($id);
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('courrier_form', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Session expiree, merci de recommencer.');

                return $this->redirectToRoute('app_courrier_modifier', ['id' => $courrier->getId()]);
            }

            // Le sens n'est pas modifiable apres enregistrement (le numero y est lie).
            $sensInitial = $courrier->getSens();
            $erreurs = $this->remplir($courrier, $request);
            $courrier->setSens($sensInitial);

            if ($erreurs === []) {
                $erreurs = $this->traiterFichiers($courrier, $request);

                if ($erreurs === []) {
                    $courrier->touch();
                    $this->em->flush();

                    $this->addFlash('success', 'Courrier mis a jour.');

                    return $this->redirectToRoute('app_courrier_detail', ['id' => $courrier->getId()]);
                }
            }
        }

        return $this->render('courrier/form.html.twig', [
            'courrier'  => $courrier,
            'erreurs'   => $erreurs,
            'services'  => $this->listeServices(),
            'mode'      => 'modification',
            'sens'      => Courrier::SENS,
            'statuts'   => Courrier::STATUTS,
            'priorites' => ['normale' => 'Normale', 'urgente' => 'Urgente', 'tres_urgente' => 'Tres urgente'],
        ]);
    }

    // ------------------------------------------------------------------
    //  Changement de statut (boutons de la fiche)
    // ------------------------------------------------------------------
    #[Route('/{id}/statut/{statut}', name: 'app_courrier_statut', methods: ['POST'])]
    public function changerStatut(string $id, string $statut, Request $request): Response
    {
        $courrier = $this->trouver($id);

        if (!$this->isCsrfTokenValid('courrier_statut_' . $courrier->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expiree, merci de reessayer.');

            return $this->redirectToRoute('app_courrier_detail', ['id' => $courrier->getId()]);
        }

        if (!isset(Courrier::STATUTS[$statut])) {
            $this->addFlash('danger', 'Statut inconnu.');

            return $this->redirectToRoute('app_courrier_detail', ['id' => $courrier->getId()]);
        }

        $courrier->setStatut($statut);
        if ($statut === Courrier::STATUT_TRAITE && !$courrier->getDateAccuse()) {
            $courrier->setDateAccuse(new \DateTimeImmutable());
        }
        $courrier->touch();
        $this->em->flush();

        $this->addFlash('success', 'Statut mis a jour : ' . Courrier::STATUTS[$statut] . '.');

        return $this->redirectToRoute('app_courrier_detail', ['id' => $courrier->getId()]);
    }

    // ------------------------------------------------------------------
    //  Pieces jointes
    // ------------------------------------------------------------------
    #[Route('/{id}/pieces', name: 'app_courrier_piece_ajouter', methods: ['POST'])]
    public function ajouterPieces(string $id, Request $request): Response
    {
        $courrier = $this->trouver($id);
        $retour = $this->redirectToRoute('app_courrier_detail', ['id' => $courrier->getId()]);

        /** @var array<int, UploadedFile> $fichiers */
        $fichiers = $request->files->get('pieces', []);

        // Un envoi depassant post_max_size est vide par PHP AVANT la lecture
        // du jeton : on repond alors sur la taille plutot que sur la session.
        if ($fichiers === [] && $request->request->count() === 0
            && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->addFlash('danger', 'Le fichier depose est trop volumineux pour le serveur : il n a pas ete transmis. Limite actuelle de PHP : post_max_size = '
                . ini_get('post_max_size') . ', upload_max_filesize = ' . ini_get('upload_max_filesize') . '.');

            return $retour;
        }

        if (!$this->isCsrfTokenValid('courrier_pieces_' . $courrier->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expiree, merci de reessayer.');

            return $retour;
        }
        if (!is_array($fichiers)) {
            $fichiers = $fichiers ? [$fichiers] : [];
        }

        $commentaire = (string) $request->request->get('commentaire_piece', '');
        $ajoutes = 0;
        $erreurs = [];

        foreach ($fichiers as $fichier) {
            if (!$fichier instanceof UploadedFile || $fichier->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $erreur = $this->gestionnaire->ajouterPieceJointe($courrier, $fichier, $commentaire);
            if ($erreur !== null) {
                $erreurs[] = $erreur;
            } else {
                $ajoutes++;
            }
        }

        if ($ajoutes > 0) {
            $courrier->touch();
            $this->em->flush();
            $this->addFlash('success', $ajoutes . ' piece(s) jointe(s) ajoutee(s) au courrier.');
        }

        if ($erreurs !== []) {
            $this->addFlash('danger', implode(' ', $erreurs));
        }

        if ($ajoutes === 0 && $erreurs === []) {
            $this->addFlash('warning', 'Aucun fichier recu.');
        }

        return $retour;
    }

    #[Route('/piece/{id}/voir', name: 'app_courrier_piece_voir', methods: ['GET'])]
    public function voirPiece(string $id): Response
    {
        $piece = $this->trouverPiece($id);
        $chemin = $this->stockage->chemin($piece);

        if ($chemin === null) {
            throw $this->createNotFoundException('Fichier introuvable sur le serveur.');
        }

        return new BinaryFileResponse($chemin, 200, [
            'Content-Type'        => $piece->getTypeMime() ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $this->nomSûr($piece) . '"',
        ]);
    }

    #[Route('/piece/{id}/telecharger', name: 'app_courrier_piece_telecharger', methods: ['GET'])]
    public function telechargerPiece(string $id): Response
    {
        $piece = $this->trouverPiece($id);
        $chemin = $this->stockage->chemin($piece);

        if ($chemin === null) {
            throw $this->createNotFoundException('Fichier introuvable sur le serveur.');
        }

        return $this->file($chemin, $piece->getNomOriginal() ?: 'piece-jointe');
    }

    #[Route('/piece/{id}/supprimer', name: 'app_courrier_piece_supprimer', methods: ['POST'])]
    public function supprimerPiece(string $id, Request $request): Response
    {
        $piece = $this->trouverPiece($id);
        $courrier = $piece->getCourrier();
        $retour = $this->redirectToRoute('app_courrier_detail', ['id' => $courrier?->getId()]);

        if (!$this->isCsrfTokenValid('courrier_piece_suppr_' . $piece->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expiree, merci de reessayer.');

            return $retour;
        }

        if ($courrier !== null) {
            $this->gestionnaire->supprimerPieceJointe($courrier, $piece);
            $courrier->touch();
            $this->em->flush();
        }

        $this->addFlash('success', 'Piece jointe supprimee.');

        return $retour;
    }

    // ------------------------------------------------------------------
    //  Suppression du courrier
    // ------------------------------------------------------------------
    #[Route('/{id}/supprimer', name: 'app_courrier_supprimer', methods: ['POST'])]
    public function supprimer(string $id, Request $request): Response
    {
        $courrier = $this->trouver($id);

        if (!$this->isCsrfTokenValid('courrier_suppr_' . $courrier->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expiree, merci de reessayer.');

            return $this->redirectToRoute('app_courrier_detail', ['id' => $courrier->getId()]);
        }

        $numero = (string) $courrier->getNumero();
        $this->gestionnaire->supprimer($courrier);

        $this->addFlash('success', 'Courrier ' . $numero . ' supprime avec ses pieces jointes.');

        return $this->redirectToRoute('app_courrier_index');
    }

    // ------------------------------------------------------------------
    //  Impression
    // ------------------------------------------------------------------
    /** Listing des courriers au format PDF, dans l'etat des filtres affiches. */
    #[Route('/impression', name: 'app_courrier_impression', methods: ['GET'])]
    public function impression(Request $request, pdfService $pdfService): Response
    {
        $filtres = [
            'sens'    => (string) $request->query->get('sens', ''),
            'statut'  => (string) $request->query->get('statut', ''),
            'service' => (string) $request->query->get('service', ''),
            'q'       => (string) $request->query->get('q', ''),
            'du'      => (string) ($request->query->get('du', '') ?: $request->query->get('date_du', '')),
            'au'      => (string) ($request->query->get('au', '') ?: $request->query->get('date_au', '')),
        ];

        $liste = $this->courriers->rechercherTous($filtres);

        $entetes = ['N°', 'Date', 'Sens', 'Objet', 'Correspondant', 'Statut'];
        $largeurs = [30, 20, 18, 73, 55, 37];
        $lignes = [];

        foreach ($liste as $c) {
            /** @var Courrier $c */
            $lignes[] = [
                (string) $c->getNumero(),
                $c->getDateCourrier()?->format('d/m/Y') ?? '-',
                $c->getSensCourt(),
                (string) $c->getObjet(),
                $c->getCorrespondantComplet(),
                (string) $c->getStatutLibelle(),
            ];
        }

        if ($lignes === []) {
            $lignes[] = ['-', '-', '-', 'Aucun courrier ne correspond aux criteres choisis.', '-', '-'];
        }

        $sousTitre = $this->resumeFiltres($filtres) . ' — ' . count($liste) . ' courrier(s)';

        $contenu = $pdfService->genererTableau(
            'Registre du courrier',
            $entetes,
            $largeurs,
            $lignes,
            'L',
            $sousTitre
        );

        return new Response($contenu, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="registre-courrier-' . date('Ymd-Hi') . '.pdf"',
        ]);
    }

    /** Fiche de circulation d'un courrier, imprimable et signable. */
    #[Route('/{id}/fiche-pdf', name: 'app_courrier_fiche_pdf', methods: ['GET'])]
    public function fichePdf(string $id, pdfService $pdfService): Response
    {
        $courrier = $this->trouver($id);

        $entetes = ['Rubrique', 'Information'];
        $largeurs = [55, 135];
        $lignes = [
            ['Numero', (string) $courrier->getNumero()],
            ['Sens', $courrier->getSensLibelle()],
            ['Date du courrier', $courrier->getDateCourrier()?->format('d/m/Y') ?? '-'],
            ['Objet', (string) $courrier->getObjet()],
            ['Correspondant', $courrier->getCorrespondantComplet()],
            ['Reference', (string) ($courrier->getReference() ?? '-')],
            ['Service concerne', (string) ($courrier->getService() ?? '-')],
            ['Responsable du suivi', (string) ($courrier->getResponsable() ?? '-')],
            ['Statut', $courrier->getStatutLibelle() . ' (' . $courrier->getPrioriteLibelle() . ')'],
            ['Date de traitement', $courrier->getDateAccuse()?->format('d/m/Y') ?? '-'],
            ['Pieces jointes', $courrier->getNombrePiecesJointes() . ' fichier(s)'],
            ['Observation', (string) ($courrier->getObservation() ?? '-')],
            ['Suite donnee', (string) ($courrier->getSuiteDonnee() ?? '-')],
            ['Enregistre le', $courrier->getCreatedAt()?->format('d/m/Y a H:i') ?? '-'],
        ];

        $contenu = $pdfService->genererTableau(
            'Fiche de courrier — ' . $courrier->getSensCourt(),
            $entetes,
            $largeurs,
            $lignes,
            'P',
            (string) $courrier->getNumero()
        );

        return new Response($contenu, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="fiche-' . $courrier->getNumero() . '.pdf"',
        ]);
    }

    // ------------------------------------------------------------------
    //  Outils internes
    // ------------------------------------------------------------------

    private function trouver(string $id): Courrier
    {
        $courrier = $this->courriers->find($id);
        if (!$courrier instanceof Courrier) {
            throw $this->createNotFoundException('Courrier introuvable.');
        }

        return $courrier;
    }

    private function trouverPiece(string $id): PieceJointeCourrier
    {
        $piece = $this->em->getRepository(PieceJointeCourrier::class)->find($id);
        if (!$piece instanceof PieceJointeCourrier) {
            throw $this->createNotFoundException('Piece jointe introuvable.');
        }

        return $piece;
    }

    /** Nom de fichier sans caractere problematique pour l'en-tete HTTP. */
    private function nomSûr(PieceJointeCourrier $piece): string
    {
        $nom = $piece->getNomOriginal() ?: 'piece-jointe';

        return preg_replace('/[^A-Za-z0-9._-]/', '-', $nom) ?: 'piece-jointe';
    }

    /**
     * Recopie les champs du formulaire dans l'entite.
     *
     * @return list<string> erreurs de validation
     */
    private function remplir(Courrier $courrier, Request $request): array
    {
        $erreurs = [];

        $sens = (string) $request->request->get('sens', $courrier->getSens());
        if (isset(Courrier::SENS[$sens])) {
            $courrier->setSens($sens);
        }

        $objet = trim((string) $request->request->get('objet', ''));
        if ($objet === '') {
            $erreurs[] = 'L\'objet du courrier est obligatoire.';
        }
        $courrier->setObjet($objet !== '' ? $objet : null);

        $correspondant = trim((string) $request->request->get('correspondant', ''));
        if ($correspondant === '') {
            $erreurs[] = $courrier->isEntrant()
                ? 'L\'expediteur du courrier est obligatoire.'
                : 'Le destinataire du courrier est obligatoire.';
        }
        $courrier->setCorrespondant($correspondant !== '' ? $correspondant : null);

        $courrier->setOrganisme($this->videOuValeur($request->request->get('organisme')));
        $courrier->setReference($this->videOuValeur($request->request->get('reference')));

        $date = trim((string) $request->request->get('date_courrier', ''));
        if ($date === '') {
            $courrier->setDateCourrier(new \DateTimeImmutable());
        } else {
            try {
                $courrier->setDateCourrier(new \DateTimeImmutable($date));
            } catch (\Throwable) {
                $erreurs[] = 'La date du courrier est invalide.';
            }
        }

        $statut = (string) $request->request->get('statut', Courrier::STATUT_A_TRAITER);
        $courrier->setStatut(isset(Courrier::STATUTS[$statut]) ? $statut : Courrier::STATUT_A_TRAITER);

        $priorite = (string) $request->request->get('priorite', 'normale');
        $courrier->setPriorite(in_array($priorite, Courrier::PRIORITES, true) ? $priorite : 'normale');

        // Service choisi dans la table des services (liste deroulante).
        $serviceId = (string) $request->request->get('service', '');
        $service = null;
        if ($serviceId !== '') {
            $service = $this->em->getRepository(Services::class)->find($serviceId);
            if (!$service instanceof Services) {
                $erreurs[] = 'Le service selectionne est introuvable ; choisissez-le a nouveau dans la liste.';
            }
        }
        $courrier->setCodeService($service);
        $courrier->setResponsable($this->videOuValeur($request->request->get('responsable')));
        $courrier->setObservation($this->videOuValeur($request->request->get('observation')));
        $courrier->setSuiteDonnee($this->videOuValeur($request->request->get('suite_donnee')));

        return $erreurs;
    }

    /**
     * Depose les fichiers joints au formulaire (champ « pieces[] »).
     *
     * @return list<string> erreurs de validation
     */
    private function traiterFichiers(Courrier $courrier, Request $request): array
    {
        /** @var array<int, UploadedFile> $fichiers */
        $fichiers = $request->files->get('pieces', []);
        if (!is_array($fichiers)) {
            $fichiers = $fichiers ? [$fichiers] : [];
        }

        $erreurs = [];
        $recus = 0;
        foreach ($fichiers as $fichier) {
            if (!$fichier instanceof UploadedFile || $fichier->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $recus++;
            $erreur = $this->gestionnaire->ajouterPieceJointe($courrier, $fichier);
            if ($erreur !== null) {
                $erreurs[] = $erreur;
            }
        }

        // Aucun fichier recu alors que le formulaire en annoncait un : le
        // serveur a refuse le transfert (taille superieure a post_max_size).
        if ($recus === 0 && $request->request->get('pieces_annoncees', '') !== '') {
            $erreurs[] = 'Le fichier depose n a pas ete recu par le serveur : il depasse probablement la limite PHP '
                . '(post_max_size = ' . ini_get('post_max_size') . ', upload_max_filesize = ' . ini_get('upload_max_filesize') . ').';
        }

        return $erreurs;
    }

    /**
     * Services proposes dans les listes deroulantes, tries par direction
     * puis par libelle. En cas d'erreur de base, on continue avec une
     * liste vide : la creation du courrier doit rester possible.
     *
     * @return Services[]
     */
    private function listeServices(): array
    {
        try {
            return $this->em->getRepository(Services::class)
                ->createQueryBuilder('s')
                ->leftJoin('s.code_direction', 'd')
                ->addSelect('d')
                ->orderBy('d.libelle', 'ASC')
                ->addOrderBy('s.libelle', 'ASC')
                ->getQuery()
                ->getResult();
        } catch (\Throwable) {
            try {
                return $this->em->getRepository(Services::class)->findBy([], ['libelle' => 'ASC']);
            } catch (\Throwable) {
                return [];
            }
        }
    }

    private function videOuValeur(mixed $valeur): ?string
    {
        $valeur = trim((string) $valeur);

        return $valeur === '' ? null : $valeur;
    }

    /** Resume lisible des filtres actifs, pour le sous-titre du PDF. */
    private function resumeFiltres(array $filtres): string
    {
        $parties = [];

        if (!empty($filtres['sens'])) {
            $parties[] = Courrier::SENS[$filtres['sens']] ?? $filtres['sens'];
        } else {
            $parties[] = 'Entrants et sortants';
        }

        if (!empty($filtres['statut'])) {
            $parties[] = 'statut : ' . (Courrier::STATUTS[$filtres['statut']] ?? $filtres['statut']);
        }
        if (!empty($filtres['service'])) {
            $parties[] = 'service : ' . $filtres['service'];
        }
        if (!empty($filtres['q'])) {
            $parties[] = 'recherche : « ' . $filtres['q'] . ' »';
        }
        if (!empty($filtres['du']) || !empty($filtres['au'])) {
            $du = $filtres['du'] ? (new \DateTimeImmutable($filtres['du']))->format('d/m/Y') : '...';
            $au = $filtres['au'] ? (new \DateTimeImmutable($filtres['au']))->format('d/m/Y') : '...';
            $parties[] = 'periode du ' . $du . ' au ' . $au;
        }

        return implode(' — ', $parties);
    }
}
