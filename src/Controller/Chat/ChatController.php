<?php

namespace App\Controller\Chat;

use App\Entity\Chat\ListeDiffusion;
use App\Entity\Chat\MessageChat;
use App\Entity\User;
use App\Repository\Chat\LectureChatRepository;
use App\Repository\Chat\ListeDiffusionRepository;
use App\Repository\Chat\MessageChatRepository;
use App\Service\Chat\AccuseLecture;
use App\Service\Chat\StockageMediasChat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Messagerie interne (chat) entre les utilisateurs de l'application.
 *
 * Trois sortes de canaux :
 *   - general          : le canal général, en écriture réservée aux responsables ;
 *   - <uuid>           : conversation privée avec un collègue ;
 *   - liste:<uuid>     : canal d'une liste de diffusion (groupe d'utilisateurs).
 *
 * Un message peut contenir du texte, une photo ou un vocal (60 s maximum).
 */
#[Route('/messagerie')]
#[IsGranted('ROLE_USER')]
final class ChatController extends AbstractController
{
    /** Rôles autorisés à publier sur le canal général. */
    private const ROLES_CANAL_GENERAL = ['ROLE_DG', 'ROLE_DL', 'ROLE_COORD_USINE'];

    public function __construct(
        private EntityManagerInterface $em,
        private MessageChatRepository $messages,
        private LectureChatRepository $lectures,
        private ListeDiffusionRepository $listes,
        private StockageMediasChat $stockage,
        private AccuseLecture $accuse,
    ) {}

    // ------------------------------------------------------------------
    //  Page principale
    // ------------------------------------------------------------------
    #[Route('', name: 'app_chat', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $moi = $this->utilisateurCourant();
        $canalDemande = (string) $request->query->get('canal', LectureChatRepository::CANAL_GENERAL);
        $resolu = $this->resoudreCanal($canalDemande, $moi);

        if ($resolu === null) {
            $this->addFlash('warning', 'Cette conversation n\'est pas accessible avec votre compte.');
            $resolu = ['type' => 'general', 'user' => null, 'liste' => null];
        }

        $interlocuteur = $resolu['user'];
        $liste = $resolu['liste'];

        $messages = match ($resolu['type']) {
            'liste'  => $this->messages->filListe($liste, 80),
            'prive'  => $this->messages->filPrive($moi, $interlocuteur, 80),
            default  => $this->messages->filGeneral(80),
        };

        // Consulter la conversation vaut remise : les messages que je viens de
        // recevoir passent en « remis » pour leur expéditeur.
        $this->accuse->marquerRemis($messages, $moi);

        $this->marquerLu($moi, $canalDemande, $resolu);

        // États « envoyé / remis / lu » des messages que J'AI envoyés.
        $mesMessages = array_values(array_filter(
            $messages,
            static fn (MessageChat $m) => (string) $m->getExpediteur()?->getId() === (string) $moi->getId()
        ));
        $accuses = $this->accuse->etats(
            $mesMessages,
            $this->canalCourant($resolu),
            $resolu['type'],
            $this->accuse->totalDestinataires($resolu['type'], $resolu['liste'], $moi)
        );

        // --- Liste des conversations privées ---------------------------------
        $nonLus = $this->messages->nonLusParExpediteur($moi);
        $conversations = [];
        foreach ($this->messages->derniersEchanges($moi) as $message) {
            $autre = $message->getExpediteur() === $moi ? $message->getDestinataire() : $message->getExpediteur();
            if ($autre === null) {
                continue;
            }
            $id = (string) $autre->getId();
            if (isset($conversations[$id])) {
                continue;
            }
            $conversations[$id] = [
                'utilisateur' => $autre,
                'dernier'     => $message,
                'non_lus'     => $nonLus[$id] ?? 0,
            ];
        }

        // --- Listes de diffusion visibles ------------------------------------
        $estAdmin = $this->isGranted('ROLE_ADMIN');
        $blocsListes = [];
        foreach ($this->listes->pourUtilisateur($moi, $estAdmin) as $uneListe) {
            $identifiant = 'liste:' . $uneListe->getId();
            $blocsListes[] = [
                'liste'     => $uneListe,
                'canal'     => $identifiant,
                'dernier'   => $this->messages->dernierDeListe($uneListe),
                'non_lus'   => $this->messages->nonLusListe(
                    $uneListe,
                    $moi,
                    $this->lectures->dernierLu($moi, $identifiant) ?? new \DateTimeImmutable('2000-01-01 00:00:00')
                ),
                'membres'   => count($uneListe->membresEffectifs()),
            ];
        }

        $general = $this->messages->filGeneral(1);

