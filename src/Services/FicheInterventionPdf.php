<?php
// src/Services/FicheInterventionPdf.php
//
// Generation PDF de la "Fiche d'intervention GMAO - CITRAC" avec FPDF.
// FPDF ne lit PAS de HTML : la mise en page du modele HTML est redessinee
// ici primitive par primitive (Rect / Line / Cell) pour un rendu identique.
//
// Utilisation (depuis un controleur) :
//   return new Response(
//       $service->generer($fiche),
//       200,
//       ['Content-Type' => 'application/pdf',
//        'Content-Disposition' => 'inline; filename="fiche_intervention.pdf"']
//   );

namespace App\Services;

use FPDF;

class FicheInterventionPdf extends Fpdf
{
    public string $logoPath = '';
    /** @var array<string,mixed> Donnees de la fiche (voir service::mapper) */
    public array $data = [];
    public int $total = 2;

    /** Utilisateur ayant lance l'impression (affiche en pied de page) */
    public string $impressionPar = '';
    /** Date/heure d'impression (figee pour etre identique sur les 2 pages) */
    public string $dateImpression = '';

    private array $societe = [
        'nom'      => 'CITRAC',
        'activite' => 'Compagnie Ivoirienne de Transformation du Cacao',
        'bp'       => '01 BP 1643 San Pedro 01',
        'tel'      => '+225 27 34 71 06 74',
        'email'    => 'infos@citrac.ci',
        'site'     => 'www.citrac.ci',
    ];

    private float $lm = 14.0;   // marge gauche
    private float $cw = 182.0;  // largeur contenu (210 - 2*14)

    // ========================================================================
    //  EN-TETE (bandeau marron + cartouche N°) redessine sur chaque page
    // ========================================================================
    public function Header(): void
    {
        $lm = $this->lm; $cw = $this->cw;

        // Bandeau marron
        $this->SetFillColor(95, 74, 45);
        $this->Rect($lm, 10, $cw, 24, 'F');

        // Pastille logo (image si dispo, sinon texte)
        $this->SetFillColor(200, 149, 58);
        $this->Rect($lm + 4, 14, 16, 10, 'F');
        if ($this->logoPath && is_file($this->logoPath)) {
            $this->Image($this->logoPath, $lm + 4, 14, 16, 10);
        } else {
            $this->SetFont('Arial', 'B', 8);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY($lm + 4, 20);
            $this->Cell(16, 4, 'CITRAC', 0, 0, 'C');
        }

        // Bloc societe
        $tx = $lm + 26;
        $this->SetXY($tx, 12.5);
        $this->SetFont('Arial', 'B', 13);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 5, $this->txt($this->societe['nom']), 0, 1);
        $this->SetX($tx);
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(212, 165, 75);
        $this->Cell(0, 3.2, $this->txt($this->societe['activite']), 0, 1);
        $this->SetTextColor(231, 217, 190);
        $this->SetX($tx);
        $this->Cell(0, 3.2, $this->txt($this->societe['bp']), 0, 1);
        $this->SetX($tx);
        $this->Cell(0, 3.2, $this->txt('Tel : '.$this->societe['tel'].'   -   '.$this->societe['email']), 0, 1);
        $this->SetX($tx);
        $this->Cell(0, 3.2, $this->txt($this->societe['site']), 0, 1);

        // Cartouche N° de fiche
        $bx = $lm + $cw - 51;
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(212, 165, 75);
        $this->SetLineWidth(0.3);
        $this->Rect($bx, 14, 48, 16, 'FD');
        $this->SetXY($bx + 3, 16);
        $this->SetFont('Arial', 'B', 6.5);
        $this->SetTextColor(125, 110, 90);
        $this->Cell(42, 3, $this->txt('N° DE FICHE'), 0, 1);
        $this->SetXY($bx + 3, 21);
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(200, 30, 30); // N° de fiche (valeur) en rouge
        $ref = (string)($this->data['reference'] ?? 'INT - 2026 - _______');
        $this->Cell(42, 6, $this->txt($ref), 0, 0);

        // Titre + regle + numero de page
        $this->SetXY($lm, 41);
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(62, 48, 35);
        $this->Cell($cw * 0.7, 8, $this->txt("FICHE D'INTERVENTION"), 0, 0, 'L');
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(200, 149, 58);
        $this->Cell($cw * 0.3, 8, $this->txt('MAINTENANCE / GMAO'), 0, 1, 'R');

        $this->SetDrawColor(200, 149, 58);
        $this->SetLineWidth(0.5);
        $this->Line($lm, 50.5, $lm + $cw, 50.5);

