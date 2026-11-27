<?php

declare(strict_types=1);

namespace App\Service\Stock;

use App\Entity\Stock\SaisieStock;
use App\Entity\Stock\SortieStock;
use Doctrine\ORM\EntityManagerInterface;

/**
 * POINT DU STOCK CACAO PAR JOUR (tableau de bord de supervision).
 *
 * Pour chaque jour de la periode : entrees, sorties et point de stock du jour
 * (stock d'ouverture + entrees - sorties), par TYPE de stock (rubrique) et par
 * NATURE de produit.
 *
 * Trois lectures possibles :
 *   - MOVEMENTES : seules les rubriques ayant bouge pendant la periode ;
 *   - CACAO      : uniquement les rubriques contenant le mot « cacao » ;
 *   - TOUTES     : toutes les rubriques connues.
 *
 * Les regles de calcul sont celles de StockInitialService (inventaire +
 * mouvements posterieurs, sorties annulees ignorees, Kg et Litres separes),
 * pour que la supervision affiche les memes chiffres que la consultation.
 */
final class StockCacaoJourService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StockInitialService $stockInitial,
        private readonly ReferentielStock $referentiel,
    ) {}

    /**
     * Point de stock jour par jour.
     *
     * @param int         $jours nombre de jours affiches (1 a 60)
     * @param string|null $mode  MOVEMENTES | CACAO | TOUTES
     *
     * @return array<string, mixed>
     */
    public function pointDuJour(int $jours = 7, ?string $mode = null): array
    {
        $jours = max(1, min(60, $jours));
        $mode  = in_array($mode, ['MOVEMENTES', 'CACAO', 'TOUTES'], true) ? $mode : 'TOUTES';

        $aujourdhui = new \DateTimeImmutable('today');
        $debut      = $aujourdhui->modify('-' . ($jours - 1) . ' days');
        $fin        = $aujourdhui->modify('+1 day'); // borne exclue

        [$lignes, $litresParRubrique] = $this->lireParJour($debut, $fin);

        // 1) Types de stock retenus selon le mode.
        $mouvementes = [];
        foreach ($lignes as $l) {
            $mouvementes[$l['rubrique']] = true;
        }

        $connues = [];
        try {
            $connues = $this->referentiel->rubriques();
        } catch (\Throwable) {
        }
        foreach (array_keys($mouvementes) as $r) {
            if (!in_array($r, $connues, true)) {
                $connues[] = $r;
            }
        }

        if ($mode === 'CACAO') {
            $types = array_values(array_filter($connues, static fn (string $r): bool => mb_stripos($r, 'cacao') !== false));
        } elseif ($mode === 'TOUTES') {
            $types = array_values($connues);
        } else {
            $types = array_keys($mouvementes);
        }
        if ($types === []) {
            $types = array_values($connues);
        }

        // 2) Stock au debut de la periode = stock final actuel moins les
        //    mouvements de la periode : le point du premier jour est exact
        //    meme si le stock a ete initialise avant la periode.
        $etatActuel = [];
        try {
            foreach ($this->stockInitial->stockFinal() as $l) {
                $etatActuel[(string) $l['rubrique']] = (float) ($l['stockFinal']['kg'] ?? 0);
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException('Calcul du stock actuel impossible : '.$e->getMessage(),0,$e);
        }

        $joursListe = [];
        for ($j = $debut; $j < $fin; $j = $j->modify('+1 day')) {
            $joursListe[] = $j->format('Y-m-d');
        }

        $parRubrique   = [];
        $totalEntrees  = 0.0;
        $totalSorties  = 0.0;
        $parNatureTot  = [];

        foreach ($types as $rubrique) {
            $parJour = [];
            foreach ($lignes as $l) {
                if ($l['rubrique'] !== $rubrique) {
                    continue;
                }
                $cle = $l['date'] . '|' . $l['nature'];
                $parJour[$cle] ??= ['date' => $l['date'], 'nature' => $l['nature'], 'entrees' => 0.0, 'sorties' => 0.0, 'mouvements' => 0];
                $parJour[$cle]['entrees'] += $l['entrees'];
                $parJour[$cle]['sorties'] += $l['sorties'];
                $parJour[$cle]['mouvements'] += $l['mouvements'];
            }

            $entreesTotal = 0.0;
            $sortiesTotal = 0.0;
            $parNature    = [];
            foreach ($parJour as $m) {
                $entreesTotal += $m['entrees'];
                $sortiesTotal += $m['sorties'];
                $parNature[$m['nature']] ??= ['nature' => $m['nature'], 'entrees' => 0.0, 'sorties' => 0.0];
                $parNature[$m['nature']]['entrees'] += $m['entrees'];
                $parNature[$m['nature']]['sorties'] += $m['sorties'];
            }

            $ouverture = ($etatActuel[$rubrique] ?? 0.0) - ($entreesTotal - $sortiesTotal);

            // Le point du jour est recalcule a partir de l'ouverture : il ne
            // depend pas de l'ordre des lignes renvoyees par la base.
            $courant = $ouverture;
            $serie   = [];
            foreach ($joursListe as $cle) {
                $e = 0.0;
                $s = 0.0;
                $nb = 0;
                foreach ($parJour as $m) {
                    if ($m['date'] === $cle) {
                        $e += $m['entrees'];
                        $s += $m['sorties'];
                        $nb += $m['mouvements'];
                    }
                }
                $courant = $courant + $e - $s;
                $serie[] = [
                    'date'       => $cle,
                    'jour'       => substr($cle, 8, 2) . '/' . substr($cle, 5, 2),
                    'entrees'    => round($e, 3),
                    'sorties'    => round($s, 3),
                    'variation'  => round($e - $s, 3),
                    'stock'      => round($courant, 3),
                    'mouvements' => $nb,
                ];
            }

            $natures = array_values($parNature);
            usort($natures, static fn (array $x, array $y): int => [$y['entrees'], $y['sorties']] <=> [$x['entrees'], $x['sorties']]);

            $totalEntrees += $entreesTotal;
            $totalSorties += $sortiesTotal;

            $parRubrique[] = [
                'rubrique'  => $rubrique,
                'ouverture' => round($ouverture, 3),
                'entrees'   => round($entreesTotal, 3),
                'sorties'   => round($sortiesTotal, 3),
                'variation' => round($entreesTotal - $sortiesTotal, 3),
                'stock'     => round($courant, 3),
                'litres'    => $litresParRubrique[$rubrique] ?? 0.0,
                'jours'     => $serie,
                'natures'   => array_map(static fn (array $n): array => [
                    'nature'  => $n['nature'],
                    'entrees' => round($n['entrees'], 3),
                    'sorties' => round($n['sorties'], 3),
                    'solde'   => round($n['entrees'] - $n['sorties'], 3),
                ], $natures),
            ];
        }

        // 3) Nature du produit, tous types confondus (bas du panneau).
        foreach ($parRubrique as $r) {
            foreach ($r['natures'] as $n) {
                $parNatureTot[$n['nature']] ??= ['nature' => $n['nature'], 'entrees' => 0.0, 'sorties' => 0.0];
                $parNatureTot[$n['nature']]['entrees'] += $n['entrees'];
                $parNatureTot[$n['nature']]['sorties'] += $n['sorties'];
            }
        }
        $naturesTot = array_values($parNatureTot);
        usort($naturesTot, static fn (array $x, array $y): int => [$y['entrees'], $y['sorties']] <=> [$x['entrees'], $x['sorties']]);

        // 4) Serie globale (tous types confondus) + valeurs du jour.
        $sommeOuverture = 0.0;
        foreach ($parRubrique as $r) {
            $sommeOuverture += $r['ouverture'];
        }
        $series = [];
        $courantGlobal = $sommeOuverture;
        foreach ($joursListe as $cle) {
            $e = 0.0;
            $s = 0.0;
            foreach ($parRubrique as $r) {
                foreach ($r['jours'] as $jj) {
                    if ($jj['date'] === $cle) {
                        $e += $jj['entrees'];
                        $s += $jj['sorties'];
                        break;
                    }
                }
            }
            $courantGlobal = $courantGlobal + $e - $s;
            $series[] = [
                'date'      => $cle,
                'jour'      => substr($cle, 8, 2) . '/' . substr($cle, 5, 2),
                'entrees'   => round($e, 3),
                'sorties'   => round($s, 3),
                'variation' => round($e - $s, 3),
                'stock'     => round($courantGlobal, 3),
            ];
        }

        $duJour = $series !== [] ? $series[count($series) - 1] : ['entrees' => 0.0, 'sorties' => 0.0, 'variation' => 0.0, 'stock' => 0.0];

        // 5) Coherence : le cumul des natures doit egaler la ligne du type.
        //    On arrondit les deux cotes pour eviter les ecarts de float.
        foreach ($parRubrique as $k => $r) {
            $e = 0.0;
            $so = 0.0;
            foreach ($r['natures'] as $n) {
                $e  += (float) $n['entrees'];
                $so += (float) $n['sorties'];
            }
            $parRubrique[$k]['coherent'] = abs(round($e - $so, 3) - (float) $r['variation']) < 0.01;
        }

        return [
            'jours'      => $jours,
            'mode'       => $mode,
            'dateDebut'  => $debut->format('d/m/Y'),
            'dateFin'    => $aujourdhui->format('d/m/Y'),
            'duJour'     => $duJour,
            'periode'    => ['entrees' => round($totalEntrees, 3), 'sorties' => round($totalSorties, 3)],
            'series'     => $series,
            'rubriques'  => $parRubrique,
            'natures'    => array_map(static fn (array $n): array => [
                'nature'  => $n['nature'],
                'entrees' => round($n['entrees'], 3),
                'sorties' => round($n['sorties'], 3),
                'solde'   => round($n['entrees'] - $n['sorties'], 3),
            ], $naturesTot),
            'litres'     => array_sum($litresParRubrique),
            'unite'      => 'Kg',
        ];
    }

    /**
     * Mouvements agreges par jour / rubrique / nature (Kg).
     *
     * Les sorties annulees sont ignorees, comme dans StockInitialService ; les
     * quantites en Litres sont tenues a part et ne faussent pas le point en Kg.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, float>}
     */
    private function lireParJour(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $lignes = [];
        $litres = [];

        // SQL DBAL : DATE() n’est pas enregistré dans le DQL de ce projet.
        // Une erreur est remontée à l’interface, jamais remplacée par des zéros.
        foreach ([SaisieStock::class=>'dateSaisie',SortieStock::class=>'dateSortie'] as $classe=>$champDate) {
            $m=$this->em->getClassMetadata($classe);$platform=$this->em->getConnection()->getDatabasePlatform();
            $q=static fn($v)=>$platform->quoteIdentifier($v);
            $table=$m->getSchemaName() && !str_contains($m->getTableName(),'.') ? $m->getSchemaName().'.'.$m->getTableName():$m->getTableName();
            $date=$q($m->getColumnName($champDate));$rubrique=$q($m->getColumnName('rubrique'));$nature=$q($m->getColumnName('nature'));$unite=$q($m->getColumnName('unite'));$quantite=$q($m->getColumnName('quantite'));
            $sql='SELECT CAST('.$date.' AS DATE) AS jour, '.$rubrique.' AS rubrique, '.$nature.' AS nature, '.$unite.' AS unite, SUM('.$quantite.') AS qte, COUNT(*) AS nb FROM '.$q($table).' WHERE '.$date.' >= :debut AND '.$date.' < :fin';
            $params=['debut'=>$debut->format('Y-m-d'),'fin'=>$fin->format('Y-m-d')];$sortie=$classe===SortieStock::class;
            if($sortie){$statut=$q($m->getColumnName('statut'));$sql.=' AND ('.$statut.' IS NULL OR '.$statut.' <> :annule)';$params['annule']=SortieStock::STATUT_ANNULE;}
            $sql.=' GROUP BY CAST('.$date.' AS DATE), '.$rubrique.', '.$nature.', '.$unite;
            foreach($this->em->getConnection()->fetchAllAssociative($sql,$params) as $row){
                $r=(string)$row['rubrique'];$kg=(float)$row['qte'];
                if($row['unite']==='Litres'){$litres[$r]=($litres[$r]??0)+($sortie?-$kg:$kg);continue;}
                $lignes[]=['date'=>(string)$row['jour'],'rubrique'=>$r,'nature'=>$this->natureLisible($row['nature']),'entrees'=>$sortie?0:$kg,'sorties'=>$sortie?$kg:0,'mouvements'=>(int)$row['nb']];
            }
        }

        return [array_values(array_filter($lignes, static fn (array $l): bool => $l['rubrique'] !== '')), $litres];
    }

    private function natureLisible(?string $nature): string
    {
        $nature = trim((string) $nature);

        return $nature !== '' ? $nature : 'Nat. non precisee';
    }
}
