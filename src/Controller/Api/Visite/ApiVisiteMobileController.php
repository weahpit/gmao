<?php

namespace App\Controller\Api\Visite;

use App\Entity\Services;
use App\Entity\User;
use App\Entity\Visite\Visite;
use App\Entity\Visite\Visiteur;
use App\Repository\Visite\VisiteRepository;
use App\Repository\Visite\VisiteurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API MOBILE du registre des visites (application CITRAC GMAO).
 *
 * Meme logique que l'API du courrier : l'agent poste a l'entree enregistre
 * un visiteur, imprime ou remet sa piece, puis valide la sortie. Les
 * permissions sont celles du courrier (ROLE_COURRIER et assimilies) :
 * un agent courrier voit et traite les visites sans configuration
 * supplementaire.
 *
 *   GET  api/visite/mobile/referentiel      -> listes fermees + agent
 *   GET  api/visite/mobile?statut=&q=&limit -> registre du jour
 *   GET  api/visite/mobile/{id}             -> fiche d'une visite
 *   GET  api/visite/mobile/autocomplete?q=  -> visiteurs deja enregistres
 *   POST api/visite/mobile/entree            -> enregistrement d'une entree
 *   POST api/visite/mobile/{id}/sortie       -> validation de la sortie
 */
#[Route('api/visite/mobile')]
class ApiVisiteMobileController extends AbstractController
{
    /** Roles autorises a enregistrer / faire sortir (identiques au courrier). */
    private const ROLES_GESTION = ['ROLE_COURRIER', 'ROLE_VIGIL', 'ROLE_ADMIN', 'ROLE_TECHNICIAN', 'ROLE_DG', 'ROLE_PDR', 'ROLE_DL', 'ROLE_CHEF_SERVICE'];
    private const ROLES_CONSULTATION = [...self::ROLES_GESTION, 'ROLE_AQ', 'ROLE_RESP_AQ'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VisiteRepository $visites,
        private readonly VisiteurRepository $visiteurs,
    ) {}

    // ------------------------------------------------------------------
    //  Referentiel
    // ------------------------------------------------------------------
    #[Route('/referentiel', name: 'api_visite_mobile_referentiel', methods: ['GET'])]
    public function referentiel(): JsonResponse
    {
        if (!$this->aDroitDeConsulter()) return $this->erreur('Consultation des visites reservee aux personnes habilitees.', 403);
        if (!$this->getUser() instanceof User) {
            return $this->erreur('Non authentifie', 401);
        }

        /** @var User $user */
        $user = $this->getUser();

        $services = [];
        foreach ($this->em->getRepository(Services::class)->findBy([], ['libelle' => 'ASC']) as $service) {
            $services[] = ['id' => (string) $service->getId(), 'libelle' => (string) $service->getLibelle()];
        }

        return $this->succes([
            'statuts'    => Visite::STATUTS,
            'motifs'     => array_keys(Visite::MOTIFS),
            'typesPiece' => Visiteur::TYPES_PIECE,
            'services'   => $services,
            'agent'      => [
                'id'        => (string) $user->getId(),
                'libelle'   => trim((string) $user->getPrenoms() . ' ' . (string) $user->getNom()),
                'serviceId' => $user->getCodeService() ? (string) $user->getCodeService()->getId() : null,
                'service'   => $user->getCodeService() ? (string) $user->getCodeService()->getLibelle() : null,
                'roles'     => $user->getRoles(),
            ],
            'peutEnregistrer' => $this->aDroitDeGerer(),
            'photoObligatoire' => false,
        ]);
    }

