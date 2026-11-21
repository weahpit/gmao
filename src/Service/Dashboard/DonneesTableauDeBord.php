<?php

namespace App\Service\Dashboard;

use App\Entity\AlerteAnomalie;
use App\Entity\BonSortie;
use App\Entity\Courrier\Courrier;
use App\Entity\DemandeAchat;
use App\Entity\Equipement;
use App\Entity\Logistique\DocsDossier;
use App\Entity\Logistique\Dossier;
use App\Entity\Maintenance\FicheIntervention;
use App\Entity\MouvementEquipement;
use App\Entity\Rh\Absence;
use App\Entity\Rh\AccidentTravail;
use App\Entity\Rh\Contrat;
use App\Entity\Rh\Employe;
use App\Entity\Rh\ParticipationFormation;
use App\Entity\Rh\Pointage;
use App\Entity\Rh\VisiteMedicale;
use App\Entity\StatutAlerte;
use App\Entity\Stock\DechargementCacao;
use App\Entity\Stock\SaisieStock;
use App\Entity\Stock\SortieStock;
use App\Entity\User;
use App\Entity\Visite\Visite;
use App\Service\Stock\ReferentielStock;
use App\Service\Stock\StockInitialService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calcule les donnees affichees par les gadgets du tableau de bord par role.
 *
 * Chaque methode renvoie soit un chiffre cle
 *   ['valeur' => int|float, 'detail' => string]
 * soit un jeu de donnees a graphique
 *   ['categories' => [...], 'series' => [['nom' => ..., 'donnees' => [...]]], 'total' => int]
 * soit une petite fiche
 *   ['lignes' => [['libelle' => ..., 'valeur' => ...], ...]]
 * soit un tableau pret a afficher
 *   ['colonnes' => [...], 'lignes' => [[...], ...], 'vide' => string]
 *
 * Aucune de ces methodes n'est appelee directement par le gabarit : le
 * controleur les protege individuellement, de sorte qu'une donnee manquante
 * n'empeche jamais l'affichage du reste du tableau de bord.
 */
class DonneesTableauDeBord
{
    public function __construct(
        private EntityManagerInterface $em,
        private StockInitialService $stockInitial,
        private ReferentielStock $referentiel
    ) {
    }

    // -----------------------------------------------------------------
    //  Outils communs
    // -----------------------------------------------------------------

    /**
     * Petite fiche lisible pour les erreurs : on ne masque pas la cause.
     */
    public static function erreur(\Throwable $e): array
    {
        return [
            'erreur' => $e->getMessage() !== '' ? $e->getMessage() : get_class($e),
        ];
    }

    private function graphique(array $categories, array $series): array
    {
        $total = 0;
        foreach ($series as $serie) {
            foreach ($serie['donnees'] as $valeur) {
                $total += $valeur;
            }
        }

        return [
            'categories' => $categories,
            'series' => $series,
            'total' => round($total, 2),
        ];
    }

    private function humaniser(string $valeur): string
    {
        $propre = str_replace('_', ' ', $valeur);

        return mb_strtoupper(mb_substr($propre, 0, 1)) . mb_substr($propre, 1);
    }

    /**
     * Execute une requete d'agregation et renvoie un jeu de donnees pret a tracer.
     */
    private function tableau(
        string $dql,
        array $parametres = [],
        string $cle = 'libelle',
        string $valeur = 'total',
        array $libelles = [],
        int $limite = 0
    ): array {
        $lignes = $this->em->createQuery($dql)->setParameters($parametres)->getArrayResult();
        $categories = [];
        $donnees = [];

        foreach ($lignes as $ligne) {
            $brut = $ligne[$cle] ?? null;
            $code = is_scalar($brut) ? (string) $brut : '';

            if (isset($libelles[$code])) {
                $code = $libelles[$code];
            } elseif ($code === '') {
                $code = 'Non renseigne';
            } else {
                $code = $this->humaniser($code);
            }

            $categories[] = $code;
            $donnees[] = round((float) ($ligne[$valeur] ?? 0), 2);
        }

        if ($limite > 0) {
            $categories = array_slice($categories, 0, $limite);
            $donnees = array_slice($donnees, 0, $limite);
        }

        return $this->graphique($categories, [['nom' => 'Total', 'donnees' => $donnees]]);
    }

    private function compter(string $entite, string $condition = '', array $parametres = []): int
    {
        $dql = 'SELECT COUNT(e.id) FROM ' . $entite . ' e';
        if ($condition !== '') {
            $dql .= ' WHERE ' . $condition;
        }

        return (int) $this->em->createQuery($dql)->setParameters($parametres)->getSingleScalarResult();
    }

    /**
     * @return array<string, string> cle 'Y-m' => libelle court
     */
    private function moisRecents(int $nombre): array
    {
        $noms = [
            1 => 'janv.', 2 => 'fevr.', 3 => 'mars', 4 => 'avr.', 5 => 'mai', 6 => 'juin',
            7 => 'juil.', 8 => 'aout', 9 => 'sept.', 10 => 'oct.', 11 => 'nov.', 12 => 'dec.',
        ];

        $sortie = [];
        $curseur = new \DateTimeImmutable('first day of this month');
        for ($i = $nombre - 1; $i >= 0; $i--) {
            $mois = $curseur->modify(sprintf('-%d months', $i));
            $sortie[$mois->format('Y-m')] = $noms[(int) $mois->format('n')] . ' ' . $mois->format('y');
        }

        return $sortie;
    }

    /**
     * @return array<string, string> cle 'Y-m-d' (lundi) => libelle
     */
    private function semainesRecentes(int $nombre): array
    {
        $lundi = new \DateTimeImmutable('monday this week');
        $sortie = [];
        for ($i = $nombre - 1; $i >= 0; $i--) {
            $debut = $lundi->modify(sprintf('-%d weeks', $i));
            $sortie[$debut->format('Y-m-d')] = 'Sem. ' . $debut->format('d/m');
        }

        return $sortie;
    }