        $this->SetXY($lm, 51);
        $this->SetFont('Arial', '', 6.5);
        $this->SetTextColor(168, 152, 128);
        $this->Cell($cw, 3, 'Page '.$this->PageNo().'/'.$this->total, 0, 1, 'R');

        $this->SetY(56);
    }

    public function Footer(): void
    {
        $this->SetDrawColor(234, 223, 203);
        $this->SetLineWidth(0.3);
        $this->Line($this->lm, 287, $this->lm + $this->cw, 287);
        $this->SetXY($this->lm, 288);
        $this->SetFont('Arial', '', 6.5);
        $this->SetTextColor(168, 152, 128);
        $this->Cell($this->cw / 2, 4, $this->txt("CITRAC - GMAO  |  Fiche d'intervention"), 0, 0, 'L');
        $this->Cell($this->cw / 2, 4, $this->txt('Document a conserver dans le dossier equipement'), 0, 1, 'R');

        // Mention d'impression : utilisateur + date
        $date = $this->dateImpression !== '' ? $this->dateImpression : date('d/m/Y') . ' à ' . date('H:i');
        $mention = ($this->impressionPar !== '' ? 'Imprime par ' . $this->impressionPar . ' ' : '') . 'le ' . $date;
        $this->SetX($this->lm);
        $this->SetFont('Arial', 'I', 6);
        $this->SetTextColor(185, 175, 160);
        $this->Cell($this->cw, 3.4, $this->txt($mention), 0, 0, 'C');
    }
