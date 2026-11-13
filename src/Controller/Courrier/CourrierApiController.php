<?php

namespace App\Controller\Courrier;

use App\Entity\Courrier\Courrier;
use App\Entity\Courrier\PieceJointeCourrier;
use App\Entity\Services;
use App\Repository\Courrier\CourrierRepository;
use App\Service\Courrier\GestionnaireCourrier;
use App\Service\Courrier\StockagePiecesJointes;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;

/**
 * API MOBILE de l'enregistrement du courrier (application CITRAC GMAO).
 *
 * Permet a l'agent en charge du courrier d'enregistrer un courrier
 * (entrant ou sortant), d'y joindre des pieces, puis de suivre son
 * traitement : modification, changement de statut, telechargement des
 * pieces. Le circuit metier est celui du module web : meme numerotation
 * (ENT-AAAA-NNNN / SOR-AAAA-NNNN), memes regles de pieces jointes.
 *
 * Roles : ROLE_COURRIER (agent courrier), ROLE_ADMIN, ROLE_TECHNICIAN
 * et ROLE_DG (hierarchie Symfony). Un utilisateur disposant d'un autre role
 * peut tout de meme LIRE le registre ; il ne peut pas enregistrer.
 */
#[Route('api/courrier')]
class CourrierApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CourrierRepository $courriers,
        private GestionnaireCourrier $gestionnaire,
        private StockagePiecesJointes $stockage,
    ) {}

    /** Roles autorises a ENREGISTRER / MODIFIER un courrier. */
    private const ROLES_GESTION = ['ROLE_COURRIER', 'ROLE_ADMIN', 'ROLE_TECHNICIAN', 'ROLE_DG', 'ROLE_PDR'];

    // ------------------------------------------------------------------
    //  Referentiel de l'ecran mobile
    // ------------------------------------------------------------------
    /**
     * GET api/courrier/referentiel
     * Listes fermees + agent connecte + formats acceptes.
     */
    #[Route('/referentiel', name: 'api_courrier_referentiel', methods: ['GET'])]
    public function referentiel(): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $services = [];
        foreach ($this->em->getRepository(Services::class)->findAll() as $s) {
            $services[] = ['id' => (string) $s->getId(), 'libelle' => (string) $s->getLibelle()];
        }

        return new JsonResponse([
            'code' => 'success',
            'msg'  => 'OK',
            'data' => [
                'sens'      => Courrier::SENS,
                'statuts'   => Courrier::STATUTS,
                'couleurs'  => Courrier::STATUT_COULEURS,
                'priorites' => ['normale' => 'Normale', 'urgente' => 'Urgente', 'tres_urgente' => 'Tres urgente'],
                'services'  => $services,
                'agent'     => [
                    'id'      => (string) $user->getId(),
                    'libelle' => trim(($user->getPrenoms() ?? '') . ' ' . ($user->getNom() ?? '')),
                    'serviceId' => $user->getCodeService() ? (string) $user->getCodeService()->getId() : null,
                    'service'   => $user->getCodeService() ? (string) $user->getCodeService()->getLibelle() : null,
                    'roles'     => $user->getRoles(),
                ],
                'peutEnregistrer'   => $this->aDroitDeGerer(),
                'extensionsPieces'  => PieceJointeCourrier::EXTENSIONS,
                'tailleMaxPieces'   => PieceJointeCourrier::TAILLE_MAX,
                // Le numero d'enregistrement est attribue par le SERVEUR :
                // il n'est jamais saisi sur mobile (champ grise).
                'numero' => [
                    'grise'   => true,
                    'origine' => 'serveur',
                    'libelle' => "Numero d'enregistrement",
                ],
            ],
        ]);
    }

    // ------------------------------------------------------------------
    //  Listing
    // ------------------------------------------------------------------
    /**
     * GET api/courrier?statut=&sens=&q=&limit=
     * Registre (les plus recents d'abord).
     */
    #[Route('', name: 'api_courrier_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }

        $filtres = [
            'statut' => (string) $request->query->get('statut', ''),
            'sens'   => (string) $request->query->get('sens', ''),
            'q'      => (string) $request->query->get('q', ''),
        ];
        $limite = max(1, min(300, (int) ($request->query->get('limit') ?: 100)));

        try {
            $items = [];
            foreach ($this->courriers->rechercher($filtres, $limite) as $c) {
                $items[] = $this->resumer($c);
            }
        } catch (\Throwable $e) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Lecture du registre impossible : ' . $e->getMessage()], 500);
        }

        return new JsonResponse(['code' => 'success', 'msg' => 'OK', 'data' => $items]);
    }

    // ------------------------------------------------------------------
    //  Creation (multipart : champs + pieces[])
    // ------------------------------------------------------------------
    /**
     * POST api/courrier  (multipart/form-data)
     * Champs : sens, objet, correspondant, organisme, reference, date_courrier,
     *          priorite, service, responsable, observation, suite_donnee,
     *          pieces[] (0..n fichiers).
     */
    #[Route('', name: 'api_courrier_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }
        if (!$this->aDroitDeGerer()) {
            return new JsonResponse([
                'code' => 'error',
                'msg'  => "Acces refuse : l'enregistrement du courrier est reserve au role courrier (ROLE_COURRIER) ou a l'administrateur.",
            ], 403);
        }

        $courrier = new Courrier();
        $erreurs = $this->remplir($courrier, $request);

        if ($erreurs === []) {
            $courrier->setCreatedBy($this->getUser());
            $erreurs = $this->deposerPieces($courrier, $request);
        }

        if ($erreurs !== []) {
            return new JsonResponse(['code' => 'warning', 'msg' => implode(' ', $erreurs), 'erreurs' => $erreurs], 400);
        }

        try {
            $this->gestionnaire->attribuerNumero($courrier);
            $this->em->persist($courrier);
            $this->em->flush();
        } catch (\Throwable $e) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Enregistrement impossible : ' . $e->getMessage()], 500);
        }

        return new JsonResponse([
            'code' => 'success',
            'msg'  => 'Courrier enregistre sous le numero ' . $courrier->getNumero() . '.',
            'data' => $this->detailComplet($courrier),
        ], 201);
    }

    // ------------------------------------------------------------------
    //  Fiche
    // ------------------------------------------------------------------
    /**
     * GET api/courrier/{id}
     */
    #[Route('/{id}', name: 'api_courrier_detail', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'], priority: 5)]
    public function detail(string $id): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }

        $courrier = $this->trouver($id);
        if ($courrier === null) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Courrier introuvable.'], 404);
        }

        return new JsonResponse(['code' => 'success', 'msg' => 'OK', 'data' => $this->detailComplet($courrier)]);
    }

    // ------------------------------------------------------------------
    //  Modification
    // ------------------------------------------------------------------
    /**
     * POST api/courrier/{id}
     * Le sens n'est pas modifiable (le numero y est lie). Le corps peut etre
     * un formulaire classique (application/x-www-form-urlencoded).
     */
    #[Route('/{id}', name: 'api_courrier_update', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function update(string $id, Request $request): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }
        if (!$this->aDroitDeGerer()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Acces refuse : modification reservee au role courrier.'], 403);
        }

        $courrier = $this->trouver($id);
        if ($courrier === null) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Courrier introuvable.'], 404);
        }

        $sensInitial = $courrier->getSens();
        $erreurs = $this->remplir($courrier, $request);
        $courrier->setSens($sensInitial);

        if ($erreurs !== []) {
            return new JsonResponse(['code' => 'warning', 'msg' => implode(' ', $erreurs), 'erreurs' => $erreurs], 400);
        }

        try {
            $courrier->touch();
            $this->em->flush();
        } catch (\Throwable $e) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Mise a jour impossible : ' . $e->getMessage()], 500);
        }

        return new JsonResponse([
            'code' => 'success',
            'msg'  => 'Courrier mis a jour.',
            'data' => $this->detailComplet($courrier),
        ]);
    }

    // ------------------------------------------------------------------
    //  Statut
    // ------------------------------------------------------------------
    /**
     * POST api/courrier/{id}/statut  {statut: a_traiter|en_cours|traite|archive}
     */
    #[Route('/{id}/statut', name: 'api_courrier_statut', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function statut(string $id, Request $request): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }
        if (!$this->aDroitDeGerer()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Acces refuse : changement de statut reserve au role courrier.'], 403);
        }

        $courrier = $this->trouver($id);
        if ($courrier === null) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Courrier introuvable.'], 404);
        }

        $valeur = (string) ($request->request->get('statut') ?? '');
        if ($valeur === '') {
            $payload = json_decode($request->getContent() ?: '[]', true);
            if (is_array($payload) && isset($payload['statut'])) {
                $valeur = (string) $payload['statut'];
            }
        }
        if (!isset(Courrier::STATUTS[$valeur])) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Statut invalide.'], 400);
        }

        $courrier->setStatut($valeur)->touch();
        $this->em->flush();

        return new JsonResponse([
            'code' => 'success',
            'msg'  => 'Statut mis a jour : ' . $courrier->getStatutLibelle() . '.',
            'data' => $this->detailComplet($courrier),
        ]);
    }

    // ------------------------------------------------------------------
    //  Pieces jointes
    // ------------------------------------------------------------------
    /**
     * POST api/courrier/{id}/pieces  (multipart, champ pieces[])
     */
    #[Route('/{id}/pieces', name: 'api_courrier_piece_ajouter', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function ajouterPieces(string $id, Request $request): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }
        if (!$this->aDroitDeGerer()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Acces refuse : depot de pieces reserve au role courrier.'], 403);
        }

        $courrier = $this->trouver($id);
        if ($courrier === null) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Courrier introuvable.'], 404);
        }

        $erreurs = $this->deposerPieces($courrier, $request);
        if ($erreurs === []) {
            $courrier->touch();
            $this->em->flush();

            return new JsonResponse([
                'code' => 'success',
                'msg'  => 'Piece(s) jointe(s) ajoutee(s).',
                'data' => $this->detailComplet($courrier),
            ]);
        }

        return new JsonResponse(['code' => 'warning', 'msg' => implode(' ', $erreurs), 'erreurs' => $erreurs], 400);
    }

    /**
     * GET api/courrier/piece/{pieceId}/telecharger
     */
    #[Route('/piece/{pieceId}/telecharger', name: 'api_courrier_piece_telecharger', methods: ['GET'], requirements: ['pieceId' => '[0-9a-fA-F-]{36}'], priority: 20)]
    public function telechargerPiece(string $pieceId): Response
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }

        $piece = $this->em->getRepository(PieceJointeCourrier::class)->find($pieceId);
        if (!$piece instanceof PieceJointeCourrier) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Piece jointe introuvable.'], 404);
        }

        $cheminFichier = $this->stockage->chemin($piece);
        if ($cheminFichier === null) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Fichier absent du serveur.'], 404);
        }

        $nom = preg_replace('/[^A-Za-z0-9._-]/', '-', $piece->getNomOriginal() ?: 'piece-jointe') ?: 'piece-jointe';

        return new BinaryFileResponse($cheminFichier, 200, [
            'Content-Type'        => $piece->getTypeMime() ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $nom . '"',
        ]);
    }

    // ------------------------------------------------------------------
    //  Cles de recherche pour la saisie semi-automatique
    // ------------------------------------------------------------------
    /**
     * GET api/courrier/suggestions?champ=correspondant&q=...&sens=entrant
     */
    #[Route('/suggestions', name: 'api_courrier_suggestions', methods: ['GET'], priority: 20)]
    public function suggestions(Request $request): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }

        $champ = (string) $request->query->get('champ', 'correspondant');
        $sens  = (string) $request->query->get('sens', '');
        $texte = trim((string) $request->query->get('q', ''));

        if (!isset(CourrierRepository::CHAMPS_SUGGERES[$champ])) {
            return new JsonResponse(['code' => 'warning', 'msg' => 'Champ inconnu.', 'data' => []], 400);
        }

        try {
            $valeurs = $this->courriers->suggerer($champ, $texte, $sens !== '' ? $sens : null);
        } catch (\Throwable) {
            $valeurs = [];
        }

        return new JsonResponse(['code' => 'success', 'msg' => 'OK', 'data' => $valeurs]);
    }

    // ------------------------------------------------------------------
    //  Outils
    // ------------------------------------------------------------------
    /** Vrai si l'utilisateur peut enregistrer / modifier un courrier. */
    private function aDroitDeGerer(): bool
    {
        foreach (self::ROLES_GESTION as $role) {
            if ($this->isGranted($role)) {
                return true;
            }
        }

        return false;
    }

    private function trouver(string $id): ?Courrier
    {
        try {
            $courrier = $this->courriers->find($id);
        } catch (\Throwable) {
            return null;
        }

        return $courrier instanceof Courrier ? $courrier : null;
    }

    /**
     * Recopie les champs du formulaire (memes regles que le module web).
     *
     * @return list<string>
     */
    private function remplir(Courrier $courrier, Request $request): array
    {
        $erreurs = [];

        $sens = (string) $request->request->get('sens', $courrier->getSens());
        if (isset(Courrier::SENS[$sens])) {
            $courrier->setSens($sens);
        }

        $objet = trim((string) $request->request->get('objet', ''));
        if ($objet === '' && $courrier->getObjet() === null) {
            $erreurs[] = "L'objet du courrier est obligatoire.";
        }
        if ($objet !== '') {
            $courrier->setObjet($objet);
        }
        if ($courrier->getObjet() !== null && mb_strlen((string) $courrier->getObjet()) > 255) {
            $erreurs[] = "L'objet est trop long (255 caracteres maximum).";
        }

        $correspondant = trim((string) $request->request->get('correspondant', ''));
        if ($correspondant === '' && $courrier->getCorrespondant() === null) {
            $erreurs[] = $courrier->isEntrant()
                ? "L'expediteur du courrier est obligatoire."
                : 'Le destinataire du courrier est obligatoire.';
        }
        if ($correspondant !== '') {
            $courrier->setCorrespondant($correspondant);
        }

        $organisme = $this->videOuValeur($request->request->get('organisme'));
        if ($organisme !== null) { $courrier->setOrganisme($organisme); }

        $reference = $this->videOuValeur($request->request->get('reference'));
        if ($reference !== null) { $courrier->setReference($reference); }

        // Date du courrier : celle du document (souvent anterieure). Elle est
        // modifiable, contrairement a la date d'enregistrement.
        $date = trim((string) $request->request->get('date_courrier', ''));
        if ($date !== '') {
            try {
                $courrier->setDateCourrier(new \DateTimeImmutable(str_replace('/', '-', $date)));
            } catch (\Throwable) {
                $erreurs[] = 'La date du courrier est invalide.';
            }
        }

        $statut = (string) $request->request->get('statut', '');
        if ($statut !== '' && isset(Courrier::STATUTS[$statut])) {
            $courrier->setStatut($statut);
        }

        $priorite = (string) $request->request->get('priorite', '');
        if ($priorite !== '') {
            $courrier->setPriorite(in_array($priorite, Courrier::PRIORITES, true) ? $priorite : 'normale');
        }

        $serviceId = (string) $request->request->get('service', '');
        if ($serviceId !== '') {
            $service = $this->em->getRepository(Services::class)->find($serviceId);
            if ($service instanceof Services) {
                $courrier->setCodeService($service);
                $courrier->setService((string) $service->getLibelle());
            } else {
                $erreurs[] = 'Le service selectionne est introuvable ; choisissez-le a nouveau dans la liste.';
            }
        }

        $responsable = $this->videOuValeur($request->request->get('responsable'));
        if ($responsable !== null) { $courrier->setResponsable($responsable); }

        $observation = $this->videOuValeur($request->request->get('observation'));
        if ($observation !== null) { $courrier->setObservation($observation); }

        $suite = $this->videOuValeur($request->request->get('suite_donnee'));
        if ($suite !== null) { $courrier->setSuiteDonnee($suite); }

        return $erreurs;
    }

    /**
     * Depose les fichiers du champ « pieces[] ».
     *
     * @return list<string> erreurs (vides si tout est accepte)
     */
    private function deposerPieces(Courrier $courrier, Request $request): array
    {
        /** @var array<int, UploadedFile> $fichiers */
        $fichiers = $request->files->get('pieces', []);
        if (!is_array($fichiers)) {
            $fichiers = $fichiers ? [$fichiers] : [];
        }

        $erreurs = [];
        foreach ($fichiers as $fichier) {
            if (!$fichier instanceof UploadedFile || $fichier->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $erreur = $this->gestionnaire->ajouterPieceJointe($courrier, $fichier);
            if ($erreur !== null) {
                $erreurs[] = $erreur;
            }
        }

        return $erreurs;
    }

    private function videOuValeur(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }
        $texte = trim((string) $valeur);

        return $texte === '' ? null : $texte;
    }

    /** Ligne de registre (liste mobile). */
    private function resumer(Courrier $c): array
    {
        return [
            'id'            => (string) $c->getId(),
            'numero'        => $c->getNumero(),
            'sens'          => $c->getSens(),
            'sensLibelle'   => $c->getSensLibelle(),
            'objet'         => $c->getObjet(),
            'correspondant' => $c->getCorrespondant(),
            'organisme'     => $c->getOrganisme(),
            'reference'     => $c->getReference(),
            'dateCourrier'  => $c->getDateCourrier()?->format('Y-m-d'),
            'statut'        => $c->getStatut(),
            'statutLibelle' => $c->getStatutLibelle(),
            'priorite'      => $c->getPriorite(),
            'prioriteLibelle' => $c->getPrioriteLibelle(),
            'service'       => $c->getCodeService() ? (string) $c->getCodeService()->getLibelle() : $c->getService(),
            'pieces'        => $c->getNombrePiecesJointes(),
        ];
    }

    /** Fiche complete (detail mobile). */
    private function detailComplet(Courrier $c): array
    {
        $pieces = [];
        foreach ($c->getPiecesJointes() as $p) {
            $pieces[] = [
                'id'           => (string) $p->getId(),
                'nom'          => $p->getNomOriginal(),
                'typeMime'     => $p->getTypeMime(),
                'taille'       => $p->getTaille(),
                'commentaire'  => $p->getCommentaire(),
                'date'         => $p->getCreatedAt()?->format('Y-m-d H:i'),
                'url'          => '/api/courrier/piece/' . $p->getId() . '/telecharger',
            ];
        }

        return array_merge($this->resumer($c), [
            'serviceId'    => $c->getCodeService() ? (string) $c->getCodeService()->getId() : null,
            'responsable'  => $c->getResponsable(),
            'observation'  => $c->getObservation(),
            'suiteDonnee'  => $c->getSuiteDonnee(),
            'dateAccuse'   => $c->getDateAccuse()?->format('Y-m-d'),
            'createdAt'    => $c->getCreatedAt()?->format('Y-m-d H:i'),
            'updatedAt'    => $c->getUpdatedAt()?->format('Y-m-d H:i'),
            'createdBy'    => $c->getCreatedBy()?->getUserIdentifier(),
            'piecesDetail' => $pieces,
        ]);
    }
}