    // ------------------------------------------------------------------
    //  Registre
    // ------------------------------------------------------------------
    #[Route('', name: 'api_visite_mobile_liste', methods: ['GET'])]
    public function liste(Request $request): JsonResponse
    {
        if (!$this->aDroitDeConsulter()) return $this->erreur('Consultation des visites reservee aux personnes habilitees.', 403);
        if (!$this->getUser() instanceof User) {
            return $this->erreur('Non authentifie', 401);
        }

        $filtres = [
            'q'      => trim((string) $request->query->get('q', '')),
            'statut' => (string) $request->query->get('statut', ''),
            'du'     => (string) $request->query->get('du', ''),
            'au'     => (string) $request->query->get('au', ''),
        ];

        $limite = max(1, min(300, (int) ($request->query->get('limit') ?: 100)));

        try {
            $lignes = [];
            foreach ($this->visites->rechercher($filtres, $limite) as $visite) {
                $lignes[] = $this->resume($visite);
            }
        } catch (\Throwable $e) {
            return $this->erreur('Lecture du registre impossible : ' . $e->getMessage(), 500);
        }

        return $this->succes([
            'total'  => \count($lignes),
            'visites' => $lignes,
        ]);
    }

    #[Route('/autocomplete', name: 'api_visite_mobile_autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request): JsonResponse
    {
        if (!$this->aDroitDeConsulter()) return $this->erreur('Consultation des visites reservee aux personnes habilitees.', 403);
        if (!$this->getUser() instanceof User) {
            return $this->erreur('Non authentifie', 401);
        }

        $texte = trim((string) $request->query->get('q', ''));
        if (mb_strlen($texte) < 2) {
            return $this->succes(['visiteurs' => []]);
        }

        $resultats = [];
        foreach ($this->visiteurs->rechercher($texte, 12) as $visiteur) {
            $resultats[] = [
                'id'          => (string) $visiteur->getId(),
                'libelle'     => $visiteur->getNomComplet(),
                'nom'         => (string) $visiteur->getNom(),
                'prenoms'     => (string) $visiteur->getPrenoms(),
                'societe'     => (string) $visiteur->getSociete(),
                'fonction'    => (string) $visiteur->getFonction(),
                'telephone'   => (string) $visiteur->getTelephone(),
                'typePiece'   => (string) $visiteur->getTypePiece(),
                'numeroPiece' => (string) $visiteur->getNumeroPiece(),
            ];
        }

        return $this->succes(['visiteurs' => $resultats]);
    }

    #[Route('/{id}', name: 'api_visite_mobile_detail', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function detail(string $id): JsonResponse
    {
        if (!$this->aDroitDeConsulter()) return $this->erreur('Consultation des visites reservee aux personnes habilitees.', 403);
        if (!$this->getUser() instanceof User) {
            return $this->erreur('Non authentifie', 401);
        }

        $visite = $this->visites->find($id);
        if ($visite === null) {
            return $this->erreur('Visite introuvable pour l\'identifiant ' . $id . '.', 404);
        }

        $historique = [];
        $visiteur = $visite->getVisiteur();
        if ($visiteur !== null) {
            foreach ($this->visites->pourVisiteur($visiteur, 10) as $ancienne) {
                $historique[] = $this->resume($ancienne);
            }
        }

        return $this->succes([
            'visite'     => $this->resume($visite, true),
            'historique' => $historique,
        ]);
    }

