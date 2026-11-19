<?php

namespace App\Service\Rh;

use Psr\Log\LoggerInterface;

/**
 * Acces a la base de pointage ZKTeco (fichier Microsoft Access att2000.mdb).
 *
 * L'ancienne application ouvrait la base avec un chemin Windows et des identifiants
 * codes en dur. Ici, la connexion est entierement configurable :
 *
 *   RH_ZKTECO_DSN="odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=C:\Program Files (x86)\ZKTeco\att2000.mdb;"
 *   RH_ZKTECO_USER=admin
 *   RH_ZKTECO_PASSWORD=******
 *
 * Si la source n'est pas configuree ou n'est pas joignable, le service ne provoque
 * pas d'erreur fatale : il renvoie un tableau vide, conserve le message d'erreur
 * reel (sans le mot de passe) et l'ecrit dans le journal. Les ecrans concernes
 * peuvent ainsi afficher un bandeau explicite au lieu d'une page blanche.
 */
class PointageService
{
    private ?\PDO $pdo = null;

    private bool $connexionTentee = false;

    private ?string $derniereErreur = null;

    public function __construct(
        private LoggerInterface $logger,
        private ?string $dsn = null,
        private ?string $utilisateur = null,
        private ?string $motDePasse = null,
    ) {
        $this->dsn = $this->dsn ?: ($_ENV['RH_ZKTECO_DSN'] ?? $_SERVER['RH_ZKTECO_DSN'] ?? getenv('RH_ZKTECO_DSN') ?: null);
        $this->utilisateur = $this->utilisateur ?: ($_ENV['RH_ZKTECO_USER'] ?? $_SERVER['RH_ZKTECO_USER'] ?? getenv('RH_ZKTECO_USER') ?: null);
        $this->motDePasse = $this->motDePasse ?: ($_ENV['RH_ZKTECO_PASSWORD'] ?? $_SERVER['RH_ZKTECO_PASSWORD'] ?? getenv('RH_ZKTECO_PASSWORD') ?: null);
    }

    /**
     * Indique si la base de pointage est configuree et joignable.
     */
    public function estDisponible(): bool
    {
        return null !== $this->connexion();
    }

    /**
     * Dernier message d'erreur lisible (jamais le mot de passe).
     */
    public function derniereErreur(): ?string
    {
        return $this->derniereErreur;
    }

    /**
     * Execute une requete de lecture sur la base de pointage.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getData(string $sql): array
    {
        $pdo = $this->connexion();
        if (null === $pdo) {
            return [];
        }

        try {
            $stmt = $pdo->query($sql);
            if (false === $stmt) {
                $this->enregistrerErreur('La requete de pointage a echoue', $pdo->errorInfo()[2] ?? null);

                return [];
            }

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $this->enregistrerErreur('La requete de pointage a echoue', $e->getMessage());

            return [];
        }
    }

    private function connexion(): ?\PDO
    {
        if ($this->connexionTentee) {
            return $this->pdo;
        }

        $this->connexionTentee = true;

        if (!$this->dsn) {
            $this->derniereErreur = "La base de pointage ZKTeco n'est pas configuree : renseignez la variable d'environnement RH_ZKTECO_DSN.";
            $this->logger->warning('[RH] ' . $this->derniereErreur);

            return null;
        }

        if (!\in_array('odbc', \PDO::getAvailableDrivers(), true)) {
            $this->derniereErreur = "Le pilote PDO ODBC n'est pas active sur ce serveur : installez php-odbc et le pilote Microsoft Access.";
            $this->logger->error('[RH] ' . $this->derniereErreur);

            return null;
        }

        try {
            $this->pdo = new \PDO($this->dsn, (string) $this->utilisateur, (string) $this->motDePasse, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (\Throwable $e) {
            $this->enregistrerErreur('Connexion a la base de pointage ZKTeco impossible', $e->getMessage());
            $this->pdo = null;
        }

        return $this->pdo;
    }

    private function enregistrerErreur(string $contexte, ?string $detail): void
    {
        $detail = trim((string) $detail);
        $this->derniereErreur = '' === $detail ? $contexte . '.' : $contexte . ' : ' . $detail;
        $this->logger->error('[RH] ' . $this->derniereErreur);
    }
}
