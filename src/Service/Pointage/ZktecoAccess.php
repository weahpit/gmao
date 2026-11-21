<?php

namespace App\Service\Pointage;

/**
 * Lecture de la base ZKTECO installee sur le poste de pointage.
 *
 * Le materiel ZKTECO (pointeuse) enregistre les passages dans une base
 * Microsoft Access, generalement composee de deux tables :
 *   - USERINFO   : USERID, Badgenumber, NAME
 *   - CHECKINOUT : USERID, CHECKTIME (date + heure), CHECKTYPE
 *
 * Les noms de tables et de colonnes peuvent varier selon la version du
 * logiciel ZKAccess : tout est donc configurable par variables
 * d'environnement, et la commande app:pointage:zkteco --diagnostic affiche
 * les tables et colonnes reellement presentes dans le fichier.
 *
 * Variables reconnues (fichier .env ou .env.local) :
 *   ZKTECO_ACCESS_DSN      DSN ODBC deja configure (ex. « ZKTECO »)
 *   ZKTECO_ACCESS_FICHIER  chemin du fichier .mdb / .accdb (local ou partage)
 *   ZKTECO_TABLE_USERS     defaut USERINFO
 *   ZKTECO_TABLE_POINTAGES defaut CHECKINOUT
 *   ZKTECO_COL_USERID      defaut USERID
 *   ZKTECO_COL_BADGE       defaut Badgenumber
 *   ZKTECO_COL_NOM         defaut NAME
 *   ZKTECO_COL_CHECKTIME   defaut CHECKTIME
 *   ZKTECO_COL_CHECKTYPE   defaut CHECKTYPE
 *   ZKTECO_HEURE_ARRIVEE   defaut 08:00
 *   ZKTECO_HEURE_DEPART    defaut 17:00
 */
final class ZktecoAccess
{
    private const DEFAUTS = [
        'ZKTECO_REQUETE' => 'Req_UserCheck',
        'ZKTECO_TABLE_USERS' => 'USERINFO',
        'ZKTECO_TABLE_POINTAGES' => 'CHECKINOUT',
        'ZKTECO_COL_USERID' => 'USERID',
        'ZKTECO_COL_BADGE' => 'Badgenumber',
        'ZKTECO_COL_NOM' => 'NAME',
        'ZKTECO_COL_CHECKTIME' => 'CHECKTIME',
        'ZKTECO_COL_CHECKTYPE' => 'CHECKTYPE',
        'ZKTECO_HEURE_ARRIVEE' => '08:00',
        'ZKTECO_HEURE_DEPART' => '17:00',
    ];

    /** Plafond de securite quand on doit filtrer les dates cote PHP. */
    private const PLAFOND_LECTURE = 100000;

    /** Source reellement utilisee lors du dernier import. */
    private string $source = '';

    /** Trace des tentatives de lecture (affichee par --diagnostic). */
    private array $journal = [];

    /** Methode de filtrage qui a effectivement fonctionne. */
    private string $methodeFiltre = '';

    /** Surcharges ponctuelles (options de la commande). */
    private array $surcharges = [];

    public function setSurcharge(string $cle, string $valeur): void
    {
        $this->surcharges[$cle] = $valeur;
    }

    /** Exemple : « requete Req_UserCheck » ou « tables USERINFO + CHECKINOUT ». */
    public function sourceUtilisee(): string
    {
        return $this->source;
    }

    /** @return list<string> */
    public function journal(): array
    {
        return $this->journal;
    }

    public function methodeFiltre(): string
    {
        return $this->methodeFiltre;
    }

    /** @return array<string, string> */
    public function configuration(): array
    {
        $config = [];
        foreach (self::DEFAUTS as $cle => $defaut) {
            $config[$cle] = $this->valeur($cle, $defaut);
        }

        $config['ZKTECO_REQUETE'] = $this->valeur('ZKTECO_REQUETE', 'Req_UserCheck');
        $config['ZKTECO_ACCESS_DSN'] = $this->valeur('ZKTECO_ACCESS_DSN', '');
        $config['ZKTECO_ACCESS_FICHIER'] = $this->valeur('ZKTECO_ACCESS_FICHIER', '');

        return $config;
    }

    public function heureArrivee(): string
    {
        return $this->configuration()['ZKTECO_HEURE_ARRIVEE'];
    }

    public function heureDepart(): string
    {
        return $this->configuration()['ZKTECO_HEURE_DEPART'];
    }

    public function driversDisponibles(): array
    {
        return \PDO::getAvailableDrivers();
    }