    // ------------------------------------------------------------------
    //  Entree
    // ------------------------------------------------------------------
    #[Route('/entree', name: 'api_visite_mobile_entree', methods: ['POST'])]
    public function entree(Request $request): JsonResponse
    {
        if (!$this->getUser() instanceof User) {
            return $this->erreur('Non authentifie', 401);
        }

        if (!$this->aDroitDeGerer()) {
            return $this->erreur(
                'Vous n\'êtes pas autorise a enregistrer une visite : role ROLE_COURRIER (ou administrateur) requis. Roles de votre compte : '
                . implode(', ', $this->getUser()->getRoles()),
                403
            );
        }

        $donnees = $this->lireDonnees($request);

        $nom = trim((string) ($donnees['nom'] ?? ''));
        if ($nom === '') {
            return $this->erreur('Le nom du visiteur est obligatoire.', 400);
        }

        $motif = (string) ($donnees['motif'] ?? '');
        if ($motif !== '' && !isset(Visite::MOTIFS[$motif])) {
            $motif = '';
        }

        try {
            $visiteur = $this->resoudreVisiteur($donnees);
            if ($visiteur === null) {
                return $this->erreur('Le visiteur n\'a pas pu etre prepare.', 400);
            }

            $service = null;
            $serviceId = trim((string) ($donnees['service'] ?? $donnees['serviceId'] ?? ''));
            if ($serviceId !== '') {
                $service = $this->em->getRepository(Services::class)->find($serviceId);
                if ($service === null) {
                    return $this->erreur('Service introuvable pour l\'identifiant ' . $serviceId . '.', 400);
                }
            }

            $maintenant = new \DateTimeImmutable();
            $visite = (new Visite())
                ->setReference($this->visites->genererReference($maintenant))
                ->setVisiteur($visiteur)
                ->setService($service)
                ->setPersonneVisitee(trim((string) ($donnees['personne_visitee'] ?? $donnees['personneVisitee'] ?? '')) ?: null)
                ->setMotif($motif !== '' ? $motif : null)
                ->setNombreAccompagnants(max(0, (int) ($donnees['nombre_accompagnants'] ?? $donnees['nombreAccompagnants'] ?? 0)))
                ->setHeureEntree($maintenant)
                ->setStatut(Visite::EN_COURS)
                ->setAgentEntree($this->getUser())
                ->setObservations(trim((string) ($donnees['observations'] ?? '')) ?: null);

            $this->em->persist($visite);
            $this->em->flush();
        } catch (\Throwable $e) {
            return $this->erreur('Enregistrement de la visite impossible : ' . $e->getMessage(), 500);
        }

        return $this->succes(['visite' => $this->resume($visite, true)], 201, 'Visite enregistree.');
    }

    // ------------------------------------------------------------------
    //  Sortie
    // ------------------------------------------------------------------
    #[Route('/{id}/sortie', name: 'api_visite_mobile_sortie', methods: ['POST'], requirements: ['id' => '[0-9a-fA-F-]{36}'])]
    public function sortie(string $id, Request $request): JsonResponse
    {
        if (!$this->getUser() instanceof User) {
            return $this->erreur('Non authentifie', 401);
        }

        if (!$this->aDroitDeGerer()) {
            return $this->erreur(
                'Vous n\'êtes pas autorise a valider une sortie : role ROLE_COURRIER (ou administrateur) requis. Roles de votre compte : '
                . implode(', ', $this->getUser()->getRoles()),
                403
            );
        }

        $visite = $this->visites->find($id);
        if ($visite === null) {
            return $this->erreur('Visite introuvable pour l\'identifiant ' . $id . '.', 404);
        }

        if ($visite->getStatut() === Visite::SORTI) {
            return $this->erreur('La sortie de cette visite est deja enregistree.', 409);
        }

        $donnees = $this->lireDonnees($request);

        $visite
            ->setHeureSortie(new \DateTimeImmutable())
            ->setStatut(Visite::SORTI)
            ->setPieceRemise((bool) ($donnees['piece_remise'] ?? $donnees['pieceRemise'] ?? true))
            ->setAgentSortie($this->getUser());

        $observation = trim((string) ($donnees['observations'] ?? ''));
        if ($observation !== '') {
            $visite->setObservations(trim((string) $visite->getObservations() . ' ' . $observation));
        }

        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            return $this->erreur('Validation de la sortie impossible : ' . $e->getMessage(), 500);
        }

