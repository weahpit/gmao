<?php

namespace App\Controller\Api;

use App\Entity\Chat\ListeDiffusion;
use App\Entity\Chat\MessageChat;
use App\Entity\User;
use App\Repository\Chat\LectureChatRepository;
use App\Repository\Chat\ListeDiffusionRepository;
use App\Repository\Chat\MessageChatRepository;
use App\Service\Chat\AccuseLecture;
use App\Service\Chat\NotifieurChat;
use App\Service\Chat\StockageMediasChat;
use Doctrine\ORM\EntityManagerInterface;
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
 * API JSON de la messagerie pour l'APPLICATION MOBILE.
 *
 * Memes regles que la messagerie web :
 *   - canal `general`  : ecriture reservee a la Direction (DG, DL, Coordinateur usine) ;
 *   - canal `<uuid>`   : conversation privee avec un collegue ;
 *   - canal `liste:<uuid>` : liste de diffusion (membres uniquement).
 *
 * Authentification : la session Symfony (cookie PHPSESSID), comme le reste
 * de l'API mobile. Aucun jeton supplementaire n'est necessaire.
 */
#[Route('/api/mobile/chat')]
#[IsGranted('ROLE_USER')]
final class ApiChatMobileController extends AbstractController
{
    /** Roles autorises a publier sur le canal general. */
    private const ROLES_CANAL_GENERAL = ['ROLE_DG', 'ROLE_DL', 'ROLE_COORD_USINE'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageChatRepository $messages,
        private readonly LectureChatRepository $lectures,
        private readonly ListeDiffusionRepository $listes,
        private readonly StockageMediasChat $stockage,
        private readonly AccuseLecture $accuses,
        private readonly NotifieurChat $notifieur,
        private readonly LoggerInterface $logger,
    ) {
    }

    // ------------------------------------------------------------------
    //  Liste des canaux (general, listes de diffusion, echanges prives)
    // ------------------------------------------------------------------
    #[Route('/conversations', name: 'api_mobile_chat_conversations', methods: ['GET'])]
    public function conversations(): JsonResponse
    {
        $moi = $this->utilisateurCourant();
        $peutPublier = $this->peutPublierGeneral();

        // L'application vient de recuperer l'apercu des messages recus : ils sont « remis ».
        $this->messages->marquerRecusPrives($moi);

        $canaux = [];

        // --- Canal general ---
        $dernierGeneral = $this->messages->filGeneral(1)[0] ?? null;
        $canaux[] = [
            'canal'       => LectureChatRepository::CANAL_GENERAL,
            'type'        => 'general',
            'titre'       => 'General',
            'sous_titre'  => $peutPublier
                ? 'Tous les utilisateurs · ecriture autorisee'
                : 'Tous les utilisateurs · lecture seule',
            'dernier'     => $this->resume($dernierGeneral),
            'non_lus'     => $this->nonLusGeneral($moi),
            'ecriture'    => $peutPublier,
        ];

        // --- Listes de diffusion visibles ---
        $estAdmin = $this->isGranted('ROLE_ADMIN');
        foreach ($this->listes->pourUtilisateur($moi, $estAdmin) as $liste) {
            $identifiant = 'liste:' . $liste->getId();
            $dernier = $this->messages->dernierDeListe($liste);
            $nbMembres = count($liste->membresEffectifs());

            $canaux[] = [
                'canal'      => $identifiant,
                'type'       => 'liste',
                'titre'      => (string) $liste->getNom(),
                'sous_titre' => $nbMembres . ' membre(s)' . ($liste->isActive() ? '' : ' · inactive'),
                'dernier'    => $this->resume($dernier),
                'non_lus'    => $this->messages->nonLusListe(
                    $liste,
                    $moi,
                    $this->lectures->dernierLu($moi, $identifiant) ?? new \DateTimeImmutable('2000-01-01 00:00:00')
                ),
                'ecriture'   => $liste->isActive() && ($estAdmin || $liste->contient($moi)),
            ];
        }

        // --- Echanges prives ---
        $nonLus = $this->messages->nonLusParExpediteur($moi);
        $echanges = [];
        $vus = [];
        foreach ($this->messages->derniersEchanges($moi) as $message) {
            $autre = $message->getExpediteur() === $moi ? $message->getDestinataire() : $message->getExpediteur();
            if ($autre === null) {
                continue;
            }
            $id = (string) $autre->getId();
            if (isset($vus[$id])) {
                continue;
            }
            $vus[$id] = true;
            $nom = $this->nomComplet($autre);
            $echanges[] = [
                'canal'      => $id,
                'type'       => 'prive',
                'titre'      => $nom,
                'sous_titre' => 'Conversation privee',
                'dernier'    => $this->resume($message),
                'non_lus'    => (int) ($nonLus[$id] ?? 0),
                'ecriture'   => true,
            ];
        }

        return new JsonResponse([
            'ok'                    => true,
            'peut_publier_general'  => $peutPublier,
            'duree_audio_max'       => MessageChat::DUREE_AUDIO_MAX,
            'canaux'                => $canaux,
            'echanges'              => $echanges,
            'contacts'              => $this->contacts($moi),
        ]);
    }