// APPEND_HELPERS

    // ====================== OUTILS DE RENDU ================================

    /** Normalise une chaine (minuscule, sans accents) pour comparer les libelles */
    private function norm(?string $s): string
    {
        $s = (string) $s;
        $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return strtolower(trim($c !== false ? $c : $s));
    }

    private function eq(?string $a, string $b): bool
    {
        return $this->norm($a) === $this->norm($b);
    }

    /** Barre de section doree */
    private function sectionBar(string $texte): void
    {
        $this->Ln(2);
        $y = $this->GetY();
        $this->SetFillColor(200, 149, 58);
        $this->Rect($this->lm, $y, $this->cw, 5.2, 'F');
        $this->SetXY($this->lm + 2, $y + 0.5);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($this->cw - 4, 4.2, $this->txt($texte), 0, 1, 'L');
        $this->Ln(1.5);
    }

    /** Un champ "label + valeur soulignee" */
    private function field(float $x, float $y, float $w, string $label, string $value, bool $gold = false): void
    {
        $this->SetXY($x, $y);
        $this->SetFont('Arial', 'B', 7);
        $this->SetTextColor(125, 110, 90);
        $this->Cell($w, 3, $this->txt(strtoupper($label)), 0, 2);
        $this->SetXY($x, $y + 3.2);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(200, 30, 30); // valeurs en rouge
        $this->Cell($w, 4.5, $this->txt($value), 0, 0, 'L');
        if ($gold) {
            $this->SetDrawColor(200, 149, 58); $this->SetLineWidth(0.5);
        } else {
            $this->SetDrawColor(234, 223, 203); $this->SetLineWidth(0.3);
        }
        $this->Line($x, $y + 8, $x + $w, $y + 8);
    }

    /** @param array<int,array{0:string,1:string,2?:bool}> $fields */
    private function fieldRow(array $fields): void
    {
        $y = $this->GetY();
        $n = count($fields);
        $gap = 6.0;
        $w = ($this->cw - $gap * ($n - 1)) / $n;
        $x = $this->lm;
        foreach ($fields as $fl) {
            $this->field($x, $y, $w, $fl[0], (string) $fl[1], $fl[2] ?? false);
            $x += $w + $gap;
        }
        $this->SetY($y + 11);
    }

    /** Petite case a cocher (cochee = carre marron + check blanc) */
    private function drawCheck(float $x, float $y, bool $active): void
    {
        $s = 3.1;
        if ($active) {
            $this->SetFillColor(95, 74, 45);
            $this->SetDrawColor(95, 74, 45);
            $this->SetLineWidth(0.2);
            $this->Rect($x, $y, $s, $s, 'FD');
            $this->SetDrawColor(255, 255, 255);
            $this->SetLineWidth(0.45);
            $this->Line($x + 0.7, $y + 1.6, $x + 1.25, $y + 2.2);
            $this->Line($x + 1.25, $y + 2.2, $x + 2.5, $y + 0.8);
        } else {
            $this->SetFillColor(255, 255, 255);
            $this->SetDrawColor(154, 121, 74);
            $this->SetLineWidth(0.2);
            $this->Rect($x, $y, $s, $s, 'D');
        }
    }

    /**
     * Ligne "tag + cases a cocher" avec retour a la ligne automatique.
     * @param array<int,array{0:string,1:bool}> $items
     */
    private function checksRow(string $tag, array $items, float $tagW = 16.0): void
    {
        $y = $this->GetY();
        $this->SetFont('Arial', 'B', 7);
        $this->SetTextColor(125, 110, 90);
        $this->SetXY($this->lm, $y + 0.6);
        $this->Cell($tagW, 4, $this->txt(strtoupper($tag)), 0, 0);

        $startX = $this->lm + $tagW + 2;
        $x = $startX;
        $cy = $y;
        $lineH = 5.0;
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(62, 48, 35);
        foreach ($items as $it) {
            $tw = $this->GetStringWidth($this->txt($it[0]));
            $itemW = 3.1 + 1.3 + $tw + 5;
            if ($x + $itemW > $this->lm + $this->cw) {
                $x = $startX;
                $cy += $lineH;
            }
            $this->drawCheck($x, $cy + 0.4, $it[1]);
            $this->SetXY($x + 3.1 + 1.3, $cy);
            $this->Cell($tw + 1, 4, $this->txt($it[0]), 0, 0);
            $x += $itemW;
        }
        $this->SetY($cy + $lineH + 0.5);
    }

    /** Simple label (petites majuscules) sur une ligne */
    private function fieldLabel(string $label): void
    {
        $this->SetX($this->lm);
        $this->SetFont('Arial', 'B', 7);
        $this->SetTextColor(125, 110, 90);
        $this->Cell($this->cw, 3.4, $this->txt(strtoupper($label)), 0, 1);
    }

    /** Zone lignee (cadre + lignes horizontales + texte optionnel) */
    private function area(float $h, string $text): void
    {
        $x = $this->lm; $y = $this->GetY(); $w = $this->cw;
        $this->SetDrawColor(234, 223, 203); $this->SetLineWidth(0.3);
        $this->Rect($x, $y, $w, $h, 'D');
        $this->SetDrawColor(242, 234, 219); $this->SetLineWidth(0.2);
        for ($ly = $y + 4; $ly < $y + $h - 0.5; $ly += 4) {
            $this->Line($x + 1, $ly, $x + $w - 1, $ly);
        }
        if ($text !== '') {
            $this->SetXY($x + 2, $y + 0.8);
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(200, 30, 30); // valeurs en rouge
            $this->MultiCell($w - 4, 4, $this->txt($text), 0, 'L');
        }
        $this->SetY($y + $h);
    }

    /** Tableau pieces & consommables
     *  @param array<int,array<int,string>> $rows */
    private function piecesTable(array $rows, int $minRows = 4): void
    {
        $w = [$this->cw*0.22, $this->cw*0.42, $this->cw*0.12, $this->cw*0.12, $this->cw*0.12];
        $head = ['Reference', 'Designation', 'Qte', 'Cout unit.', 'Montant'];
        $this->SetX($this->lm);
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetFillColor(125, 94, 58);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(125, 94, 58);
        $this->SetLineWidth(0.2);
        foreach ($head as $i => $h) {
            $this->Cell($w[$i], 6.2, $this->txt($h), 1, 0, 'L', true);
        }
        $this->Ln();
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(200, 30, 30); // valeurs en rouge
        $this->SetDrawColor(234, 223, 203);
        $count = max(count($rows), $minRows);
        for ($r = 0; $r < $count; $r++) {
            $even = ($r % 2 === 1);
            if ($even) { $this->SetFillColor(250, 248, 244); } else { $this->SetFillColor(255, 255, 255); }
            $this->SetX($this->lm);
            $cells = $rows[$r] ?? ['', '', '', '', ''];
            foreach ($w as $i => $cwi) {
                $align = $i >= 2 ? 'R' : 'L';
                $this->Cell($cwi, 6.2, $this->txt((string)($cells[$i] ?? '')), 1, 0, $align, true);
            }
            $this->Ln();
        }
    }

    /** Trois cartouches de signature */
    private function signatures(): void
    {
        $this->Ln(3);
        $y = $this->GetY();
        $gap = 6.0;
        $w = ($this->cw - 2 * $gap) / 3;
        $roles = [
            ['LE TECHNICIEN', (string)($this->data['technicien'] ?? '') ?: 'Nom & signature'],
            ['RESP. MAINTENANCE', 'Nom & signature'],
            ['LE DEMANDEUR', 'Reception des travaux'],
        ];
        $x = $this->lm;
        foreach ($roles as $r) {
            $this->SetFillColor(253, 244, 227);
            $this->SetDrawColor(234, 223, 203);
            $this->SetLineWidth(0.3);
            $this->Rect($x, $y, $w, 20, 'FD');
            $this->SetXY($x + 2, $y + 2);
            $this->SetFont('Arial', 'B', 7.5);
            $this->SetTextColor(95, 74, 45);
            $this->Cell($w - 4, 4, $this->txt($r[0]), 0, 2);
            $this->SetXY($x + 2, $y + 6);
            $this->SetFont('Arial', '', 6.5);
            $this->SetTextColor(168, 152, 128);
            $this->Cell($w - 4, 3, $this->txt($r[1]), 0, 0);
            $x += $w + $gap;
        }
        $this->SetY($y + 20);
    }

    // ========================= CONSTRUCTION DU DOCUMENT ===================
    public function build(): string
    {
        $f = $this->data;
        if ($this->dateImpression === '') {
            $this->dateImpression = date('d/m/Y') . ' à ' . date('H:i');
        }
        $this->SetMargins($this->lm, 10, $this->lm);
        $this->SetAutoPageBreak(false);

        // ======================= PAGE 1 =======================
        $this->AddPage();

        // A. IDENTIFICATION
        $this->sectionBar('A.  IDENTIFICATION');
        $this->fieldRow([
            ["Date / heure d'emission", (string)($f['dateEmission'] ?? '')],
            ['Demande par', (string)($f['demandePar'] ?? '')],
        ]);
        $this->checksRow('Type', [
            ['Corrective',    $this->eq($f['type'] ?? '', 'Corrective')],
            ['Preventive',    $this->eq($f['type'] ?? '', 'Preventive')],
            ['Ameliorative',  $this->eq($f['type'] ?? '', 'Ameliorative')],
            ['Reglementaire', $this->eq($f['type'] ?? '', 'Reglementaire')],
        ]);
        $this->checksRow('Priorite', [
            ['Urgente', $this->eq($f['priorite'] ?? '', 'Urgente')],
            ['Haute',   $this->eq($f['priorite'] ?? '', 'Haute')],
            ['Normale', $this->eq($f['priorite'] ?? '', 'Normale')],
            ['Basse',   $this->eq($f['priorite'] ?? '', 'Basse')],
        ]);
        $this->checksRow('Statut', [
            ['Ouverte',        $this->eq($f['statut'] ?? '', 'Ouverte')],
            ['Planifiee',      $this->eq($f['statut'] ?? '', 'Planifiee')],
            ['En cours',       $this->eq($f['statut'] ?? '', 'En cours')],
            ['Attente pieces', $this->eq($f['statut'] ?? '', 'Attente pieces')],
            ['Terminee',       $this->eq($f['statut'] ?? '', 'Terminee')],
            ['Cloturee',       $this->eq($f['statut'] ?? '', 'Cloturee')],
        ]);
        $alerte = (string)($f['alerteNumero'] ?? '');
        $this->checksRow('Origine', [
            ['Alerte / anomalie'.($alerte !== '' ? ' N° '.$alerte : ' N° : ____'), $this->eq($f['origine'] ?? '', 'Alerte')],
            ["Demande d'intervention", $this->eq($f['origine'] ?? '', 'Demande')],
            ['Plan preventif',          $this->eq($f['origine'] ?? '', 'Preventif')],
        ]);

        // B. LOCALISATION & EQUIPEMENT
        $this->sectionBar('B.  LOCALISATION & EQUIPEMENT');
        $this->fieldRow([
            ['Equipement (type)', (string)($f['equipement'] ?? '')],
            ['Code equipement',   (string)($f['codeEquipement'] ?? '')],
        ]);
        $this->fieldRow([
            ["Zone d'exploitation", (string)($f['zone'] ?? '')],
            ['Emplacement precis',  (string)($f['emplacement'] ?? '')],
        ]);
        $this->fieldRow([
            ['Demandeur (nom)',    (string)($f['demandeur'] ?? '')],
            ['Service / Direction', (string)($f['service'] ?? '')],
        ]);

        // C. DESCRIPTION DU PROBLEME
        $this->sectionBar('C.  DESCRIPTION DU PROBLEME');
        $this->fieldRow([
            ['Sujet / symptome constate', (string)($f['sujet'] ?? '')],
        ]);
        $this->fieldLabel('Description detaillee');
        $yA = $this->GetY();
        $this->area(18.0, (string)($f['description'] ?? ''));
        // note photo
        $hasPhoto = (bool)($f['hasPhoto'] ?? false);
        $this->SetXY($this->lm, $yA + 18.0 - 4.2);
        $this->SetFont('Arial', '', 6.8);
        $this->SetTextColor(168, 152, 128);
        $this->Cell($this->cw - 2, 3, $this->txt('Photo(s) jointe(s) :  Oui ['.($hasPhoto ? 'X' : ' ').']   Non ['.($hasPhoto ? ' ' : 'X').']'), 0, 1, 'R');

        // D. DIAGNOSTIC
        $this->sectionBar('D.  DIAGNOSTIC');
        $this->fieldLabel('Cause identifiee (analyse de cause racine)');
        $this->area(12.0, (string)($f['cause'] ?? ''));
        $this->Ln(1.5);
        $this->checksRow('Nature', [
            ['Mecanique',   $this->eq($f['nature'] ?? '', 'Mecanique')],
            ['Electrique',  $this->eq($f['nature'] ?? '', 'Electrique')],
            ['Hydraulique', $this->eq($f['nature'] ?? '', 'Hydraulique')],
            ['Pneumatique', $this->eq($f['nature'] ?? '', 'Pneumatique')],
            ['Autre',       $this->eq($f['nature'] ?? '', 'Autre')],
        ]);
        $arret = $f['arretMachine'] ?? null;
        $this->checksRow('Arret machine', [
            ['Oui', $arret === true],
            ['Non', $arret === false],
        ], 26.0);
        $this->fieldRow([
            ["Duree d'immobilisation", (string)($f['dureeImmobilisation'] ?? '')],
        ]);

        // ======================= PAGE 2 =======================
        $this->AddPage();

        // E. REALISATION DES TRAVAUX
        $this->sectionBar('E.  REALISATION DES TRAVAUX');
        $this->fieldRow([
            ['Technicien(s) / equipe',    (string)($f['technicien'] ?? '')],
            ['Sous-traitant (si externe)', (string)($f['sousTraitant'] ?? '')],
        ]);
        $this->fieldRow([
            ['Debut planifie', (string)($f['datePlanifieeDebut'] ?? '')],
            ['Fin planifiee',  (string)($f['datePlanifieeFin'] ?? '')],
            ['Debut reel',     (string)($f['dateDebutReel'] ?? '')],
            ['Fin reelle',     (string)($f['dateFinReel'] ?? '')],
        ]);
        $this->fieldLabel('Travaux effectues');
        $this->area(24.0, (string)($f['travauxEffectues'] ?? ''));

        // F. PIECES & CONSOMMABLES
        $this->sectionBar('F.  PIECES & CONSOMMABLES');
        $this->piecesTable($f['pieces'] ?? [], 4);

        // G. COUTS (FCFA)
        $this->sectionBar('G.  COUTS (FCFA)');
        $this->fieldRow([
            ["Main-d'oeuvre",  (string)($f['coutMainOeuvre'] ?? '')],
            ['Pieces',         (string)($f['coutPieces'] ?? '')],
            ['Sous-traitance', (string)($f['coutSousTraitance'] ?? '')],
            ['Total',          (string)($f['coutTotal'] ?? ''), true],
        ]);

        // H. CLOTURE & VALIDATION
        $this->sectionBar('H.  CLOTURE & VALIDATION');
        $this->checksRow('Resultat', [
            ['Resolu',               $this->eq($f['resultat'] ?? '', 'Resolu')],
            ['Partiellement resolu', $this->eq($f['resultat'] ?? '', 'Partiellement resolu')],
            ['Non resolu',           $this->eq($f['resultat'] ?? '', 'Non resolu')],
        ], 18.0);
        $remis = $f['remisEnService'] ?? null;
        $this->checksRow('Remis en service', [
            ['Oui', $remis === true],
            ['Non', $remis === false],
        ], 30.0);
        $this->fieldRow([
            ['Date de remise en service', (string)($f['dateRemiseService'] ?? '')],
        ]);
        $this->fieldLabel('Recommandations / preventif a prevoir');
        $this->area(12.0, (string)($f['recommandations'] ?? ''));

        $this->signatures();

        return $this->Output('S');
    }

    // ========================= NETTOYAGE TEXTE (UTF-8 -> ISO-8859-1) ======
    public function txt(?string $texte): string
    {
        $texte = (string) $texte;
        $texte = strip_tags($texte);
        $texte = html_entity_decode($texte, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texte = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texte);
        $converti = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $texte);
        return $converti !== false ? $converti : $texte;
    }
}
