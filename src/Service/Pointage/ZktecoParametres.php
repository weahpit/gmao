<?php

namespace App\Service\Pointage;

/**
 * Parametres de connexion ZKTECO enregistres depuis l'interface.
 *
 * Les valeurs sont stockees dans var/zkteco/config.json afin de ne pas
 * dependre d'une modification du fichier .env par l'exploitant. Elles sont
 * prioritaires sur les variables d'environnement, qui restent le repli.
 */
class ZktecoParametres
{
    public const DEFAUTS = [
        'fichier' => '',
        'dsn' => '',
        'tolerance' => 5,
        'jours_reprise' => 3,
        'auto_actif' => false,
        'heure' => '07:30',
    ];

    private const MAX_HISTORIQUE = 40;

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function dossier(): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'zkteco';
    }

    public function fichierConfiguration(): string
    {
        return $this->dossier() . DIRECTORY_SEPARATOR . 'config.json';
    }

    public function fichierHistorique(): string
    {
        return $this->dossier() . DIRECTORY_SEPARATOR . 'historique.json';
    }

    /**
     * Parametres enregistres, completes par les valeurs par defaut.
     *
     * @return array<string, mixed>
     */
    public function tous(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $enregistres = $this->lire($this->fichierConfiguration());
        self::$cache = array_merge(self::DEFAUTS, $enregistres);

        return self::$cache;
    }

    /** Valeur enregistree, ou null si elle n'a jamais ete saisie. */
    public function valeur(string $cle): ?string
    {
        $tous = $this->tous();
        if (!array_key_exists($cle, $tous)) {
            return null;
        }

        $valeur = trim((string) $tous[$cle]);

        return $valeur === '' ? null : $valeur;
    }

    /**
     * Enregistre les parametres saisis dans l'interface.
     *
     * @param array<string, mixed> $valeurs
     *
     * @throws \RuntimeException si le dossier var/zkteco n'est pas inscriptible
     */
    public function enregistrer(array $valeurs, string $auteur = ''): void
    {
        $propres = array_intersect_key($valeurs, self::DEFAUTS);
        $propres['fichier'] = $this->nettoyerChemin((string) ($propres['fichier'] ?? ''));
        $propres['dsn'] = trim((string) ($propres['dsn'] ?? ''));
        $propres['tolerance'] = max(0, min(120, (int) ($propres['tolerance'] ?? self::DEFAUTS['tolerance'])));
        $propres['jours_reprise'] = max(1, min(90, (int) ($propres['jours_reprise'] ?? self::DEFAUTS['jours_reprise'])));
        $propres['auto_actif'] = (bool) ($propres['auto_actif'] ?? false);
        $propres['heure'] = (string) ($propres['heure'] ?? self::DEFAUTS['heure']);
        $propres['updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $propres['updated_by'] = $auteur;

        $this->ecrire($this->fichierConfiguration(), $propres);
        self::$cache = array_merge(self::DEFAUTS, $propres);
    }

    /**
     * Ajoute une ligne a l'historique des executions (30 dernieres).
     *
     * @param array<string, mixed> $ligne
     */
    public function journaliser(array $ligne): void
    {
        $historique = $this->historique();
        $ligne['date'] = $ligne['date'] ?? (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        array_unshift($historique, $ligne);
        $historique = array_slice($historique, 0, self::MAX_HISTORIQUE);

        try {
            $this->ecrire($this->fichierHistorique(), ['executions' => $historique]);
        } catch (\RuntimeException) {
            // L'historique est un confort : son echec ne doit pas interrompre l'import.
        }
    }

    /** @return list<array<string, mixed>> */
    public function historique(): array
    {
        $contenu = $this->lire($this->fichierHistorique());
        $lignes = $contenu['executions'] ?? [];

        return is_array($lignes) ? array_values(array_filter($lignes, 'is_array')) : [];
    }

    /** Derniere execution reussie, ou null. */
    public function derniereExecution(): ?array
    {
        foreach ($this->historique() as $ligne) {
            if (($ligne['succes'] ?? false) === true) {
                return $ligne;
            }
        }

        return null;
    }

    public function dossierInscriptible(): bool
    {
        $dossier = $this->dossier();

        return is_dir($dossier) ? is_writable($dossier) : is_writable(dirname($dossier));
    }

    /** Nettoie un chemin Windows colle depuis l'explorateur. */
    public function nettoyerChemin(string $chemin): string
    {
        $chemin = trim($chemin, " \t\n\r\0\x0B\"'");
        $chemin = str_replace('/', '\\', $chemin);
        $chemin = (string) preg_replace('/\\\\{2,}/', '\\\\', $chemin);

        if (str_starts_with(strtolower($chemin), '\\\\?\\')) {
            $chemin = substr($chemin, 4);
        }

        return $chemin;
    }

    /** @return array<string, mixed> */
    private function lire(string $fichier): array
    {
        if (!is_file($fichier)) {
            return [];
        }

        $contenu = @file_get_contents($fichier);
        if ($contenu === false || trim($contenu) === '') {
            return [];
        }

        $donnees = json_decode($contenu, true);

        return is_array($donnees) ? $donnees : [];
    }

    /** @param array<string, mixed> $donnees */
    private function ecrire(string $fichier, array $donnees): void
    {
        $dossier = \dirname($fichier);
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            throw new \RuntimeException('Le dossier ' . $dossier . ' n\'a pas pu etre cree. Verifiez les droits du compte PHP.');
        }

        $json = json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Les parametres n\'ont pas pu etre convertis en JSON : ' . json_last_error_msg());
        }

        if (@file_put_contents($fichier, $json) === false) {
            throw new \RuntimeException('Ecriture impossible dans ' . $fichier . '. Le dossier var/zkteco doit etre inscriptible par le compte PHP.');
        }
    }
}