    /**
     * Etat du fichier Access : chemin reellement utilise, existence, droits,
     * partage reseau et compte qui execute PHP.
     *
     * Le chemin est d'abord nettoye : les guillemets et les barres obliques
     * inverses doublees (copier-coller Windows ou valeur .env trop echappee)
     * sont la cause la plus frequente d'un « Existe : NON » alors que le
     * fichier est bien present sur le partage.
     *
     * @return array<string, mixed>
     */
    public function etatFichier(?string $fichier = null): array
    {
        $brut = trim((string) ($fichier ?? $this->configuration()['ZKTECO_ACCESS_FICHIER']));
        $brut = trim($brut, " \t\n\r\0\x0B\"'");

        $etat = [
            'fichier' => '',
            'brut' => $brut,
            'normalise' => '',
            'type' => 'aucun',
            'existe' => false,
            'lisible' => false,
            'taille' => 0,
            'modifie' => null,
            'partage' => [],
            'compte' => $this->compteExecution(),
            'architecture' => PHP_INT_SIZE * 8 . ' bits',
            'pistes' => [],
            'message' => '',
        ];

        if ($brut === '') {
            $etat['message'] = 'Aucun fichier configure : renseignez ZKTECO_ACCESS_FICHIER dans .env.local'
                . ' (exemple : ZKTECO_ACCESS_FICHIER="\\\\\\\\192.168.10.15\\\\ZKTeco\\\\att2000.mdb").';

            return $etat;
        }

        $chemin = $this->normaliserCheminFichier($brut);
        $etat['normalise'] = $chemin;
        $etat['fichier'] = $chemin;
        $etat['partage'] = $this->partageDepuisChemin($chemin);

        if (stripos($brut, 'smb://') === 0 || stripos($chemin, 'smb://') === 0) {
            $etat['type'] = 'smb';
            $etat['message'] = "Les adresses smb:// ne sont pas lisibles directement par PHP : "
                . 'montez le partage (montage CIFS) ou mappez un lecteur reseau, puis indiquez le chemin du fichier monte.';
            $etat['pistes'] = [
                'Windows : net use Z: \\\\192.168.10.15\\ZKTeco /user:DOMAINE\\compte /persistent:yes puis ZKTECO_ACCESS_FICHIER="Z:\\att2000.mdb".',
                'Linux : mount -t cifs //192.168.10.15/ZKTeco /mnt/zkteco -o username=...,password=...,vers=3.0 puis /mnt/zkteco/att2000.mdb.',
            ];

            return $etat;
        }

        $existe = @is_file($chemin);
        $estDossier = !$existe && @is_dir($chemin);

        if ($existe) {
            $etat['type'] = 'fichier';
            $etat['existe'] = true;
            $etat['lisible'] = @is_readable($chemin);
            $etat['taille'] = (int) @filesize($chemin);
            $modifie = @filemtime($chemin);
            $etat['modifie'] = $modifie ? (string) @date('d/m/Y H:i', (int) $modifie) : null;

            if (!$etat['lisible']) {
                $etat['message'] = 'Le fichier existe mais le compte « ' . $etat['compte']
                    . ' » (celui qui execute PHP) n\'a pas le droit de le lire.'
                    . ' Accordez au moins « Lecture » sur le fichier et sur le dossier partage.';
            }

            return $etat;
        }

        if ($estDossier) {
            $etat['type'] = 'dossier';
            $etat['message'] = 'Le chemin designe un dossier, pas le fichier Access : ajoutez le nom du fichier'
                . ' (att2000.mdb) a la fin du chemin.';

            return $etat;
        }

        $etat['type'] = 'introuvable';

        if ($etat['partage'] !== []) {
            $partage = $etat['partage'];
            $racineAccessible = @is_dir($partage['racine']);
            $dossierLisible = @is_readable($partage['racine']);

            if (!$racineAccessible) {
                $etat['message'] = 'Le partage ' . $partage['racine'] . ' n\'est pas accessible depuis le compte « '
                    . $etat['compte'] . ' » qui execute PHP. Le chemin du fichier est donc juste,'
                    . ' mais ce compte n\'a pas le droit d\'ouvrir le partage (ou le poste 192.168.10.15 est eteint,'
                    . ' ou le pare-feu bloque le partage de fichiers).';

                $etat['pistes'] = DIRECTORY_SEPARATOR === '\\'
                    ? [
                        'Testez avec le MEME compte que le service : ouvrez une invite de commandes en tant que ce compte puis « dir \\\\192.168.10.15\\\\ZKTeco ».',
                        'Dans PowerShell : Test-Path \'\\\\192.168.10.15\\ZKTeco\\att2000.mdb\' — un False confirme un probleme de droits ou de reseau, pas de chemin.',
                        'Mappez le partage pour ce compte : net use Z: \\\\192.168.10.15\\ZKTeco /user:DOMAINE\\compte /persistent:yes, puis ZKTECO_ACCESS_FICHIER="Z:\\att2000.mdb".',
                        'Verifiez que le partage ZKTeco autorise le compte du service (et non seulement votre session Windows).',
                        'Si le serveur est en 64 bits, le pilote ODBC Access doit aussi etre en 64 bits.',
                    ]
                    : [
                        'Montez le partage : mount -t cifs //192.168.10.15/ZKTeco /mnt/zkteco -o username=...,password=...,vers=3.0',
                        'Puis ZKTECO_ACCESS_FICHIER="/mnt/zkteco/att2000.mdb".',
                        'Verifiez que le compte du service web (www-data) peut lire /mnt/zkteco/att2000.mdb.',
                    ];
            } elseif (!$dossierLisible) {
                $etat['message'] = 'Le partage ' . $partage['racine'] . ' existe mais n\'est pas lisible par le compte « '
                    . $etat['compte'] . ' ». Accordez les droits de lecture au compte du service.';
            } else {
                $etat['message'] = 'Le partage ' . $partage['racine'] . ' est accessible, mais le fichier « '
                    . ($partage['fichier'] !== '' ? $partage['fichier'] : basename($chemin))
                    . ' » n\'y a pas ete trouve. Verifiez le nom exact du fichier (att2000.mdb) et le sous-dossier.';
                $etat['pistes'] = [
                    'Le partage est joignable : le probleme vient du nom du fichier ou du sous-dossier, pas des droits.',
                    'Verifiez la casse et l\'extension reelle (.mdb ou .accdb) dans l\'explorateur Windows.',
                ];
            }
        } else {
            $etat['message'] = 'Fichier introuvable a cet emplacement : ' . $chemin . '.'
                . ' Verifiez le chemin (lecteur reseau mappe ou dossier local) et l\'orthographe du fichier.';
            $etat['pistes'] = DIRECTORY_SEPARATOR === '\\'
                ? [
                    'Un chemin en \\\\serveur\\partage n\'est lisible par PHP que si le compte du service a acces au partage.',
                    'Vous pouvez aussi mapper un lecteur (Z:) et utiliser Z:\\att2000.mdb.',
                ]
                : [
                    'Sur Linux, un chemin Windows doit etre monte (mount -t cifs) avant d\'etre lu par PHP.',
                ];
        }

        return $etat;
    }

