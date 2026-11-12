<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Compteur de tentatives de connexion echouees + verrouillage par paliers.
 *
 * Principe (demande metier CITRAC) :
 *   - a partir de 3 echecs   -> page verrouillee 5 minutes
 *   - a partir de 5 echecs   -> page verrouillee 15 minutes
 *   - a partir de 10 echecs  -> page verrouillee 1 heure
 *   - l'administrateur est alerte des 3 echecs (voir LoginThrottleSubscriber)
 *
 * Le suivi est stocke dans des fichiers JSON sous var/login_throttle/,
 * ce qui evite toute migration de base de donnees et reste totalement
 * reversible : supprimer ce dossier remet les compteurs a zero.
 *
 * La cle de suivi combine l'identifiant saisi et l'adresse IP, afin de ne
 * pas bloquer tout un reseau pour un seul utilisateur maladroit.
 */
class LoginThrottler
{
    /** Paliers : [nombre d'echecs => duree de verrouillage en secondes]. */
    private const TIERS = [
        10 => 3600,  // >= 10 echecs : 1 heure
        5  => 900,   // >= 5  echecs : 15 minutes
        3  => 300,   // >= 3  echecs : 5 minutes
    ];

    /** Palier a partir duquel on alerte l'administrateur. */
    public const ALERT_THRESHOLD = 3;

    private string $storageDir;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ) {
        $this->storageDir = rtrim($projectDir, '/') . '/var/login_throttle';
    }

    /**
     * Construit une cle de suivi stable a partir de l'identifiant et de l'IP.
     */
    public function buildKey(?string $username, ?string $ip): string
    {
        $username = strtolower(trim((string) $username));
        $ip = (string) $ip;

        return sha1($username . '|' . $ip);
    }

    /**
     * Indique si la connexion est actuellement verrouillee pour cette cle.
     *
     * @return array{locked: bool, seconds: int, until: int|null}
     */
    public function getLockState(string $key): array
    {
        $data = $this->read($key);
        $until = $data['lockedUntil'] ?? null;

        if ($until !== null && $until > time()) {
            return ['locked' => true, 'seconds' => $until - time(), 'until' => $until];
        }

        return ['locked' => false, 'seconds' => 0, 'until' => null];
    }

    /**
     * Enregistre un echec de connexion et recalcule le verrouillage.
     *
     * @return array{count: int, lockedUntil: int|null, newAlertTier: int|null}
     *         newAlertTier = palier franchi qui n'avait pas encore declenche
     *         d'alerte administrateur (null si aucune nouvelle alerte).
     */
    public function registerFailure(string $key, ?string $username, ?string $ip): array
    {
        $data = $this->read($key);

        $now = time();
        $data['username'] = (string) $username;
        $data['ip'] = (string) $ip;
        $data['count'] = (int) ($data['count'] ?? 0) + 1;
        $data['firstAt'] = $data['firstAt'] ?? $now;
        $data['lastAt'] = $now;
        $data['alertedTiers'] = $data['alertedTiers'] ?? [];

        // Duree de verrouillage correspondant au nombre d'echecs cumules.
        $lockSeconds = 0;
        foreach (self::TIERS as $threshold => $seconds) {
            if ($data['count'] >= $threshold) {
                $lockSeconds = $seconds;
                break;
            }
        }

        $data['lockedUntil'] = $lockSeconds > 0 ? ($now + $lockSeconds) : null;

        // Determine si un NOUVEAU palier d'alerte vient d'etre atteint.
        $newAlertTier = null;
        foreach (array_keys(self::TIERS) as $threshold) {
            if ($threshold < self::ALERT_THRESHOLD) {
                continue;
            }
            if ($data['count'] >= $threshold && !in_array($threshold, $data['alertedTiers'], true)) {
                $newAlertTier = max($newAlertTier ?? 0, $threshold);
            }
        }
        if ($newAlertTier !== null) {
            $data['alertedTiers'][] = $newAlertTier;
        }

        $this->write($key, $data);

        return [
            'count' => $data['count'],
            'lockedUntil' => $data['lockedUntil'],
            'newAlertTier' => $newAlertTier,
        ];
    }

    /**
     * Remet le compteur a zero (connexion reussie).
     */
    public function reset(string $key): void
    {
        $file = $this->file($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    // ---------------------------------------------------------------
    // Stockage fichier (JSON)
    // ---------------------------------------------------------------

    private function file(string $key): string
    {
        return $this->storageDir . '/' . $key . '.json';
    }

    private function read(string $key): array
    {
        $file = $this->file($key);
        if (!is_file($file)) {
            return [];
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    private function write(string $key, array $data): void
    {
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
        @file_put_contents(
            $this->file($key),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}
