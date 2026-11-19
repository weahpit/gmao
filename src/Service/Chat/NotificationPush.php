<?php

namespace App\Service\Chat;

use App\Entity\Chat\DeviceToken;
use App\Entity\User;
use App\Repository\Chat\DeviceTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Notifications push Firebase Cloud Messaging (API HTTP v1).
 *
 * Configuration (facultative) — a definir dans le fichier .env.local du
 * serveur, jamais dans le code :
 *
 *   CITRAC_FCM_PROJECT_ID=mon-projet-citrac
 *   CITRAC_FCM_COMPTE_SERVICE=/chemin/absolu/service-account.json
 *
 * `CITRAC_FCM_COMPTE_SERVICE` peut aussi contenir le JSON complet encode en
 * base64 (pratique sur un hebergement mutualise ou l'on ne peut pas deposer
 * de fichier hors du dossier public).
 *
 * Si la configuration est absente, la classe ne fait rien : la messagerie
 * continue de fonctionner normalement, simplement sans notification.
 */
class NotificationPush
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const CHANNEL_ANDROID = 'citrac_chat';

    /** Jeton OAuth2 en cache (evite un appel reseau par message). */
    private ?string $jetonAcces = null;
    private int $expirationJeton = 0;

    /** Compte de service deja charge (null = non configure). */
    private ?array $compte = null;
    private bool $compteCharge = false;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly DeviceTokenRepository $jetons,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Envoie une notification aux appareils des utilisateurs indiques.
     *
     * @param User[]              $utilisateurs
     * @param array<string,string> $donnees      donnees jointes (canal, type...)
     */
    public function envoyer(array $utilisateurs, string $titre, string $corps, array $donnees = []): void
    {
        if ($utilisateurs === []) {
            return;
        }

        $compte = $this->compteService();
        if ($compte === null) {
            return; // push non configure : on n'echoue pas
        }

        $destinataires = $this->jetons->pourUtilisateurs($utilisateurs);
        if ($destinataires === []) {
            return;
        }

        $acces = $this->jetonAcces($compte);
        if ($acces === null) {
            return;
        }

        $url = 'https://fcm.googleapis.com/v1/projects/' . $compte['project_id'] . '/messages:send';

        foreach ($destinataires as $appareil) {
            $token = (string) $appareil->getToken();
            if ($token === '') {
                continue;
            }

            $charge = [
                'message' => [
                    'token'        => $token,
                    'notification' => ['title' => $titre, 'body' => $corps],
                    'data'         => array_map('strval', $donnees),
                    'android'      => [
                        'priority'     => 'high',
                        'notification' => [
                            'channel_id' => self::CHANNEL_ANDROID,
                            'sound'      => 'default',
                        ],
                    ],
                    'apns' => [
                        'payload' => ['aps' => ['sound' => 'default', 'badge' => 1]],
                    ],
                ],
            ];

            try {
                $reponse = $this->http->request('POST', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $acces,
                        'Content-Type'  => 'application/json',
                    ],
                    'json'    => $charge,
                    'timeout' => 10,
                ]);

                if ($reponse->getStatusCode() === 404 || $reponse->getStatusCode() === 400) {
                    // Jeton perime ou invalide : on le retire de la base.
                    $this->em->remove($appareil);
                    $this->em->flush();
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Push FCM : echec pour un appareil', [
                    'erreur' => $e->getMessage(),
                    'statut' => method_exists($e, 'getStatusCode') ? null : null,
                ]);
            }
        }
    }

    /** Indique si les notifications push sont configurees sur ce serveur. */
    public function estConfigure(): bool
    {
        return $this->compteService() !== null;
    }

    // ------------------------------------------------------------------
    //  Compte de service
    // ------------------------------------------------------------------
    private function compteService(): ?array
    {
        if ($this->compteCharge) {
            return $this->compte;
        }
        $this->compteCharge = true;

        $brut = $this->variableEnvironnement('CITRAC_FCM_COMPTE_SERVICE');
        $projet = $this->variableEnvironnement('CITRAC_FCM_PROJECT_ID');

        $json = null;
        if ($brut !== '') {
            if (is_file($brut)) {
                $json = (string) @file_get_contents($brut);
            } else {
                // Peut etre le JSON direct ou du base64.
                $json = $brut;
                if (!str_contains($json, '{')) {
                    $decode = base64_decode($json, true);
                    if ($decode !== false) {
                        $json = $decode;
                    }
                }
            }
        }

        if ($json === null || trim($json) === '') {
            $this->logger->info('Push FCM non configure : CITRAC_FCM_COMPTE_SERVICE absent.');

            return null;
        }

        $compte = json_decode($json, true);
        if (!is_array($compte) || !isset($compte['client_email'], $compte['private_key'])) {
            $this->logger->warning('Push FCM : compte de service illisible (client_email / private_key manquants).');

            return null;
        }

        $compte['project_id'] = $compte['project_id'] ?? ($projet !== '' ? $projet : '');
        $compte['token_uri'] = $compte['token_uri'] ?? 'https://oauth2.googleapis.com/token';

        if ($compte['project_id'] === '') {
            $this->logger->warning('Push FCM : project_id introuvable (definir CITRAC_FCM_PROJECT_ID).');

            return null;
        }

        $this->compte = $compte;

        return $compte;
    }

    private function variableEnvironnement(string $nom): string
    {
        $valeur = $_ENV[$nom] ?? $_SERVER[$nom] ?? getenv($nom);

        return is_string($valeur) ? trim($valeur) : '';
    }

    // ------------------------------------------------------------------
    //  Jeton OAuth2 (JWT signe RS256)
    // ------------------------------------------------------------------
    private function jetonAcces(array $compte): ?string
    {
        if ($this->jetonAcces !== null && time() < $this->expirationJeton - 60) {
            return $this->jetonAcces;
        }

        $maintenant = time();
        $entete = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64Url(json_encode([
            'iss'   => $compte['client_email'],
            'scope' => self::SCOPE,
            'aud'   => $compte['token_uri'],
            'iat'   => $maintenant,
            'exp'   => $maintenant + 3600,
        ]));

        $aSigner = $entete . '.' . $claims;
        $signature = '';
        $cle = openssl_pkey_get_private((string) $compte['private_key']);
        if ($cle === false) {
            $this->logger->warning('Push FCM : cle privee du compte de service invalide.');

            return null;
        }
        if (!openssl_sign($aSigner, $signature, $cle, OPENSSL_ALGO_SHA256)) {
            $this->logger->warning('Push FCM : signature du jeton impossible.');

            return null;
        }
        $assertion = $aSigner . '.' . $this->base64Url($signature);

        try {
            $reponse = $this->http->request('POST', (string) $compte['token_uri'], [
                'body' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $assertion,
                ],
                'timeout' => 10,
            ]);

            $data = json_decode((string) $reponse->getContent(), true);
            if (is_array($data) && isset($data['access_token'])) {
                $this->jetonAcces = (string) $data['access_token'];
                $this->expirationJeton = $maintenant + (int) ($data['expires_in'] ?? 3600);

                return $this->jetonAcces;
            }

            $this->logger->warning('Push FCM : reponse OAuth2 inattendue', [
                'statut' => $reponse->getStatusCode(),
                'corps'  => mb_substr((string) $reponse->getContent(), 0, 300),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Push FCM : echec OAuth2', ['erreur' => $e->getMessage()]);
        }

        return null;
    }

    private function base64Url(string $valeur): string
    {
        return rtrim(strtr(base64_encode($valeur), '+/', '-_'), '=');
    }
}