    /**
     * Nettoie un chemin saisi dans .env, dans une variable d'environnement ou
     * sur la ligne de commande.
     *
     *   "\\\\192.168.10.15\\ZKTeco\\att2000.mdb"  ->  \\192.168.10.15\ZKTeco\att2000.mdb
     *   \\?\UNC\192.168.10.15\ZKTeco\att2000.mdb     ->  \\192.168.10.15\ZKTeco\att2000.mdb
     */
    public function normaliserCheminFichier(?string $brut): string
    {
        $chemin = str_replace("\xC2\xA0", ' ', (string) $brut);
        $chemin = trim($chemin);
        $chemin = trim($chemin, "\"'");
        $chemin = trim($chemin);

        if ($chemin === '') {
            return '';
        }

        if (stripos($chemin, 'file://') === 0) {
            $chemin = substr($chemin, 7);
            if (preg_match('#^/[A-Za-z]:#', $chemin) === 1) {
                $chemin = ltrim($chemin, '/');
            }
        }

        if (str_starts_with($chemin, '\\\\?\\')) {
            $reste = substr($chemin, 4);
            $chemin = stripos($reste, 'UNC\\') === 0 ? '\\\\' . substr($reste, 4) : $reste;
        }

        $reseau = str_starts_with($chemin, '\\\\') || str_starts_with($chemin, '//');
        $chemin = (string) preg_replace_callback(
            '#[\\\\/]+#',
            static fn (): string => DIRECTORY_SEPARATOR,
            $chemin
        );

        if ($reseau) {
            $chemin = (DIRECTORY_SEPARATOR === '\\' ? '\\\\' : '//') . ltrim($chemin, '/\\');
        }

        return $chemin;
    }

    /**
     * Decoupe un chemin reseau en serveur / partage / reste du chemin.
     *
     * @return array{serveur: string, partage: string, racine: string, fichier: string}|array{}
     */
    public function partageDepuisChemin(string $chemin): array
    {
        if ($chemin === '') {
            return [];
        }

        // On analyse une copie en barres obliques : le motif reste lisible et
        // fonctionne aussi bien sous Windows que sous Linux.
        $normalise = str_replace('\\', '/', $chemin);

        if (preg_match('#^//([^/]+)/([^/]+)(?:/(.*))?$#', $normalise, $m) !== 1) {
            return [];
        }

        $racine = '//' . $m[1] . '/' . $m[2];
        if (DIRECTORY_SEPARATOR === '\\') {
            $racine = str_replace('/', '\\', $racine);
        }

        return [
            'serveur' => $m[1],
            'partage' => $m[2],
            'racine' => $racine,
            'fichier' => $m[3] ?? '',
        ];
    }

    /** Compte systeme qui execute PHP (utile quand le service n'a pas les droits). */
    public function compteExecution(): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $utilisateur = getenv('USERNAME');
            $domaine = getenv('USERDOMAIN');
            if (is_string($utilisateur) && $utilisateur !== '') {
                return is_string($domaine) && $domaine !== '' ? $domaine . '\\' . $utilisateur : $utilisateur;
            }

