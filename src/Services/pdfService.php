<?php
// src/Service/PdfTableService.php

namespace App\Services;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use FPDF;

/**
 * Sous-classe FPDF : l'en-tête société et le pied de page
 * sont dessinés automatiquement à chaque page.
 */
class CitracPdf extends Fpdf
{
    public string $logoPath = '';
    public string $titreDocument = '';
    public string $sousTitre = '';

    // Coordonnées de l'entreprise — À COMPLÉTER avec tes vraies infos
    private array $societe = [
        'nom'       => 'CITRAC',
        'activite'  => 'Compagnie Ivoirienne de Transformation du Cacao', // exemple à adapter
        'adresse'   => 'Adresse, Ville, Pays',
        'bp'   => '01 BP 1643 San Pedro 01',
        'telephone' => '+225 27 34 71 06 74',
        'email'     => 'infos@citrac.ci',
        'site'      => 'www.citrac.ci',
    ];

    public function Header(): void
    {
        // Logo à gauche
        if ($this->logoPath && is_file($this->logoPath)) {
            // (fichier, x, y, largeur en mm) — hauteur auto
            $this->Image($this->logoPath, 10, 8, 28);
        }

        // Bloc d'informations société à droite du logo
        /*$this->SetXY(45, 10);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(90, 55, 30); // marron, cohérent avec le logo
        $this->Cell(0, 6, $this->txt($this->societe['nom']), 0, 1, 'L');*/

        $this->SetXY(45, 10);
        $this->SetFont('Arial', '', 6);
        $this->SetTextColor(70, 70, 70);
        $this->Cell(0, 3, $this->txt($this->societe['activite']), 0, 1, 'L');

        $this->SetX(45);
        $this->Cell(0, 3, $this->txt($this->societe['bp']), 0, 1, 'L');

        $this->SetX(45);
        $this->Cell(0, 3, $this->txt($this->societe['adresse']), 0, 1, 'L');

        $this->SetX(45);
        $this->Cell(0, 3, $this->txt(
            'Tél : ' . $this->societe['telephone'] . '   -   ' . $this->societe['email']
        ), 0, 1, 'L');

        $this->SetX(45);
        $this->Cell(0, 3, $this->txt($this->societe['site']), 0, 1, 'L');

        // Ligne de séparation
        $this->SetDrawColor(90, 55, 30);
        $this->SetLineWidth(0.4);
        $this->Line(10, 32, $this->GetPageWidth() - 10, 32);

        // Titre du document, centré sous la ligne
        $this->SetY(36);
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 9, $this->txt($this->titreDocument), 0, 1, 'C');

        // Sous-titre (nom de l'équipement) sous le titre
        if ($this->sousTitre !== '') {
            $this->SetFont('Arial', 'B', 11);
            $this->SetTextColor(90, 55, 30); // marron
            $this->Cell(0, 6, $this->txt($this->sousTitre), 0, 1, 'C');
        }


        $this->SetFont('Arial', '', 6);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 5, $this->txt('Généré le ' . date('d/m/Y à H:i')), 0, 1, 'C');
        $this->Ln(3);
    }

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 6);
        $this->SetTextColor(120, 120, 120);
        // Numéro de page : « Page X/Y »
        $this->Cell(0, 10, $this->txt('Page ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
    }

    public function txt(?string $texte): string
    {
        $texte = (string) $texte;

        // 1. Retirer les balises HTML éventuelles (<br>, <b>, etc.)
        $texte = strip_tags($texte);

        // 2. Décoder les entités HTML (&eacute; &amp; ...)
        $texte = html_entity_decode($texte, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 3. Supprimer les caractères de contrôle invisibles
        $texte = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texte);

        // 4. UTF-8 -> ISO-8859-1 : translière ce qui peut l'être,
        //    ignore (supprime) ce qui n'est pas convertible.
        $converti = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $texte);

        return $converti !== false ? $converti : $texte;
    }

    /**
     * Calcule le nombre de lignes qu'occupera une chaine dans une cellule
     * de largeur $w (en mm), avec la police courante. Permet de dessiner
     * des lignes de tableau a hauteur variable (retour a la ligne auto).
     */
    public function NbLines(float $w, ?string $txt): int
    {
        $cw = $this->CurrentFont['cw'] ?? [];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', (string) $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] === "\n") {
            $nb--;
        }
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c === ' ') { $sep = $i; }
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep === -1) {
                    if ($i == $j) { $i++; }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else {
                $i++;
            }
        }
        return max(1, $nl);
    }
}

/**
 * Service appelé depuis tes contrôleurs.
 */
class pdfService
{
    private CitracPdf $pdf;

