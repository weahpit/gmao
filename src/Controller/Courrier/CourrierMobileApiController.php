<?php

namespace App\Controller\Courrier;

use App\Entity\User;
use App\Repository\Courrier\CourrierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints de lecture attendus par l'application mobile (ecran « Nouveau courrier »).
 *
 * L'application mobile appelle :
 *   GET  api/courrier/mobile/referentiel              -> listes + agent + parametres
 *   GET  api/courrier/mobile/suggestions?q=           -> autocompletion expediteur / organisme
 *   POST api/courrier/mobile/creer                    -> enregistrement rapide du courrier
 *
 * Les pieces jointes, la modification et le changement de statut restent
 * geres par CourrierApiController (api/courrier).
 */
#[Route('api/courrier/mobile')]
class CourrierMobileApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CourrierRepository $courriers,
    ) {}

    private function refuseSiNonConnecte(): ?JsonResponse
    {
        if (!$this->getUser() instanceof User) {
            return new JsonResponse(['code' => 'error', 'msg' => 'Non authentifie'], 401);
        }

        return null;
    }

    /**
     * GET api/courrier/mobile/referentiel
     *
     * Repond aux deux formats utilises par les applications : celui de la
     * GMAO (code sucess/error + data) et celui de la gestion du courrier
     * (code 200 + data).
     */
    #[Route('/referentiel', name: 'api_courrier_mobile_referentiel', methods: ['GET'])]
    public function referentiel(): JsonResponse
    {
        if ($refus = $this->refuseSiNonConnecte()) {
            return $refus;
        }

        /** @var User $user */
        $user = $this->getUser();

        $services = [];
        foreach ($this->em->getRepository(\App\Entity\Services::class)->findBy([], ['libelle' => 'ASC']) as $s) {
            $services[] = ['id' => (string) $s->getId(), 'libelle' => (string) $s->getLibelle()];
        }

        $agences = [];
        foreach ($services as $s) {
            $agences[] = ['id' => $s['id'], 'libelle' => $s['libelle']];
        }

        $agent = [
            'id'        => (string) $user->getId(),
            'libelle'   => trim(((string) ($user->getPrenoms() ?? '')) . ' ' . ((string) ($user->getNom() ?? ''))),
            'nom'       => (string) ($user->getNom() ?? ''),
            'prenoms'   => (string) ($user->getPrenoms() ?? ''),
            'serviceId' => $user->getCodeService() ? (string) $user->getCodeService()->getId() : null,
            'service'   => $user->getCodeService() ? (string) $user->getCodeService()->getLibelle() : null,
            'roles'     => $user->getRoles(),
        ];

        $sens = [];
        $sensSimple = [];
        foreach (\App\Entity\Courrier\Courrier::SENS as $cle => $libelle) {
            $sens[] = ['code' => (string) $cle, 'libelle' => (string) $libelle];
            $sensSimple[] = (string) $cle;
        }

        $priorites = [];
        foreach (\App\Entity\Courrier\Courrier::PRIORITES as $cle) {
            $priorites[] = ['code' => $cle, 'libelle' => ucfirst(str_replace('_', ' ', $cle))];
        }

        $statuts = [];
        $statutsSimple = [];
        foreach (\App\Entity\Courrier\Courrier::STATUTS as $cle => $libelle) {
            $statuts[] = ['code' => (string) $cle, 'libelle' => (string) $libelle];
            $statutsSimple[] = (string) $cle;
        }

        $donnees = [
            'sens'             => $sens,
            'statuts'          => $statuts,
            'priorites'        => $priorites,
            'services'         => $services,
            'agences'          => $agences,
            'emplacements'     => $agences,
            'agent'            => $agent,
            'agentLibelle'     => $agent['libelle'],
            'agentService'     => $agent['service'],
            'peutEnregistrer'  => $this->peutGerer(),
            'extensionsPieces' => \App\Entity\Courrier\PieceJointeCourrier::EXTENSIONS,
            'tailleMaxPieces'  => \App\Entity\Courrier\PieceJointeCourrier::TAILLE_MAX,
            'numeroGrise'      => true,
            // Variantes « listes de chaines » : tolere les deux lectures possibles
            // d'une liste de valeurs dans l'application mobile.
            'sensCodes'        => $sensSimple,
            'statutCodes'      => $statutsSimple,
            'servicesLibelles' => array_map(static fn (array $s) => $s['libelle'], $services),
        ];

        return new JsonResponse([
            'code' => 'success',
            'msg'  => 'OK',
            'data' => $donnees,
        ]);
    }

    /**
     * GET api/courrier/mobile/suggestions?q=...
     * Autocompletion a partir des courriers deja enregistres.
     */
    #[Route('/suggestions', name: 'api_courrier_mobile_suggestions', methods: ['GET'], priority: 30)]
    public function suggestions(Request $request): JsonResponse
    {
        if ($refus = $this->refuseSiNonConnecte()) {
            return $refus;
        }

        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) < 2) {
            return new JsonResponse(['code' => 'success', 'msg' => 'OK', 'data' => ['correspondants' => [], 'organismes' => [], 'objets' => []]]);
        }

        $resultat = ['correspondants' => [], 'organismes' => [], 'objets' => []];

        try {
            $lignes = $this->em->getConnection()->fetchAllAssociative(
                'SELECT objet, correspondant, organisme FROM courrier
                 WHERE objet LIKE :q OR correspondant LIKE :q OR organisme LIKE :q
                 ORDER BY created_at DESC LIMIT 30',
                ['q' => '%' . $q . '%']
            );
            foreach ($lignes as $l) {
                if (!empty($l['correspondant'])) { $resultat['correspondants'][] = (string) $l['correspondant']; }
                if (!empty($l['organisme'])) { $resultat['organismes'][] = (string) $l['organisme']; }
                if (!empty($l['objet'])) { $resultat['objets'][] = (string) $l['objet']; }
            }
            foreach ($resultat as $cle => $valeurs) {
                $resultat[$cle] = array_slice(array_values(array_unique($valeurs)), 0, 8);
            }
        } catch (\Throwable) {
            // Suggestions facultatives : on n'empeche jamais la saisie.
        }

        return new JsonResponse(['code' => 'success', 'msg' => 'OK', 'data' => $resultat]);
    }

    /**
     * POST api/courrier/mobile/creer
     * Enregistrement minimal : sens, objet, correspondant (+ service, priorite, observation).
     */
    #[Route('/creer', name: 'api_courrier_mobile_creer', methods: ['POST'], priority: 30)]
    public function creer(Request $request): JsonResponse
    {
        if ($refus = $this->refuseSiNonConnecte()) {
            return $refus;
        }
        if (!$this->peutGerer()) {
            return new JsonResponse(['code' => 'error', 'msg' => "Enregistrement reserve au role courrier ou a l'administrateur."], 403);
        }

        $erreurs = [];
        $sens = (string) $request->request->get('sens', 'entrant');
        if (!isset(\App\Entity\Courrier\Courrier::SENS[$sens])) {
            $erreurs[] = 'Le sens du courrier est invalide.';
        }
        $objet = trim((string) $request->request->get('objet', ''));
        if ($objet === '') {
            $erreurs[] = "L'objet du courrier est obligatoire.";
        }
        $correspondant = trim((string) $request->request->get('correspondant', ''));
        if ($correspondant === '') {
            $erreurs[] = $sens === 'sortant' ? 'Le destinataire est obligatoire.' : "L'expediteur est obligatoire.";
        }

        if ($erreurs !== []) {
            return new JsonResponse(['code' => 'warning', 'msg' => implode(' ', $erreurs), 'erreurs' => $erreurs], 400);
        }

        // Delegue au controleur de l'API courrier (numerotation, pieces, regles).
        return $this->forward(
            'App\\Controller\\Courrier\\CourrierApiController::create',
            ['request' => $request]
        );
    }

    private function peutGerer(): bool
    {
        foreach (['ROLE_COURRIER', 'ROLE_ADMIN', 'ROLE_TECHNICIAN', 'ROLE_DG', 'ROLE_PDR'] as $role) {
            if ($this->isGranted($role)) {
                return true;
            }
        }

        return false;
    }
}
