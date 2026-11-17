<?php

namespace App\Service\Stock;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Lecture d'un fichier tabulaire (.xlsx, .xls, .csv) envoye par l'utilisateur.
 *
 * - .xlsx : lecture native (ZIP + XML OOXML) sans dependance externe.
 * - .csv / .txt : detection du separateur (; , tab |) et de l'encodage (UTF-8 / Windows-1252).
 * - .xls  : format binaire BIFF : l'extension est acceptee mais le contenu ne peut
 *           pas etre decode sans bibliotheque dediee -> message explicite demandant
 *           d'enregistrer le fichier en .xlsx ou .csv.
 *
 * Les cellules sont renvoyees indexees par leur ADRESSE ("A2", "B2"...) et non par
 * une lettre seule : c'est indispensable, car une ligne Excel peut sauter des
 * colonnes (cellules vides) ou etre decalee. L'en-tete retenue peut etre n'importe
 * quelle ligne du fichier (un modele comporte souvent un titre au-dessus).
 *
 * Le resultat est toujours de la forme :
 *   [
 *     'entetes'         => ['A' => 'Code', 'B' => 'Date', ...],
 *     'ligneEntete'     => 1,
 *     'lignes'          => [ ['A' => 'valeur', 'B' => 'valeur', ...], ... ],
 *     'apercu'          => [ lignes brutes de tete, pour le diagnostic ],
 *     'format'          => 'xlsx'|'csv',
 *     'feuille'         => 'Feuil1',
 *     'nbFeuilles'      => 1,
 *   ]
 */
final class LecteurTabulaireService
{
    /** Taille maximale acceptee (10 Mo). */
    public const TAILLE_MAX = 10 * 1024 * 1024;

    public const EXTENSIONS = ['xlsx', 'xls', 'csv', 'txt'];

    /** Nombre de lignes envoyees au navigateur pour le diagnostic d'en-tete. */
    private const LIGNES_APERCU = 12;

    /**
     * Lit le fichier et renvoie la feuille demandee (la premiere par defaut).
     *
     * @return array{entetes: array<string,string>, ligneEntete: int, lignes: array<int, array<string,string>>, apercu: array<int, array<string,string>>, format: string, feuille: string, nbFeuilles: int}
     *
     * @throws \RuntimeException message lisible par l'utilisateur
     */
    public function lire(UploadedFile $fichier, ?string $feuilleDemandee = null): array
    {
        if (!$fichier->isValid()) {
            throw new \RuntimeException('Le fichier n\'a pas pu etre recu completement (fichier trop volumineux ou envoi interrompu). Verifiez aussi upload_max_filesize et post_max_size sur le serveur.');
        }

        if ($fichier->getSize() !== null && $fichier->getSize() > self::TAILLE_MAX) {
            throw new \RuntimeException('Le fichier depasse la taille maximale autorisee (10 Mo).');
        }

        $chemin = $fichier->getPathname();
        $extension = strtolower((string) $fichier->getClientOriginalExtension());

        // Certains navigateurs envoient une extension vide : on regarde la signature du fichier.
        if ($extension === '') {
            $signature = (string) @file_get_contents($chemin, false, null, 0, 4);
            $extension = str_starts_with($signature, "PK") ? 'xlsx' : 'csv';
        }

        if (!in_array($extension, self::EXTENSIONS, true)) {
            throw new \RuntimeException(sprintf(
                'Format non accepte (.%s). Utilisez un fichier .xlsx, .xls ou .csv.',
                $extension
            ));
        }

        if ($extension === 'xls') {
            // Certains fichiers .xls sont en realite des .xlsx (ou des CSV) mal nommes.
            $signature = (string) @file_get_contents($chemin, false, null, 0, 8);
            if (str_starts_with($signature, "PK")) {
                $extension = 'xlsx';
            } elseif (str_contains($signature, ';') || str_contains($signature, ',')) {
                $extension = 'csv';
            } else {
                throw new \RuntimeException(
                    'Le format Excel 97-2003 (.xls) n\'est pas lu par le serveur. '
                    . 'Enregistrez le fichier en .xlsx (Excel : Fichier > Enregistrer sous) ou en .csv, puis relancez l\'import.'
                );
            }
        }

        return $extension === 'xlsx'
            ? $this->lireXlsx($chemin, $feuilleDemandee)
            : $this->lireCsv($chemin);
    }

