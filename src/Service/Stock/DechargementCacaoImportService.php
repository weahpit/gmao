<?php

namespace App\Service\Stock;

use App\Dto\Stock\LigneDechargement;
use App\Entity\Stock\DechargementCacao;
use App\Repository\Stock\DechargementCacaoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Coeur metier de l'import de dechargement de cacao :
 *   1) mapper les colonnes du fichier (par nom d'en-tete) sur les champs metier ;
 *   2) normaliser et controler chaque ligne ;
 *   3) comparer a la base (nouveau / deja present / different / doublon interne) ;
 *   4) produire un rapport (compteurs + lignes + diagnostic d'en-tete) ;
 *   5) enregistrer uniquement les lignes confirmees par l'utilisateur.
 *
 * Depuis la version 2 du module, les colonnes sont reperees par leur LIBELLE
 * (n'importe quelle lettre de colonne Excel) et non plus par une lettre fixe.
 * Un fichier dont la coopérative se trouve en colonne P, ou dont l'en-tete occupe
 * la ligne 3, est donc lu correctement : plus de faux "Code du bon manquant".
 */
final class DechargementCacaoImportService
{
    /** Nombre maximal de lignes traitees en une fois. */
    public const LIGNES_MAX = 5000;

    /**
     * Alias de colonnes : cle = nom technique, valeur = libelles acceptes (normalises).
     * L'ordre compte : le premier alias present dans le fichier gagne.
     * Les alias multi-mots sont testes en priorite ("datededechargement" avant "date").
     */
    private const COLONNES = [
        'code' => ['code', 'codebarre', 'codechar', 'ncode', 'codebon', 'numerobonchar', 'numerobon', 'numbon', 'bon'],
        'date' => ['date', 'datedechargement', 'datearrivee', 'datedubon'],
        'site' => ['site', 'sitede', 'siteexploitation'],
        'produit' => ['produit', 'produits'],
        'cooperative' => ['cooperative', 'coop', 'coopca', 'cooperativeca', 'cooperativede', 'cooperativeca'],
        'immat' => ['immat', 'immatriculation', 'matricule', 'vehicule', 'camion'],
        'transporteur' => ['transporteur', 'transporteurs', 'transitaire'],
        'nbreSacs' => ['nbresacs', 'nbsacs', 'nombresacs', 'sacs', 'nbredecr', 'nbdecr'],
        'poidsNet' => ['poidsnet', 'poids', 'poidsnet1', 'pn'],
        'statut' => ['statut', 'status', 'etat'],
        'dateDechargement' => ['datededechargement', 'datedecharge', 'datdechargement', 'datededechargeme', 'datededehargement'],
        'observation' => ['obersavation', 'observation', 'observations', 'remarque', 'observ'],
        'pnp' => ['pnp'],
        'traitant' => ['traitant', 'traitants'],
        'categorie' => ['categorie', 'categoriede', 'categoried', 'cathegorie'],
    ];

    /** @var array<string,string> */
    private const CHAMPS_LIBELLES = [
        'code' => 'Code du bon', 'date' => 'Date', 'site' => 'Site', 'produit' => 'Produit',
        'cooperative' => 'Coopérative', 'immat' => 'Immatriculation', 'transporteur' => 'Transporteur',
        'nbreSacs' => 'Nombre de sacs', 'poidsNet' => 'PoidsNet', 'statut' => 'Statut',
        'dateDechargement' => 'Date de déchargement', 'observation' => 'Observation',
        'pnp' => 'PNP', 'traitant' => 'Traitant', 'categorie' => 'Catégorie',
    ];

    /** @var array<string,string> */
    private const OBLIGATOIRES = ['code' => 'Code du bon', 'date' => 'Date', 'poidsNet' => 'PoidsNet'];

    public function __construct(
        private readonly DechargementCacaoRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    /* ==================================================================
     *  1. ANALYSE / COMPARAISON
     * ================================================================== */

    /**
     * @param array{entetes: array<string,string>, ligneEntete: int, lignes: array<int, array<string,string>>, apercu: array<int, array<string,string>>, format: string, feuille: string, nbFeuilles: int} $lecture
     *
     * @return array<string, mixed> rapport complet (JSON-ready)
     */
    /**
     * DIAGNOSTIC : montre ce que le serveur lit dans le fichier (aucune analyse metier).
     *
     * @param array<string, mixed> $lecture
     *
     * @return array<string, mixed>
     */
    public function structure(array $lecture): array
    {
        $carte = $this->carteColonnes($lecture['entetes'] ?? []);
        $colonnesAbsentes = [];
        foreach (self::OBLIGATOIRES as $obligatoire => $libelle) {
            if (!isset($carte[$obligatoire])) {
                $colonnesAbsentes[] = $libelle;
            }
        }

        return [
            'format'            => strtoupper((string) ($lecture['format'] ?? '')),
            'feuille'           => (string) ($lecture['feuille'] ?? ''),
            'nbFeuilles'        => (int) ($lecture['nbFeuilles'] ?? 1),
            'ligneEntete'       => (int) ($lecture['ligneEntete'] ?? 1),
            'nbLignes'          => count($lecture['lignes'] ?? []),
            'colonnesAbsentes'  => $colonnesAbsentes,
            'correspondance'    => $this->correspondance($carte, $lecture['entetes'] ?? []),
            'apercu'            => $this->apercu($lecture['apercu'] ?? [], $carte),
            'champs'            => self::CHAMPS_LIBELLES,
            // Lettre de la colonne actuellement associee a chaque champ (pour preparer un renommage).
            'lettres'           => $carte,
        ];
    }

    public function analyser(array $lecture): array
    {
        $carte = $this->carteColonnes($lecture['entetes'] ?? []);
        $colonnesAbsentes = [];
        foreach (self::OBLIGATOIRES as $obligatoire => $libelle) {
            if (!isset($carte[$obligatoire])) {
                $colonnesAbsentes[] = $libelle;
            }
        }

        $naturesAutorisees = $this->naturesAutorisees();

        // Controle PREALABLE : si une colonne obligatoire n'est pas reconnue, on s'arrete
        // ici avec un message qui dit exactement ce qui cloche (au lieu de rejeter
        // chacune des lignes avec "manquant" sans expliquer pourquoi).
        if ($colonnesAbsentes !== []) {
            throw new \RuntimeException(
                'Colonne(s) obligatoire(s) non reconnue(s) dans le fichier : ' . implode(', ', $colonnesAbsentes)
                . '. En-tete utilisee : ligne ' . ($lecture['ligneEntete'] ?? 1)
                . ' ; colonnes lues : ' . $this->resumeEntetes($lecture['entetes'] ?? []) . '.'
                . ' Corrigez la ligne d\'en-tete ou renommez les colonnes, puis relancez l\'analyse.'
            );
        }

        $dejaEnBase = $this->repo->indexParCode();

        $lignes = [];
        $numero = 1;

        foreach ($lecture['lignes'] as $brute) {
            if ($numero > self::LIGNES_MAX) {
                break;
            }
            $ligne = $this->normaliser($brute, $carte, $numero, $naturesAutorisees, $colonnesAbsentes);
            $lignes[] = $ligne;
            $numero++;
        }

        // Detection des doublons a l'interieur du fichier (code present 2 fois).
        $parCode = [];
        foreach ($lignes as $index => $ligne) {
            if ($ligne->code !== '') {
                $parCode[$ligne->code][] = $index;
            }
        }
        foreach ($parCode as $code => $indices) {
            if (count($indices) < 2) {
                continue;
            }
            $premier = $lignes[$indices[0]];
            for ($k = 1; $k < count($indices); $k++) {
                $doublon = $lignes[$indices[$k]];
                $memeContenu = $doublon->versTableau() === $premier->versTableau();
                $doublon->verdict = $memeContenu ? 'doublon_fichier' : 'conflit_fichier';
                $doublon->anomalies[] = $memeContenu
                    ? sprintf('Code %s deja present ligne %d du fichier (copie identique, sera ignoree).', $code, $premier->ligne)
                    : sprintf('Code %s present aussi ligne %d du fichier avec des valeurs differentes (arbitrage necessaire).', $code, $premier->ligne);
                $lignes[$indices[$k]] = $doublon;
            }
            // Un conflit interne n'est jamais enregistrable, meme sur la premiere occurrence.
            foreach ($indices as $index) {
                if ($lignes[$index]->verdict === 'conflit_fichier') {
                    foreach ($indices as $autre) {
                        if ($lignes[$autre]->verdict !== 'conflit_fichier') {
                            $lignes[$autre]->anomalies[] = 'Ce code apparait plusieurs fois avec des valeurs differentes dans le fichier.';
                        }
                    }
                    break;
                }
            }
        }

        // Comparaison avec la base.
        foreach ($lignes as $index => $ligne) {
            if (in_array($ligne->verdict, ['doublon_fichier', 'conflit_fichier'], true)) {
                continue;
            }
            $this->comparerAvecBase($ligne, $dejaEnBase[$ligne->code] ?? null);
            $lignes[$index] = $ligne;
        }

        $compteurs = [
            'total' => 0, 'nouveau' => 0, 'existant' => 0, 'modifie' => 0,
            'doublon_fichier' => 0, 'conflit_fichier' => 0, 'rejete' => 0, 'invalide' => 0,
            'poids_nouveau' => 0.0, 'poids_modifie' => 0.0, 'poids_existant' => 0.0, 'poids_rejete' => 0.0,
            'sacs_nouveau' => 0, 'ecarts_base' => 0, 'poids_ecart' => 0.0,
        ];
        foreach ($lignes as $ligne) {
            $compteurs['total']++;
            $cle = array_key_exists($ligne->verdict, $compteurs) ? $ligne->verdict : 'invalide';
            $compteurs[$cle]++;
            $poids = (float) ($ligne->poidsNet ?? 0);
            match ($cle) {
                'nouveau' => $compteurs['poids_nouveau'] += $poids,
                'modifie' => $compteurs['poids_modifie'] += $poids,
                'existant' => $compteurs['poids_existant'] += $poids,
                default => $compteurs['poids_rejete'] += $poids,
            };
            if ($cle === 'nouveau') {
                $compteurs['sacs_nouveau'] += (int) ($ligne->nbreSacs ?? 0);
            }
            if (in_array($cle, ['existant', 'modifie'], true) && $ligne->ecart !== null && abs($ligne->ecart) > 0.5) {
                $compteurs['ecarts_base']++;
                $compteurs['poids_ecart'] += $ligne->ecart;
            }
        }
        $compteurs['poids_nouveau'] = round($compteurs['poids_nouveau'], 3);
        $compteurs['poids_modifie'] = round($compteurs['poids_modifie'], 3);
        $compteurs['poids_existant'] = round($compteurs['poids_existant'], 3);
        $compteurs['poids_rejete'] = round($compteurs['poids_rejete'], 3);
        $compteurs['poids_ecart'] = round($compteurs['poids_ecart'], 3);
        $compteurs['enregistrables'] = $compteurs['nouveau'] + $compteurs['modifie'];
        $compteurs['a_controler'] = $compteurs['rejete'] + $compteurs['invalide'] + $compteurs['conflit_fichier'];

        $rapport = [
            'fichier'   => [
                'format'    => strtoupper((string) $lecture['format']),
                'feuille'   => $lecture['feuille'] ?? '',
                'nbFeuilles' => (int) ($lecture['nbFeuilles'] ?? 1),
                'ligneEntete' => (int) ($lecture['ligneEntete'] ?? 1),
                'colonnesIgnorees' => $this->colonnesIgnorees($lecture['entetes'] ?? [], $carte),
                'colonnesAbsentes' => $colonnesAbsentes,
                'correspondance' => $this->correspondance($carte, $lecture['entetes'] ?? []),
                'apercu'    => $this->apercu($lecture['apercu'] ?? [], $carte),
            ],
            'compteurs' => $compteurs,
            'lignes'    => array_map(fn (LigneDechargement $l) => $this->ligneVersTableau($l), $lignes),
        ];

        $this->logger->info('Import dechargement cacao : analyse', [
            'format'  => $lecture['format'],
            'entete'  => $lecture['ligneEntete'] ?? null,
            'carte'   => $carte,
            'total'   => $compteurs['total'],
            'nouveau' => $compteurs['nouveau'],
            'modifie' => $compteurs['modifie'],
            'existant' => $compteurs['existant'],
            'rejete'  => $compteurs['rejete'],
        ]);

        return $rapport;
    }

    /** @param array<string,string> $brute */
    private function normaliser(array $brute, array $carte, int $numero, array $natures, array $colonnesAbsentes): LigneDechargement
    {
        $l = new LigneDechargement();
        // Nettoyage : certaines cellules arrivent avec des espaces insecables (copier-coller Excel).
        foreach ($brute as $adresse => $valeur) {
            $brute[$adresse] = str_replace(["\u{00A0}", "\u{202F}", "\r", "\n"], ' ', (string) $valeur);
        }
        $l->ligne = $numero;
        $l->carte = $carte;

        $valeur = function (string $cle) use ($brute, $carte): string {
            if (!isset($carte[$cle])) {
                return '';
            }
            $v = $brute[$carte[$cle]] ?? '';

            return trim((string) $v);
        };

        $l->code = $this->nettoyerTexte($valeur('code'));
        $l->date = $this->date($valeur('date'));
        $l->site = $this->nettoyerTexte($valeur('site')) ?: null;
        $l->produit = $this->nettoyerTexte($valeur('produit')) ?: null;
        $l->cooperative = $this->nettoyerTexte($valeur('cooperative')) ?: null;
        $l->immat = $this->nettoyerTexte($valeur('immat')) ?: null;
        $l->transporteur = $this->nettoyerTexte($valeur('transporteur')) ?: null;
        $l->nbreSacs = $this->entier($valeur('nbreSacs'));
        $l->poidsNet = $this->decimal($valeur('poidsNet'));
        $l->statut = $this->nettoyerTexte($valeur('statut')) ?: null;
        $l->dateDechargement = $this->date($valeur('dateDechargement'));
        $l->observation = $this->nettoyerTexte($valeur('observation')) ?: null;
        $l->pnp = $this->decimal($valeur('pnp'));
        $l->traitant = $this->nettoyerTexte($valeur('traitant')) ?: null;
        $l->categorie = $this->nettoyerTexte($valeur('categorie')) ?: null;

        // --- Controles d'integrite ---
        if ($l->code === '') {
            $l->anomalies[] = $valeur('code') === '' && isset($carte['code'])
                ? 'Code du bon manquant (cellule vide).'
                : 'Code du bon manquant.';
        } elseif (mb_strlen($l->code) > 60) {
            $l->anomalies[] = 'Code du bon trop long (60 caracteres maximum).';
        }
        if ($l->date === null) {
            $l->anomalies[] = $valeur('date') === '' ? 'Date manquante.' : sprintf('Date illisible ("%s").', $valeur('date'));
        }
        if ($l->poidsNet === null) {
            $l->anomalies[] = $valeur('poidsNet') === '' ? 'PoidsNet manquant.' : sprintf('PoidsNet illisible ("%s").', $valeur('poidsNet'));
        } elseif ($l->poidsNet <= 0) {
            $l->anomalies[] = 'PoidsNet doit etre superieur a zero.';
        }
        if ($l->nbreSacs !== null && $l->nbreSacs < 0) {
            $l->anomalies[] = 'Nbre Sacs negatif.';
        }
        if ($l->produit !== null && $natures !== []) {
            $connu = false;
            foreach ($natures as $nature) {
                if ($this->nettoyerTexte(mb_strtolower($nature)) === $this->nettoyerTexte(mb_strtolower($l->produit))) {
                    $connu = true;
                    break;
                }
            }
            if (!$connu) {
                $l->anomalies[] = sprintf('Produit "%s" inconnu du referentiel (%s).', $l->produit, implode(', ', array_slice($natures, 0, 4)));
            }
        }
        if ($l->poidsNet !== null && $l->nbreSacs !== null && $l->nbreSacs > 0) {
            $moyenne = $l->poidsNet / $l->nbreSacs;
            if ($moyenne < 10 || $moyenne > 110) {
                $l->anomalies[] = sprintf('Poids moyen par sac anormal : %.1f kg/sac (attendu entre 10 et 110).', $moyenne);
            }
        }
        if ($l->observation !== null && stripos($l->observation, 'refoul') !== false && ($l->pnp === null || $l->pnp > 0)) {
            $l->anomalies[] = 'Observation "refoule" mais PNP non nul (coherence a verifier).';
        }
        if ($l->dateDechargement !== null && $l->date !== null && $l->dateDechargement < substr($l->date, 0, 10)) {
            $l->anomalies[] = 'Date de dechargement anterieure a la date du bon.';
        }
        if ($colonnesAbsentes !== []) {
            $l->anomalies[] = 'Colonne(s) obligatoire(s) absente(s) du fichier : ' . implode(', ', $colonnesAbsentes) . '.';
        }

        if ($l->anomalies !== []) {
            $l->verdict = 'rejete';
        }

        return $l;
    }

    private function comparerAvecBase(LigneDechargement $l, ?DechargementCacao $existant): void
    {
        if ($l->verdict === 'rejete') {
            return;
        }
        if ($existant === null) {
            $l->verdict = 'nouveau';

            return;
        }

        $champs = [
            'date'              => [$existant->getDateBon()?->format('Y-m-d H:i'), $l->date],
            'site'              => [$existant->getSite(), $l->site],
            'produit'           => [$existant->getProduit(), $l->produit],
            'cooperative'       => [$existant->getCooperative(), $l->cooperative],
            'immat'             => [$existant->getImmatriculation(), $l->immat],
            'transporteur'      => [$existant->getTransporteur(), $l->transporteur],
            'nbreSacs'          => [$existant->getNbreSacs(), $l->nbreSacs],
            'poidsNet'          => [$existant->getPoidsNet(), $l->poidsNet],
            'statut'            => [$existant->getStatut(), $l->statut],
            'dateDechargement'  => [$existant->getDateDechargement()?->format('Y-m-d'), $l->dateDechargement !== null ? substr($l->dateDechargement, 0, 10) : null],
            'observation'       => [$existant->getObservation(), $l->observation],
            'pnp'               => [$existant->getPnp(), $l->pnp],
            'traitant'          => [$existant->getTraitant(), $l->traitant],
            'categorie'         => [$existant->getCategorie(), $l->categorie],
        ];

        foreach ($champs as $label => [$avant, $apres]) {
            $normaliseAvant = is_float($avant) || is_int($avant) ? (float) $avant : $this->nettoyerTexte((string) ($avant ?? ''));
            $normaliseApres = is_float($apres) || is_int($apres) ? (float) $apres : $this->nettoyerTexte((string) ($apres ?? ''));
            if ($normaliseAvant === $normaliseApres) {
                continue;
            }
            if (is_float($normaliseAvant) && is_float($normaliseApres) && abs($normaliseAvant - $normaliseApres) < 0.001) {
                continue;
            }
            $l->modifications[] = sprintf('%s : %s -> %s', $label, $this->afficher($avant), $this->afficher($apres));
        }

        $l->idExistant = (string) $existant->getId();
        $l->ecart = $l->poidsNet !== null && $existant->getPoidsNet() !== null
            ? round($l->poidsNet - (float) $existant->getPoidsNet(), 2)
            : null;

        $l->existant = [
            'code'        => $existant->getCode(),
            'date'        => $existant->getDateBon()?->format('d/m/Y H:i'),
            'poids_net'   => $existant->getPoidsNet(),
            'nbre_sacs'   => $existant->getNbreSacs(),
            'statut'      => $existant->getStatut(),
            'observation' => $existant->getObservation(),
            'pnp'         => $existant->getPnp(),
            'modified_at' => $existant->getUpdatedAt()?->format('d/m/Y H:i'),
        ];

        $l->verdict = $l->modifications === [] ? 'existant' : 'modifie';
    }

    /* ==================================================================
     *  2. ENREGISTREMENT
     * ================================================================== */

    /**
     * Enregistre uniquement les lignes selectionnees et confirmees.
     *
     * @param array<int, array<string, mixed>> $lignes    lignes du rapport (JSON renvoye au navigateur)
     * @param string                           $empreinte empreinte du rapport affiche
     * @param string                           $utilisateur nom de l'agent connecte
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException si le fichier a change entre l'analyse et la confirmation
     */
    public function enregistrer(array $lignes, string $empreinte, string $utilisateur, string $source): array
    {
        $creees = [];
        $misesAJour = [];
        $ignorees = 0;
        $erreurs = [];

        $this->em->wrapInTransaction(function () use ($lignes, $empreinte, $utilisateur, $source, &$creees, &$misesAJour, &$ignorees, &$erreurs): void {
            // Index recalcule APRES le debut de transaction : on ne fait jamais confiance
            // au rapport affiche a l'ecran (quelqu'un a pu saisir le meme bon entre-temps).
            $index = $this->repo->indexParCode();

            foreach ($lignes as $donnees) {
                $l = $this->ligneDepuisTableau($donnees, $empreinte);
                if ($l === null) {
                    $ignorees++;
                    continue;
                }

                try {
                    $existant = $index[$l->code] ?? null;
                    if ($existant === null) {
                        $entite = new DechargementCacao();
                        $entite->setCode($l->code);
                        $entite->setCreatedBy($utilisateur);
                        $entite->setSource($source);
                        $entite->setImportUuid($empreinte);
                        $this->remplir($entite, $l);
                        $this->em->persist($entite);
                        $index[$l->code] = $entite;
                        $creees[] = $l->code;
                    } else {
                        $this->remplir($existant, $l);
                        $existant->setUpdatedBy($utilisateur);
                        $existant->touch();
                        $misesAJour[] = $l->code;
                    }
                } catch (\Throwable $e) {
                    $erreurs[] = sprintf('Ligne %d (%s) : %s', $l->ligne, $l->code ?: 'sans code', $e->getMessage());
                    $this->logger->error('Import dechargement cacao : echec ligne', ['ligne' => $l->ligne, 'code' => $l->code, 'erreur' => $e->getMessage()]);
                }
            }

            if ($erreurs !== []) {
                // Tout ou rien : une erreur sur une ligne annule l'ensemble.
                throw new \RuntimeException('Enregistrement annule. ' . implode(' | ', array_slice($erreurs, 0, 3)));
            }

            $this->em->flush();
        });

        $this->logger->info('Import dechargement cacao : enregistrement', [
            'source' => $source, 'creees' => count($creees), 'mises_a_jour' => count($misesAJour), 'ignorees' => $ignorees,
        ]);

        return [
            'creees'        => count($creees),
            'mises_a_jour'  => count($misesAJour),
            'ignorees'      => $ignorees,
            'codes_crees'   => array_slice($creees, 0, 200),
            'codes_maj'     => array_slice($misesAJour, 0, 200),
            'empreinte'     => $empreinte,
        ];
    }

    /** @param array<string,mixed> $donnees */
    private function ligneDepuisTableau(array $donnees, string $empreinte): ?LigneDechargement
    {
        $l = new LigneDechargement();
        $l->code = trim((string) ($donnees['code'] ?? ''));
        $l->ligne = (int) ($donnees['ligne'] ?? 0);
        $l->date = $donnees['date'] ?? null;
        $l->site = $donnees['site'] ?? null;
        $l->produit = $donnees['produit'] ?? null;
        $l->cooperative = $donnees['cooperative'] ?? null;
        $l->immat = $donnees['immat'] ?? null;
        $l->transporteur = $donnees['transporteur'] ?? null;
        $l->nbreSacs = isset($donnees['nbre_sacs']) ? (int) $donnees['nbre_sacs'] : null;
        $l->poidsNet = isset($donnees['poids_net']) ? (float) $donnees['poids_net'] : null;
        $l->statut = $donnees['statut'] ?? null;
        $l->dateDechargement = $donnees['date_dechargement'] ?? null;
        $l->observation = $donnees['observation'] ?? null;
        $l->pnp = isset($donnees['pnp']) ? (float) $donnees['pnp'] : null;
        $l->traitant = $donnees['traitant'] ?? null;
        $l->categorie = $donnees['categorie'] ?? null;

        if ((string) ($donnees['empreinte'] ?? '') !== $empreinte) {
            throw new \RuntimeException('Le rapport affiche ne correspond plus a la page : rechargez la page puis relancez l\'analyse.');
        }
        if ($l->code === '' || $l->date === null || $l->poidsNet === null) {
            return null;
        }

        return $l;
    }

    private function remplir(DechargementCacao $e, LigneDechargement $l): void
    {
        $e->setDateBon(new \DateTime($l->date));
        $e->setSite($l->site);
        $e->setProduit($l->produit);
        $e->setCooperative($l->cooperative);
        $e->setImmatriculation($l->immat);
        $e->setTransporteur($l->transporteur);
        $e->setNbreSacs($l->nbreSacs);
        $e->setPoidsNet($l->poidsNet);
        $e->setStatut($l->statut);
        $e->setDateDechargement($l->dateDechargement !== null ? new \DateTime($l->dateDechargement) : null);
        $e->setObservation($l->observation);
        $e->setPnp($l->pnp);
        $e->setTraitant($l->traitant);
        $e->setCategorie($l->categorie);
    }

    /* ==================================================================
     *  Outils
     * ================================================================== */

    /**
     * Associe chaque champ metier a l'ADRESSE de la cellule d'en-tete correspondante
     * ("A", "B", ...). Aucune lettre n'est supposee : tout depend du libelle.
     *
     * @param array<string,string> $entetes adresse -> libelle
     *
     * @return array<string, string> champ metier -> lettre de colonne
     */
    private function carteColonnes(array $entetes): array
    {
        // Le nombre de colonnes de l'en-tete peut varier : on ne garde que la lettre.
        $normalisees = [];
        foreach ($entetes as $adresse => $libelle) {
            $lettre = (string) (preg_replace('/[0-9]+/', '', (string) $adresse) ?: '');
            if ($lettre === '') {
                continue;
            }
            $normalisees[$lettre] = $this->normaliserLibelle((string) $libelle);
        }

        $carte = [];

        // 1) correspondance exacte
        foreach (self::COLONNES as $cle => $alias) {
            foreach ($alias as $nom) {
                foreach ($normalisees as $lettre => $libelle) {
                    if ($libelle === $nom) {
                        $carte[$cle] = $lettre;
                        continue 3;
                    }
                }
            }
        }

        // 2) correspondance souple (fautes de frappe : "Obersavation", "Poids Net", ...)
        foreach (self::COLONNES as $cle => $alias) {
            if (isset($carte[$cle])) {
                continue;
            }
            foreach ($alias as $nom) {
                foreach ($normalisees as $lettre => $libelle) {
                    if ($libelle === '' || in_array($lettre, $carte, true)) {
                        continue;
                    }
                    if (mb_strlen($nom) < 3 || mb_strlen($libelle) < 3) {
                        continue;
                    }
                    if (str_contains($libelle, $nom) || str_contains($nom, $libelle)) {
                        $carte[$cle] = $lettre;
                        continue 3;
                    }
                }
            }
        }

        return $carte;
    }

    /** @return string[] */
    private function colonnesIgnorees(array $entetes, array $carte): array
    {
        $utilisees = array_values($carte);
        $ignorees = [];
        foreach ($entetes as $adresse => $libelle) {
            $lettre = (string) (preg_replace('/[0-9]+/', '', (string) $adresse) ?: '');
            if (trim((string) $libelle) === '' || in_array($lettre, $utilisees, true)) {
                continue;
            }
            $ignorees[] = (string) $libelle;
        }

        return $ignorees;
    }

    /**
     * Tableau lisible "libelle du fichier -> champ du bon", affiche dans le rapport.
     *
     * @return array<int, array{colonne: string, libelle: string, champ: string}>
     */
    private function correspondance(array $carte, array $entetes): array
    {
        $retour = [];
        foreach ($carte as $champ => $lettre) {
            $libelle = '';
            foreach ($entetes as $adresse => $texte) {
                if ((string) (preg_replace('/[0-9]+/', '', (string) $adresse)) === $lettre) {
                    $libelle = (string) $texte;
                    break;
                }
            }
            $retour[] = [
                'colonne' => $lettre,
                'libelle' => $libelle,
                'champ'   => self::CHAMPS_LIBELLES[$champ] ?? $champ,
            ];
        }

        usort($retour, static fn (array $a, array $b): int => strnatcmp($a['colonne'], $b['colonne']));

        return $retour;
    }

    /**
     * Premieres lignes du fichier, telles qu'elles ont ete lues : sert a montrer
     * a l'utilisateur ce que le serveur voit reellement.
     *
     * @return array<int, array{rangee: string, valeurs: array<int, array{colonne: string, valeur: string}>}>
     */
    private function apercu(array $apercu, array $carte): array
    {
        $retour = [];
        $rang = 0;
        foreach ($apercu as $ligne) {
            $rang++;
            $valeurs = [];
            foreach ($ligne as $adresse => $valeur) {
                $lettre = (string) (preg_replace('/[0-9]+/', '', (string) $adresse) ?: '');
                $valeurs[] = ['colonne' => $lettre, 'valeur' => (string) $valeur];
            }
            $retour[] = ['rangee' => (string) $rang, 'valeurs' => array_slice($valeurs, 0, 16)];
        }

        return $retour;
    }

    /** "A=Code, P=Cooperative, ..." : sert au message d'erreur quand rien n'est reconnu. */
    private function resumeEntetes(array $entetes): string
    {
        $morceaux = [];
        foreach ($entetes as $adresse => $libelle) {
            $lettre = (string) (preg_replace('/[0-9]+/', '', (string) $adresse) ?: '');
            if ($lettre === '' || trim((string) $libelle) === '') {
                continue;
            }
            $morceaux[] = $lettre . '=' . trim((string) $libelle);
        }

        return $morceaux === [] ? '(aucune)' : implode(', ', array_slice($morceaux, 0, 15));
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

    private function nettoyerTexte(string $valeur): string
    {
        return preg_replace('/\s+/', ' ', trim($valeur)) ?? '';
    }

    private function date(string $valeur): ?string
    {
        $valeur = trim($valeur);
        if ($valeur === '') {
            return null;
        }
        $valeur = str_replace('/', '-', $valeur);

        // Excel peut renvoyer un nombre de serie brut (ex. 45998.71) : on le convertit
        // avant tout essai de format, sinon la date serait illisible.
        $propre = str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $valeur) ?? '');
        if (is_numeric($propre) && (float) $propre > 20000 && (float) $propre < 80000) {
            $nombre = (float) $propre;
            $jours = (int) floor($nombre);
            $minutes = (int) round(($nombre - $jours) * 1440);
            $d = (new \DateTimeImmutable('1899-12-30'))->modify('+' . $jours . ' days')->modify('+' . $minutes . ' minutes');

            return $minutes > 0 ? $d->format('Y-m-d H:i') : $d->format('Y-m-d');
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y'] as $format) {
            $d = \DateTime::createFromFormat($format, $valeur);
            if ($d !== false && $d->format($format) === $valeur) {
                return $d->format(str_contains($format, 'H') ? 'Y-m-d H:i' : 'Y-m-d');
            }
        }
        $timestamp = strtotime($valeur);
        if ($timestamp !== false && $timestamp > 0) {
            return date('Y-m-d H:i', $timestamp);
        }

        return null;
    }

    private function decimal(string $valeur): ?float
    {
        $valeur = trim($valeur);
        if ($valeur === '') {
            return null;
        }
        $valeur = str_replace([' ', "\u{00A0}", 'kg', 'KG', 'Kg'], '', $valeur);
        // 17 413,5 / 17413.5 / 17,413.5 -> 17413.5
        if (str_contains($valeur, ',') && str_contains($valeur, '.')) {
            $valeur = str_replace(',', '', $valeur);
        }
        $valeur = str_replace(',', '.', $valeur);
        if (!is_numeric($valeur)) {
            return null;
        }

        return round((float) $valeur, 3);
    }

    private function entier(string $valeur): ?int
    {
        $decimal = $this->decimal($valeur);

        return $decimal === null ? null : (int) round($decimal);
    }

    private function afficher(mixed $valeur): string
    {
        if ($valeur === null || $valeur === '') {
            return '(vide)';
        }
        if (is_float($valeur)) {
            return rtrim(rtrim(number_format($valeur, 2, ',', ' '), '0'), ',');
        }

        return (string) $valeur;
    }

    /** @return string[] */
    private function naturesAutorisees(): array
    {
        try {
            return $this->em->getRepository(\App\Entity\Production\Produit::class)->createQueryBuilder('p')
                ->select('p.libelle')
                ->getQuery()
                ->getSingleColumnResult();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return array<string, mixed> */
    public function ligneVersTableau(LigneDechargement $l): array
    {
        return [
            'ligne'             => $l->ligne,
            'code'              => $l->code,
            'date'              => $l->date,
            'site'              => $l->site,
            'produit'           => $l->produit,
            'cooperative'       => $l->cooperative,
            'immat'             => $l->immat,
            'transporteur'      => $l->transporteur,
            'nbre_sacs'         => $l->nbreSacs,
            'poids_net'         => $l->poidsNet,
            'statut'            => $l->statut,
            'date_dechargement' => $l->dateDechargement,
            'observation'       => $l->observation,
            'pnp'               => $l->pnp,
            'traitant'          => $l->traitant,
            'categorie'         => $l->categorie,
            'ecart'             => $l->ecart,
            'verdict'           => $l->verdict,
            'id_existant'       => $l->idExistant,
            'anomalies'         => $l->anomalies,
            'modifications'     => $l->modifications,
            'existant'          => $l->existant,
        ];
    }
}