    /**
     * @return array<string, string> cle 'Y-m-d' => libelle j/m
     */
    private function joursRecents(int $nombre): array
    {
        $sortie = [];
        $aujourdhui = new \DateTimeImmutable('today');
        for ($i = $nombre - 1; $i >= 0; $i--) {
            $jour = $aujourdhui->modify(sprintf('-%d days', $i));
            $sortie[$jour->format('Y-m-d')] = $jour->format('d/m');
        }

        return $sortie;
    }

    private function enDate(mixed $valeur): ?\DateTimeInterface
    {
        if ($valeur instanceof \DateTimeInterface) {
            return $valeur;
        }
        if (is_string($valeur) && $valeur !== '') {
            try {
                return new \DateTimeImmutable($valeur);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function debutMois(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('first day of this month 00:00:00');
    }

    // -----------------------------------------------------------------
    //  Ressources humaines
    // -----------------------------------------------------------------

    public function effectifParService(): array
    {
        return $this->tableau(
            'SELECT s.libelle AS libelle, COUNT(e.id) AS total'
            . ' FROM ' . Employe::class . ' e LEFT JOIN e.service s'
            . ' WHERE e.statut = :actif GROUP BY s.libelle ORDER BY total DESC',
            ['actif' => Employe::STATUT_ACTIF]
        );
    }

    public function effectifParSexe(): array
    {
        return $this->tableau(
            'SELECT e.sexe AS libelle, COUNT(e.id) AS total'
            . ' FROM ' . Employe::class . ' e'
            . ' WHERE e.statut = :actif GROUP BY e.sexe ORDER BY total DESC',
            ['actif' => Employe::STATUT_ACTIF],
            'libelle',
            'total',
            ['M' => 'Hommes', 'F' => 'Femmes']
        );
    }

    public function contratsParType(): array
    {
        return $this->tableau(
            'SELECT c.type AS libelle, COUNT(c.id) AS total'
            . ' FROM ' . Contrat::class . ' c'
            . ' WHERE c.statut = :encours GROUP BY c.type ORDER BY total DESC',
            ['encours' => Contrat::EN_COURS],
            'libelle',
            'total',
            ['cdi' => 'CDI', 'cdd' => 'CDD', 'stage' => 'Stage', 'interim' => 'Interim']
        );
    }

    public function embauchesDeparts(): array
    {
        $mois = $this->moisRecents(6);
        $debut = new \DateTimeImmutable('first day of -5 months');

        $lignes = $this->em->createQuery(
            'SELECT e.dateEmbauche AS embauche, e.dateSortie AS sortie'
            . ' FROM ' . Employe::class . ' e'
            . ' WHERE e.dateEmbauche >= :debut OR e.dateSortie >= :debut'
        )->setParameter('debut', $debut)->getArrayResult();

        $embauches = array_fill_keys(array_keys($mois), 0);
        $departs = array_fill_keys(array_keys($mois), 0);

        foreach ($lignes as $ligne) {
            $embauche = $this->enDate($ligne['embauche'] ?? null);
            if ($embauche && isset($embauches[$embauche->format('Y-m')])) {
                $embauches[$embauche->format('Y-m')]++;
            }
            $sortie = $this->enDate($ligne['sortie'] ?? null);
            if ($sortie && isset($departs[$sortie->format('Y-m')])) {
                $departs[$sortie->format('Y-m')]++;
            }
        }

        return $this->graphique(array_values($mois), [
            ['nom' => 'Embauches', 'donnees' => array_values($embauches)],
            ['nom' => 'Departs', 'donnees' => array_values($departs)],
        ]);
    }

    public function absencesParType(): array
    {
        return $this->tableau(
            'SELECT a.type AS libelle, COUNT(a.id) AS total'
            . ' FROM ' . Absence::class . ' a'
            . ' WHERE a.dateDebut >= :debut GROUP BY a.type ORDER BY total DESC',
            ['debut' => $this->debutMois()],
            'libelle',
            'total',
            Absence::TYPES
        );
    }

    public function retardsMois(): array
    {
        $ligne = $this->em->createQuery(
            'SELECT COUNT(p.id) AS nombre, SUM(p.retardMinutes) AS minutes, SUM(p.heuresSupplementaires) AS heures'
            . ' FROM ' . Pointage::class . ' p'
            . ' WHERE p.jour >= :debut AND p.retardMinutes > 0'
        )->setParameter('debut', $this->debutMois())->getSingleResult();

        $nombre = (int) ($ligne['nombre'] ?? 0);
        $minutes = (int) ($ligne['minutes'] ?? 0);
        $heures = (int) ($ligne['heures'] ?? 0);

        return [
            'valeur' => $nombre,
            'unite' => 'retards',
            'detail' => $nombre === 0
                ? 'Aucun retard enregistre ce mois.'
                : sprintf('%d h %02d de retard cumule - %d h supplementaires', intdiv($minutes, 60), $minutes % 60, $heures),
        ];
    }

    public function pointagesParJour(int $jours = 14): array
    {
        $jours = max(2, $jours);
        $libelles = $this->joursRecents($jours);
        $debut = new \DateTimeImmutable(sprintf('-%d days', $jours - 1));

        $lignes = $this->em->createQuery(
            'SELECT p.jour AS jour, COUNT(p.id) AS total'
            . ' FROM ' . Pointage::class . ' p'
            . ' WHERE p.jour >= :debut GROUP BY p.jour ORDER BY p.jour ASC'
        )->setParameter('debut', $debut)->getArrayResult();

        $valeurs = array_fill_keys(array_keys($libelles), 0);
        foreach ($lignes as $ligne) {
            $jour = $this->enDate($ligne['jour'] ?? null);
            if ($jour && isset($valeurs[$jour->format('Y-m-d')])) {
                $valeurs[$jour->format('Y-m-d')] = (int) $ligne['total'];
            }
        }

        return $this->graphique(array_values($libelles), [['nom' => 'Pointages', 'donnees' => array_values($valeurs)]]);
    }

    // -----------------------------------------------------------------
    //  Stock / magasin
    // -----------------------------------------------------------------

    public function articlesSousSeuil(): array
    {
        $nombre = $this->compter(
            Equipement::class,
            'e.seuil IS NOT NULL AND e.seuil > 0 AND e.qte <= e.seuil'
        );

        return [
            'valeur' => $nombre,
            'unite' => 'articles',
            'detail' => $nombre === 0 ? 'Aucun article sous le seuil.' : 'Articles a reapprovisionner en priorite.',
        ];
    }

    public function bonsParStatut(): array
    {
        return $this->tableau(
            'SELECT b.statut AS libelle, COUNT(b.id) AS total'
            . ' FROM ' . BonSortie::class . ' b GROUP BY b.statut ORDER BY total DESC',
            [],
            'libelle',
            'total',
            BonSortie::LIBELLES
        );
    }

    public function demandesParStatut(): array
    {
        return $this->tableau(
            'SELECT d.statut AS libelle, COUNT(d.id) AS total'
            . ' FROM ' . DemandeAchat::class . ' d GROUP BY d.statut ORDER BY total DESC',
            [],
            'libelle',
            'total',
            DemandeAchat::LIBELLES
        );
    }

    public function topRubriquesSorties(): array
    {
        return $this->tableau(
            'SELECT s.rubrique AS libelle, SUM(s.quantite) AS total'
            . ' FROM ' . SortieStock::class . ' s'
            . ' WHERE s.dateSortie >= :debut GROUP BY s.rubrique ORDER BY total DESC',
            ['debut' => new \DateTimeImmutable('-30 days')],
            'libelle',
            'total',
            [],
            5
        );
    }

    public function fluxStock(): array
    {
        $semaines = $this->semainesRecentes(6);
        $premierLundi = array_key_first($semaines);
        $debut = new \DateTimeImmutable($premierLundi . ' 00:00:00');

        $entrees = array_fill_keys(array_keys($semaines), 0);
        $sorties = array_fill_keys(array_keys($semaines), 0);

        $lignesEntrees = $this->em->createQuery(
            'SELECT s.dateSaisie AS d FROM ' . SaisieStock::class . ' s WHERE s.dateSaisie >= :debut'
        )->setParameter('debut', $debut)->getArrayResult();

        foreach ($lignesEntrees as $ligne) {
            $date = $this->enDate($ligne['d'] ?? null);
            if ($date) {
                $cle = $this->cleSemaine($date, $debut, $semaines);
                if ($cle !== null) {
                    $entrees[$cle]++;
                }
            }
        }

        $lignesSorties = $this->em->createQuery(
            'SELECT s.dateSortie AS d FROM ' . SortieStock::class . ' s WHERE s.dateSortie >= :debut'
        )->setParameter('debut', $debut)->getArrayResult();

        foreach ($lignesSorties as $ligne) {
            $date = $this->enDate($ligne['d'] ?? null);
            if ($date) {
                $cle = $this->cleSemaine($date, $debut, $semaines);
                if ($cle !== null) {
                    $sorties[$cle]++;
                }
            }
        }

        return $this->graphique(array_values($semaines), [
            ['nom' => 'Entrees', 'donnees' => array_values($entrees)],
            ['nom' => 'Sorties', 'donnees' => array_values($sorties)],
        ]);
    }

    private function cleSemaine(\DateTimeInterface $date, \DateTimeImmutable $debut, array $semaines): ?string
    {
        $jours = (int) $debut->diff(new \DateTimeImmutable($date->format('Y-m-d')))->days;
        $index = intdiv($jours, 7);
        $cles = array_keys($semaines);
        if (!isset($cles[$index])) {
            return null;
        }

        return $cles[$index];
    }

    public function dechargementParJour(int $jours = 14): array
    {
        $libelles = $this->joursRecents($jours);
        $debut = new \DateTimeImmutable(sprintf('-%d days', $jours - 1));

        $lignes = $this->em->createQuery(
            'SELECT d.dateDechargement AS jour, SUM(d.poidsNet) AS total'
            . ' FROM ' . DechargementCacao::class . ' d'
            . ' WHERE d.dateDechargement >= :debut GROUP BY d.dateDechargement ORDER BY d.dateDechargement ASC'
        )->setParameter('debut', $debut)->getArrayResult();

        $valeurs = array_fill_keys(array_keys($libelles), 0);
        foreach ($lignes as $ligne) {
            $jour = $this->enDate($ligne['jour'] ?? null);
            if ($jour && isset($valeurs[$jour->format('Y-m-d')])) {
                $valeurs[$jour->format('Y-m-d')] = round((float) $ligne['total'], 2);
            }
        }

        return $this->graphique(array_values($libelles), [['nom' => 'Kg decharges', 'donnees' => array_values($valeurs)]]);
    }

    // -----------------------------------------------------------------
    //  Stocks : recapitulatif par produit et saisies du jour
    // -----------------------------------------------------------------

    /** Quantite lisible a la francaise (virgule decimale, espace des milliers). */
    private function quantite(float $valeur): string
    {
        $arrondi = round($valeur, 3);
        $decimales = fmod(abs($arrondi), 1.0) > 0.000001 ? 2 : 0;

        return number_format($arrondi, $decimales, ',', ' ');
    }

    private function heureDe(mixed $valeur): string
    {
        $date = $this->enDate($valeur);

        return $date !== null ? $date->format('H:i') : '-';
    }

    /**
     * Vrai si le libelle de la rubrique designe des pieces de rechange (PDR).
     * Sert au cloisonnement : le stock PDR n'est jamais melange au cacao.
     */
    private function rubriquePdr(string $libelle): bool
    {
        $texte = mb_strtolower($libelle);

        foreach (['pdr', 'rechange', 'pièce', 'piece', 'détachée', 'detachee'] as $motif) {
            if (mb_strpos($texte, $motif) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rubriques du stock cacao : le referentiel, moins les rubriques PDR.
     *
     * @return list<string>
     */
    private function rubriquesCacao(): array
    {
        $rubriques = $this->referentiel->rubriques();
        $cacao = array_values(array_filter($rubriques, fn (string $r): bool => !$this->rubriquePdr($r)));

        return $cacao !== [] ? $cacao : $rubriques;
    }

    /**
     * Recapitulatif du stock cacao : une ligne par rubrique et par nature
     * de produit, calculee par le service officiel du stock (inventaire +
     * entrees - sorties).
     *
     * @return array<string, mixed>
     */
    public function recapitulatifStockCacao(): array
    {
        $colonnes = ['Type de stock', 'Produit', 'Unité', 'Stock initial', 'Entrées', 'Sorties', 'Stock final'];
        $rubriques = $this->rubriquesCacao();
        $apercu = $this->stockInitial->apercu($rubriques);
        $lignes = [];

        foreach ($apercu['parRubrique'] as $rubrique => $ligne) {
            $natures = $ligne['parNature'] ?? [];

            if ($natures === []) {
                $lignes[] = [
                    $rubrique,
                    'Aucun mouvement',
                    'Kg',
                    $this->quantite((float) $ligne['stockInitialKg']),
                    $this->quantite((float) $ligne['entreeKg']),
                    $this->quantite((float) $ligne['sortieKg']),
                    $this->quantite((float) $ligne['stockFinalKg']),
                ];
                continue;
            }

            foreach ($natures as $nature => $detail) {
                $ajoutee = false;

                foreach (['Kg', 'Litres'] as $unite) {
                    $initial = (float) ($detail['stockInitial' . $unite] ?? 0);
                    $entrees = (float) ($detail['entree' . $unite] ?? 0);
                    $sorties = (float) ($detail['sortie' . $unite] ?? 0);
                    $final = (float) ($detail['stockFinal' . $unite] ?? 0);

                    if (abs($initial) + abs($entrees) + abs($sorties) + abs($final) < 0.0001) {
                        continue;
                    }

                    $lignes[] = [
                        $rubrique,
                        $nature,
                        $unite,
                        $this->quantite($initial),
                        $this->quantite($entrees),
                        $this->quantite($sorties),
                        $this->quantite($final),
                    ];
                    $ajoutee = true;
                }

                if (!$ajoutee) {
                    $lignes[] = [$rubrique, $nature, 'Kg', '0', '0', '0', '0'];
                }
            }
        }

        if ($lignes === []) {
            return [
                'colonnes' => $colonnes,
                'lignes' => [],
                'vide' => "Aucun stock enregistré pour l'instant : aucune rubrique ne contient de mouvement.",
            ];
        }

        return [
            'colonnes' => $colonnes,
            'lignes' => $lignes,
            'sous_titre' => count($lignes) . ' ligne(s) sur ' . count($rubriques) . ' rubrique(s) de stock cacao.',
            'note' => "Stock final = quantité inventoriée + entrées - sorties postérieures à l'inventaire.",
        ];
    }

    /**
     * Detail des saisies de stock cacao enregistrees aujourd hui.
     *
     * @return array<string, mixed>
     */
    public function saisiesDuJour(): array
    {
        $colonnes = ['Heure', 'Agent', 'Type de stock', 'Produit', 'Quantité', 'Unité', 'Service', 'Emplacement'];
        $debut = new \DateTimeImmutable('today 00:00:00');

        $saisies = $this->em->createQuery(
            'SELECT s FROM ' . SaisieStock::class . ' s'
            . ' WHERE s.dateSaisie >= :debut'
            . ' ORDER BY s.dateSaisie ASC'
        )->setParameter('debut', $debut)->setMaxResults(300)->getResult();

        $lignes = [];
        $totalKg = 0.0;
        $totalLitres = 0.0;

        foreach ($saisies as $saisie) {
            if ($this->rubriquePdr((string) $saisie->getRubrique())) {
                continue;
            }

            $agent = trim((string) $saisie->getAgentLibelle());
            if ($agent === '') {
                $compte = $saisie->getAgent();
                $agent = trim(((string) ($compte?->getPrenoms() ?? '')) . ' ' . ((string) ($compte?->getNom() ?? '')));
            }

            $service = trim((string) $saisie->getServiceLibelle());
            if ($service === '') {
                $service = (string) ($saisie->getService()?->getLibelle() ?? '');
            }

            $emplacement = trim((string) $saisie->getEmplacementLibelle());
            if ($emplacement === '') {
                $emplacement = (string) ($saisie->getEmplacement()?->getLibelle() ?? '');
            }

            $unite = (string) ($saisie->getUnite() ?? 'Kg');
            if ($unite === 'Litres') {
                $totalLitres += (float) $saisie->getQuantite();
            } else {
                $totalKg += (float) $saisie->getQuantite();
            }

            $lignes[] = [
                $this->heureDe($saisie->getDateSaisie()),
                $agent !== '' ? $agent : 'Non renseigné',
                (string) ($saisie->getRubrique() ?? 'Non renseigné'),
                (string) ($saisie->getNature() ?? 'Non précisée'),
                $this->quantite((float) $saisie->getQuantite()),
                $unite,
                $service !== '' ? $service : 'Non renseigné',
                $emplacement !== '' ? $emplacement : '-',
            ];
        }

        if ($lignes === []) {
            return [
                'colonnes' => $colonnes,
                'lignes' => [],
                'vide' => "Aucune saisie de stock cacao enregistrée aujourd'hui.",
            ];
        }

        $totaux = [];
        if ($totalKg > 0) {
            $totaux[] = $this->quantite($totalKg) . ' Kg';
        }
        if ($totalLitres > 0) {
            $totaux[] = $this->quantite($totalLitres) . ' Litres';
        }

        return [
            'colonnes' => $colonnes,
            'lignes' => $lignes,
            'sous_titre' => count($lignes) . ' saisie(s) aujourd hui'
                . ($totaux !== [] ? ' - total : ' . implode(' et ', $totaux) : '') . '.',
            'note' => "Seules les entrées de stock cacao sont listées ; le stock PDR n'apparaît jamais ici.",
        ];
    }

    /**
     * Recapitulatif du stock PDR (pieces de rechange) : aucune requete
     * sur le stock cacao, uniquement le parc equipements.
     *
     * @return array<string, mixed>
     */
    public function recapitulatifStockPdr(): array
    {
        $colonnes = ['Code', 'Désignation', 'Nature', 'Stock actuel', 'Seuil', 'Alerte'];

        $equipements = $this->em->createQuery(
            'SELECT e FROM ' . Equipement::class . ' e ORDER BY e.nom ASC'
        )->setMaxResults(300)->getResult();

        $lignes = [];
        $sousSeuil = 0;

        foreach ($equipements as $equipement) {
            $stock = $equipement->getStockActuel();
            if ($stock === null) {
                $stock = $equipement->getStockFinal();
            }
            if ($stock === null && $equipement->getQte() !== null) {
                $stock = (float) $equipement->getQte();
            }
            $stock = (float) ($stock ?? 0);
            $seuil = (int) ($equipement->getSeuil() ?? 0);

            $alerte = '-';
            if ($seuil > 0 && $stock <= 0) {
                $alerte = 'Rupture';
                $sousSeuil++;
            } elseif ($seuil > 0 && $stock <= $seuil) {
                $alerte = 'Sous le seuil';
                $sousSeuil++;
            }

            $lignes[] = [
                (string) ($equipement->getCode() ?? '-'),
                (string) ($equipement->getNom() ?? '-'),
                (string) ($equipement->getNature()?->getLibelle() ?? 'Non classé'),
                $this->quantite($stock),
                $seuil > 0 ? (string) $seuil : '-',
                $alerte,
            ];
        }

        if ($lignes === []) {
            return [
                'colonnes' => $colonnes,
                'lignes' => [],
                'vide' => "Aucune pièce de rechange enregistrée dans le parc.",
            ];
        }

        return [
            'colonnes' => $colonnes,
            'lignes' => $lignes,
            'sous_titre' => count($lignes) . ' pièce(s) de rechange'
                . ($sousSeuil > 0 ? ' - ' . $sousSeuil . ' à réapprovisionner.' : ' - aucun seuil dépassé.'),
            'note' => "Stock PDR uniquement : le stock cacao est volontairement exclu de ce tableau.",
        ];
    }

    /**
     * Mouvements PDR du jour (entrees et sorties de pieces de rechange).
     *
     * @return array<string, mixed>
     */
    public function mouvementsPdrDuJour(): array
    {
        $colonnes = ['Heure', 'Pièce', 'Sens', 'Quantité', 'Service', 'Demandeur', 'Stock après'];
        $debut = new \DateTimeImmutable('today 00:00:00');

        $mouvements = $this->em->createQuery(
            'SELECT m FROM ' . MouvementEquipement::class . ' m'
            . ' LEFT JOIN m.code_equipement e'
            . ' WHERE m.created_at >= :debut OR m.date_operation >= :jour'
            . ' ORDER BY m.created_at ASC'
        )
            ->setParameter('debut', $debut)
            ->setParameter('jour', new \DateTime('today'))
            ->setMaxResults(300)
            ->getResult();

        $lignes = [];
        $entrees = 0;
        $sorties = 0;

        foreach ($mouvements as $mouvement) {
            $piece = $mouvement->getCodeEquipement();
            $nom = (string) ($piece?->getNom() ?? 'Pièce non précisée');
            $code = (string) ($piece?->getCode() ?? '');
            $sens = $mouvement->getTypeMvt() === '1' ? 'Entrée' : 'Sortie';

            if ($sens === 'Entrée') {
                $entrees++;
            } else {
                $sorties++;
            }

            $stockApres = $mouvement->getStockApres();

            $lignes[] = [
                $this->heureDe($mouvement->getCreatedAt()),
                $code !== '' ? $nom . ' (' . $code . ')' : $nom,
                $sens,
                $mouvement->getQte() !== null ? $this->quantite((float) $mouvement->getQte()) : '-',
                (string) ($mouvement->getCodeService()?->getLibelle() ?? '-'),
                (string) ($mouvement->getDemandeur() ?? '-'),
                $stockApres !== null ? $this->quantite((float) $stockApres) : '-',
            ];
        }

        if ($lignes === []) {
            return [
                'colonnes' => $colonnes,
                'lignes' => [],
                'vide' => "Aucun mouvement de pièce de rechange enregistré aujourd'hui.",
            ];
        }

        return [
            'colonnes' => $colonnes,
            'lignes' => $lignes,
            'sous_titre' => count($lignes) . ' mouvement(s) aujourd hui : ' . $entrees . ' entrée(s), ' . $sorties . ' sortie(s).',
            'note' => "Sens du mouvement : type « 1 » = entrée, toute autre valeur = sortie.",
        ];
    }

    // -----------------------------------------------------------------
    //  Logistique / documents
    // -----------------------------------------------------------------

    public function dossiersEtDocuments(): array
    {
        $dossiers = $this->compter(Dossier::class);
        $documents = $this->compter(DocsDossier::class);

        return [
            'valeur' => $dossiers,
            'unite' => 'dossiers',
            'detail' => sprintf('%d documents rattaches aux dossiers.', $documents),
        ];
    }

    public function documentsParModele(): array
    {
        return $this->tableau(
            'SELECT m.nom_modele AS libelle, COUNT(d.id) AS total'
            . ' FROM ' . DocsDossier::class . ' d LEFT JOIN d.code_modele m'
            . ' GROUP BY m.nom_modele ORDER BY total DESC',
            [],
            'libelle',
            'total',
            [],
            6
        );
    }

    public function equipementsParEtat(): array
    {
        return $this->tableau(
            'SELECT et.libelle AS libelle, COUNT(e.id) AS total'
            . ' FROM ' . Equipement::class . ' e LEFT JOIN e.etat et'
            . ' GROUP BY et.libelle ORDER BY total DESC'
        );
    }

    public function bonsParService(): array
    {
        return $this->tableau(
            'SELECT b.service AS libelle, COUNT(b.id) AS total'
            . ' FROM ' . BonSortie::class . ' b'
            . ' WHERE b.created_at >= :debut GROUP BY b.service ORDER BY total DESC',
            ['debut' => new \DateTimeImmutable('-30 days')],
            'libelle',
            'total',
            [],
            6
        );
    }

    // -----------------------------------------------------------------
    //  Courrier
    // -----------------------------------------------------------------

    public function courriersParStatut(): array
    {
        return $this->tableau(
            'SELECT c.statut AS libelle, COUNT(c.id) AS total'
            . ' FROM ' . Courrier::class . ' c GROUP BY c.statut ORDER BY total DESC',
            [],
            'libelle',
            'total',
            Courrier::STATUTS
        );
    }

    public function courriersParPriorite(): array
    {
        return $this->tableau(
            'SELECT c.priorite AS libelle, COUNT(c.id) AS total'
            . ' FROM ' . Courrier::class . ' c GROUP BY c.priorite ORDER BY total DESC',
            [],
            'libelle',
            'total',
            ['normale' => 'Normale', 'urgente' => 'Urgente', 'tres_urgente' => 'Tres urgente']
        );
    }

    public function courriersParService(): array
    {
        return $this->tableau(
            'SELECT c.service AS libelle, COUNT(c.id) AS total'
            . ' FROM ' . Courrier::class . ' c'
            . ' WHERE c.service IS NOT NULL GROUP BY c.service ORDER BY total DESC',
            [],
            'libelle',
            'total',
            [],
            6
        );
    }

    public function courriersFluxMois(): array
    {
        $mois = $this->moisRecents(6);
        $debut = new \DateTimeImmutable('first day of -5 months');

        $lignes = $this->em->createQuery(
            'SELECT c.dateCourrier AS d, c.sens AS sens FROM ' . Courrier::class . ' c'
            . ' WHERE c.dateCourrier >= :debut'
        )->setParameter('debut', $debut)->getArrayResult();

        $entrants = array_fill_keys(array_keys($mois), 0);
        $sortants = array_fill_keys(array_keys($mois), 0);

        foreach ($lignes as $ligne) {
            $date = $this->enDate($ligne['d'] ?? null);
            if (!$date) {
                continue;
            }
            $cle = $date->format('Y-m');
            if (!isset($entrants[$cle])) {
                continue;
            }
            if (($ligne['sens'] ?? '') === Courrier::SENS_SORTANT) {
                $sortants[$cle]++;
            } else {
                $entrants[$cle]++;
            }
        }

        return $this->graphique(array_values($mois), [
            ['nom' => 'Entrants', 'donnees' => array_values($entrants)],
            ['nom' => 'Sortants', 'donnees' => array_values($sortants)],
        ]);
    }

    public function courriersATraiter(): array
    {
        $aTraiter = $this->compter(Courrier::class, 'e.statut = :statut', ['statut' => Courrier::STATUT_A_TRAITER]);
        $urgents = $this->compter(
            Courrier::class,
            'e.statut = :statut AND e.priorite IN (:priorites)',
            ['statut' => Courrier::STATUT_A_TRAITER, 'priorites' => ['urgente', 'tres_urgente']]
        );

        return [
            'valeur' => $aTraiter,
            'unite' => 'a traiter',
            'detail' => $urgents > 0 ? sprintf('Dont %d marques urgents.', $urgents) : 'Aucun courrier urgent en attente.',
        ];
    }

    // -----------------------------------------------------------------
    //  Maintenance
    // -----------------------------------------------------------------

    public function interventionsParStatut(): array
    {
        return $this->tableau(
            'SELECT s.libelle AS libelle, COUNT(f.id) AS total'
            . ' FROM ' . FicheIntervention::class . ' f LEFT JOIN f.statut s'
            . ' GROUP BY s.libelle ORDER BY total DESC'
        );
    }

    public function interventionsParService(): array
    {
        return $this->tableau(
            'SELECT s.libelle AS libelle, COUNT(f.id) AS total'
            . ' FROM ' . FicheIntervention::class . ' f LEFT JOIN f.service_demandeur s'
            . ' GROUP BY s.libelle ORDER BY total DESC',
            [],
            'libelle',
            'total',
            [],
            6
        );
    }

    public function interventionsParMoisType(): array
    {
        $mois = $this->moisRecents(6);
        $debut = new \DateTimeImmutable('first day of -5 months');

        $lignes = $this->em->createQuery(
            'SELECT f.created_at AS d, t.libelle AS type'
            . ' FROM ' . FicheIntervention::class . ' f LEFT JOIN f.type_intervention t'
            . ' WHERE f.created_at >= :debut'
        )->setParameter('debut', $debut)->getArrayResult();

        $preventives = array_fill_keys(array_keys($mois), 0);
        $correctives = array_fill_keys(array_keys($mois), 0);

        foreach ($lignes as $ligne) {
            $date = $this->enDate($ligne['d'] ?? null);
            if (!$date) {
                continue;
            }
            $cle = $date->format('Y-m');
            if (!isset($preventives[$cle])) {
                continue;
            }
            $type = mb_strtoupper((string) ($ligne['type'] ?? ''));
            if (str_contains($type, 'PREVENT')) {
                $preventives[$cle]++;
            } else {
                $correctives[$cle]++;
            }
        }

        return $this->graphique(array_values($mois), [
            ['nom' => 'Preventif', 'donnees' => array_values($preventives)],
            ['nom' => 'Correctif', 'donnees' => array_values($correctives)],
        ]);
    }

    public function alertesOuvertes(): array
    {
        $statut = $this->em->getRepository(StatutAlerte::class)->findOneBy(['libelle' => 'OUVERT']);
        if (!$statut) {
            return ['valeur' => 0, 'detail' => "Statut d'alerte 'OUVERT' introuvable en base."];
        }

        $nombre = $this->compter(AlerteAnomalie::class, 'e.statut = :statut', ['statut' => $statut]);

        return [
            'valeur' => $nombre,
            'unite' => 'alertes',
            'detail' => $nombre === 0 ? 'Aucune alerte en cours.' : 'Alertes equipements a traiter.',
        ];
    }

    // -----------------------------------------------------------------
    //  Direction generale
    // -----------------------------------------------------------------

    public function effectifActif(): array
    {
        $nombre = $this->compter(Employe::class, 'e.statut = :actif', ['actif' => Employe::STATUT_ACTIF]);

        return ['valeur' => $nombre, 'unite' => 'actifs', 'detail' => 'Employes en activite a ce jour.'];
    }

    public function bonsEnAttente(): array
    {
        $nombre = $this->compter(BonSortie::class, 'e.statut = :statut', ['statut' => BonSortie::STATUT_EN_ATTENTE]);

        return ['valeur' => $nombre, 'unite' => 'bons', 'detail' => 'Bons de sortie en attente de signature.'];
    }

    public function demandesNouvelles(): array
    {
        $nombre = $this->compter(DemandeAchat::class, 'e.statut IN (:statuts)', [
            'statuts' => [DemandeAchat::STATUT_NOUVELLE, DemandeAchat::STATUT_SOUMISE],
        ]);

        return ['valeur' => $nombre, 'unite' => 'demandes', 'detail' => 'Demandes achat a traiter par la direction.'];
    }

    public function activiteMensuelle(): array
    {
        $mois = $this->moisRecents(6);
        $debut = new \DateTimeImmutable('first day of -5 months');

        $courriers = array_fill_keys(array_keys($mois), 0);
        $bons = array_fill_keys(array_keys($mois), 0);
        $interventions = array_fill_keys(array_keys($mois), 0);

        $lignesCourrier = $this->em->createQuery(
            'SELECT c.createdAt AS d FROM ' . Courrier::class . ' c WHERE c.createdAt >= :debut'
        )->setParameter('debut', $debut)->getArrayResult();
        foreach ($lignesCourrier as $ligne) {
            $date = $this->enDate($ligne['d'] ?? null);
            if ($date && isset($courriers[$date->format('Y-m')])) {
                $courriers[$date->format('Y-m')]++;
            }
        }

        $lignesBons = $this->em->createQuery(
            'SELECT b.created_at AS d FROM ' . BonSortie::class . ' b WHERE b.created_at >= :debut'
        )->setParameter('debut', $debut)->getArrayResult();
        foreach ($lignesBons as $ligne) {
            $date = $this->enDate($ligne['d'] ?? null);
            if ($date && isset($bons[$date->format('Y-m')])) {
                $bons[$date->format('Y-m')]++;
            }
        }

        $lignesInterventions = $this->em->createQuery(
            'SELECT f.created_at AS d FROM ' . FicheIntervention::class . ' f WHERE f.created_at >= :debut'
        )->setParameter('debut', $debut)->getArrayResult();
        foreach ($lignesInterventions as $ligne) {
            $date = $this->enDate($ligne['d'] ?? null);
            if ($date && isset($interventions[$date->format('Y-m')])) {
                $interventions[$date->format('Y-m')]++;
            }
        }

        return $this->graphique(array_values($mois), [
            ['nom' => 'Courriers', 'donnees' => array_values($courriers)],
            ['nom' => 'Bons de sortie', 'donnees' => array_values($bons)],
            ['nom' => 'Interventions', 'donnees' => array_values($interventions)],
        ]);
    }

    // -----------------------------------------------------------------
    //  Securite / guerite
    // -----------------------------------------------------------------

    public function visiteursPresents(): array
    {
        $presents = $this->compter(Visite::class, 'e.statut = :statut', ['statut' => Visite::EN_COURS]);
        $entrees = $this->compter(Visite::class, 'e.heureEntree >= :debut', ['debut' => new \DateTimeImmutable('today')]);
        $sorties = $this->compter(Visite::class, 'e.heureSortie >= :debut', ['debut' => new \DateTimeImmutable('today')]);

        return [
            'valeur' => $presents,
            'unite' => 'visiteurs',
            'detail' => sprintf("Aujourd'hui : %d entree(s), %d sortie(s).", $entrees, $sorties),
        ];
    }

    public function visitesParJour(int $jours = 7): array
    {
        $libelles = $this->joursRecents($jours);
        $debut = new \DateTimeImmutable(sprintf('-%d days', $jours - 1));

        $lignes = $this->em->createQuery(
            'SELECT v.heureEntree AS d FROM ' . Visite::class . ' v WHERE v.heureEntree >= :debut'
        )->setParameter('debut', $debut)->getArrayResult();

        $valeurs = array_fill_keys(array_keys($libelles), 0);
        foreach ($lignes as $ligne) {
            $date = $this->enDate($ligne['d'] ?? null);
            if ($date && isset($valeurs[$date->format('Y-m-d')])) {
                $valeurs[$date->format('Y-m-d')]++;
            }
        }

        return $this->graphique(array_values($libelles), [['nom' => 'Visites', 'donnees' => array_values($valeurs)]]);
    }

    public function visitesParMotif(): array
    {
        return $this->tableau(
            'SELECT v.motif AS libelle, COUNT(v.id) AS total'
            . ' FROM ' . Visite::class . ' v'
            . ' WHERE v.heureEntree >= :debut GROUP BY v.motif ORDER BY total DESC',
            ['debut' => new \DateTimeImmutable('-30 days')],
            'libelle',
            'total',
            [],
            6
        );
    }

    public function sortiesJour(): array
    {
        $sorties = $this->compter(Visite::class, 'e.heureSortie >= :debut', ['debut' => new \DateTimeImmutable('today')]);

        return [
            'valeur' => $sorties,
            'unite' => 'sorties',
            'detail' => 'Sorties enregistrees depuis ce matin a la guerite.',
        ];
    }

    // -----------------------------------------------------------------
    //  Sante au travail (roles medicaux uniquement)
    // -----------------------------------------------------------------

    public function visitesMedicalesParMois(): array
    {
        $mois = $this->moisRecents(6);
        $debut = new \DateTimeImmutable('first day of -5 months');

        $lignes = $this->em->createQuery(
            'SELECT v.date AS d FROM ' . VisiteMedicale::class . ' v WHERE v.date >= :debut'
        )->setParameter('debut', $debut)->getArrayResult();

        $valeurs = array_fill_keys(array_keys($mois), 0);
        foreach ($lignes as $ligne) {
            $date = $this->enDate($ligne['d'] ?? null);
            if ($date && isset($valeurs[$date->format('Y-m')])) {
                $valeurs[$date->format('Y-m')]++;
            }
        }

        return $this->graphique(array_values($mois), [['nom' => 'Visites', 'donnees' => array_values($valeurs)]]);
    }

    public function aptitudes(): array
    {
        return $this->tableau(
            'SELECT v.aptitude AS libelle, COUNT(v.id) AS total'
            . ' FROM ' . VisiteMedicale::class . ' v GROUP BY v.aptitude ORDER BY total DESC'
        );
    }

    public function accidentsParGravite(): array
    {
        return $this->tableau(
            'SELECT a.gravite AS libelle, COUNT(a.id) AS total'
            . ' FROM ' . AccidentTravail::class . ' a'
            . ' WHERE a.date >= :debut GROUP BY a.gravite ORDER BY total DESC',
            ['debut' => new \DateTimeImmutable('-12 months')]
        );
    }

    public function prochainesVisites(): array
    {
        $debut = new \DateTimeImmutable('today');
        $fin = new \DateTimeImmutable('+30 days');

        $nombre = $this->compter(
            VisiteMedicale::class,
            'e.dateProchaine IS NOT NULL AND e.dateProchaine BETWEEN :debut AND :fin',
            ['debut' => $debut, 'fin' => $fin]
        );

        return [
            'valeur' => $nombre,
            'unite' => 'echeances',
            'detail' => 'Visites de controle prevues dans les 30 jours.',
        ];
    }

    public function typesVisiteMedicale(): array
    {
        return $this->tableau(
            'SELECT v.type AS libelle, COUNT(v.id) AS total'
            . ' FROM ' . VisiteMedicale::class . ' v GROUP BY v.type ORDER BY total DESC'
        );
    }

    // -----------------------------------------------------------------
    //  Encadrement (chef de service / manager)
    // -----------------------------------------------------------------

    public function absencesAValider(): array
    {
        $soumis = $this->compter(Absence::class, 'e.statut = :statut', ['statut' => Absence::SOUMIS]);
        $valides = $this->compter(Absence::class, 'e.statut = :statut', ['statut' => Absence::VALIDE_CHEF]);

        return [
            'valeur' => $soumis,
            'unite' => 'demandes',
            'detail' => sprintf('%d demande(s) deja visee(s) par un chef, en attente RH.', $valides),
        ];
    }

    // -----------------------------------------------------------------
    //  Mon espace (employe sans role fonctionnel particulier)
    // -----------------------------------------------------------------

    public function monEmploye(User $utilisateur): ?Employe
    {
        return $this->em->getRepository(Employe::class)->findOneBy(['utilisateur' => $utilisateur]);
    }

    public function monEspaceAbsences(User $utilisateur): array
    {
        $employe = $this->monEmploye($utilisateur);
        if (!$employe) {
            return ['valeur' => 0, 'detail' => "Aucune fiche employe n'est rattachee a votre compte."];
        }

        $total = $this->compter(Absence::class, 'e.employe = :employe', ['employe' => $employe]);
        $attente = $this->compter(
            Absence::class,
            'e.employe = :employe AND e.statut IN (:statuts)',
            ['employe' => $employe, 'statuts' => [Absence::SOUMIS, Absence::VALIDE_CHEF]]
        );

        return [
            'valeur' => $total,
            'unite' => 'absences',
            'detail' => $attente > 0 ? sprintf('%d en attente de validation.', $attente) : 'Toutes vos demandes sont traitees.',
        ];
    }

    public function monEspacePointages(User $utilisateur, int $jours = 14): array
    {
        $employe = $this->monEmploye($utilisateur);
        $libelles = $this->joursRecents($jours);
        $valeurs = array_fill_keys(array_keys($libelles), 0);

        if ($employe) {
            $debut = new \DateTimeImmutable(sprintf('-%d days', $jours - 1));
            $lignes = $this->em->createQuery(
                'SELECT p.jour AS jour, p.retardMinutes AS retard'
                . ' FROM ' . Pointage::class . ' p'
                . ' WHERE p.employe = :employe AND p.jour >= :debut'
            )->setParameter('employe', $employe)->setParameter('debut', $debut)->getArrayResult();

            foreach ($lignes as $ligne) {
                $jour = $this->enDate($ligne['jour'] ?? null);
                if ($jour && isset($valeurs[$jour->format('Y-m-d')])) {
                    $valeurs[$jour->format('Y-m-d')] = 1;
                }
            }
        }

        return $this->graphique(array_values($libelles), [['nom' => 'Presences', 'donnees' => array_values($valeurs)]]);
    }

    public function monEspaceFormations(User $utilisateur): array
    {
        $employe = $this->monEmploye($utilisateur);
        if (!$employe) {
            return ['valeur' => 0, 'detail' => 'Aucune fiche employe rattachee a votre compte.'];
        }

        $nombre = $this->compter(ParticipationFormation::class, 'e.employe = :employe', ['employe' => $employe]);

        return ['valeur' => $nombre, 'unite' => 'formations', 'detail' => 'Formations suivies ou planifiees.'];
    }

    public function monEspaceFiche(User $utilisateur): array
    {
        $employe = $this->monEmploye($utilisateur);
        if (!$employe) {
            return ['lignes' => [['libelle' => 'Fiche employe', 'valeur' => 'Non rattachee a ce compte']]];
        }

        $contrat = $this->em->getRepository(Contrat::class)->findOneBy(
            ['employe' => $employe, 'statut' => Contrat::EN_COURS],
            ['dateDebut' => 'DESC']
        );

        return [
            'lignes' => [
                ['libelle' => 'Matricule', 'valeur' => $employe->getMatricule() ?? '-'],
                ['libelle' => 'Service', 'valeur' => $employe->getService()?->getLibelle() ?? '-'],
                ['libelle' => 'Poste', 'valeur' => $employe->getPoste()?->getLibelle() ?? '-'],
                ['libelle' => 'Contrat', 'valeur' => $contrat ? mb_strtoupper($contrat->getType()) : 'Aucun contrat en cours'],
                ['libelle' => 'Embauche', 'valeur' => $employe->getDateEmbauche()?->format('d/m/Y') ?? '-'],
            ],
        ];
    }
}