    /* ------------------------------------------------------------------
     * CSV
     * ------------------------------------------------------------------ */

    private function lireCsv(string $chemin): array
    {
        $contenu = (string) @file_get_contents($chemin);
        if (trim($contenu) === '') {
            throw new \RuntimeException('Le fichier est vide.');
        }

        // Encodage : on suppose UTF-8, sinon Windows-1252 (Excel francais).
        if (!mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }
        $contenu = str_replace(["\xEF\xBB\xBF", "\r\n", "\r"], ['', "\n", "\n"], $contenu);

        $premiere = explode("\n", $contenu)[0] ?? '';
        $separateur = $this->devinerSeparateur($premiere);

        $brut = [];
        foreach (explode("\n", $contenu) as $ligne) {
            if (trim($ligne) === '') {
                continue;
            }
            $cellules = str_getcsv($ligne, $separateur, '"', '\\');
            $adresse = [];
            $index = 0;
            foreach ($cellules as $cellule) {
                $adresse[$this->lettre(++$index)] = trim((string) $cellule);
            }
            $brut[] = $adresse;
        }

        return $this->structurer($brut, 'csv', 'CSV', 1);
    }

    private function devinerSeparateur(string $ligne): string
    {
        $scores = [
            ';'  => substr_count($ligne, ';'),
            ','  => substr_count($ligne, ','),
            "\t" => substr_count($ligne, "\t"),
            '|'  => substr_count($ligne, '|'),
        ];
        arsort($scores);
        $meilleur = array_key_first($scores);

        return ($scores[$meilleur] ?? 0) > 0 ? $meilleur : ';';
    }

    /* ------------------------------------------------------------------
     * XLSX (lecture native OOXML)
     * ------------------------------------------------------------------ */