        return $this->succes(['visite' => $this->resume($visite, true)], 200, 'Sortie enregistree.');
    }

    // ------------------------------------------------------------------
    //  Utilitaires
    // ------------------------------------------------------------------
    private function aDroitDeConsulter(): bool
    {
        foreach (self::ROLES_CONSULTATION as $role) if ($this->isGranted($role)) return true;
        return false;
    }

    private function aDroitDeGerer(): bool
    {
        foreach (self::ROLES_GESTION as $role) {
            if ($this->isGranted($role)) {
                return true;
            }
        }

        return false;
    }

    private function lireDonnees(Request $request): array
    {
        $donnees = $request->request->all();

        if ($donnees === [] && $request->getContent() !== '') {
            $json = json_decode((string) $request->getContent(), true);
            if (\is_array($json)) {
                $donnees = $json;
            }
        }

        return $donnees;
    }

    private function resoudreVisiteur(array $donnees): ?Visiteur
    {
        $typePiece = trim((string) ($donnees['type_piece'] ?? $donnees['typePiece'] ?? ''));
        $numeroPiece = trim((string) ($donnees['numero_piece'] ?? $donnees['numeroPiece'] ?? ''));

        $visiteur = null;
        if ($numeroPiece !== '') {
            $visiteur = $this->visiteurs->trouverParPiece($typePiece !== '' ? $typePiece : null, $numeroPiece);
        }

        $visiteur ??= new Visiteur();

        $visiteur
            ->setNom((string) ($donnees['nom'] ?? ''))
            ->setPrenoms(trim((string) ($donnees['prenoms'] ?? '')) ?: null)
            ->setTypePiece($typePiece !== '' ? $typePiece : null)
            ->setNumeroPiece($numeroPiece !== '' ? $numeroPiece : null)
            ->setSociete(trim((string) ($donnees['societe'] ?? '')) ?: null)
            ->setFonction(trim((string) ($donnees['fonction'] ?? '')) ?: null)
            ->setTelephone(trim((string) ($donnees['telephone'] ?? '')) ?: null)
            ->setEmail(trim((string) ($donnees['email'] ?? '')) ?: null)
            ->setObservations(trim((string) ($donnees['observations'] ?? '')) ?: null);

        if ($visiteur->getId() === null) {
            $this->em->persist($visiteur);
        }

        return $visiteur;
    }

    /**
     * @return array<string, mixed>
     */
    private function resume(Visite $visite, bool $complet = false): array
    {
        $visiteur = $visite->getVisiteur();

        $resume = [
            'id'                 => (string) $visite->getId(),
            'reference'          => $visite->getReference(),
            'visiteur'           => $visiteur ? $visiteur->getNomComplet() : '',
            'societe'            => $visiteur ? (string) $visiteur->getSociete() : '',
            'telephone'          => $visiteur ? (string) $visiteur->getTelephone() : '',
            'service'            => $visite->getService() ? (string) $visite->getService()->getLibelle() : '',
            'personneVisitee'    => (string) $visite->getPersonneVisitee(),
            'motif'              => (string) $visite->getMotif(),
            'nombreAccompagnants' => $visite->getNombreAccompagnants(),
            'heureEntree'        => $visite->getHeureEntree()->format('Y-m-d H:i'),
            'heureSortie'        => $visite->getHeureSortie()?->format('Y-m-d H:i'),
            'statut'             => $visite->getStatut(),
            'statutLibelle'      => Visite::STATUTS[$visite->getStatut()] ?? $visite->getStatut(),
            'duree'              => $visite->getDureeLibelle(),
            'pieceRemise'        => $visite->isPieceRemise(),
            'enqueteSanitaire'   => $visite->getEnqueteSanitaire(),
            'signature'          => $visite->getSignatureVisiteur() !== null,
        ];

        if ($complet) {
            $resume['observations'] = (string) $visite->getObservations();
            $resume['visiteurDetail'] = $visiteur === null ? null : [
                'id'          => (string) $visiteur->getId(),
                'nom'         => (string) $visiteur->getNom(),
                'prenoms'     => (string) $visiteur->getPrenoms(),
                'typePiece'   => (string) $visiteur->getTypePiece(),
                'numeroPiece' => (string) $visiteur->getNumeroPiece(),
                'email'       => (string) $visiteur->getEmail(),
            ];
            $resume['agentEntree'] = $visite->getAgentEntree() ? (string) $visite->getAgentEntree()->getNom() : '';
            $resume['agentSortie'] = $visite->getAgentSortie() ? (string) $visite->getAgentSortie()->getNom() : '';
        }

        return $resume;
    }

    private function succes(array $data, int $code = 200, string $message = 'OK'): JsonResponse
    {
        return new JsonResponse(['code' => 'success', 'msg' => $message, 'data' => $data], $code);
    }

    private function erreur(string $message, int $code): JsonResponse
    {
        return new JsonResponse(['code' => 'error', 'msg' => $message, 'data' => null], $code);
    }
}
