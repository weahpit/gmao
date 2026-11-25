<?php

namespace App\Controller\Logistique;

use App\Entity\Logistique\Magasin;
use App\Entity\Logistique\Proprietaire;
use App\Entity\Production\Lot;
use App\Entity\Production\Produit;
use App\Repository\Logistique\MagasinRepository;
use App\Repository\Logistique\ProprietaireRepository;
use App\Repository\Production\LotRepository;
use App\Repository\Production\ProduitRepository;
use App\Service\Production\GestionLotService;
use App\Service\Stock\LecteurTabulaireService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * IMPORT DE LOTS DE PRODUCTION (fichier Excel / CSV).
 *
 *  - /logistique/ProductionLots/Import              : page + formulaire
 *  - /logistique/ProductionLots/Import/Executer     : traitement du fichier
 *  - /logistique/ProductionLots/Import/modele.csv   : modele a telecharger
 *  - /logistique/ProductionLots/Import/modele.xlsx  : modele Excel a telecharger
 *
 * Regles appliquees :
 *  - un numero de lot deja present en base n'est JAMAIS ecrase : la ligne est
 *    refusee et signalee comme "deja existant" ;
 *  - un numero present deux fois dans le fichier est refuse (doublon interne) ;
 *  - seules les lignes entierement valides sont enregistrees, via le meme
 *    service metier que l'ecran de saisie (GestionLotService) ;
 *  - un rapport ligne par ligne est affiche a la fin de l'import.
 */
#[Route('/logistique/ProductionLots/Import')]
final class ImportLots extends AbstractController
{
    private const MAX_LIGNES = 500;

