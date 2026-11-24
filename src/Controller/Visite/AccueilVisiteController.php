<?php

namespace App\Controller\Visite;

use App\Entity\Directions;
use App\Entity\Services;
use App\Entity\Visite\Visite;
use App\Entity\Visite\Visiteur;
use App\Repository\Visite\VisiteRepository;
use App\Repository\Visite\VisiteurRepository;
use App\Service\Visite\StockagePieceIdentite;
use App\Service\Visite\EnqueteVisiteur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Accueil des visiteurs de CITRAC.
 *
 *  - /visites                    : tableau de bord (visiteurs presents, passages du jour)
 *  - /visites/entree             : enregistrement d'une entree (piece d'identite jointe)
 *  - /visites/sortie/{id}        : restitution de la piece et enregistrement de la sortie
 *  - /visites/visiteur/{id}      : fiche d'un visiteur et historique de ses passages
 *  - /visites/autocomplete       : saisie semi-automatique des visiteurs connus
 *  - /visites/piece/{id}         : consultation de la piece d'identite scannee
 */
#[Route('/visites')]
#[IsGranted('VISITES_CONSULTER')]
final class AccueilVisiteController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private VisiteRepository $visites,
        private VisiteurRepository $visiteurs,
        private StockagePieceIdentite $stockage,
    ) {}

    // ------------------------------------------------------------------
    //  Tableau de bord
    // ------------------------------------------------------------------
    #[Route('', name: 'app_visites', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $recherche = trim((string) $request->query->get('q', ''));
        $aujourdhui = new \DateTimeImmutable();

        $presents = $this->visites->presents();
        $jour = $this->visites->duJour($aujourdhui);

        // La recherche libre s'applique aussi a la liste des presents.
        if ($recherche !== '') {
            $motif = mb_strtolower($recherche);
            $filtre = static function (Visite $v) use ($motif): bool {
                $texte = mb_strtolower(implode(' ', array_filter([
                    $v->getVisiteur()?->getNomComplet(),
                    $v->getVisiteur()?->getNumeroPiece(),
                    $v->getVisiteur()?->getSociete(),
                    $v->getPersonneVisitee(),
                    $v->getReference(),
                ])));

                return str_contains($texte, $motif);
            };
            $presents = array_values(array_filter($presents, $filtre));
            $jour = array_values(array_filter($jour, $filtre));
        }

        $debutMois = new \DateTimeImmutable('first day of this month 00:00:00');
        $finMois = new \DateTimeImmutable('last day of this month 23:59:59');

        return $this->render('visite/index.html.twig', [
            'presents' => $presents,
            'jour' => $jour,
            'recherche' => $recherche,
            'nbPresents' => count($presents),
            'nbEntreesJour' => count($jour),
            'nbSortiesJour' => count(array_filter($jour, static fn (Visite $v) => !$v->isEnCours())),
            'nbMois' => $this->visites->compterPeriode($debutMois, $finMois),
            'maintenant' => $aujourdhui,
        ]);
    }

    // ------------------------------------------------------------------
    //  Enregistrement d'une entree
    // ------------------------------------------------------------------
    #[Route('/entree', name: 'app_visites_entree', methods: ['GET', 'POST'])]
    #[IsGranted('VISITES_GERER')]
    public function entree(Request $request): Response
    {
        $erreurs = [];
        $donnees = [
            'visiteur_id' => '',
            'nom' => '',
            'prenoms' => '',
            'type_piece' => 'CNI',
            'numero_piece' => '',
            'societe' => '',
            'fonction' => '',
            'telephone' => '',
            'email' => '',
            'service_id' => '',
            'personne_visitee' => '',
            'motif' => '',
            'nombre_accompagnants' => '0',
            'observations' => '',
        ];

        $enquete=[]; $signature='';

        // Arrivee depuis la fiche d'un visiteur : on reprend sa fiche pour eviter la ressaisie.
        if (!$request->isMethod('POST') && $request->query->get('visiteur')) {
            $connu = $this->visiteurs->find((string) $request->query->get('visiteur'));
            if ($connu instanceof Visiteur) {
                $donnees = [
                    'visiteur_id' => (string) $connu->getId(),
                    'nom' => (string) $connu->getNom(),
                    'prenoms' => (string) $connu->getPrenoms(),
                    'type_piece' => (string) ($connu->getTypePiece() ?: 'CNI'),
                    'numero_piece' => (string) $connu->getNumeroPiece(),
                    'societe' => (string) $connu->getSociete(),
                    'fonction' => (string) $connu->getFonction(),
                    'telephone' => (string) $connu->getTelephone(),
                    'email' => (string) $connu->getEmail(),
                    'service_id' => '',
                    'personne_visitee' => '',
                    'motif' => '',
                    'nombre_accompagnants' => '0',
                    'observations' => '',
                ];
            }
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('visite_entree', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Session expiree, merci de recommencer l\'enregistrement.');

                return $this->redirectToRoute('app_visites_entree');
            }

            foreach (array_keys($donnees) as $cle) {
                $donnees[$cle] = trim((string) $request->request->get($cle, ''));
            }

            $enquete=$request->request->all('enquete');
            $signature=(string) $request->request->get('signature_visiteur','');
            $traits=null;
            try { $enquete=EnqueteVisiteur::reponses($enquete); }
            catch (\InvalidArgumentException $e) { $erreurs[]=$e->getMessage(); }
            try { $traits=EnqueteVisiteur::signature($signature); }
            catch (\InvalidArgumentException $e) { $erreurs[]=$e->getMessage(); $signature=''; }

            $visiteur = $this->resoudreVisiteur($donnees, $erreurs);
            $service = $this->resoudreService($donnees['service_id'], $erreurs);
            $motif = $donnees['motif'] !== '' ? $donnees['motif'] : null;

            if ($motif !== null && !isset(Visite::MOTIFS[$motif])) {
                $motif = 'Autre';
            }

            if ($visiteur !== null && $visiteur->getId() === null) {
                $this->em->persist($visiteur);
            }

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $fichier */
            $fichier = $request->files->get('piece');
            if ($fichier === null || $fichier->getError() === UPLOAD_ERR_NO_FILE) {
                $erreurs[] = 'La piece d\'identite est obligatoire : joignez un scan ou une photo (JPG, PNG, WEBP ou PDF, 8 Mo maximum).';
            }

            if ($erreurs === []) {
                $visite = new Visite();
                $visite->setVisiteur($visiteur)
                    ->setService($service)
                    ->setPersonneVisitee($donnees['personne_visitee'] !== '' ? $donnees['personne_visitee'] : null)
                    ->setMotif($motif)
                    ->setNombreAccompagnants((int) $donnees['nombre_accompagnants'])
                    ->setObservations($donnees['observations'] !== '' ? $donnees['observations'] : null)
                    ->setHeureEntree(new \DateTimeImmutable())
                    ->setStatut(Visite::EN_COURS)
                    ->setReference($this->visites->genererReference(new \DateTimeImmutable()))
                    ->setEnqueteSanitaire($enquete)->setSignatureVisiteur($traits);

                $utilisateur = $this->getUser();
                if ($utilisateur instanceof \App\Entity\User) {
                    $visite->setAgentEntree($utilisateur);
                }

                try {
                    $this->stockage->stocker($fichier, $visite);
                } catch (\RuntimeException $e) {
                    $erreurs[] = $e->getMessage();
                }
            }

            if ($erreurs === [] && isset($visite)) {
                $visiteur->touch();
                $this->em->persist($visite);
                $this->em->flush();

                $this->addFlash('success', 'Entree enregistree : ' . $visiteur->getNomComplet()
                    . ' (' . $visite->getReference() . '). La piece d\'identite est conservee a l\'accueil.');

                return $this->redirectToRoute('app_visites');
            }

            if ($visiteur !== null && $visiteur->getId() === null) {
                $this->em->detach($visiteur);
            }
        }

        return $this->render('visite/entree.html.twig', [
            'donnees' => $donnees,
            'questionsSanitaires' => EnqueteVisiteur::QUESTIONS, 'enquete' => $enquete, 'signature' => $signature,
            'erreurs' => $erreurs,
            'services' => $this->listeServices(),
            'servicesParDirection' => $this->servicesParDirection(),
            'servicesDirection' => $this->em->getRepository(Directions::class)->findBy([], ['libelle'=>'ASC']),
            'typesPiece' => Visiteur::TYPES_PIECE,
            'motifs' => Visite::MOTIFS,
            'tailleMax' => StockagePieceIdentite::TAILLE_MAX,
            'extensions' => StockagePieceIdentite::EXTENSIONS,
        ]);
    }

    // ------------------------------------------------------------------
    //  Sortie / restitution de la piece
    // ------------------------------------------------------------------
    #[Route('/sortie/{id}', name: 'app_visites_sortie', methods: ['GET', 'POST'])]
    public function sortie(string $id, Request $request): Response
    {
        if ($request->isMethod('POST')) $this->denyAccessUnlessGranted('VISITES_GERER');
        $visite = $this->visites->find($id);
        if (!$visite instanceof Visite) {
            throw $this->createNotFoundException('Ce passage est introuvable (identifiant : ' . $id . ').');
        }

        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('visite_sortie_' . $visite->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Session expiree, merci de recommencer.');

                return $this->redirectToRoute('app_visites_sortie', ['id' => $visite->getId()]);
            }

            if (!$visite->isEnCours()) {
                $this->addFlash('info', 'Ce passage est deja cloture (' . $visite->getStatutLibelle() . ').');

                return $this->redirectToRoute('app_visites');
            }

            if (!$request->request->getBoolean('piece_remise')) {
                $erreurs[] = 'Cochez la case de confirmation : la piece d\'identite doit avoir ete remise au visiteur avant de cloturer le passage.';
            }

            if ($erreurs === []) {
                $visite->setHeureSortie(new \DateTimeImmutable())
                    ->setPieceRemise(true)
                    ->setStatut(Visite::SORTI);

                $utilisateur = $this->getUser();
                if ($utilisateur instanceof \App\Entity\User) {
                    $visite->setAgentSortie($utilisateur);
                }

                $this->em->flush();

                $this->addFlash('success', 'Sortie enregistree pour ' . $visite->getVisiteur()?->getNomComplet()
                    . ' a ' . $visite->getHeureSortie()->format('H:i') . '. La piece d\'identite a ete remise.');

                return $this->redirectToRoute('app_visites');
            }
        }

        return $this->render('visite/sortie.html.twig', [
            'visite' => $visite,
            'erreurs' => $erreurs,
        ]);
    }

    // ------------------------------------------------------------------
    //  Fiche d'un visiteur
    // ------------------------------------------------------------------
    #[Route('/visiteur/{id}', name: 'app_visites_visiteur', methods: ['GET'])]
    public function visiteur(string $id): Response
    {
        $visiteur = $this->visiteurs->find($id);
        if (!$visiteur instanceof Visiteur) {
            throw $this->createNotFoundException('Ce visiteur est introuvable (identifiant : ' . $id . ').');
        }

        $historique = $this->visites->pourVisiteur($visiteur);

        return $this->render('visite/visiteur.html.twig', [
            'visiteur' => $visiteur,
            'historique' => $historique,
            'enCours' => array_values(array_filter($historique, static fn (Visite $v) => $v->isEnCours())),
        ]);
    }

    // ------------------------------------------------------------------
    //  Saisie semi-automatique
    // ------------------------------------------------------------------
    #[Route('/autocomplete', name: 'app_visites_autocomplete', methods: ['GET'], priority: 10)]
    public function autocomplete(Request $request): JsonResponse
    {
        $texte = trim((string) $request->query->get('q', ''));
        $resultats = [];

        if (mb_strlen($texte) >= 2) {
            foreach ($this->visiteurs->rechercher($texte, 10) as $visiteur) {
                $resultats[] = [
                    'id' => (string) $visiteur->getId(),
                    'nomComplet' => $visiteur->getNomComplet(),
                    'nom' => $visiteur->getNom(),
                    'prenoms' => (string) $visiteur->getPrenoms(),
                    'typePiece' => (string) $visiteur->getTypePiece(),
                    'typePieceLibelle' => $visiteur->getTypePieceLibelle(),
                    'numeroPiece' => (string) $visiteur->getNumeroPiece(),
                    'pieceComplete' => $visiteur->getPieceComplete(),
                    'societe' => (string) $visiteur->getSociete(),
                    'fonction' => (string) $visiteur->getFonction(),
                    'telephone' => (string) $visiteur->getTelephone(),
                    'email' => (string) $visiteur->getEmail(),
                    'visites' => $visiteur->getVisites()->count(),
                    'url' => $this->generateUrl('app_visites_visiteur', ['id' => $visiteur->getId()]),
                ];
            }
        }

        return new JsonResponse(['resultats' => $resultats]);
    }

    // ------------------------------------------------------------------
    //  Piece d'identite jointe
    // ------------------------------------------------------------------
    #[Route('/piece/{id}', name: 'app_visites_piece', methods: ['GET'])]
    public function piece(string $id): Response
    {
        $visite = $this->visites->find($id);
        if (!$visite instanceof Visite) {
            throw $this->createNotFoundException('Ce passage est introuvable (identifiant : ' . $id . ').');
        }

        $chemin = $this->stockage->chemin($visite);
        if ($chemin === null) {
            $this->addFlash('warning', 'La piece d\'identite de ce passage est absente du disque ('
                . ($visite->getPieceIdentite() ?? 'aucun fichier enregistre') . ').');

            return $this->redirectToRoute('app_visites_sortie', ['id' => $visite->getId()]);
        }

        $reponse = new BinaryFileResponse($chemin);
        $reponse->headers->set('X-Content-Type-Options', 'nosniff');
        $reponse->setContentDisposition('inline', (string) ($visite->getPieceNomOriginal() ?: 'piece-identite'));

        return $reponse;
    }

    #[Route('/signature/{id}', name: 'app_visites_signature', methods: ['GET'])]
    public function signature(string $id): Response
    {
        $visite=$this->visites->find($id);
        if (!$visite instanceof Visite || !$visite->getSignatureVisiteur()) throw $this->createNotFoundException('Aucune signature enregistree pour ce passage.');
        return new Response(EnqueteVisiteur::image($visite->getSignatureVisiteur()),200,[
            'Content-Type'=>'image/svg+xml', 'Cache-Control'=>'private, no-store',
            'X-Content-Type-Options'=>'nosniff', 'Content-Security-Policy'=>"default-src 'none'; style-src 'none'; sandbox",
        ]);
    }

    // ------------------------------------------------------------------
    //  Outils
    // ------------------------------------------------------------------
    /**
     * Determine le visiteur : fiche existante selectionnee, fiche retrouvee par
     * sa piece, ou nouvelle fiche a creer.
     *
     * @param array<string, string> $donnees
     * @param list<string> $erreurs
     */
    private function resoudreVisiteur(array $donnees, array &$erreurs): ?Visiteur
    {
        $visiteurId = (string) ($donnees['visiteur_id'] ?? '');
        if ($visiteurId !== '') {
            $existant = $this->visiteurs->find($visiteurId);
            if ($existant instanceof Visiteur) {
                $this->mettreAJourFiche($existant, $donnees);

                return $existant;
            }
            $erreurs[] = 'Le visiteur selectionne est introuvable ; recherchez-le a nouveau ou saisissez une nouvelle fiche.';
        }

        if ($donnees['nom'] === '') {
            $erreurs[] = 'Le nom du visiteur est obligatoire (ou selectionnez un visiteur deja enregistre).';

            return null;
        }

        $typePiece = $donnees['type_piece'] !== '' ? $donnees['type_piece'] : null;
        $numeroPiece = $donnees['numero_piece'] !== '' ? $donnees['numero_piece'] : null;

        // Meme piece deja connue : on complete la fiche existante plutot que
        // de creer un doublon.
        $existant = $this->visiteurs->trouverParPiece($typePiece, $numeroPiece);
        if ($existant instanceof Visiteur) {
            $this->mettreAJourFiche($existant, $donnees);

            return $existant;
        }

        $visiteur = new Visiteur();
        $visiteur->setNom($donnees['nom']);
        $this->mettreAJourFiche($visiteur, $donnees);

        return $visiteur;
    }

    /** @param array<string, string> $donnees */
    private function mettreAJourFiche(Visiteur $visiteur, array $donnees): void
    {
        $visiteur->setNom($donnees['nom'] !== '' ? $donnees['nom'] : $visiteur->getNom())
            ->setPrenoms($donnees['prenoms'] !== '' ? $donnees['prenoms'] : $visiteur->getPrenoms())
            ->setTypePiece($donnees['type_piece'] !== '' ? $donnees['type_piece'] : $visiteur->getTypePiece())
            ->setNumeroPiece($donnees['numero_piece'] !== '' ? $donnees['numero_piece'] : $visiteur->getNumeroPiece())
            ->setSociete($donnees['societe'] !== '' ? $donnees['societe'] : $visiteur->getSociete())
            ->setFonction($donnees['fonction'] !== '' ? $donnees['fonction'] : $visiteur->getFonction())
            ->setTelephone($donnees['telephone'] !== '' ? $donnees['telephone'] : $visiteur->getTelephone())
            ->setEmail($donnees['email'] !== '' ? $donnees['email'] : $visiteur->getEmail());
    }

    /** @param list<string> $erreurs */
    private function resoudreService(string $serviceId, array &$erreurs): ?Services
    {
        if ($serviceId === '') {
            return null;
        }

        $service = $this->em->getRepository(Services::class)->find($serviceId);
        if (!$service instanceof Services) {
            $erreurs[] = 'Le service visite est introuvable ; choisissez-le a nouveau dans la liste.';

            return null;
        }

        return $service;
    }

    /**
     * Services regroupes sous leur direction, pour la liste deroulante.
     *
     * @return list<array{direction: string, services: list<Services>}>
     */
    private function servicesParDirection(): array
    {
        $groupes = [];
        foreach ($this->listeServices() as $service) {
            $direction = $service->getCodeDirection()?->getLibelle() ?: 'Sans direction';
            $groupes[$direction][] = $service;
        }

        ksort($groupes);

        $resultat = [];
        foreach ($groupes as $direction => $services) {
            $resultat[] = ['direction' => (string) $direction, 'services' => $services];
        }

        return $resultat;
    }

    /** @return list<Services> */
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
            return [];
        }
    }
}
