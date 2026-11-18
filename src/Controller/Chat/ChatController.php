<?php

namespace App\Controller\Chat;

use App\Entity\Chat\MessageChat;
use App\Entity\User;
use App\Repository\Chat\LectureChatRepository;
use App\Repository\Chat\MessageChatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Messagerie interne (chat) entre les utilisateurs de l'application.
 *
 *  - /messagerie                    : page du chat (canal général + conversations privées)
 *  - /messagerie/envoyer            : envoi d'un message (formulaire ou AJAX)
 *  - /messagerie/messages           : nouveaux messages en JSON (rafraîchissement auto)
 *  - /messagerie/compteur           : nombre de messages non lus (pastille du menu)
 */
#[Route('/messagerie')]
#[IsGranted('ROLE_USER')]
final class ChatController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageChatRepository $messages,
        private LectureChatRepository $lectures,
    ) {}

    // ------------------------------------------------------------------
    //  Page principale
    // ------------------------------------------------------------------
    #[Route('', name: 'app_chat', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $moi = $this->utilisateurCourant();
        $canal = (string) $request->query->get('canal', LectureChatRepository::CANAL_GENERAL);
        $interlocuteur = $this->resoudreInterlocuteur($canal);

        if ($interlocuteur === null) {
            $canal = LectureChatRepository::CANAL_GENERAL;
        }

        // Messages affichés
        $messages = $interlocuteur === null
            ? $this->messages->filGeneral(80)
            : $this->messages->filPrive($moi, $interlocuteur, 80);

        // Ouverture de la conversation = lecture
        $this->marquerLu($moi, $interlocuteur);

        // Liste des conversations (dernier échange par personne)
        $nonLus = $this->messages->nonLusParExpediteur($moi);
        $conversations = [];
        foreach ($this->messages->derniersEchanges($moi) as $message) {
            $autre = $message->getExpediteur() === $moi ? $message->getDestinataire() : $message->getExpediteur();
            if ($autre === null) {
                continue; // canal général : traité à part
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

        $general = $this->messages->filGeneral(1);

        return $this->render('chat/index.html.twig', [
            'canal'          => $interlocuteur ? (string) $interlocuteur->getId() : LectureChatRepository::CANAL_GENERAL,
            'interlocuteur'  => $interlocuteur,
            'messages'       => $messages,
            'conversations'  => $conversations,
            'generalDernier' => $general[0] ?? null,
            'nonLusGeneral'  => $this->nonLusGeneral($moi),
            'utilisateurs'   => $this->autresUtilisateurs($moi),
            'sansConversation' => array_values(array_filter(
                $this->autresUtilisateurs($moi),
                fn (User $u) => !isset($conversations[(string) $u->getId()])
            )),
            'moi'            => $moi,
        ]);
    }

    // ------------------------------------------------------------------
    //  Envoi d'un message
    // ------------------------------------------------------------------
    #[Route('/envoyer', name: 'app_chat_envoyer', methods: ['POST'])]
    public function envoyer(Request $request): Response
    {
        $moi = $this->utilisateurCourant();
        $canal = (string) $request->request->get('canal', LectureChatRepository::CANAL_GENERAL);
        $interlocuteur = $this->resoudreInterlocuteur($canal);

        $contenu = trim((string) $request->request->get('contenu', ''));
        $estAjax = $request->isXmlHttpRequest();

        if (!$this->isCsrfTokenValid('chat_envoyer', (string) $request->request->get('_token'))) {
            return $this->reponseErreur($estAjax, 'Jeton de sécurité invalide. Rechargez la page et réessayez.', 419);
        }

        if ($contenu === '') {
            return $this->reponseErreur($estAjax, 'Le message est vide.', 422);
        }

        if (mb_strlen($contenu) > MessageChat::LONGUEUR_MAX) {
            return $this->reponseErreur($estAjax, 'Message trop long : ' . MessageChat::LONGUEUR_MAX . ' caractères maximum.', 422);
        }

        $message = (new MessageChat())
            ->setExpediteur($moi)
            ->setDestinataire($interlocuteur)
            ->setContenu($contenu)
            ->setLu(false);

        $this->em->persist($message);
        $this->em->flush();

        // L'expéditeur a évidemment lu son propre message.
        $this->marquerLu($moi, $interlocuteur);

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
        $interlocuteur = $this->resoudreInterlocuteur((string) $request->query->get('canal', 'general'));

        $apres = (string) $request->query->get('apres', '');
        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $apres);
        if (!$date instanceof \DateTimeImmutable) {
            $date = new \DateTimeImmutable('-1 day');
        }

        $nouveaux = $this->messages->nouveaux($interlocuteur, $moi, $date);

        $donnees = [];
        foreach ($nouveaux as $message) {
            // On ignore les messages écrits par soi-même : ils sont déjà affichés
            // par l'envoi AJAX (évite les doublons).
            if ($message->getExpediteur() === $moi) {
                continue;
            }
            $donnees[] = $this->serialiser($message, $moi);
        }

        if ($donnees !== []) {
            $this->marquerLu($moi, $interlocuteur);
        }

        return new JsonResponse(['ok' => true, 'messages' => $donnees]);
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

        return new JsonResponse([
            'total'  => $prives + $general,
            'canaux' => array_merge(
                ['general' => $general],
                $this->messages->nonLusParExpediteur($moi)
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

    /** Retrouve l'interlocuteur d'un canal privé (null = canal général). */
    private function resoudreInterlocuteur(string $canal): ?User
    {
        if ($canal === '' || $canal === LectureChatRepository::CANAL_GENERAL) {
            return null;
        }

        try {
            $id = Uuid::fromString($canal);
        } catch (\Throwable) {
            return null;
        }

        $utilisateur = $this->em->getRepository(User::class)->find($id);
        if (!$utilisateur instanceof User || $utilisateur === $this->getUser()) {
            return null;
        }

        return $utilisateur;
    }

    /** Marque le canal comme lu pour l'utilisateur courant. */
    private function marquerLu(User $moi, ?User $interlocuteur): void
    {
        if ($interlocuteur === null) {
            $this->lectures->marquerLu($moi, LectureChatRepository::CANAL_GENERAL);

            return;
        }

        $this->messages->marquerLusDe($moi, $interlocuteur);
        $this->lectures->marquerLu($moi, (string) $interlocuteur->getId());
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
                && $u !== $moi
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
