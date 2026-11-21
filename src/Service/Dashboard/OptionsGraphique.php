<?php

namespace App\Service\Dashboard;

/**
 * Traduit un jeu de donnees en options ApexCharts.
 *
 * ApexCharts est deja charge par le gabarit de base du projet : aucune
 * bibliotheque supplementaire n'est necessaire.
 *
 * Les tableaux de bord metier utilisent une palette de teintes vives mais
 * sobres (emeraude, bleu, violet, rose, ambre, cyan, olive, magenta). Chaque
 * gadget recoit une teinte differente, ce qui distingue les blocs sans
 * tomber dans le multicolore. Le cacao reste la couleur de l'habillage
 * general du site.
 */
final class OptionsGraphique
{
    /**
     * Une teinte = base profonde (texte, contour), accent vif (graphique),
     * nuance claire (series secondaires) et fond tres doux (carte).
     */
    public const TEINTES = [
        ['#0f766e', '#14b8a6', '#99f6e4', '#eaf7f5'], // emeraude
        ['#1d4ed8', '#3b82f6', '#bfdbfe', '#eef4ff'], // bleu
        ['#6d28d9', '#8b5cf6', '#ddd6fe', '#f4f0ff'], // violet
        ['#be123c', '#f43f5e', '#fecdd3', '#fff1f4'], // rose
        ['#b45309', '#f59e0b', '#fde68a', '#fff8ea'], // ambre
        ['#0e7490', '#06b6d4', '#a5f3fc', '#eaf9fd'], // cyan
        ['#4d7c0f', '#84cc16', '#d9f99d', '#f6fbe9'], // olive
        ['#a21caf', '#d946ef', '#f5d0fe', '#fdf1fe'], // magenta
    ];

    /** Teinte de depart de chaque tableau de bord. */
    public const TEINTE_ROLE = [
        'DIRECTION' => 1,   // bleu
        'RH' => 0,          // emeraude
        'STOCK' => 4,       // ambre
        'STOCK_PDR' => 6,   // olive
        'LOGISTIQUE' => 5,  // cyan
        'COURRIER' => 2,    // violet
        'MAINTENANCE' => 6, // olive
        'SECURITE' => 3,    // rose
        'VISITES' => 5,     // cyan
        'SANTE' => 7,       // magenta
        'ENCADREMENT' => 1, // bleu
        'MON_ESPACE' => 2,  // violet
    ];

    /**
     * Couleur d'un gadget : teinte du role decalee du rang du gadget.
     *
     * @return array{base: string, accent: string, clair: string, douce: string, teinte: int, palette: list<string>}
     */
    public static function couleur(string $role, int $rang): array
    {
        $depart = self::TEINTE_ROLE[$role] ?? 0;
        $teinte = ($depart + $rang) % count(self::TEINTES);
        $couleurs = self::TEINTES[$teinte];

        return [
            'base' => $couleurs[0],
            'accent' => $couleurs[1],
            'clair' => $couleurs[2],
            'douce' => $couleurs[3],
            'teinte' => $teinte,
            'palette' => self::palette($teinte),
        ];
    }

    /** Teinte principale d'un tableau de bord (pastille, entete). */
    public static function couleurRole(string $role): array
    {
        $teinte = self::TEINTE_ROLE[$role] ?? 0;
        $couleurs = self::TEINTES[$teinte];

        return [
            'base' => $couleurs[0],
            'accent' => $couleurs[1],
            'douce' => $couleurs[3],
        ];
    }

    /**
     * Palette de series : la teinte du gadget puis des accents contrastes.
     *
     * @return list<string>
     */
    public static function palette(int $teinte, int $nombre = 5): array
    {
        $nombre = max(2, $nombre);
        $couleurs = [self::TEINTES[$teinte][0], self::TEINTES[$teinte][1]];
        $pas = 3;
        $i = 1;

        while (count($couleurs) < $nombre) {
            $suivante = self::TEINTES[($teinte + $i * $pas) % count(self::TEINTES)];
            foreach ([$suivante[1], $suivante[0], $suivante[2]] as $candidat) {
                if (!in_array($candidat, $couleurs, true)) {
                    $couleurs[] = $candidat;
                    break;
                }
            }
            ++$i;
            if ($i > 40) {
                break;
            }
        }

        return array_slice($couleurs, 0, $nombre);
    }