    private function lireXlsx(string $chemin, ?string $feuilleDemandee): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($chemin) !== true) {
            throw new \RuntimeException('Le fichier .xlsx est illisible (archive endommagee).');
        }

        try {
            $partagees = $this->chainesPartagees($zip);
            $feuilles = $this->listeFeuilles($zip);
            if ($feuilles === []) {
                throw new \RuntimeException('Aucune feuille trouvee dans le classeur.');
            }

            $cible = $feuilles[0];
            if ($feuilleDemandee !== null && $feuilleDemandee !== '') {
                foreach ($feuilles as $f) {
                    if (mb_strtolower($f['nom']) === mb_strtolower($feuilleDemandee)) {
                        $cible = $f;
                        break;
                    }
                }
            }

            $xml = $zip->getFromName($cible['chemin']);
            if ($xml === false) {
                throw new \RuntimeException('La feuille "' . $cible['nom'] . '" n\'a pas pu etre lue.');
            }

            // ".rels" de la feuille : permet de resoudre les textes riches (sharedStrings).
            $brut = $this->lignesXml($xml, $partagees);

            return $this->structurer($brut, 'xlsx', $cible['nom'], count($feuilles));
        } finally {
            $zip->close();
        }
    }

    /** @return array<int, string> */
    private function chainesPartagees(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $doc = $this->xml($xml);
        $liste = [];
        foreach ($doc->si as $si) {
            // Un <si> peut contenir plusieurs <r><t> (texte enrichi).
            $texte = '';
            if (isset($si->t)) {
                $texte .= (string) $si->t;
            }
            foreach ($si->r as $r) {
                $texte .= (string) $r->t;
            }
            $liste[] = $texte;
        }

        return $liste;
    }

    /** @return array<int, array{nom: string, chemin: string}> */
    private function listeFeuilles(\ZipArchive $zip): array
    {
        $feuilles = [];
        $rels = [];

        $xmlRels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($xmlRels !== false) {
            foreach ($this->xml($xmlRels)->Relationship as $rel) {
                $cible = (string) $rel['Target'];
                $cible = ltrim(str_replace('\\', '/', $cible), '/');
                if (!str_starts_with($cible, 'xl/')) {
                    $cible = 'xl/' . $cible;
                }
                $rels[(string) $rel['Id']] = $cible;
            }
        }

        $xmlWorkbook = $zip->getFromName('xl/workbook.xml');
        if ($xmlWorkbook !== false) {
            $docWorkbook = $this->xml($xmlWorkbook);
            foreach ($docWorkbook->sheets->sheet ?? [] as $sheet) {
                $rid = (string) ($sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');
                $feuilles[] = [
                    'nom'    => (string) $sheet['name'],
                    'chemin' => $rels[$rid] ?? 'xl/worksheets/sheet' . (count($feuilles) + 1) . '.xml',
                ];
            }
        }

        if ($feuilles === []) {
            $feuilles[] = ['nom' => 'Feuil1', 'chemin' => 'xl/worksheets/sheet1.xml'];
        }

        return $feuilles;
    }

    /**
     * Transforme le XML d'une feuille en lignes de valeurs indexees par ADRESSE ("A1", "B1").
     *
     * @param array<int, string> $partagees
     *
     * @return array<int, array<string, string>>
     */
    private function lignesXml(string $xml, array $partagees): array
    {
        $doc = $this->xml($xml);
        $lignes = [];

        foreach ($doc->sheetData->row ?? [] as $row) {
            $ligne = [];
            $indexColonne = 0;

            foreach ($row->c as $c) {
                $ref = strtoupper((string) $c['r']);
                if ($ref !== '') {
                    $colonne = (string) (preg_replace('/[0-9]+/', '', $ref) ?: $this->lettre(++$indexColonne));
                    $indexColonne = $this->indexColonne($colonne);
                } else {
                    $colonne = $this->lettre(++$indexColonne);
                }

                $type = (string) $c['t'];
                $valeur = '';

                if ($type === 'inlineStr') {
                    $valeur = (string) ($c->is->t ?? '');
                    foreach ($c->is->r ?? [] as $r) {
                        $valeur .= (string) $r->t;
                    }
                } elseif (isset($c->v)) {
                    $brut = (string) $c->v;
                    if ($type === 's') {
                        $valeur = $partagees[(int) $brut] ?? '';
                    } elseif ($type === 'b') {
                        $valeur = $brut === '1' ? '1' : '0';
                    } elseif ($type === 'str' || $type === 'e' || $type === 'd') {
                        $valeur = $brut;
                    } else {
                        $valeur = $this->nombreLisible($brut);
                    }
                }

                $adresse = ($ref !== '' ? preg_replace('/[0-9]+/', '', $ref) : $colonne) . (string) $row['r'];
                $ligne[(string) $adresse] = trim($valeur);
            }

            if ($ligne !== []) {
                $lignes[] = $ligne;
            }
        }

        return $lignes;
    }

    /**
     * Excel stocke les dates en nombre de jours depuis le 30/12/1899 :
     * on renvoie la date au format AAAA-MM-JJ HH:MM pour que l'utilisateur
     * voie une vraie date dans l'apercu.
     */
    private function nombreLisible(string $brut): string
    {
        if (!is_numeric($brut)) {
            return $brut;
        }
        $nombre = (float) $brut;
        if ($nombre > 20000 && $nombre < 80000) {
            $jours = (int) floor($nombre);
            $fraction = $nombre - $jours;
            $date = (new \DateTimeImmutable('1899-12-30'))->modify('+' . $jours . ' days');
            $minutes = (int) round($fraction * 1440);
            $date = $date->modify('+' . $minutes . ' minutes');

            return $minutes > 0 ? $date->format('Y-m-d H:i') : $date->format('Y-m-d');
        }
        if ($nombre == (int) $nombre) {
            return (string) (int) $nombre;
        }

        return rtrim(rtrim(number_format($nombre, 4, '.', ''), '0'), '.');
    }

    private function lettre(int $index): string
    {
        $lettre = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $lettre = chr(65 + $mod) . $lettre;
            $index = intdiv($index - 1, 26);
        }

        return $lettre;
    }

    private function indexColonne(string $lettre): int
    {
        $n = 0;
        foreach (str_split($lettre) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n;
    }

    private function xml(string $contenu): \SimpleXMLElement
    {
        $precedent = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($contenu);
        libxml_use_internal_errors($precedent);
        if ($doc === false) {
            throw new \RuntimeException('Le contenu du fichier est illisible.');
        }

        return $doc;
    }

    /**
     * Determine la ligne d'en-tete (la plus vraisemblable) et renvoie les lignes
     * associatives dont les cles sont les ADRESSES de cellules de la ligne d'en-tete
     * ("A2", "B2", ...) : chaque valeur est retrouvee dans la meme colonne.
     *
     * @param array<int, array<string, string>> $brut
     *
     * @return array{entetes: array<string,string>, ligneEntete: int, lignes: array<int, array<string,string>>, apercu: array<int, array<string,string>>, format: string, feuille: string, nbFeuilles: int}
     */
    private function structurer(array $brut, string $format, string $feuille, int $nbFeuilles): array
    {
        // Lignes non vides uniquement (certains exports commencent par des lignes vides).
        $exploitables = [];
        foreach ($brut as $ligne) {
            $remplie = trim(implode('', $ligne)) !== '';
            if ($remplie) {
                $exploitables[] = $ligne;
            }
        }

        if ($exploitables === []) {
            throw new \RuntimeException('Le fichier ne contient aucune ligne exploitable.');
        }

        $indexEntete = $this->detecterEntete($exploitables);
        $ligneEntete = $exploitables[$indexEntete];

        $entetes = [];
        foreach ($ligneEntete as $adresse => $libelle) {
            $entetes[(string) $adresse] = trim((string) $libelle);
        }

        $lignes = [];
        foreach (array_slice($exploitables, $indexEntete + 1) as $ligne) {
            $colonne = [];
            foreach ($entetes as $adresse => $libelle) {
                $colonne[$adresse] = (string) ($ligne[$adresse] ?? '');
            }
            // On ignore les lignes completement vides au regard des colonnes retenues.
            if (trim(implode('', $colonne)) === '') {
                continue;
            }
            $lignes[] = $colonne;
        }

        return [
            'entetes'     => $entetes,
            'ligneEntete' => $indexEntete + 1,
            'lignes'      => $lignes,
            'apercu'      => array_slice($exploitables, 0, self::LIGNES_APERCU),
            'lignesBrutes' => $exploitables,
            'format'      => $format,
            'feuille'     => $feuille,
            'nbFeuilles'  => $nbFeuilles,
        ];
    }

    /**
     * Cherche la ligne qui ressemble le plus a une ligne d'en-tete :
     * celle qui contient le plus de libelles courts et non numeriques,
     * en privilegiant les premieres lignes du fichier.
     *
     * @param array<int, array<string, string>> $lignes
     */
    private function detecterEntete(array $lignes): int
    {
        $mots = ['code', 'date', 'site', 'produit', 'cooperative', 'immat', 'transporteur', 'sacs', 'poids', 'statut', 'observation', 'pnp', 'traitant', 'categorie'];

        $meilleur = 0;
        $meilleurScore = -1;

        $limite = min(count($lignes), 20);
        for ($i = 0; $i < $limite; $i++) {
            $score = 0;
            foreach ($lignes[$i] as $valeur) {
                $texte = $this->normaliserLibelle($valeur);
                if ($texte === '' || is_numeric($texte)) {
                    continue;
                }
                foreach ($mots as $mot) {
                    if (str_contains($texte, $mot)) {
                        $score += 3;
                        break;
                    }
                }
                if (mb_strlen($texte) <= 24) {
                    $score += 1;
                }
            }
            // A score egal, la ligne la plus haute gagne.
            if ($score > $meilleurScore) {
                $meilleurScore = $score;
                $meilleur = $i;
            }
        }

        return $meilleur;
    }

    private function normaliserLibelle(string $libelle): string
    {
        $libelle = mb_strtolower(trim($libelle));
        $libelle = strtr($libelle, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae',
        ]);

        return preg_replace('/[^a-z0-9]/', '', $libelle) ?? '';
    }
}
