<?php

namespace App\Service\Chat;

use App\Entity\Chat\ListeDiffusion;
use App\Entity\Chat\MessageChat;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Envoie la notification push d'un nouveau message de la messagerie.
 *
 * Utilise par la messagerie WEB et par l'API MOBILE : un message ecrit depuis
 * le navigateur declenche donc, lui aussi, une notification sur le telephone
 * du destinataire, meme application fermee.
 *
 * Point important : l'identifiant de canal place dans la notification est
 * celui que le DESTINATAIRE utilise pour ouvrir la conversation. Pour un
 * echange prive, c'est l'identifiant de l'EXPEDITEUR (et non celui du
 * destinataire, qui renverrait « conversation inaccessible »).
 */
final class NotifieurChat
{
    public function __construct(
        private readonly NotificationPush $push,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** Jamais bloquant : une erreur de notification ne doit pas faire echouer l'envoi. */
    public function notifier(MessageChat $message, User $expediteur): void
    {
        try {
            $liste = $message->getListeDiffusion();
            $prive = $message->getDestinataire();

            if ($liste instanceof ListeDiffusion) {
                $destinataires = $liste->membresEffectifs();
                $canal = 'liste:' . $liste->getId();
                $titre = (string) $liste->getNom() . ' · ' . $this->nomComplet($expediteur);
                $typeCanal = 'liste';
            } elseif ($prive instanceof User) {
                $destinataires = [$prive];
                $canal = (string) $expediteur->getId();
                $titre = $this->nomComplet($expediteur);
                $typeCanal = 'prive';
            } else {
                $destinataires = $this->em->getRepository(User::class)->findAll();
                $canal = 'general';
                $titre = 'Général · ' . $this->nomComplet($expediteur);
                $typeCanal = 'general';
            }

            $destinataires = array_values(array_filter(
                $destinataires,
                static fn ($u) => $u instanceof User && $u->getId() !== $expediteur->getId()
            ));
            if ($destinataires === []) {
                return;
            }

            $this->push->envoyer($destinataires, $titre, $this->apercu($message), [
                'type'       => 'chat',
                'canal'      => $canal,
                'type_canal' => $typeCanal,
                'titre'      => $titre,
                'message_id' => (string) $message->getId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Messagerie : notification non envoyee', ['erreur' => $e->getMessage()]);
        }
    }

    private function apercu(MessageChat $message): string
    {
        $texte = trim((string) $message->getContenu());

        if ($message->getType() === MessageChat::TYPE_AUDIO) {
            return '🎤 Message vocal';
        }
        if ($message->getType() === MessageChat::TYPE_IMAGE && ($texte === '' || $texte === 'Photo')) {
            return '📷 Photo';
        }

        return mb_strlen($texte) > 120 ? mb_substr($texte, 0, 117) . '…' : $texte;
    }

    private function nomComplet(User $user): string
    {
        $nom = trim(((string) $user->getNom()) . ' ' . ((string) $user->getPrenoms()));

        return $nom !== '' ? $nom : (string) $user->getEmail();
    }
}