    public static function pour(array $gadget, array $donnees): array
    {
        $type = $gadget['type'] ?? 'barres';
        $teinte = (int) ($gadget['teinte'] ?? 0);
        $categories = $donnees['categories'] ?? [];
        $series = $donnees['series'] ?? [];

        $palette = self::palette($teinte, max(4, count($series) + 1));

        $base = [
            'chart' => [
                'height' => 265,
                'fontFamily' => 'DM Sans, system-ui, sans-serif',
                'toolbar' => ['show' => false],
                'animations' => ['enabled' => true, 'speed' => 500],
                'parentHeightOffset' => 0,
            ],
            'colors' => $palette,
            'dataLabels' => ['enabled' => false],
            'stroke' => ['curve' => 'smooth', 'width' => 2.6, 'lineCap' => 'round'],
            'grid' => [
                'borderColor' => '#e8ecf3',
                'strokeDashArray' => 4,
                'padding' => ['left' => 4, 'right' => 4],
            ],
            'tooltip' => ['theme' => 'light'],
            'legend' => [
                'position' => 'bottom',
                'horizontalAlign' => 'center',
                'fontSize' => '12px',
                'markers' => ['radius' => 4],
                'itemMargin' => ['horizontal' => 6, 'vertical' => 2],
            ],
            'noData' => [
                'text' => 'Aucune donnee sur la periode',
                'align' => 'center',
                'verticalAlign' => 'middle',
                'style' => ['color' => '#7a8a9a', 'fontSize' => '13px'],
            ],
        ];

        if ($type === 'donut') {
            return array_merge($base, [
                'chart' => array_merge($base['chart'], ['type' => 'donut']),
                'colors' => self::palette($teinte, max(3, count($categories))),
                'labels' => $categories,
                'series' => $series[0]['donnees'] ?? [],
                'stroke' => ['width' => 2, 'colors' => ['#ffffff']],
                'plotOptions' => [
                    'pie' => [
                        'donut' => [
                            'size' => '62%',
                            'labels' => [
                                'show' => true,
                                'name' => ['fontSize' => '12px', 'color' => '#7a8a9a'],
                                'value' => [
                                    'fontSize' => '20px',
                                    'fontWeight' => 700,
                                    'color' => '#243447',
                                ],
                                'total' => [
                                    'show' => true,
                                    'label' => 'Total',
                                    'fontSize' => '12px',
                                    'color' => '#7a8a9a',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        $options = array_merge($base, [
            'series' => $series,
            'xaxis' => [
                'categories' => $categories,
                'labels' => [
                    'style' => ['fontSize' => '11px', 'colors' => '#7a8a9a'],
                    'rotate' => count($categories) > 8 ? -35 : 0,
                    'trim' => true,
                ],
                'axisBorder' => ['color' => '#e8ecf3'],
                'axisTicks' => ['color' => '#e8ecf3'],
            ],
            'yaxis' => [
                'labels' => [
                    'style' => ['fontSize' => '11px', 'colors' => '#7a8a9a'],
                    'minWidth' => 24,
                ],
            ],
        ]);

        if ($type === 'barres') {
            $options['chart'] = array_merge($base['chart'], ['type' => 'bar']);
            $options['stroke'] = ['width' => 0];
            $options['plotOptions'] = [
                'bar' => [
                    'horizontal' => false,
                    'columnWidth' => count($series) > 1 ? '58%' : '46%',
                    'borderRadius' => 7,
                    'borderRadiusApplication' => 'end',
                    // Une seule serie : chaque barre prend une teinte de la
                    // palette, ce qui rend les rubriques immediatement
                    // identifiables.
                    'distributed' => count($series) === 1 && count($categories) <= 10,
                ],
            ];

            if (count($series) === 1 && count($categories) <= 10) {
                $options['colors'] = self::palette($teinte, max(3, count($categories)));
            }

            return $options;
        }

        if ($type === 'aire') {
            $options['chart'] = array_merge($base['chart'], ['type' => 'area']);
            $options['fill'] = [
                'type' => 'gradient',
                'gradient' => [
                    'shadeIntensity' => 1,
                    'opacityFrom' => 0.38,
                    'opacityTo' => 0.03,
                    'stops' => [0, 90, 100],
                ],
            ];

            return $options;
        }

        $options['chart'] = array_merge($base['chart'], ['type' => 'line']);
        $options['markers'] = ['size' => 4, 'strokeWidth' => 0, 'hover' => ['size' => 6]];

        return $options;
    }
}