    // ------------------------------------------------------------------
    //  Messages d'un canal
    // ------------------------------------------------------------------
    #[Route('/messages', name: 'api_mobile_chat_messages', methods: ['GET'])]
    public function messages(Request $request): JsonResponse
    {
        $moi = $this->utilisateurCourant();
        $canal = (string) $request->query->get('canal', LectureChatRepository::CANAL_GENERAL);
        $resolu = $this->resoudreCanal($canal, $moi);

        if ($resolu === null) {
            return new JsonResponse(['ok' => false, 'erreur' => 'Cette conversation n\'est pas accessible avec votre compte.'], 403);
        }

        $apres = trim((string) $request->query->get('apres', ''));

        if ($apres !== '') {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $apres);
            if (!$date instanceof \DateTimeImmutable) {
                $date = new \DateTimeImmutable('-1 day');
            }
            $bruts = $this->messages->nouveaux($resolu['user'], $moi, $date, $resolu['liste']);
        } else {
            $bruts = match ($resolu['type']) {
                'liste' => $this->messages->filListe($resolu['liste'], 80),
                'prive' => $this->messages->filPrive($moi, $resolu['user'], 80),
                default => $this->messages->filGeneral(80),
            };
        }

        // Les messages des autres sont arrives sur cet appareil : « remis ».
        $this->accuses->marquerRemis($bruts, $moi);

        $canalId = $this->canalCourant($resolu);
        $etats = $this->etatsRecents($moi, $resolu, $canalId, $bruts);

        $donnees = [];
        foreach ($bruts as $message) {
            $donnees[] = $this->serialiser($message, $moi, $request, $etats[(string) $message->getId()] ?? null);
        }

        // Consulter un canal vaut lecture : la pastille se vide.
        $this->marquerLu($moi, $resolu);

        // Etat actuel de mes derniers messages (coches de remise / lecture).
        $statuts = [];
        foreach ($etats as $id => $etat) {
            $statuts[$id] = $etat;
        }

