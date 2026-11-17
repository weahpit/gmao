<?php

namespace App\Service\Stock;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Lecture d'un fichier tabulaire (.xlsx, .xls, .csv) envoye par l'utilisateur.
 *
 * - .xlsx : lecture native (ZIP + XML OOXML) sans dependance externe.
 * - .csv / .txt : detection du separateur (; , tab) et de l'encodage (UTF-8 / Windows-1252).
 * - .xls  : format binaire BIFF : l'extension est acceptee mais le contenu ne peut
 *           pas etre decode sans bibliotheque dediee -> message explicite demandant
 *           d'enregistrer le fichier en .xlsx ou .csv.
 *
 * Le resultat est toujours un tableau de lignes :
 *   ['lignes' => [ [ 'colonne' => valeur, ... ], ... ], 'entetes' => [...], 'format' => 'xlsx'|'csv', 'nbFeuilles' => n]
 */
final class LecteurTabulaireService
{
    /** Taille maximale acceptee (10 Mo). */
    public const TAILLE_MAX = 10 * 1024 * 1024;

    public const EXTENSIONS = ['xlsx', 'xls', 'csv', 'txt'];

    /**
     * Lit le fichier et renvoie la premiere feuille (ou la feuille demandee).
     *
     * @return array{entetes: string[], lignes: array<int, array<string, string>>, format: string, feuille: string, nbFeuilles: int}
     *
     * @throws \RuntimeException message lisible par l'utilisateur
     */
    public function lire(UploadedFile $fichier, ?string $feuilleDemandee = null): array
    {
        if (!$fichier->isValid()) {
            throw new \RuntimeException('Le fichier n\'a pas pu etre recu completement (fichier trop volumineux ou envoi interrompu).');
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
        if ($contenu === '') {
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
            $brut[] = str_getcsv($ligne, $separateur, '"', '\\');
        }

        [$entetes, $lignes] = $this->structurer($brut);

        return [
            'entetes'    => $entetes,
            'lignes'     => $lignes,
            'format'     => 'csv',
            'feuille'    => 'CSV',
            'nbFeuilles' => 1,
        ];
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

            $brut = $this->lignesXml($xml, $partagees);
            [$entetes, $lignes] = $this->structurer($brut);

            return [
                'entetes'    => $entetes,
                'lignes'     => $lignes,
                'format'     => 'xlsx',
                'feuille'    => $cible['nom'],
                'nbFeuilles' => count($feuilles),
            ];
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
     * Transforme le XML d'une feuille en lignes de valeurs (indexees par lettre de colonne).
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
            $auto = 0;
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $colonne = $ref !== '' ? preg_replace('/\d+/', '', $ref) : $this->lettre(++$auto);
                if ($ref === '') {
                    $auto++;
                }
                $type = (string) $c['t'];
                $valeur = '';

                if ($type === 'inlineStr') {
                    $valeur = (string) ($c->is->t ?? '');
                } elseif (isset($c->v)) {
                    $brut = (string) $c->v;
                    if ($type === 's') {
                        $valeur = $partagees[(int) $brut] ?? '';
                    } elseif ($type === 'b') {
                        $valeur = $brut === '1' ? '1' : '0';
                    } else {
                        $valeur = $this->nombreLisible($brut);
                    }
                }

                $ligne[$colonne] = trim($valeur);
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
     * Determine la ligne d'en-tete et renvoie des lignes associatives
     * (indexees par le libelle de colonne, en minuscules sans accents).
     *
     * @param array<int, array<string, string>> $brut
     *
     * @return array{0: string[], 1: array<int, array<string, string>>}
     */
    private function structurer(array $brut): array
    {
        // On ignore les lignes vides en tete (certains exports commencent par une ligne vide).
        $indexEntete = null;
        foreach ($brut as $i => $ligne) {
            if (count(array_filter($ligne, static fn ($v) => trim((string) $v) !== '')) >= 2) {
                $indexEntete = $i;
                break;
            }
        }
        if ($indexEntete === null) {
            throw new \RuntimeException('Le fichier ne contient aucune ligne exploitable.');
        }

        $entete = $brut[$indexEntete];
        $entetes = [];
        foreach ($entete as $colonne => $libelle) {
            $entetes[$colonne] = trim((string) $libelle);
        }

        $lignes = [];
        foreach (array_slice($brut, $indexEntete + 1) as $ligne) {
            $vide = true;
            foreach ($ligne as $v) {
                if (trim((string) $v) !== '') {
                    $vide = false;
                    break;
                }
            }
            if ($vide) {
                continue;
            }
            $lignes[] = $ligne;
        }

        return [array_values($entetes), $lignes];
    }
}