    /** Libelles acceptes pour chaque colonne du fichier (normalises). */
    private const COLONNES = [
        'numero_lot' => ['numerodelot', 'numerolot', 'numlot', 'nlot', 'lot', 'numero', 'codelot'],
        'proprietaire' => ['proprietaire', 'proprio', 'proprietairelot', 'propritaire'],
        'produit' => ['produit', 'produitfini', 'nature', 'natureproduit', 'article'],
        'date_prod' => ['datedeproduction', 'dateproduction', 'dateprod', 'datedeprod', 'date'],
        'pn_th' => ['poidsnettheorique', 'poidsnetth', 'poidsnetthorique', 'poidsnet', 'pnth', 'pnet'],
        'pb_th' => ['poidsbruttheorique', 'poidsbrutth', 'poidsbrut', 'pbth', 'pbrut'],
        'nb_sacs' => ['nombredesacs', 'nombresacs', 'nbsacs', 'sacs', 'nombredecolis', 'nbcolis', 'colis'],
        'qualite' => ['qualite', 'etat', 'conformite'],
        'origine' => ['origine', 'originedulot'],
        'magasin' => ['magasin', 'emplacement', 'entrepot', 'depot'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LotRepository $lots,
        private readonly ProprietaireRepository $proprietaires,
        private readonly ProduitRepository $produits,
        private readonly MagasinRepository $magasins,
        private readonly GestionLotService $gestion,
        private readonly LecteurTabulaireService $lecteur,
    ) {
    }

    // ------------------------------------------------------------------
    //  Page d'import
    // ------------------------------------------------------------------
    #[Route('', name: 'production_lot_import', methods: ['GET'])]
    public function index(): Response
    {
        return $this->page();
    }

    #[Route('/Executer', name: 'production_lot_import_executer', methods: ['POST'])]
    public function executer(Request $request): Response
    {
        if (!$this->peutModifier()) {
            $this->addFlash('danger', 'Votre profil ne permet pas de créer des lots de production.');

            return $this->redirectToRoute('production_lot');
        }

        if (!$this->isCsrfTokenValid('lot_import', (string) $request->request->get('_token'))) {
            return $this->page(['Le formulaire a expiré. Merci de renvoyer le fichier.'], 400);
        }

        /** @var UploadedFile|null $fichier */
        $fichier = $request->files->get('fichier');
        if (!$fichier instanceof UploadedFile) {
            return $this->page(['Choisissez d\'abord un fichier .xlsx, .xls ou .csv.'], 400);
        }

        try {
            $lecture = $this->lecteur->lire($fichier);
        } catch (\RuntimeException $e) {
            return $this->page([$e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->page(['Lecture impossible : ' . $e->getMessage()], 400);
        }

        $colonnes = $this->associerColonnes($lecture['entetes'] ?? []);
        $manquantes = [];
        foreach (['numero_lot' => 'Numéro de lot', 'proprietaire' => 'Propriétaire', 'produit' => 'Produit', 'date_prod' => 'Date de production', 'pn_th' => 'Poids net théorique'] as $cle => $libelle) {
            if (!isset($colonnes[$cle])) {
                $manquantes[] = $libelle;
            }
        }

        if ($manquantes !== []) {
            return $this->page([sprintf(
                'Colonne(s) obligatoire(s) introuvable(s) : %s. Colonnes lues : %s.',
                implode(', ', $manquantes),
                implode(', ', array_filter(array_map('strval', $lecture['entetes'] ?? []))) ?: '(aucune)'
            )], 400, $fichier->getClientOriginalName());
        }

        $lignes = $lecture['lignes'] ?? [];
        if (count($lignes) > self::MAX_LIGNES) {
            return $this->page([sprintf('Le fichier contient %d lignes : limitez l\'import à %d lignes par fichier.', count($lignes), self::MAX_LIGNES)], 400, $fichier->getClientOriginalName());
        }

        $proprietaires = $this->indexer($this->proprietaires->findBy([], ['denomination' => 'ASC']), static fn (Proprietaire $p): string => (string) $p->getDenomination());
        $produits = $this->indexer($this->produits->findBy([], ['libelle' => 'ASC']), static fn (Produit $p): string => (string) $p->getLibelle());
        $magasins = $this->indexer($this->magasins->findBy([], ['libelle' => 'ASC']), static fn (Magasin $m): string => (string) $m->getLibelle());

        $rapport = [];
        $dejaVus = [];
        $compteurs = ['crees' => 0, 'existants' => 0, 'doublons' => 0, 'rejetes' => 0];

        foreach ($lignes as $indice => $ligne) {
            $numeroLigne = (int) ($lecture['ligneEntete'] ?? 1) + $indice + 1;
            $numero = mb_strtoupper($this->valeur($ligne, $colonnes, 'numero_lot'));

            if ($this->ligneVide($ligne, $colonnes)) {
                continue;
            }

            if ($numero === '') {
                $rapport[] = ['ligne' => $numeroLigne, 'numero' => '', 'statut' => 'rejete', 'message' => 'Numéro de lot vide.'];
                ++$compteurs['rejetes'];

                continue;
            }

            if (isset($dejaVus[$numero])) {
                $rapport[] = ['ligne' => $numeroLigne, 'numero' => $numero, 'statut' => 'doublon', 'message' => sprintf('Déjà présent dans le fichier (ligne %d) : ligne ignorée.', $dejaVus[$numero])];
                ++$compteurs['doublons'];

                continue;
            }
            $dejaVus[$numero] = $numeroLigne;

            if ($this->lots->numeroDejaUtilise($numero)) {
                $rapport[] = ['ligne' => $numeroLigne, 'numero' => $numero, 'statut' => 'existant', 'message' => 'Ce numéro de lot existe déjà en base : aucun écrasement, ligne ignorée.'];
                ++$compteurs['existants'];

                continue;
            }

            $erreurs = [];
            $proprietaire = $this->chercher($proprietaires, $this->valeur($ligne, $colonnes, 'proprietaire'));
            if (!$proprietaire instanceof Proprietaire) {
                $erreurs[] = 'Propriétaire introuvable : ' . ($this->valeur($ligne, $colonnes, 'proprietaire') ?: '(vide)');
            }

            $produit = $this->chercher($produits, $this->valeur($ligne, $colonnes, 'produit'));
            if (!$produit instanceof Produit) {
                $erreurs[] = 'Produit introuvable : ' . ($this->valeur($ligne, $colonnes, 'produit') ?: '(vide)');
            }

            $date = $this->date($this->valeur($ligne, $colonnes, 'date_prod'));
            if (null === $date) {
                $erreurs[] = 'Date de production illisible : ' . ($this->valeur($ligne, $colonnes, 'date_prod') ?: '(vide)');
            }

            $magasin = $this->chercher($magasins, $this->valeur($ligne, $colonnes, 'magasin'));

            $donnees = [
                'numero_lot' => $numero,
                'proprietaire' => $proprietaire,
                'produit' => $produit,
                'magasin' => $magasin instanceof Magasin ? $magasin : null,
                'date_prod' => $date ?? '',
                'pn_th' => $this->nombre($this->valeur($ligne, $colonnes, 'pn_th')),
                'pb_th' => $this->nombre($this->valeur($ligne, $colonnes, 'pb_th')),
                'nb_sacs' => $this->nombre($this->valeur($ligne, $colonnes, 'nb_sacs')),
                'qualite' => $this->valeur($ligne, $colonnes, 'qualite') ?: 'C',
                'origine' => $this->valeur($ligne, $colonnes, 'origine'),
            ];

            if ($erreurs !== []) {
                $rapport[] = ['ligne' => $numeroLigne, 'numero' => $numero, 'statut' => 'rejete', 'message' => implode(' ', $erreurs)];
                ++$compteurs['rejetes'];

                continue;
            }

            $resultat = $this->gestion->enregistrer($donnees, null, $this->utilisateur());
            if (!$resultat['ok']) {
                $rapport[] = ['ligne' => $numeroLigne, 'numero' => $numero, 'statut' => 'rejete', 'message' => implode(' ', $resultat['erreurs'])];
                ++$compteurs['rejetes'];

                continue;
            }

            $rapport[] = ['ligne' => $numeroLigne, 'numero' => $numero, 'statut' => 'cree', 'message' => 'Lot créé.'];
            ++$compteurs['crees'];
        }

        if ($compteurs['crees'] > 0) {
            $this->addFlash('success', sprintf('%d lot(s) importé(s). %d ligne(s) ignorée(s) car déjà existante(s).', $compteurs['crees'], $compteurs['existants'] + $compteurs['doublons']));
        } else {
            $this->addFlash('warning', 'Aucun lot importé : toutes les lignes ont été ignorées ou rejetées. Consultez le rapport ci-dessous.');
        }

        return $this->page([], 200, $fichier->getClientOriginalName(), $rapport, $compteurs);
    }

    // ------------------------------------------------------------------
    //  Modeles
    // ------------------------------------------------------------------
    #[Route('/modele.csv', name: 'production_lot_import_modele_csv', methods: ['GET'])]
    public function modeleCsv(): Response
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $this->entetesModele(), ';');
        fputcsv($fh, [
            'LOT-2026-001', 'CITRAC', 'FEVES DE CACAO', '2026-01-31', '20000', '20400', '400', 'C', 'LOT-2026-001', 'MAGASIN FEVES CITRAC',
        ], ';');
        rewind($fh);
        $contenu = (string) stream_get_contents($fh);
        fclose($fh);

        return new Response("\xEF\xBB\xBF" . $contenu, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele_import_lots.csv"',
        ]);
    }