        return new JsonResponse([
            'ok'       => true,
            'canal'    => $canalId,
            'messages' => $donnees,
            'statuts'  => (object) $statuts,
        ]);
    }

    // ------------------------------------------------------------------
    //  Envoi d'un message (texte, photo ou vocal)
    // ------------------------------------------------------------------
    #[Route('/envoyer', name: 'api_mobile_chat_envoyer', methods: ['POST'])]
    public function envoyer(Request $request): JsonResponse
    {
        $moi = $this->utilisateurCourant();
        $canal = (string) $request->request->get('canal', LectureChatRepository::CANAL_GENERAL);
        $resolu = $this->resoudreCanal($canal, $moi);

        if ($resolu === null) {
            return new JsonResponse(['ok' => false, 'erreur' => 'Vous n\'avez pas acces a cette conversation.'], 403);
        }

        if ($resolu['type'] === 'general' && !$this->peutPublierGeneral()) {
            return new JsonResponse([
                'ok'     => false,
                'erreur' => 'Le canal general est reserve a la Direction (DG, DL, Coordonnateur usine).',
            ], 403);
        }
        if ($resolu['type'] === 'liste') {
            if (!$resolu['liste']->isActive() && !$this->isGranted('ROLE_ADMIN')) {
                return new JsonResponse(['ok' => false, 'erreur' => 'Cette liste de diffusion est inactive.'], 403);
            }
            if (!$resolu['liste']->contient($moi) && !$this->isGranted('ROLE_ADMIN')) {
                return new JsonResponse(['ok' => false, 'erreur' => 'Vous ne faites pas partie de cette liste de diffusion.'], 403);
            }
        }

        $contenu = trim((string) $request->request->get('contenu', ''));
        $fichier = $request->files->get('fichier');
        if (!$fichier instanceof UploadedFile) {
            $fichier = null;
        }

        if ($contenu === '' && $fichier === null) {
            return new JsonResponse(['ok' => false, 'erreur' => 'Le message est vide.'], 422);
        }
        if (mb_strlen($contenu) > MessageChat::LONGUEUR_MAX) {
            return new JsonResponse([
                'ok'     => false,
                'erreur' => 'Message trop long : ' . MessageChat::LONGUEUR_MAX . ' caracteres maximum.',
            ], 422);
        }

        $type = MessageChat::TYPE_TEXTE;
        $duree = null;
        if ($fichier !== null) {
            $type = (string) $request->request->get('type_piece', MessageChat::TYPE_IMAGE);
            if (!in_array($type, [MessageChat::TYPE_IMAGE, MessageChat::TYPE_AUDIO], true)) {
                return new JsonResponse(['ok' => false, 'erreur' => 'Type de piece jointe non reconnu : ' . $type . '.'], 422);
            }
            if ($type === MessageChat::TYPE_AUDIO) {
                $duree = (int) $request->request->get('duree', 0);
                if ($duree > MessageChat::DUREE_AUDIO_MAX) {
                    return new JsonResponse([
                        'ok'     => false,
                        'erreur' => 'Message vocal trop long : ' . $duree . ' s. La limite est de '
                            . MessageChat::DUREE_AUDIO_MAX . ' secondes.',
                    ], 422);
                }
                $duree = max(0, $duree);
            }
        }

        // Envoi repete apres une coupure reseau : le message est peut-etre deja
        // enregistre. On le renvoie tel quel, sans creer de doublon.
        $idClient = trim((string) $request->request->get('client_id', ''));
        if ($idClient !== '') {
            $existant = $this->messages->parIdClient($moi, mb_substr($idClient, 0, 64));
            if ($existant instanceof MessageChat) {
                $etats = $this->accuses->etats(
                    [$existant],
                    $this->canalCourant($resolu),
                    $resolu['type'],
                    $this->accuses->totalDestinataires($resolu['type'], $resolu['liste'], $moi)
                );

                return new JsonResponse([
                    'ok'      => true,
                    'doublon' => true,
                    'message' => $this->serialiser($existant, $moi, $request, $etats[(string) $existant->getId()] ?? null),
                ]);
            }
        }

        $message = (new MessageChat())
            ->setExpediteur($moi)
            ->setContenu($contenu)
            ->setClientId($idClient !== '' ? $idClient : null)
            ->setLu(false);

        if ($resolu['type'] === 'prive') {
            $message->setDestinataire($resolu['user']);
        } elseif ($resolu['type'] === 'liste') {
            $message->setListeDiffusion($resolu['liste']);
        }

        if ($fichier !== null) {
            try {
                $this->stockage->enregistrer($fichier, $message, $type);
            } catch (\InvalidArgumentException|\RuntimeException $e) {
                return new JsonResponse(['ok' => false, 'erreur' => $e->getMessage()], 422);
            }
            $message->setDuree($duree);
            if ((string) $message->getContenu() === '') {
                $message->setContenu($type === MessageChat::TYPE_AUDIO ? 'Message vocal' : 'Photo');
            }
        }

        $this->em->persist($message);
        $this->em->flush();
        $this->marquerLu($moi, $resolu);

        // Notification push aux destinataires (jamais bloquante).
        $this->notifieur->notifier($message, $moi);

        return new JsonResponse([
            'ok'      => true,
            'message' => $this->serialiser($message, $moi, $request, [
                'statut'   => 'envoye',
                'lu_par'   => 0,
                'total'    => $this->accuses->totalDestinataires($resolu['type'], $resolu['liste'], $moi),
                'lu_at'    => null,
                'remis_at' => null,
            ]),
        ]);
    }

    // ------------------------------------------------------------------
    //  Marquer un canal comme lu (sans charger les messages)
    // ------------------------------------------------------------------
    #[Route('/lire', name: 'api_mobile_chat_lire', methods: ['POST'])]
    public function lire(Request $request): JsonResponse
    {
        $moi = $this->utilisateurCourant();
        $canal = (string) $request->request->get('canal', LectureChatRepository::CANAL_GENERAL);
        $resolu = $this->resoudreCanal($canal, $moi);

        if ($resolu === null) {
            return new JsonResponse(['ok' => false, 'erreur' => 'Conversation inaccessible.'], 403);
        }

        $this->marquerLu($moi, $resolu);

        return new JsonResponse(['ok' => true]);
    }

    // ------------------------------------------------------------------
    //  Accuse de reception : la notification est arrivee sur le telephone
    // ------------------------------------------------------------------
    #[Route('/recu', name: 'api_mobile_chat_recu', methods: ['POST'])]
    public function recu(Request $request): JsonResponse
    {
        $moi = $this->utilisateurCourant();
        $id = trim((string) $request->request->get('message_id', ''));

        try {
            $message = $this->messages->find(Uuid::fromString($id));
        } catch (\Throwable) {
            return new JsonResponse(['ok' => false, 'erreur' => 'Identifiant de message invalide.'], 422);
        }
        if (!$message instanceof MessageChat) {
            return new JsonResponse(['ok' => true]);
        }

        // Seul un destinataire reel peut accuser reception.
        $liste = $message->getListeDiffusion();
        $prive = $message->getDestinataire();
        $autorise = match (true) {
            $liste instanceof ListeDiffusion => $liste->contient($moi) || $this->isGranted('ROLE_ADMIN'),
            $prive instanceof User           => $prive->getId() === $moi->getId(),
            default                          => true,
        };
        if ($autorise) {
            $this->accuses->marquerRemis([$message], $moi);
        }

        return new JsonResponse(['ok' => true]);
    }

    // ------------------------------------------------------------------
    //  Pastille globale « non lus »
    // ------------------------------------------------------------------
    #[Route('/compteur', name: 'api_mobile_chat_compteur', methods: ['GET'])]
    public function compteur(): JsonResponse
    {
        $moi = $this->utilisateurCourant();
        $this->messages->marquerRecusPrives($moi);
        $total = $this->messages->totalNonLusPrives($moi) + $this->nonLusGeneral($moi);

        foreach ($this->listes->pourUtilisateur($moi, $this->isGranted('ROLE_ADMIN')) as $liste) {
            $canal = 'liste:' . $liste->getId();
            $total += $this->messages->nonLusListe(
                $liste,
                $moi,
                $this->lectures->dernierLu($moi, $canal) ?? new \DateTimeImmutable('2000-01-01 00:00:00')
            );
        }

        return new JsonResponse(['ok' => true, 'total' => $total]);
    }

    // ------------------------------------------------------------------
    //  Aides
    // ------------------------------------------------------------------
    private function utilisateurCourant(): User
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof User) {
            throw $this->createAccessDeniedException('Vous devez etre connecte pour utiliser la messagerie.');
        }

        return $utilisateur;
    }

    private function peutPublierGeneral(): bool
    {
        foreach (self::ROLES_CANAL_GENERAL as $role) {
            if ($this->isGranted($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{type: string, user: ?User, liste: ?ListeDiffusion}|null
     */
    private function resoudreCanal(string $canal, User $moi): ?array
    {
        $canal = trim($canal);

        if ($canal === '' || $canal === LectureChatRepository::CANAL_GENERAL) {
            return ['type' => 'general', 'user' => null, 'liste' => null];
        }

        if (str_starts_with($canal, 'liste:')) {
            $id = substr($canal, 6);
            try {
                $liste = $this->listes->find(Uuid::fromString($id));
            } catch (\Throwable) {
                return null;
            }
            if (!$liste instanceof ListeDiffusion) {
                return null;
            }
            if (!$liste->isActive() && !$this->isGranted('ROLE_ADMIN')) {
                return null;
            }
            if (!$liste->contient($moi) && !$this->isGranted('ROLE_ADMIN')) {
                return null;
            }

            return ['type' => 'liste', 'user' => null, 'liste' => $liste];
        }

        try {
            $id = Uuid::fromString($canal);
        } catch (\Throwable) {
            return null;
        }

        $utilisateur = $this->em->getRepository(User::class)->find($id);
        if (!$utilisateur instanceof User || $utilisateur->getId() === $moi->getId()) {
            return null;
        }

        return ['type' => 'prive', 'user' => $utilisateur, 'liste' => null];
    }

    private function canalCourant(array $resolu): string
    {
        return match ($resolu['type']) {
            'liste' => 'liste:' . $resolu['liste']->getId(),
            'prive' => (string) $resolu['user']->getId(),
            default => LectureChatRepository::CANAL_GENERAL,
        };
    }

    private function marquerLu(User $moi, array $resolu): void
    {
        if ($resolu['type'] === 'prive') {
            $this->messages->marquerLusDe($moi, $resolu['user']);
        }

        $this->lectures->marquerLu($moi, $this->canalCourant($resolu));
    }

    private function nonLusGeneral(User $moi): int
    {
        $dernier = $this->lectures->dernierLu($moi, LectureChatRepository::CANAL_GENERAL);

        return $this->messages->nonLusGeneral($dernier ?? new \DateTimeImmutable('2000-01-01 00:00:00'), $moi);
    }

    /** Colleagues a qui ecrire en prive. */
    private function contacts(User $moi): array
    {
        $tous = $this->em->getRepository(User::class)->findBy([], ['nom' => 'ASC']);
        $liste = [];
        foreach ($tous as $u) {
            if (!$u instanceof User || $u->getId() === null || $u->getId() === $moi->getId()) {
                continue;
            }
            if ($u->isActive() === false) {
                continue;
            }
            $liste[] = [
                'canal' => (string) $u->getId(),
                'nom'   => $this->nomComplet($u),
            ];
        }

        return $liste;
    }

    private function nomComplet(User $user): string
    {
        $nom = trim(((string) $user->getNom()) . ' ' . ((string) $user->getPrenoms()));

        return $nom !== '' ? $nom : (string) $user->getEmail();
    }

    /** Resume court pour la liste des conversations. */
    private function resume(?MessageChat $message): ?array
    {
        if (!$message instanceof MessageChat) {
            return null;
        }

        $texte = trim((string) $message->getContenu());
        if ($message->getType() === MessageChat::TYPE_IMAGE && $texte === '') {
            $texte = 'Photo';
        } elseif ($message->getType() === MessageChat::TYPE_AUDIO && $texte === '') {
            $texte = 'Message vocal';
        }

        $nom = $message->getExpediteur() ? $this->nomComplet($message->getExpediteur()) : '';

        return [
            'auteur' => $nom,
            'texte'  => mb_substr($texte, 0, 90),
            'iso'    => $message->getCreatedAt()?->format('Y-m-d H:i:s'),
            'type'   => $message->getType(),
        ];
    }

    /** @param array<string, mixed>|null $etat etat de remise/lecture (messages que j'ai ecrits) */
    private function serialiser(MessageChat $message, User $moi, Request $request, ?array $etat = null): array
    {
        $auteur = $message->getExpediteur();
        $nom = $auteur instanceof User ? $this->nomComplet($auteur) : 'Utilisateur';
        $base = $request->getSchemeAndHttpHost();

        $estMoi = $auteur instanceof User && $auteur->getId() === $moi->getId();

        return [
            'id'          => (string) $message->getId(),
            'client_id'   => $message->getClientId(),
            'statut'      => $estMoi ? ($etat['statut'] ?? 'envoye') : null,
            'lu_par'      => $estMoi ? (int) ($etat['lu_par'] ?? 0) : null,
            'total'       => $estMoi ? (int) ($etat['total'] ?? 1) : null,
            'lu_at'       => $estMoi ? ($etat['lu_at'] ?? null) : null,
            'remis_at'    => $estMoi ? ($etat['remis_at'] ?? null) : null,
            'contenu'     => (string) $message->getContenu(),
            'auteur'      => $nom,
            'initiales'   => $this->initiales($nom),
            'moi'         => $estMoi,
            'iso'         => $message->getCreatedAt()?->format('Y-m-d H:i:s'),
            'heure'       => $message->getCreatedAt()?->format('H:i'),
            'jour'        => $message->getCreatedAt()?->format('d/m/Y'),
            'type'        => $message->getType(),
            'fichier_url' => $message->getFichier() ? $base . '/uploads/chat/' . $message->getFichier() : null,
            'fichier_nom' => $message->getFichierNom(),
            'duree'       => $message->getDuree(),
        ];
    }

    private function initiales(string $nom): string
    {
        $parties = preg_split('/\s+/', trim($nom)) ?: [];
        $lettres = '';
        foreach (array_slice($parties, 0, 2) as $partie) {
            if ($partie !== '') {
                $lettres .= mb_strtoupper(mb_substr($partie, 0, 1));
            }
        }

        return $lettres !== '' ? $lettres : '?';
    }

    /**
     * Etat de remise / lecture de mes derniers messages dans ce canal, plus
     * celui des messages que l'on vient de charger.
     *
     * @param MessageChat[] $charges
     *
     * @return array<string, array<string, mixed>>
     */
    private function etatsRecents(User $moi, array $resolu, string $canalId, array $charges): array
    {
        $miens = $this->messages->recentsDe($moi, $resolu['user'], $resolu['liste'], 60);
        foreach ($charges as $message) {
            $auteur = $message->getExpediteur();
            if ($auteur instanceof User && $auteur->getId() === $moi->getId()) {
                $miens[] = $message;
            }
        }
        if ($miens === []) {
            return [];
        }

        // Dedoublonnage par identifiant.
        $uniques = [];
        foreach ($miens as $message) {
            $uniques[(string) $message->getId()] = $message;
        }

        return $this->accuses->etats(
            array_values($uniques),
            $canalId,
            $resolu['type'],
            $this->accuses->totalDestinataires($resolu['type'], $resolu['liste'], $moi)
        );
    }
}