            return 'compte du service';
        }

        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $infos = @posix_getpwuid(posix_geteuid());
            if (is_array($infos) && isset($infos['name'])) {
                return (string) $infos['name'];
            }
        }

        $utilisateur = getenv('USER');

        return is_string($utilisateur) && $utilisateur !== '' ? $utilisateur : 'compte du service';
    }

    /**
     * Sources ODBC declarees sur le poste (Windows : Outils d'administration >
     * Sources de donnees ODBC). Permet de voir le nom exact du DSN a utiliser.
     *
     * @return list<string>
     */
    public function sourcesOdbc(): array
    {
        if (!function_exists('odbc_data_source') || !defined('SQL_FETCH_FIRST')) {
            return [];
        }

        $sources = [];
        try {
            $source = @odbc_data_source(null, SQL_FETCH_FIRST);
            while (is_array($source)) {
                $nom = (string) reset($source);
                if ($nom === '') {
                    break;
                }
                $sources[] = $nom;
                if (count($sources) >= 50) {
                    break;
                }
                $source = @odbc_data_source(null, SQL_FETCH_NEXT);
            }
        } catch (\Throwable) {
            // Extension ODBC absente ou pilote muet : on renvoie ce qu'on a.
        }

        return array_values(array_unique($sources));
    }

    /**
     * Ouvre la base Access.
     *
     * @throws \RuntimeException message directement affichable (pilote absent, chemin faux...)
     */
    public function connecter(): \PDO
    {
        $config = $this->configuration();
        $drivers = $this->driversDisponibles();

        if (!in_array('odbc', $drivers, true)) {
            throw new \RuntimeException(
                'Le pilote PHP « odbc » n\'est pas active (pilotes disponibles : '
                . (implode(', ', $drivers) ?: 'aucun') . ').'
                . ' Installez php-odbc (Linux) ou activez extension=php_odbc.dll (Windows),'
                . ' puis redemarrez PHP. A defaut, exportez la pointeuse en CSV et utilisez --csv.'
            );
        }

        $dsn = trim($config['ZKTECO_ACCESS_DSN']);
        $fichier = trim($config['ZKTECO_ACCESS_FICHIER']);
        $etat = $dsn === '' ? $this->etatFichier($fichier) : null;
        $architecture = (PHP_INT_SIZE * 8) . ' bits';

        if ($dsn !== '') {
            $chaines = ['odbc:' . $dsn];
        } elseif ($fichier !== '') {
            if (!$etat['existe'] || !$etat['lisible']) {
                throw new \RuntimeException(
                    'Base Access inaccessible : ' . $etat['message']
                    . ' Chemin utilise : ' . $etat['fichier'] . '.'
                    . ' Compte PHP : ' . $etat['compte'] . ' | PHP ' . $architecture . '.'
                    . ($etat['pistes'] !== [] ? ' A verifier : ' . implode(' ', $etat['pistes']) : '')
                    . ' Pour un essai rapide sans modifier .env : php bin/console app:pointage:zkteco --fichier="C:\\ZKTeco\\att2000.mdb".'
                );
            }

            $chemin = $etat['fichier'];
            $chaines = [
                'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=' . $chemin . ';',
                'odbc:Driver={Microsoft Access Driver (*.mdb)};Dbq=' . $chemin . ';',
                'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};DBQ=' . $chemin . ';',
                'odbc:DRIVER={MDBTools};DBQ=' . $chemin . ';',
                'odbc:Driver={MDBTools};DBQ=' . $chemin . ';',
            ];
        } else {
            throw new \RuntimeException(
                'Aucune source ZKTECO configuree : renseignez ZKTECO_ACCESS_DSN (nom du DSN ODBC)'
                . ' ou ZKTECO_ACCESS_FICHIER (chemin du fichier .mdb / .accdb) dans le fichier .env.local.'
                . ' Pour connaitre les variables disponibles : php bin/console app:pointage:zkteco --diagnostic.'
            );
        }

        $erreurs = [];
        foreach ($chaines as $chaine) {
            try {
                $pdo = new \PDO($chaine, null, null, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]);

                try {
                    $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 15);
                } catch (\Throwable) {
                    // delai non supporte par ce pilote : sans consequence.
                }

                return $pdo;
            } catch (\Throwable $e) {
                $erreurs[] = $this->resumeChaine($chaine) . ' -> ' . $e->getMessage();
            }
        }

        $sources = $this->sourcesOdbc();
        $piloteAbsent = $erreurs !== [] && count(array_filter(
            $erreurs,
            static fn (string $erreur): bool => stripos($erreur, 'IM002') !== false
                || stripos($erreur, 'data source name not found') !== false
                || stripos($erreur, 'driver not found') !== false
        )) === count($erreurs);

        throw new \RuntimeException(
            'Connexion a la base ZKTECO impossible. Tentatives : ' . implode(' | ', array_slice($erreurs, 0, 3))
            . ($piloteAbsent
                ? ' Aucun pilote ODBC Access n\'est installe pour PHP ' . $architecture . ' : installez le moteur'
                    . ' « Microsoft Access Database Engine » de la MEME architecture que PHP, puis redemarrez le service.'
                    . ' Un PHP 64 bits ne voit pas un pilote 32 bits (et inversement).'
                : ' Verifiez que le pilote ODBC Access est installe (Windows : « Microsoft Access Driver (*.mdb, *.accdb) »),'
                    . ' que le DSN existe (Panneau de configuration > Outils d\'administration > Sources de donnees ODBC)'
                    . ' et que le compte du serveur a acces au fichier.')
            . ' PHP ' . $architecture . '.'
            . ($sources !== []
                ? ' Sources ODBC declarees sur ce poste : ' . implode(', ', array_slice($sources, 0, 10)) . '.'
                : ' Aucune source ODBC n\'est declaree sur ce poste.')
            . (count($erreurs) > 3 ? ' (' . (count($erreurs) - 3) . ' autre(s) tentative(s) similaire(s).)' : '')
        );
    }

    private function resumeChaine(string $chaine): string
    {
        if (preg_match('/Driver=\{([^}]+)\}/i', $chaine, $m) === 1) {
            return 'pilote ' . $m[1];
        }

        return 'DSN ' . substr($chaine, 5, 40);
    }

    /**
     * Inventaire des tables et colonnes : sert a adapter les noms si la
     * version de ZKAccess utilise d'autres libelles.
     *
     * @return array{connecte: bool, message: string, tables: array<string, list<string>>}
     */
    public function diagnostic(): array
    {
        try {
            $pdo = $this->connecter();
        } catch (\RuntimeException $e) {
            return ['connecte' => false, 'message' => $e->getMessage(), 'tables' => []];
        }

        $tables = [];
        $requetes = [
            "SELECT name FROM MSysObjects WHERE Type=1 AND Flags=0 ORDER BY name",
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES ORDER BY TABLE_NAME",
        ];

        foreach ($requetes as $sql) {
            try {
                foreach ($pdo->query($sql) as $ligne) {
                    $nom = (string) reset($ligne);
                    if ($nom !== '' && !str_starts_with($nom, 'MSys')) {
                        $tables[$nom] = [];
                    }
                }
            } catch (\Throwable) {
                // Requete non supportee par ce pilote : on essaie la suivante.
            }

            if ($tables !== []) {
                break;
            }
        }

        foreach (array_keys($tables) as $table) {
            try {
                $colonnes = [];
                foreach ($pdo->query('SELECT * FROM [' . $table . ']') as $ligne) {
                    $colonnes = array_keys($ligne);
                    break;
                }
                if ($colonnes === []) {
                    $colonnes = $this->colonnesViaMeta($pdo, $table);
                }
                $tables[$table] = $colonnes;
            } catch (\Throwable $e) {
                $tables[$table] = ['(lecture impossible : ' . $e->getMessage() . ')'];
            }
        }

        return [
            'connecte' => true,
            'message' => $tables === []
                ? 'Connexion reussie mais aucune table visible : le fichier Access est peut-etre vide ou protege par mot de passe.'
                : 'Connexion reussie : ' . count($tables) . ' table(s) detectee(s).',
            'tables' => $tables,
        ];
    }

    /**
     * Controle complet de la chaine d'import : fichier, tables, volumes,
     * plage de dates reellement presente et correspondance des badges.
     *
     * C'est ce rapport qui permet de comprendre pourquoi un import ne remonte
     * aucune ligne alors que la pointeuse contient des passages.
     *
     * @return array<string, mixed>
     */
    public function verifierSource(): array
    {
        $config = $this->configuration();
        $rapport = [
            'os' => PHP_OS_FAMILY . ' / PHP ' . PHP_VERSION,
            'drivers' => $this->driversDisponibles(),
            'fichier' => $this->etatFichier(),
            'dsn' => $config['ZKTECO_ACCESS_DSN'],
            'connexion' => false,
            'message' => '',
            'tables' => [],
            'volumes' => [],
            'plage' => [],
            'requete' => [],
            'echantillon' => [],
        ];

        try {
            $pdo = $this->connecter();
        } catch (\RuntimeException $e) {
            $rapport['message'] = $e->getMessage();

            return $rapport;
        }

        $rapport['connexion'] = true;

        $tablePointages = $this->nomSur($config['ZKTECO_TABLE_POINTAGES']);
        $tableUsers = $this->nomSur($config['ZKTECO_TABLE_USERS']);
        $colQuand = $config['ZKTECO_COL_CHECKTIME'];

        foreach ([$tableUsers => 'employes', $tablePointages => 'pointages'] as $table => $libelle) {
            try {
                $total = (int) $pdo->query('SELECT COUNT(*) FROM [' . $table . ']')->fetchColumn();
                $rapport['volumes'][$libelle] = ['table' => $table, 'lignes' => $total];
            } catch (\Throwable $e) {
                $rapport['volumes'][$libelle] = ['table' => $table, 'erreur' => $e->getMessage()];
            }
        }

        try {
            $bornes = $pdo->query('SELECT MIN([' . $colQuand . ']) AS debut, MAX([' . $colQuand . ']) AS fin FROM [' . $tablePointages . ']')->fetch();
            if ($bornes) {
                $rapport['plage'] = [
                    'debut' => (string) ($bornes['debut'] ?? ''),
                    'fin' => (string) ($bornes['fin'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            $rapport['plage'] = ['erreur' => $e->getMessage()];
        }

        try {
            $sql = 'SELECT TOP 5 [' . $config['ZKTECO_COL_BADGE'] . '] AS badge, [' . $config['ZKTECO_COL_NOM'] . '] AS nom FROM [' . $tableUsers . ']';
            $rapport['echantillon'] = $pdo->query($sql)->fetchAll();
        } catch (\Throwable $e) {
            $rapport['echantillon'] = [['erreur' => $e->getMessage()]];
        }

        $nomRequete = trim($config['ZKTECO_REQUETE']);
        if ($nomRequete !== '') {
            try {
                $total = (int) $pdo->query('SELECT COUNT(*) FROM [' . $nomRequete . ']')->fetchColumn();
                $rapport['requete'] = ['nom' => $nomRequete, 'lignes' => $total];
            } catch (\Throwable $e) {
                $rapport['requete'] = ['nom' => $nomRequete, 'erreur' => $e->getMessage()];
            }
        }

        $rapport['message'] = 'Connexion reussie.';

        return $rapport;
    }

    /**
     * Pointages bruts sur une periode.
     *
     * Deux sources possibles, dans cet ordre :
     *   1. la requete enregistree dans la base Access (par defaut
     *      « Req_UserCheck »), c'est celle que l'equipe RH utilise deja ;
     *   2. les tables USERINFO et CHECKINOUT jointes sur USERID.
     *
     * Si la requete ne renvoie aucune ligne sur la periode demandee, on lit
     * les tables : c'est le cas le plus frequent quand la requete enregistree
     * filtre sur une autre date (aujourd'hui, par exemple).
     *
     * @return list<array{badge: string, nom: string, quand: \DateTimeImmutable, sens: string}>
     */
    public function pointages(\DateTimeInterface $du, \DateTimeInterface $au, bool $ignorerRequete = false): array
    {
        $this->journal = [];
        $this->methodeFiltre = '';
        $pdo = $this->connecter();
        $config = $this->configuration();

        $requete = trim($config['ZKTECO_REQUETE']);
        if ($requete !== '' && !$ignorerRequete) {
            $passages = $this->pointagesViaRequete($pdo, $requete, $du, $au);
            if ($passages !== null) {
                $this->journal[] = 'Requete ' . $requete . ' : ' . count($passages) . ' passage(s) sur la periode.';
                if ($passages !== []) {
                    $this->source = 'requete ' . $requete;

                    return $passages;
                }
                $this->journal[] = 'Requete ' . $requete . ' vide sur cette periode : lecture directe des tables.';
            } else {
                $this->journal[] = 'Requete ' . $requete . ' illisible ou sans colonne de date : lecture directe des tables.';
            }
        }

        $this->source = 'tables ' . $config['ZKTECO_TABLE_USERS'] . ' + ' . $config['ZKTECO_TABLE_POINTAGES'];

        $passages = $this->pointagesViaTables($pdo, $config, $du, $au);
        $this->journal[] = 'Tables : ' . count($passages) . ' passage(s) sur la periode (filtre : ' . ($this->methodeFiltre ?: 'inconnu') . ').';

        return $passages;
    }

    /**
     * Lecture de la requete enregistree (Req_UserCheck).
     *
     * Renvoie null si la requete n'existe pas ou n'est pas lisible par le
     * pilote ODBC : l'appelant bascule alors sur les tables.
     *
     * @return list<array{badge: string, nom: string, quand: \DateTimeImmutable, sens: string}>|null
     */
    private function pointagesViaRequete(\PDO $pdo, string $requete, \DateTimeInterface $du, \DateTimeInterface $au): ?array
    {
        $colonnes = $this->colonnesDe($pdo, $requete);
        if ($colonnes === []) {
            return null;
        }

        $colBadge = $this->choisir($colonnes, ['badgenumber', 'badge', 'badgenumber2', 'code', 'matricule', 'userid', 'user_id', 'id']);
        $colNom = $this->choisir($colonnes, ['name', 'nom', 'employe', 'employee', 'fullname', 'nom_complet']);
        $colQuand = $this->choisir($colonnes, ['checktime', 'datetime', 'dateheure', 'horodatage', 'date', 'jour']);
        $colSens = $this->choisir($colonnes, ['checktype', 'sens', 'inout', 'type']);

        if ($colBadge === null || $colQuand === null) {
            return null;
        }

        $lignes = $this->lignesPeriode($pdo, $requete, $colonnes, $colQuand, $du, $au);
        if ($lignes === null) {
            return null;
        }

        // Certaines requetes renvoient la date et l'heure dans deux colonnes.
        $colHeure = $colonnes[$colQuand]['normalise'] === 'date'
            ? $this->choisir($colonnes, ['checktimeonly', 'time', 'heure'])
            : null;

        $passages = [];
        foreach ($lignes as $ligne) {
            $quand = $this->versDate($ligne[$colonnes[$colQuand]['nom']] ?? null);

            if ($quand === null && $colHeure !== null) {
                $quand = $this->versDate(
                    (string) ($ligne[$colonnes[$colQuand]['nom']] ?? '') . ' '
                    . (string) ($ligne[$colonnes[$colHeure]['nom']] ?? '')
                );
            }

            if ($quand === null) {
                continue;
            }

            $passages[] = [
                'badge' => trim((string) ($ligne[$colonnes[$colBadge]['nom']] ?? '')),
                'nom' => $colNom !== null ? trim((string) ($ligne[$colonnes[$colNom]['nom']] ?? '')) : '',
                'quand' => $quand,
                'sens' => $colSens !== null ? trim((string) ($ligne[$colonnes[$colSens]['nom']] ?? '')) : '',
            ];
        }

        return $passages;
    }

    /**
     * Lecture directe des tables USERINFO / CHECKINOUT.
     *
     * @param array<string, string> $config
     *
     * @return list<array{badge: string, nom: string, quand: \DateTimeImmutable, sens: string}>
     */
    private function pointagesViaTables(\PDO $pdo, array $config, \DateTimeInterface $du, \DateTimeInterface $au): array
    {
        $tablePointages = $this->nomSur($config['ZKTECO_TABLE_POINTAGES']);
        $tableUsers = $this->nomSur($config['ZKTECO_TABLE_USERS']);

        $colUser = $config['ZKTECO_COL_USERID'];
        $colBadge = $config['ZKTECO_COL_BADGE'];
        $colNom = $config['ZKTECO_COL_NOM'];
        $colQuand = $config['ZKTECO_COL_CHECKTIME'];
        $colSens = $config['ZKTECO_COL_CHECKTYPE'];

        $utilisateurs = [];
        try {
            $sql = 'SELECT [' . $colUser . '] AS uid, [' . $colBadge . '] AS badge, [' . $colNom . '] AS nom FROM [' . $tableUsers . ']';
            foreach ($pdo->query($sql) as $ligne) {
                $utilisateurs[(string) $ligne['uid']] = [
                    'badge' => trim((string) $ligne['badge']),
                    'nom' => trim((string) $ligne['nom']),
                ];
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Lecture de la table des employes « ' . $tableUsers . ' » impossible : ' . $e->getMessage()
                . ' Verifiez les colonnes ' . $colUser . ', ' . $colBadge . ', ' . $colNom
                . ' (commande --diagnostic pour la liste reelle).'
            );
        }

        if ($utilisateurs === []) {
            $this->journal[] = 'Table ' . $tableUsers . ' vide : les badges ne pourront pas etre traduits en noms, mais les passages restent lisibles par USERID.';
        }

        $colonnes = [
            ['nom' => $colQuand, 'normalise' => $this->normaliser($colQuand)],
        ];

        $lignes = $this->lignesPeriode($pdo, $tablePointages, $colonnes, 0, $du, $au, $colUser, $colSens);
        if ($lignes === null) {
            throw new \RuntimeException(
                'Lecture de la table des pointages « ' . $tablePointages . ' » impossible.'
                . ' Verifiez les colonnes ' . $colUser . ', ' . $colQuand . ', ' . $colSens
                . ' (commande --diagnostic pour la liste reelle).'
            );
        }

        $resultat = [];
        foreach ($lignes as $ligne) {
            $uid = (string) ($ligne['uid'] ?? '');
            $quand = $this->versDate($ligne['quand'] ?? null);
            if ($quand === null) {
                continue;
            }

            $resultat[] = [
                'badge' => $utilisateurs[$uid]['badge'] ?? $uid,
                'nom' => $utilisateurs[$uid]['nom'] ?? '',
                'quand' => $quand,
                'sens' => $colSens !== null ? (string) ($ligne['sens'] ?? '') : '',
            ];
        }

        return $resultat;
    }

    /**
     * Execute la requete de periode sur une source (requete ou table).
     *
     * Access accepte les dates entre #...# ; d'autres pilotes (MDBTools) ne
     * les comprennent pas et comparent la date a du texte. On essaie donc
     * plusieurs ecritures, puis, en dernier recours, on lit la table et on
     * filtre en PHP : c'est ce qui evite un import vide alors que la base
     * contient bien des passages.
     *
     * @param list<array{nom: string, normalise: string}> $colonnes
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function lignesPeriode(
        \PDO $pdo,
        string $source,
        array $colonnes,
        int $indexDate,
        \DateTimeInterface $du,
        \DateTimeInterface $au,
        ?string $colUser = null,
        ?string $colSens = null,
    ): ?array {
        $nomDate = $colonnes[$indexDate]['nom'];
        $debut = $du->format('Y-m-d') . ' 00:00:00';
        $fin = $au->format('Y-m-d') . ' 23:59:59';

        $select = $colUser !== null
            ? '[' . $colUser . '] AS uid, [' . $nomDate . '] AS quand' . ($colSens !== null ? ', [' . $colSens . '] AS sens' : '')
            : '*';

        $conditions = [
            'dates Access' => '[' . $nomDate . '] >= #' . $debut . '# AND [' . $nomDate . '] <= #' . $fin . '#',
            'dates texte' => "[" . $nomDate . "] >= '" . $debut . "' AND [" . $nomDate . "] <= '" . $fin . "'",
            'CDate' => "[" . $nomDate . "] >= CDate('" . $debut . "') AND [" . $nomDate . "] <= CDate('" . $fin . "')",
        ];

        $derniereErreur = null;
        foreach ($conditions as $libelle => $condition) {
            $sql = 'SELECT ' . $select . ' FROM [' . $source . '] WHERE ' . $condition . ' ORDER BY [' . $nomDate . '] ASC';
            try {
                $lignes = $pdo->query($sql)->fetchAll();
                $this->methodeFiltre = $libelle;
                $this->journal[] = $source . ' : filtre ' . $libelle . ' -> ' . count($lignes) . ' ligne(s).';

                // Un resultat vide peut venir d'une comparaison mal comprise
                // par le pilote : on verifie une fois sans filtre avant de
                // conclure qu'il n'y a rien.
                if ($lignes !== []) {
                    return $lignes;
                }

                $verification = $this->lignesSansFiltre($pdo, $source, $select, $nomDate, $du, $au, $colUser !== null);
                if ($verification !== []) {
                    $this->methodeFiltre = 'lecture complete filtree en PHP';
                    $this->journal[] = $source . ' : le filtre ' . $libelle . ' ne remontait rien, '
                        . count($verification) . ' ligne(s) trouvee(s) en filtrant en PHP.';

                    return $verification;
                }

                return [];
            } catch (\Throwable $e) {
                $derniereErreur = $e;
            }
        }

        $verification = $this->lignesSansFiltre($pdo, $source, $select, $nomDate, $du, $au, $colUser !== null);
        if ($verification !== []) {
            $this->methodeFiltre = 'lecture complete filtree en PHP';

            return $verification;
        }

        if ($derniereErreur !== null) {
            $this->journal[] = $source . ' : aucune ecriture de date acceptee (' . $derniereErreur->getMessage() . ').';

            return null;
        }

        return [];
    }

    /**
     * Lecture de secours : la source entiere (plafonnee), filtree en PHP.
     *
     * @return array<int, array<string, mixed>>
     */
    private function lignesSansFiltre(
        \PDO $pdo,
        string $source,
        string $select,
        string $nomDate,
        \DateTimeInterface $du,
        \DateTimeInterface $au,
        bool $dateAliasee,
    ): array {
        $colonneLue = $dateAliasee ? 'quand' : $nomDate;
        $debut = \DateTimeImmutable::createFromInterface($du)->setTime(0, 0, 0);
        $fin = \DateTimeImmutable::createFromInterface($au)->setTime(23, 59, 59);

        $sqls = [
            'SELECT TOP ' . self::PLAFOND_LECTURE . ' ' . $select . ' FROM [' . $source . '] ORDER BY [' . $nomDate . '] DESC',
            'SELECT ' . $select . ' FROM [' . $source . ']',
        ];

        foreach ($sqls as $sql) {
            try {
                $lignes = $pdo->query($sql)->fetchAll();
            } catch (\Throwable) {
                continue;
            }

            $retenues = [];
            foreach ($lignes as $ligne) {
                $quand = $this->versDate($ligne[$colonneLue] ?? $ligne[$nomDate] ?? null);
                if ($quand === null) {
                    continue;
                }
                if ($quand >= $debut && $quand <= $fin) {
                    $retenues[] = $ligne;
                }
            }

            return $retenues;
        }

        return [];
    }

    /**
     * Colonnes reellement renvoyees par une source (requete ou table).
     *
     * @return list<array{nom: string, normalise: string}>
     */
    public function colonnesDe(\PDO $pdo, string $source): array
    {
        $colonnes = [];

        try {
            $requete = $pdo->query('SELECT * FROM [' . $this->nomSur($source) . '] WHERE 1=0');
            for ($i = 0; $i < $requete->columnCount(); ++$i) {
                $meta = $requete->getColumnMeta($i);
                if (is_array($meta) && isset($meta['name'])) {
                    $nom = (string) $meta['name'];
                    $colonnes[] = ['nom' => $nom, 'normalise' => $this->normaliser($nom)];
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $colonnes;
    }

    /**
     * Indice de la premiere colonne dont le nom figure dans la liste donnee.
     *
     * @param list<array{nom: string, normalise: string}> $colonnes
     * @param list<string> $candidats
     */
    private function choisir(array $colonnes, array $candidats): ?int
    {
        foreach ($candidats as $candidat) {
            foreach ($colonnes as $indice => $colonne) {
                if ($colonne['normalise'] === $candidat) {
                    return $indice;
                }
            }
        }

        return null;
    }

    /**
     * Controle de la requete enregistree : nom, colonnes renvoyees, message.
     *
     * @return array{nom: string, colonnes: list<string>, message: string}
     */
    public function diagnosticRequete(): array
    {
        $nom = trim($this->configuration()['ZKTECO_REQUETE']);
        if ($nom === '') {
            return ['nom' => '', 'colonnes' => [], 'message' => 'Aucune requete configuree (variable ZKTECO_REQUETE vide).'];
        }

        try {
            $pdo = $this->connecter();
        } catch (\RuntimeException $e) {
            return ['nom' => $nom, 'colonnes' => [], 'message' => $e->getMessage()];
        }

        $colonnes = $this->colonnesDe($pdo, $nom);
        if ($colonnes === []) {
            return [
                'nom' => $nom,
                'colonnes' => [],
                'message' => 'La requete « ' . $nom . ' » n\'est pas lisible par le pilote ODBC'
                    . ' (certains pilotes ne voient que les tables). L\'import utilisera alors les tables USERINFO et CHECKINOUT.',
            ];
        }

        return [
            'nom' => $nom,
            'colonnes' => array_map(static fn (array $c) => $c['nom'], $colonnes),
            'message' => 'Requete « ' . $nom . ' » lisible : ' . count($colonnes) . ' colonne(s).',
        ];
    }

    /**
     * Repli sans pilote Access : fichier CSV exporte depuis le logiciel ZKTECO.
     * Colonnes reconnues (entete, insensible a la casse) : badge/badgenumber/
     * userid, nom/name, datetime/checktime/date+time.
     *
     * @return list<array{badge: string, nom: string, quand: \DateTimeImmutable, sens: string}>
     */
    public function pointagesDepuisCsv(string $fichier): array
    {
        if (!is_file($fichier)) {
            throw new \RuntimeException('Le fichier CSV « ' . $fichier . ' » est introuvable.');
        }

        $contenu = (string) @file_get_contents($fichier);
        if (trim($contenu) === '') {
            throw new \RuntimeException('Le fichier CSV « ' . $fichier . ' » est vide.');
        }

        $contenu = str_replace("\xEF\xBB\xBF", '', $contenu);
        $lignes = preg_split("/\r\n|\n|\r/", trim($contenu)) ?: [];
        $separateur = substr_count($lignes[0], ';') >= substr_count($lignes[0], ',') ? ';' : ',';

        $entetes = array_map(fn ($v) => $this->normaliser((string) $v), str_getcsv($lignes[0], $separateur));
        $index = [];
        foreach ($entetes as $position => $entete) {
            $index[$entete] = $position;
        }

        $trouver = function (array $candidats) use ($index): ?int {
            foreach ($candidats as $candidat) {
                if (isset($index[$candidat])) {
                    return $index[$candidat];
                }
            }

            return null;
        };

        $iBadge = $trouver(['badge', 'badgenumber', 'userid', 'matricule', 'code']);
        $iNom = $trouver(['nom', 'name', 'employe', 'employee']);
        $iDate = $trouver(['datetime', 'checktime', 'dateheure', 'horodatage']);
        $iJour = $trouver(['date', 'jour']);
        $iHeure = $trouver(['time', 'heure', 'checktimeonly']);
        $iSens = $trouver(['checktype', 'sens', 'type']);

        if ($iBadge === null || ($iDate === null && ($iJour === null || $iHeure === null))) {
            throw new \RuntimeException(
                'Colonnes CSV non reconnues. Entetes attendus : badge (ou Badgenumber / USERID) et'
                . ' datetime (ou date + time). Entetes lus : ' . implode(', ', $entetes) . '.'
            );
        }

        $resultat = [];
        foreach (array_slice($lignes, 1) as $numero => $ligne) {
            if (trim($ligne) === '') {
                continue;
            }

            $champs = str_getcsv($ligne, $separateur);
            $valeurDate = $iDate !== null ? (string) ($champs[$iDate] ?? '') : '';
            if ($valeurDate === '') {
                $valeurDate = trim((string) ($champs[$iJour] ?? '') . ' ' . (string) ($champs[$iHeure] ?? ''));
            }

            $quand = $this->versDate($valeurDate);
            if ($quand === null) {
                continue;
            }

            $badge = trim((string) ($champs[$iBadge] ?? ''));
            if ($badge === '') {
                continue;
            }

            $resultat[] = [
                'badge' => $badge,
                'nom' => $iNom !== null ? trim((string) ($champs[$iNom] ?? '')) : '',
                'quand' => $quand,
                'sens' => $iSens !== null ? trim((string) ($champs[$iSens] ?? '')) : '',
            ];
        }

        usort($resultat, static fn (array $a, array $b) => $a['quand'] <=> $b['quand']);

        return $resultat;
    }

    /** Intervalle [debut, fin] de la journee de travail, en minutes depuis minuit. */
    public function bornesJournee(): array
    {
        return [$this->minutes($this->heureArrivee()), $this->minutes($this->heureDepart())];
    }

    private function minutes(string $heure): int
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $heure, $m) !== 1) {
            return 0;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    private function versDate(mixed $valeur): ?\DateTimeImmutable
    {
        if ($valeur instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($valeur);
        }

        $texte = trim((string) $valeur);
        if ($texte === '') {
            return null;
        }

        // Date Access avec millisecondes ou fraction de seconde.
        $texte = preg_replace('/\.\d+$/', '', $texte) ?? $texte;

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d', 'd/m/Y', 'm/d/Y H:i:s a', 'm/d/Y H:i:s'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $texte);
            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        try {
            return new \DateTimeImmutable($texte);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normaliser(string $texte): string
    {
        $texte = strtolower(trim($texte));

        return preg_replace('/[^a-z0-9]/', '', $texte) ?? $texte;
    }

    /** Entoure un nom de table de crochets (syntaxe Access). */
    private function nomSur(string $table): string
    {
        return str_replace(['[', ']'], '', trim($table));
    }

    /** @return list<string> */
    private function colonnesViaMeta(\PDO $pdo, string $table): array
    {
        $colonnes = [];
        try {
            $requete = $pdo->query('SELECT * FROM [' . $table . '] WHERE 1=0');
            for ($i = 0; $i < $requete->columnCount(); ++$i) {
                $meta = $requete->getColumnMeta($i);
                if (is_array($meta) && isset($meta['name'])) {
                    $colonnes[] = (string) $meta['name'];
                }
            }
        } catch (\Throwable) {
            // Inventaire indisponible : le diagnostic l'indiquera.
        }

        return array_values(array_unique($colonnes));
    }

    private function valeur(string $cle, string $defaut): string
    {
        if (isset($this->surcharges[$cle])) {
            return trim($this->surcharges[$cle]);
        }

        // Parametres enregistres depuis la page « Connexion ZKTECO » : ils
        // priment sur le fichier .env, sans avoir a le modifier sur le serveur.
        $correspondance = [
            'ZKTECO_ACCESS_FICHIER' => 'fichier',
            'ZKTECO_ACCESS_DSN' => 'dsn',
        ];
        if (isset($correspondance[$cle])) {
            $enregistre = (new ZktecoParametres())->valeur($correspondance[$cle]);
            if ($enregistre !== null) {
                return $enregistre;
            }
        }

        $valeur = $_ENV[$cle] ?? $_SERVER[$cle] ?? getenv($cle);

        if ($valeur === false || $valeur === null || trim((string) $valeur) === '') {
            return $defaut;
        }

        return trim((string) $valeur);
    }
}