        return $this->render('chat/index.html.twig', [
            'canal'            => $this->canalCourant($resolu),
            'typeCanal'        => $resolu['type'],
            'interlocuteur'    => $interlocuteur,
            'listeCourante'    => $liste,
            'messages'         => $messages,
            'conversations'    => $conversations,
            'listesDiffusion'  => $blocsListes,
            'generalDernier'   => $general[0] ?? null,
            'nonLusGeneral'    => $this->nonLusGeneral($moi),
            'utilisateurs'     => $this->autresUtilisateurs($moi),
            'sansConversation' => array_values(array_filter(
                $this->autresUtilisateurs($moi),
                fn (User $u) => !isset($conversations[(string) $u->getId()])
            )),
            'moi'              => $moi,
            'peutPublierGeneral' => $this->peutPublierGeneral(),
            'estAdmin'         => $estAdmin,
            'dureeAudioMax'    => MessageChat::DUREE_AUDIO_MAX,
            'accuses'          => $accuses,
        ]);
    }

    // ------------------------------------------------------------------
    //  Envoi d'un message (texte, photo ou vocal)
    // ------------------------------------------------------------------
    #[Route('/envoyer', name: 'app_chat_envoyer', methods: ['POST'])]
    public function envoyer(Request $request): Response
    {
        $moi = $this->utilisateurCourant();
        $canal = (string) $request->request->get('canal', LectureChatRepository::CANAL_GENERAL);
        $estAjax = $request->isXmlHttpRequest();

        if (!$this->isCsrfTokenValid('chat_envoyer', (string) $request->request->get('_token'))) {
            return $this->reponseErreur($estAjax, 'Jeton de sécurité invalide. Rechargez la page (Ctrl+F5) et réessayez.', 419);
        }

        $resolu = $this->resoudreCanal($canal, $moi);
        if ($resolu === null) {
            return $this->reponseErreur($estAjax, 'Vous n\'avez pas accès à cette conversation.', 403);
        }

        // --- Droit d'écriture sur le canal -----------------------------------
        if ($resolu['type'] === 'general' && !$this->peutPublierGeneral()) {
            return $this->reponseErreur(
                $estAjax,
                'Le canal général est réservé aux publications de la Direction (DG, DL, Coordonnateur usine). Vous pouvez écrire en privé à vos collègues.',
                403
            );
        }
        if ($resolu['type'] === 'liste' && !$this->isGranted('ROLE_ADMIN') && !$resolu['liste']->contient($moi)) {
            return $this->reponseErreur($estAjax, 'Vous ne faites pas partie de cette liste de diffusion.', 403);
        }

        $contenu = trim((string) $request->request->get('contenu', ''));
        $fichier = $request->files->get('fichier');
        if (!$fichier instanceof UploadedFile) {
            $fichier = null;
        }

        if ($contenu === '' && $fichier === null) {
            return $this->reponseErreur($estAjax, 'Le message est vide.', 422);
        }

        if (mb_strlen($contenu) > MessageChat::LONGUEUR_MAX) {
            return $this->reponseErreur($estAjax, 'Message trop long : ' . MessageChat::LONGUEUR_MAX . ' caractères maximum.', 422);
        }

        $type = MessageChat::TYPE_TEXTE;
        $duree = null;
        if ($fichier !== null) {
            $type = (string) $request->request->get('type_piece', MessageChat::TYPE_IMAGE);
            if (!in_array($type, [MessageChat::TYPE_IMAGE, MessageChat::TYPE_AUDIO], true)) {
                return $this->reponseErreur($estAjax, 'Type de pièce jointe non reconnu : ' . $type . '.', 422);
            }

            if ($type === MessageChat::TYPE_AUDIO) {
                $duree = (int) $request->request->get('duree', 0);
                if ($duree > MessageChat::DUREE_AUDIO_MAX) {
                    return $this->reponseErreur(
                        $estAjax,
                        'Message vocal trop long : ' . $duree . ' s. La limite est de ' . MessageChat::DUREE_AUDIO_MAX . ' secondes.',
                        422
                    );
                }
                $duree = max(0, $duree);
            }
        }

        $message = (new MessageChat())
            ->setExpediteur($moi)
            ->setContenu($contenu)
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
                return $this->reponseErreur($estAjax, $e->getMessage(), 422);
            }
            $message->setDuree($duree);
            if ($message->getContenu() === '') {
                $message->setContenu($type === MessageChat::TYPE_AUDIO ? 'Message vocal' : 'Photo');
            }
        }

        $this->em->persist($message);
        $this->em->flush();

        $this->marquerLu($moi, $canal, $resolu);

        if ($estAjax) {
            return new JsonResponse(['ok' => true, 'message' => $this->serialiser($message, $moi)]);
        }

        return $this->redirectToRoute('app_chat', ['canal' => $canal]);
    }

    // ------------------------------------------------------------------
    //  Rafraîchissement automatique (JSON)
    // ------------------------------------------------------------------
    #[Route('/messages', name: 'app_chat_messages', methods: ['GET'])]
    public function messagesRecents(Request $request): JsonResponse
    {
        $moi = $this->utilisateurCourant();
        $canal = (string) $request->query->get('canal', 'general');
        $resolu = $this->resoudreCanal($canal, $moi);

        if ($resolu === null) {
            return new JsonResponse(['ok' => false, 'erreur' => 'Conversation inaccessible.', 'messages' => []], 403);
        }

        $apres = (string) $request->query->get('apres', '');
        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $apres);
        if (!$date instanceof \DateTimeImmutable) {
            $date = new \DateTimeImmutable('-1 day');
        }

        $nouveaux = $this->messages->nouveaux($resolu['user'], $moi, $date, $resolu['liste']);

        // Les messages recus a l'instant sont marques « remis ».
        $this->accuse->marquerRemis($nouveaux, $moi);

        $donnees = [];
        foreach ($nouveaux as $message) {
            if ($message->getExpediteur() === $moi) {
                continue;
            }
            $donnees[] = $this->serialiser($message, $moi);
        }

        if ($donnees !== []) {
            $this->marquerLu($moi, $canal, $resolu);
        }

        // Etats de mes propres messages : mis a jour a chaque rafraichissement.
        $canalCourant = $this->canalCourant($resolu);
        $mesMessages = $this->messages->miensDepuis(
            $moi,
            $resolu['user'],
            $resolu['liste'],
            new \DateTimeImmutable('-3 days')
        );
        $accuses = $this->accuse->etats(
            $mesMessages,
            $canalCourant,
            $resolu['type'],
            $this->accuse->totalDestinataires($resolu['type'], $resolu['liste'], $moi)
        );

        return new JsonResponse(['ok' => true, 'messages' => $donnees, 'accuses' => $accuses]);
    }

    // ------------------------------------------------------------------
    //  Pastille « non lus » du menu
    // ------------------------------------------------------------------
    #[Route('/compteur', name: 'app_chat_compteur', methods: ['GET'])]
    public function compteur(): JsonResponse
    {
        $moi = $this->utilisateurCourant();

        $prives = $this->messages->totalNonLusPrives($moi);
        $general = $this->nonLusGeneral($moi);

        $listes = 0;
        $detailListes = [];
        foreach ($this->listes->pourUtilisateur($moi, $this->isGranted('ROLE_ADMIN')) as $uneListe) {
            $canal = 'liste:' . $uneListe->getId();
            $n = $this->messages->nonLusListe(
                $uneListe,
                $moi,
                $this->lectures->dernierLu($moi, $canal) ?? new \DateTimeImmutable('2000-01-01 00:00:00')
            );
            if ($n > 0) {
                $detailListes[$canal] = $n;
                $listes += $n;
            }
        }

        return new JsonResponse([
            'total'  => $prives + $general + $listes,
            'canaux' => array_merge(
                ['general' => $general],
                $this->messages->nonLusParExpediteur($moi),
                $detailListes
            ),
        ]);
    }

    // ------------------------------------------------------------------
    //  Aides
    // ------------------------------------------------------------------
    private function utilisateurCourant(): User
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof User) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour utiliser la messagerie.');
        }

        return $utilisateur;
    }

    /** Le profil peut-il publier sur le canal général ? */
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
     * Traduit un identifiant de canal en cible exploitable.
     *
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

    /** Identifiant de canal renvoye au gabarit. */
    private function canalCourant(array $resolu): string
    {
        return match ($resolu['type']) {
            'liste' => 'liste:' . $resolu['liste']->getId(),
            'prive' => (string) $resolu['user']->getId(),
            default => LectureChatRepository::CANAL_GENERAL,
        };
    }

    /** Marque le canal comme lu pour l'utilisateur courant. */
    private function marquerLu(User $moi, string $canal, array $resolu): void
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

    /** Collègues proposés pour démarrer une conversation. */
    private function autresUtilisateurs(User $moi): array
    {
        $tous = $this->em->getRepository(User::class)->findBy([], ['nom' => 'ASC']);

        return array_values(array_filter($tous, static function ($u) use ($moi) {
            return $u instanceof User
                && $u->getId() !== $moi->getId()
                && $u->getId() !== null
                && $u->isActive() !== false;
        }));
    }

    private function serialiser(MessageChat $message, User $moi): array
    {
        $auteur = $message->getExpediteur();
        $nom = $auteur ? trim(((string) $auteur->getNom()) . ' ' . ((string) $auteur->getPrenoms())) : '';
        if ($nom === '') {
            $nom = $auteur ? (string) $auteur->getEmail() : 'Utilisateur';
        }

        return [
            'id'        => (string) $message->getId(),
            'contenu'   => (string) $message->getContenu(),
            'auteur'    => $nom,
            'initiales' => $this->initiales($nom),
            'moi'       => $auteur === $moi,
            'jour'      => $message->getCreatedAt()?->format('d/m/Y'),
            'heure'     => $message->getCreatedAt()?->format('H:i'),
            'iso'       => $message->getCreatedAt()?->format('Y-m-d H:i:s'),
            'type'      => $message->getType(),
            'fichier'   => $message->getFichier() ? '/uploads/chat/' . $message->getFichier() : null,
            'fichier_nom' => $message->getFichierNom(),
            'duree'     => $message->getDuree(),
            'statut'    => $message->statut(),
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

    private function reponseErreur(bool $ajax, string $message, int $code): Response
    {
        if ($ajax) {
            return new JsonResponse(['ok' => false, 'erreur' => $message], $code);
        }

        $this->addFlash('danger', $message);

        return $this->redirectToRoute('app_chat');
    }
}