    public function __construct(
        // Symfony injecte le chemin absolu du dossier public
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {}

    public function genererTableau(
        string $titre,
        array $entetes,
        array $largeurs,
        array $lignes,
        string $orientation = 'P',
        string $sousTitre = ''
    ): string {
        $this->pdf = new CitracPdf($orientation, 'mm', 'A4');
        $this->pdf->logoPath      = $this->projectDir . '/public/images/CITRAC.png';
        $this->pdf->titreDocument = $titre;
        $this->pdf->sousTitre     = $sousTitre;

        $this->pdf->AliasNbPages();     // active {nb} = nombre total de pages
        $this->pdf->SetTitle($titre);
        // Marge haute suffisante pour laisser la place à l'en-tête
        $this->pdf->SetMargins(10, 48, 10);
        $this->pdf->SetAutoPageBreak(true, 20);
        $this->pdf->AddPage();

        $this->ecrireEntete($entetes, $largeurs);
        $this->ecrireLignes($entetes, $largeurs, $lignes);

        return $this->pdf->Output('S');
    }

    private function ecrireEntete(array $entetes, array $largeurs): void
    {
        $this->pdf->SetFont('Arial', 'B', 10);
        $this->pdf->SetFillColor(90, 55, 30);   // marron
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->SetDrawColor(200, 200, 200);

        foreach ($entetes as $i => $libelle) {
            $this->pdf->Cell($largeurs[$i], 8, $this->pdf->txt($libelle), 1, 0, 'C', true);
        }
        $this->pdf->Ln();
    }

    private function ecrireLignes(array $entetes, array $largeurs, array $lignes): void
    {
        $this->pdf->SetFont('Arial', '', 9);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetDrawColor(200, 200, 200);

        $hauteurLigne = 5; // hauteur d'UNE ligne de texte (mm)
        $alterne = false;

        foreach ($lignes as $ligne) {
            $cellules = array_values($ligne);

            // 1) Nombre de lignes max necessaire sur toute la rangee
            $nbLignesMax = 1;
            foreach ($cellules as $i => $valeur) {
                $largeur = $largeurs[$i] ?? 20;
                $n = $this->pdf->NbLines($largeur, $this->pdf->txt((string) $valeur));
                if ($n > $nbLignesMax) {
                    $nbLignesMax = $n;
                }
            }
            $hauteur = $nbLignesMax * $hauteurLigne;

            // 2) Saut de page si la rangee ne tient pas
            if ($this->pdf->GetY() + $hauteur > ($this->pdf->GetPageHeight() - 20)) {
                $this->pdf->AddPage();
                $this->ecrireEntete($entetes, $largeurs);
                $this->pdf->SetFont('Arial', '', 9);
                $this->pdf->SetTextColor(0, 0, 0);
                $this->pdf->SetDrawColor(200, 200, 200);
            }

            // 3) Dessin de la rangee : cadre + fond a hauteur fixe, texte en MultiCell
            $this->pdf->SetFillColor(245, 240, 235);
            $x0 = $this->pdf->GetX();
            $y0 = $this->pdf->GetY();
            $x  = $x0;

            foreach ($cellules as $i => $valeur) {
                $largeur = $largeurs[$i] ?? 20;
                // 'DF' = cadre + remplissage (lignes paires), 'D' = cadre seul
                $this->pdf->Rect($x, $y0, $largeur, $hauteur, $alterne ? 'DF' : 'D');
                $this->pdf->SetXY($x, $y0);
                $this->pdf->MultiCell($largeur, $hauteurLigne, $this->pdf->txt((string) $valeur), 0, 'L', false);
                $x += $largeur;
            }

            // 4) Repositionnement pour la rangee suivante
            $this->pdf->SetXY($x0, $y0 + $hauteur);
            $alterne = !$alterne;
        }
    }

    /**
     * Génère un BON DE SORTIE PDF (une page) avec QR code unique,
     * les informations de la sortie, le tableau des articles et
     * les blocs d'affiliation (Service Magasinage / Direction Générale).
     *
     * @param array $infos  reference, date, service, demandeur, etabli_par
     * @param array $lignes chaque ligne = [code, designation, qte, stock_avant, stock_apres, mention]
     * @param string $qrData contenu (unique) encodé dans le QR code
     */
    public function genererBonSortie(array $infos, array $lignes, string $qrData): string
    {
        $this->pdf = new CitracPdf('P', 'mm', 'A4');
        $this->pdf->logoPath      = $this->projectDir . '/public/images/CITRAC.png';
        $this->pdf->titreDocument = 'BON DE SORTIE';
        $this->pdf->sousTitre     = 'N° ' . ($infos['reference'] ?? '');

        $this->pdf->AliasNbPages();
        $this->pdf->SetTitle('Bon de sortie ' . ($infos['reference'] ?? ''));
        $this->pdf->SetMargins(10, 48, 10);
        $this->pdf->SetAutoPageBreak(true, 35);
        $this->pdf->AddPage();

        $pdf = $this->pdf;

        // --- QR code (en haut à droite) ---
        $qrFile = $this->genererQrImage($qrData);
        if ($qrFile) {
            $pdf->Image($qrFile, $pdf->GetPageWidth() - 42, 50, 30, 30, 'PNG');
            @unlink($qrFile);
            $pdf->SetFont('Arial', '', 6);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->SetXY($pdf->GetPageWidth() - 42, 80);
            $pdf->Cell(30, 4, $pdf->txt('Scanner à la guérite pour valider'), 0, 0, 'C');
        }

        // --- Bloc : informations de la sortie ---
        $pdf->SetXY(10, 52);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(90, 55, 30);
        $pdf->Cell(0, 6, $pdf->txt('Informations de la sortie'), 0, 1, 'L');
        $pdf->SetDrawColor(90, 55, 30);
        $pdf->SetLineWidth(0.3);
        $pdf->Line(10, $pdf->GetY(), 120, $pdf->GetY());
        $pdf->Ln(2);

        $infoLines = [
            ['Référence du bon', $infos['reference'] ?? ''],
            ['Date de sortie',   $infos['date'] ?? ''],
            ['Service demandeur',$infos['service'] ?? ''],
            ['Demandeur',        $infos['demandeur'] ?? ''],
            ['Établi par',       $infos['etabli_par'] ?? ''],
        ];
        foreach ($infoLines as $il) {
            $pdf->SetX(10);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetTextColor(70, 70, 70);
            $pdf->Cell(45, 5, $pdf->txt($il[0] . ' :'), 0, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(70, 5, $pdf->txt((string) $il[1]), 0, 1, 'L');
        }
        $pdf->Ln(2);

        // --- Badge de statut (EN ATTENTE par défaut, VALIDÉ après scan vigil) ---
        $statut = strtoupper((string) ($infos['statut'] ?? 'EN_ATTENTE'));
        if ($statut === 'VALIDE') {
            $libelleStatut = 'SORTIE VALIDÉE';
            $rgb = [34, 139, 34];   // vert
        } elseif ($statut === 'ANNULE') {
            $libelleStatut = 'BON ANNULÉ';
            $rgb = [176, 32, 32];   // rouge
        } else {
            $libelleStatut = 'EN ATTENTE DE VALIDATION';
            $rgb = [200, 120, 0];   // orange
        }
        $pdf->SetX(10);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(70, 6, $pdf->txt('Statut : ' . $libelleStatut), 0, 1, 'C', true);
        if ($statut === 'VALIDE') {
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(70, 70, 70);
            $pdf->SetX(10);
            $detailValidation = 'Validé par ' . (string) ($infos['valide_par'] ?? '');
            if (!empty($infos['valide_at'])) { $detailValidation .= ' le ' . $infos['valide_at']; }
            $pdf->Cell(0, 5, $pdf->txt($detailValidation), 0, 1, 'L');
        }
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(4);

        // --- Tableau des articles ---
        $entetes  = ['Code', 'Désignation', 'Qté', 'Stock avant', 'Stock après', 'Mention'];
        $largeurs = [25, 55, 15, 25, 25, 45]; // total = 190 mm

        $this->ecrireEnteteBon($entetes, $largeurs);

        $pdf->SetFont('Arial', '', 8.5);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(200, 200, 200);
        $hauteurLigne = 5;
        $alterne = false;

        foreach ($lignes as $ligne) {
            $cellules = array_values($ligne);
            $nbLignesMax = 1;
            foreach ($cellules as $i => $valeur) {
                $largeur = $largeurs[$i] ?? 20;
                $n = $pdf->NbLines($largeur, $pdf->txt((string) $valeur));
                if ($n > $nbLignesMax) { $nbLignesMax = $n; }
            }
            $hauteur = $nbLignesMax * $hauteurLigne;

            if ($pdf->GetY() + $hauteur > ($pdf->GetPageHeight() - 35)) {
                $pdf->AddPage();
                $this->ecrireEnteteBon($entetes, $largeurs);
                $pdf->SetFont('Arial', '', 8.5);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetDrawColor(200, 200, 200);
            }

            $pdf->SetFillColor(245, 240, 235);
            $x0 = $pdf->GetX();
            $y0 = $pdf->GetY();
            $x  = $x0;
            foreach ($cellules as $i => $valeur) {
                $largeur = $largeurs[$i] ?? 20;
                $pdf->Rect($x, $y0, $largeur, $hauteur, $alterne ? 'DF' : 'D');
                $pdf->SetXY($x, $y0);
                $pdf->MultiCell($largeur, $hauteurLigne, $pdf->txt((string) $valeur), 0, 'L', false);
                $x += $largeur;
            }
            $pdf->SetXY($x0, $y0 + $hauteur);
            $alterne = !$alterne;
        }

        // --- Blocs d'affiliation / signatures ---
        $pdf->Ln(14);
        if ($pdf->GetY() > $pdf->GetPageHeight() - 55) {
            $pdf->AddPage();
            $pdf->Ln(10);
        }

        $colW = 90;
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(90, 55, 30);
        $pdf->SetX(10);
        $pdf->Cell($colW, 6, $pdf->txt('Service Magasinage'), 0, 0, 'C');
        $pdf->SetX(110);
        $pdf->Cell($colW, 6, $pdf->txt('Direction Générale'), 0, 1, 'C');

        $pdf->Ln(18);
        $yl = $pdf->GetY();
        $pdf->SetDrawColor(120, 120, 120);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(25, $yl, 85, $yl);
        $pdf->Line(125, $yl, 185, $yl);

        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetXY(10, $yl + 1);
        $pdf->Cell($colW, 5, $pdf->txt('(Nom, signature et cachet)'), 0, 0, 'C');
        $pdf->SetX(110);
        $pdf->Cell($colW, 5, $pdf->txt('(Nom, signature et cachet)'), 0, 1, 'C');

        // --- Contrôle sortie à la guérite (validation par le vigil via scan QR) ---
        $pdf->Ln(10);
        if ($pdf->GetY() > $pdf->GetPageHeight() - 30) {
            $pdf->AddPage();
            $pdf->Ln(6);
        }
        $statut = strtoupper((string) ($infos['statut'] ?? 'EN_ATTENTE'));
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(90, 55, 30);
        $pdf->SetX(10);
        $pdf->Cell(0, 6, $pdf->txt('Contrôle sortie — Guérite'), 0, 1, 'L');
        $pdf->SetDrawColor(90, 55, 30);
        $pdf->SetLineWidth(0.3);
        $pdf->Line(10, $pdf->GetY(), 90, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->SetTextColor(70, 70, 70);
        $pdf->SetX(10);
        if ($statut === 'VALIDE') {
            $ligneVigil = 'Sortie validée par le vigil : ' . (string) ($infos['valide_par'] ?? '');
            if (!empty($infos['valide_at'])) { $ligneVigil .= ' le ' . $infos['valide_at']; }
            $pdf->MultiCell(0, 5, $pdf->txt($ligneVigil), 0, 'L');
        } else {
            $pdf->MultiCell(0, 5, $pdf->txt(
                'Ce bon n\'est pas encore validé. À la guérite, le vigil scanne le QR code '
                . 'ci-dessus avec l\'application mobile pour autoriser la sortie des articles.'
            ), 0, 'L');
        }

        return $pdf->Output('S');
    }

    private function ecrireEnteteBon(array $entetes, array $largeurs): void
    {
        $this->pdf->SetFont('Arial', 'B', 9);
        $this->pdf->SetFillColor(90, 55, 30);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->SetDrawColor(200, 200, 200);
        $this->pdf->SetX(10);
        foreach ($entetes as $i => $libelle) {
            $this->pdf->Cell($largeurs[$i], 7, $this->pdf->txt($libelle), 1, 0, 'C', true);
        }
        $this->pdf->Ln();
    }

    /**
     * Génère l'image PNG du QR code dans un fichier temporaire et retourne son chemin.
     * Utilise endroid/qr-code s'il est installé ; sinon retourne null (le bon
     * reste généré, sans l'image). Installez : composer require endroid/qr-code:^5
     */
    private function genererQrImage(string $data): ?string
    {
        try {
            if (class_exists('Endroid\\QrCode\\QrCode')
                && class_exists('Endroid\\QrCode\\Writer\\PngWriter')) {

                $writerClass = 'Endroid\\QrCode\\Writer\\PngWriter';
                $qrClass     = 'Endroid\\QrCode\\QrCode';

                /** @var object $writer */
                $writer = new $writerClass();
                /** @var object $qr */
                $qr = $qrClass::create($data)->setSize(300)->setMargin(10);
                $result = $writer->write($qr);

                $tmp = tempnam(sys_get_temp_dir(), 'qr_');
                if ($tmp === false) { return null; }
                $png = $tmp . '.png';
                file_put_contents($png, $result->getString());
                @unlink($tmp);
                return $png;
            }
        } catch (\Throwable $e) {
            // Librairie absente ou API différente -> pas d'image QR
        }
        return null;
    }
}