    #[Route('/modele.xlsx', name: 'production_lot_import_modele_xlsx', methods: ['GET'])]
    public function modeleXlsx(): Response
    {
        $contenu = $this->construireXlsx([
            $this->entetesModele(),
            ['LOT-2026-001', 'CITRAC', 'FEVES DE CACAO', '2026-01-31', '20000', '20400', '400', 'C', 'LOT-2026-001', 'MAGASIN FEVES CITRAC'],
        ]);

        return new Response($contenu, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="modele_import_lots.xlsx"',
        ]);
    }

    /** @return list<string> */
    private function entetesModele(): array
    {
        return ['Numéro de lot', 'Propriétaire', 'Produit', 'Date de production', 'Poids net théorique', 'Poids brut théorique', 'Nombre de sacs', 'Qualité', 'Origine', 'Magasin'];
    }

    // ------------------------------------------------------------------
    //  Rendu
    // ------------------------------------------------------------------
    /**
     * @param list<string> $erreurs
     * @param list<array{ligne:int, numero:string, statut:string, message:string}> $rapport
     * @param array<string, int> $compteurs
     */
    private function page(array $erreurs = [], int $code = 200, string $nomFichier = '', array $rapport = [], array $compteurs = []): Response
    {
        return $this->render('logistique/lots/import.html.twig', [
            'erreurs' => $erreurs,
            'nomFichier' => $nomFichier,
            'rapport' => $rapport,
            'compteurs' => $compteurs,
            'colonnesModele' => [
                ['Numéro de lot', 'Oui', '20 caractères maximum, unique. Un numéro déjà en base est ignoré.'] ,
                ['Propriétaire', 'Oui', 'Dénomination exacte telle qu\'elle apparaît dans la fiche propriétaire.'] ,
                ['Produit', 'Oui', 'Libellé exact du produit fini (fèves, beurre, masse, tourteau...).'] ,
                ['Date de production', 'Oui', 'JJ/MM/AAAA ou AAAA-MM-JJ.'] ,
                ['Poids net théorique', 'Oui', 'En kilogrammes, nombre positif.'] ,
                ['Poids brut théorique', 'Non', 'En kilogrammes. Calculé (poids net + tare × sacs) si vide.'] ,
                ['Nombre de sacs', 'Non', 'Nombre entier de sacs du lot.'] ,
                ['Qualité', 'Non', 'C / CONFORME ou NC / NON CONFORME. C par défaut.'] ,
                ['Origine', 'Non', 'Reprise du numéro de lot si vide.'] ,
                ['Magasin', 'Non', 'Déduit du produit (fèves / produits finis) si vide.'] ,
            ],
            'peutModifier' => $this->peutModifier(),
        ], new Response(status: $code));
    }

    // ------------------------------------------------------------------
    //  Lecture du fichier
    // ------------------------------------------------------------------
    /**
     * @param array<string, string> $entetes
     *
     * @return array<string, string> champ => adresse de colonne
     */
    private function associerColonnes(array $entetes): array
    {
        $normalisees = [];
        foreach ($entetes as $adresse => $libelle) {
            $cle = $this->normaliser((string) $libelle);
            if ($cle !== '' && !isset($normalisees[$cle])) {
                $normalisees[$cle] = (string) $adresse;
            }
        }

        $resultat = [];
        foreach (self::COLONNES as $champ => $alias) {
            foreach ($alias as $nom) {
                if (isset($normalisees[$nom])) {
                    $resultat[$champ] = $normalisees[$nom];

                    break;
                }
            }
        }

        return $resultat;
    }

    /** @param array<string, string> $ligne @param array<string, string> $colonnes */
    private function valeur(array $ligne, array $colonnes, string $champ): string
    {
        if (!isset($colonnes[$champ])) {
            return '';
        }

        return trim((string) ($ligne[$colonnes[$champ]] ?? ''));
    }

    /** @param array<string, string> $ligne @param array<string, string> $colonnes */
    private function ligneVide(array $ligne, array $colonnes): bool
    {
        foreach ($colonnes as $adresse) {
            if (trim((string) ($ligne[$adresse] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normaliser(string $valeur): string
    {
        $valeur = strtr($valeur, ['à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', '°' => '', 'º' => '']);

        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($valeur)) ?? '';
    }

    /**
     * Indexe des entites par libelle normalise.
     *
     * @param iterable<object> $entites
     *
     * @return array<string, object>
     */
    private function indexer(iterable $entites, callable $libelle): array
    {
        $index = [];
        foreach ($entites as $entite) {
            $cle = $this->normaliser((string) $libelle($entite));
            if ($cle !== '' && !isset($index[$cle])) {
                $index[$cle] = $entite;
            }
        }

        return $index;
    }

    /** @param array<string, object> $index */
    private function chercher(array $index, string $libelle): ?object
    {
        $cle = $this->normaliser($libelle);
        if ($cle === '') {
            return null;
        }
        if (isset($index[$cle])) {
            return $index[$cle];
        }

        // Tolerance : libelle partiel unique ("CITRAC" pour "CITRAC SA").
        $trouves = [];
        foreach ($index as $reference => $entite) {
            if (str_contains($reference, $cle) || str_contains($cle, $reference)) {
                $trouves[] = $entite;
            }
        }

        return count($trouves) === 1 ? $trouves[0] : null;
    }

    private function date(string $valeur): ?string
    {
        $valeur = trim($valeur);
        if ($valeur === '') {
            return null;
        }

        // Date serialisee par Excel (ex. 46001).
        if (preg_match('/^\d{5}(\.0+)?$/', $valeur) === 1) {
            $objet = (new \DateTimeImmutable('1899-12-30'))->modify('+' . (int) $valeur . ' days');

            return $objet->format('Y-m-d');
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d', 'd/m/y'] as $format) {
            $objet = \DateTimeImmutable::createFromFormat('!' . $format, $valeur);
            if ($objet instanceof \DateTimeImmutable) {
                return $objet->format('Y-m-d');
            }
        }

        $horodatage = strtotime(str_replace('/', '-', $valeur));

        return false !== $horodatage ? date('Y-m-d', $horodatage) : null;
    }

    private function nombre(string $valeur): ?float
    {
        $valeur = trim(str_replace(["\xc2\xa0", ' ', 'kg', 'KG', 'Kg'], '', $valeur));
        if ($valeur === '') {
            return null;
        }
        $valeur = str_replace(',', '.', $valeur);

        return is_numeric($valeur) ? (float) $valeur : null;
    }

    // ------------------------------------------------------------------
    //  Generateur de modele .xlsx (sans dependance externe)
    // ------------------------------------------------------------------
    /** @param list<list<string>> $lignes */
    private function construireXlsx(array $lignes): string
    {
        $feuille = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<cols><col min="1" max="1" width="22" customWidth="1"/><col min="2" max="10" width="24" customWidth="1"/></cols>'
            . '<sheetData>';

        foreach (array_values($lignes) as $index => $ligne) {
            $rang = $index + 1;
            $feuille .= '<row r="' . $rang . '">';
            foreach (array_values($ligne) as $position => $valeur) {
                $feuille .= '<c r="' . $this->lettre($position + 1) . $rang . '" t="inlineStr"><is><t xml:space="preserve">'
                    . htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_XML1, 'UTF-8')
                    . '</t></is></c>';
            }
            $feuille .= '</row>';
        }

        $feuille .= '</sheetData></worksheet>';

        $fichiers = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="Lots" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '</Relationships>',
            'xl/worksheets/sheet1.xml' => $feuille,
        ];

        $chemin = (string) tempnam(sys_get_temp_dir(), 'modele_lots_');
        $zip = new \ZipArchive();
        if (true !== $zip->open($chemin, \ZipArchive::OVERWRITE)) {
            throw new \RuntimeException('Impossible de préparer le modèle Excel.');
        }
        foreach ($fichiers as $nom => $contenu) {
            $zip->addFromString($nom, $contenu);
        }
        $zip->close();

        $binaire = (string) file_get_contents($chemin);
        @unlink($chemin);

        return $binaire;
    }

    private function lettre(int $position): string
    {
        $lettre = '';
        while ($position > 0) {
            --$position;
            $lettre = chr(65 + $position % 26) . $lettre;
            $position = intdiv($position, 26);
        }

        return $lettre;
    }

    private function peutModifier(): bool
    {
        return $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DG');
    }

    private function utilisateur(): string
    {
        $utilisateur = $this->getUser();

        return null !== $utilisateur && method_exists($utilisateur, 'getUserIdentifier')
            ? (string) $utilisateur->getUserIdentifier()
            : 'system';
    }
}
