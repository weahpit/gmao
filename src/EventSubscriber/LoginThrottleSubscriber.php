<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\AccountLockedException;
use App\Security\LoginThrottler;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Branche le compteur de tentatives (LoginThrottler) sur les evenements de
 * securite Symfony :
 *   - echec de connexion  -> incremente le compteur, applique les paliers de
 *     verrouillage et alerte l'administrateur des 3 echecs ;
 *   - connexion reussie   -> remet le compteur a zero.
 *
 * S'enregistre automatiquement (autoconfigure), sans configuration YAML.
 */
class LoginThrottleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoginThrottler $throttler,
        private LoggerInterface $logger,
        private UserRepository $userRepository,
        private MailerInterface $mailer,
    ) {
    }

    /** Adresse d'expedition des alertes securite (modifiable au besoin). */
    private const MAIL_FROM = 'no-reply@citrac.local';

    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        // Si c'est NOTRE verrouillage qui a bloque la tentative, on n'incremente
        // pas le compteur (sinon le verrou s'auto-entretiendrait).
        if ($event->getException() instanceof AccountLockedException) {
            return;
        }

        $request = $event->getRequest();
        $username = $request?->getPayload()->getString('email') ?: null;
        $ip = $request?->getClientIp();

        $key = $this->throttler->buildKey($username, $ip);
        $result = $this->throttler->registerFailure($key, $username, $ip);

        $this->logger->warning('Tentative de connexion echouee', [
            'identifiant' => $username,
            'ip' => $ip,
            'nombre_echecs' => $result['count'],
            'verrouille_jusqua' => $result['lockedUntil']
                ? date('Y-m-d H:i:s', $result['lockedUntil']) . ' UTC'
                : null,
        ]);

        // Alerte administrateur a partir du palier configure (>= 3 echecs).
        if ($result['newAlertTier'] !== null) {
            $this->alertAdministrators($username, $ip, $result['count'], $result['lockedUntil']);
        }
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        $username = $request?->getPayload()->getString('email') ?: null;
        $ip = $request?->getClientIp();

        // On remet a zero la cle identifiant+IP ET, par securite, la cle liee a
        // l'identifiant reellement authentifie.
        $this->throttler->reset($this->throttler->buildKey($username, $ip));

        $user = $event->getUser();
        if ($user !== null) {
            $this->throttler->reset(
                $this->throttler->buildKey($user->getUserIdentifier(), $ip)
            );
        }
    }

    /**
     * Notifie les administrateurs (journal + e-mail) d'une serie d'echecs.
     */
    private function alertAdministrators(?string $username, ?string $ip, int $count, ?int $lockedUntil): void
    {
        $lockMsg = $lockedUntil
            ? 'Connexion verrouillee jusqu\'au ' . date('d/m/Y H:i:s', $lockedUntil) . ' (UTC).'
            : 'Aucun verrouillage actif.';

        $subject = sprintf('[CITRAC GMAO] Alerte securite : %d tentatives de connexion echouees', $count);
        $body = sprintf(
            "Plusieurs tentatives de connexion ont echoue sur CITRAC GMAO.\n\n"
            . "Identifiant saisi : %s\n"
            . "Adresse IP        : %s\n"
            . "Nombre d'echecs   : %d\n"
            . "%s\n\n"
            . "Date (UTC)        : %s\n",
            $username ?: '(inconnu)',
            $ip ?: '(inconnue)',
            $count,
            $lockMsg,
            date('d/m/Y H:i:s')
        );

        // Journalisation (toujours disponible, meme sans serveur mail).
        $this->logger->alert('Alerte securite connexion CITRAC GMAO', [
            'identifiant' => $username,
            'ip' => $ip,
            'nombre_echecs' => $count,
            'verrouille_jusqua' => $lockedUntil ? date('Y-m-d H:i:s', $lockedUntil) . ' UTC' : null,
        ]);

        $recipients = $this->adminEmails();
        if ($recipients === []) {
            return;
        }

        try {
            $email = (new Email())
                ->from(self::MAIL_FROM)
                ->subject($subject)
                ->text($body);
            foreach ($recipients as $to) {
                $email->addTo($to);
            }
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // L'envoi d'e-mail ne doit jamais casser le flux de connexion :
            // l'alerte reste tracee dans les journaux ci-dessus.
            $this->logger->error('Echec de l\'envoi de l\'alerte securite par e-mail', [
                'erreur' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Recupere les adresses e-mail des administrateurs (ROLE_ADMIN).
     *
     * @return list<string>
     */
    private function adminEmails(): array
    {
        $emails = [];
        try {
            foreach ($this->userRepository->findAll() as $user) {
                if (!$user instanceof User) {
                    continue;
                }
                if (in_array('ROLE_ADMIN', $user->getRoles(), true) && $user->getEmail()) {
                    $emails[] = $user->getEmail();
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Impossible de lister les administrateurs pour l\'alerte securite', [
                'erreur' => $e->getMessage(),
            ]);
        }

        return array_values(array_unique($emails));
    }
}
