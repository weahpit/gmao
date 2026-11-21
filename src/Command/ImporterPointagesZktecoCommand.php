<?php

namespace App\Command;

use App\Entity\Rh\Employe;
use App\Entity\Rh\Pointage;
use App\Repository\Rh\PointageRepository;
use App\Service\Pointage\ZktecoAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Recupere les passages enregistres par la pointeuse ZKTECO et alimente les
 * pointages du module RH (arrivee, depart, retard, heures supplementaires).
 *
 *   php bin/console app:pointage:zkteco --diagnostic
 *   php bin/console app:pointage:zkteco --du=2026-11-01 --au=2026-11-20 --simulation
 *   php bin/console app:pointage:zkteco --du=2026-11-01 --au=2026-11-20
 *   php bin/console app:pointage:zkteco --csv=var/zkteco/export.csv --du=2026-11-01 --au=2026-11-20
 */
#[AsCommand(
    name: 'app:pointage:zkteco',
    description: 'Importe les pointages de la pointeuse ZKTECO (base Access ou export CSV) dans le module RH.'
)]
class ImporterPointagesZktecoCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private PointageRepository $pointages,
        private ZktecoAccess $zkteco,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('du', null, InputOption::VALUE_REQUIRED, 'Premier jour a importer (AAAA-MM-JJ). Defaut : aujourd\'hui.')
            ->addOption('au', null, InputOption::VALUE_REQUIRED, 'Dernier jour a importer (AAAA-MM-JJ). Defaut : aujourd\'hui.')
            ->addOption('diagnostic', null, InputOption::VALUE_NONE, 'Affiche les tables et colonnes de la base Access, sans rien importer.')
            ->addOption('csv', null, InputOption::VALUE_REQUIRED, 'Lit un export CSV de la pointeuse au lieu de la base Access.')
            ->addOption('fichier', null, InputOption::VALUE_REQUIRED, 'Chemin du fichier Access pour cet essai (remplace ZKTECO_ACCESS_FICHIER sans modifier .env).')
            ->addOption('dsn', null, InputOption::VALUE_REQUIRED, 'Nom du DSN ODBC pour cet essai (remplace ZKTECO_ACCESS_DSN).')
            ->addOption('verifier', null, InputOption::VALUE_NONE, 'Controle complet : fichier, volumes, plage de dates, badges. A utiliser quand l\'import ne remonte rien.')
            ->addOption('sans-requete', null, InputOption::VALUE_NONE, 'Ignore la requete Access (Req_UserCheck) et lit directement les tables USERINFO / CHECKINOUT.')
            ->addOption('simulation', null, InputOption::VALUE_NONE, 'Affiche ce qui serait importe sans enregistrer en base.')
            ->addOption('tolerance', null, InputOption::VALUE_REQUIRED, 'Minutes de retard ignorees apres l\'heure d\'arrivee (defaut 5).', '5')
            ->addOption('lot', null, InputOption::VALUE_REQUIRED, 'Nombre de pointages enregistres par flush (defaut 200).', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pointages ZKTECO');

        foreach (['ZKTECO_ACCESS_FICHIER' => 'fichier', 'ZKTECO_ACCESS_DSN' => 'dsn'] as $cle => $option) {
            $valeur = trim((string) $input->getOption($option));
            if ($valeur !== '') {
                $this->zkteco->setSurcharge($cle, $valeur);
            }
        }

        if ((bool) $input->getOption('diagnostic') || (bool) $input->getOption('verifier')) {
            return $this->diagnostic($io);
        }

        $configuration = $this->zkteco->configuration();
        $io->definitionList(
            ['Base / DSN' => $configuration['ZKTECO_ACCESS_DSN'] ?: ($configuration['ZKTECO_ACCESS_FICHIER'] ?: 'non configure')],
            ['Requete Access' => $configuration['ZKTECO_REQUETE'] ?: 'aucune'],
            ['Table employes' => $configuration['ZKTECO_TABLE_USERS']],
            ['Table pointages' => $configuration['ZKTECO_TABLE_POINTAGES']],
            ['Heure d\'arrivee' => $configuration['ZKTECO_HEURE_ARRIVEE']],
            ['Heure de depart' => $configuration['ZKTECO_HEURE_DEPART']],
        );

        if ($configuration['ZKTECO_ACCESS_DSN'] === '' && $configuration['ZKTECO_ACCESS_FICHIER'] !== '') {
            $etat = $this->zkteco->etatFichier();
            $io->text([
                'Fichier Access : ' . $etat['fichier'],
                '  Existe : ' . ($etat['existe'] ? 'oui' : 'NON')
                . ' | Lisible : ' . ($etat['lisible'] ? 'oui' : 'non')
                . ($etat['existe'] ? ' | ' . number_format($etat['taille'] / 1024, 0, ',', ' ') . ' Ko | modifie le ' . $etat['modifie'] : ''),
            ]);
            if ($etat['message'] !== '') {
                $io->note($etat['message']);
            }
        }

        $du = $this->dateOption((string) $input->getOption('du'), new \DateTimeImmutable('today'));
        $au = $this->dateOption((string) $input->getOption('au'), $du);
        if ($au < $du) {
            $io->error('La date de fin (' . $au->format('d/m/Y') . ') est anterieure a la date de debut (' . $du->format('d/m/Y') . ').');

            return Command::FAILURE;
        }

        $csv = trim((string) $input->getOption('csv'));
        try {
            $passages = $csv !== ''
                ? $this->zkteco->pointagesDepuisCsv($csv)
                : $this->zkteco->pointages($du, $au, (bool) $input->getOption('sans-requete'));
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            $io->note('Pour verifier la connexion et les noms de colonnes : php bin/console app:pointage:zkteco --diagnostic');

            return Command::FAILURE;
        }

        if ($csv !== '') {
            $fin = $au->modify('+1 day');
            $passages = array_values(array_filter($passages, static fn (array $p) => $p['quand'] >= $du && $p['quand'] < $fin));
        }

        if ($passages === []) {
            $io->warning('Aucun passage trouve entre le ' . $du->format('d/m/Y') . ' et le ' . $au->format('d/m/Y') . '.');
            if ($csv !== '') {
                $io->note(
                    'Le fichier « ' . $csv . ' » ne contient aucun passage sur cette periode.'
                    . ' Verifiez le separateur (; ou ,), la presence d\'une ligne d\'en-tete avec les colonnes'
                    . ' badge / nom / date heure (ou checktime), et la periode demandee.'
                );
            } else {
                $this->expliquerImportVide($io, $du, $au);
            }

            return Command::SUCCESS;
        }

        $mapEmployes = $this->mapEmployes();
        $simulation = (bool) $input->getOption('simulation');
        $tolerance = max(0, (int) $input->getOption('tolerance'));
        $tailleLot = max(1, (int) $input->getOption('lot'));
        [$debutJournee, $finJournee] = $this->zkteco->bornesJournee();

        /** @var array<string, array{employe: Employe, jour: string, arrivee: \DateTimeImmutable, depart: ?\DateTimeImmutable}> $journees */
        $journees = [];
        $badgesInconnus = [];

        foreach ($passages as $passage) {
            $badge = $passage['badge'];
            $employe = $this->trouverEmploye($mapEmployes, $badge);

            if ($employe === null) {
                if (count($badgesInconnus) < 40) {
                    $badgesInconnus[$badge] = $passage['nom'] !== '' ? $passage['nom'] : '(sans nom)';
                }

                continue;
            }

            $cle = $employe->getId() . '|' . $passage['quand']->format('Y-m-d');
            $jour = $passage['quand']->format('Y-m-d');

            if (!isset($journees[$cle])) {
                $journees[$cle] = ['employe' => $employe, 'jour' => $jour, 'arrivee' => $passage['quand'], 'depart' => null];

                continue;
            }

            if ($passage['quand'] < $journees[$cle]['arrivee']) {
                $journees[$cle]['arrivee'] = $passage['quand'];
            }
            if ($journees[$cle]['depart'] === null || $passage['quand'] > $journees[$cle]['depart']) {
                $journees[$cle]['depart'] = $passage['quand'];
            }
        }

        $crees = 0;
        $maj = 0;
        $traites = 0;
        $lignes = [];

        foreach ($journees as $donnee) {
            /** @var Employe $employe */
            $employe = $donnee['employe'];
            $jour = new \DateTimeImmutable($donnee['jour']);

            $arrivee = $donnee['arrivee'];
            $depart = $donnee['depart'];

            // Un seul passage dans la journee : on ne fabrique pas un depart.
            if ($depart !== null && $depart <= $arrivee) {
                $depart = null;
            }

            $minutesArrivee = (int) $arrivee->format('H') * 60 + (int) $arrivee->format('i');
            $minutesDepart = $depart !== null ? (int) $depart->format('H') * 60 + (int) $depart->format('i') : null;

            $retard = max(0, $minutesArrivee - $debutJournee - $tolerance);
            $supplementaires = $minutesDepart !== null ? max(0, $minutesDepart - $finJournee) : 0;

            $heureArrivee = \DateTime::createFromFormat('H:i:s', $arrivee->format('H:i:s')) ?: null;
            $heureDepart = $depart !== null ? (\DateTime::createFromFormat('H:i:s', $depart->format('H:i:s')) ?: null) : null;

            $lignes[] = [
                $employe->getMatricule() ?: '-',
                $employe->getNomComplet(),
                $jour->format('d/m/Y'),
                $arrivee->format('H:i'),
                $depart !== null ? $depart->format('H:i') : '-',
                $retard > 0 ? $retard . ' min' : '-',
                $supplementaires > 0 ? $supplementaires . ' min' : '-',
            ];

            ++$traites;

            if ($simulation) {
                continue;
            }

            $pointage = $this->pointages->findUnPourEmployeEtJour($employe, $jour);
            if ($pointage === null) {
                $pointage = (new Pointage())->setEmploye($employe)->setJour($jour);
                $this->em->persist($pointage);
                ++$crees;
            } else {
                ++$maj;
            }

            $pointage
                ->setHeureArrivee($heureArrivee)
                ->setHeureDepart($heureDepart ?? $pointage->getHeureDepart())
                ->setRetardMinutes($retard)
                ->setHeuresSupplementaires($supplementaires)
                ->setCommentaire('POINTAGE ZKTECO - BADGE ' . ($donnee['employe']->getCodePointage() ?: ($donnee['employe']->getMatricule() ?: 'INCONNU')));

            if ($traites % $tailleLot === 0) {
                $this->em->flush();
            }
        }

        if (!$simulation) {
            $this->em->flush();
        }

        $io->table(
            ['Matricule', 'Employe', 'Jour', 'Arrivee', 'Depart', 'Retard', 'Supp. (min)'],
            array_slice($lignes, 0, 200)
        );
        if (count($lignes) > 200) {
            $io->note('Seules les 200 premieres lignes sont affichees (' . count($lignes) . ' au total).');
        }

        $io->success([
            'Source lue : ' . ($this->zkteco->sourceUtilisee() ?: 'inconnue'),
            'Passages lus : ' . count($passages),
            'Journees traitees : ' . $traites,
            $simulation ? 'Simulation : aucune ecriture en base.' : 'Pointages crees : ' . $crees . ' / mis a jour : ' . $maj,
            'Filtre de dates retenu : ' . ($this->zkteco->methodeFiltre() ?: 'aucun'),
        ]);

        if ($this->zkteco->journal() !== []) {
            $io->text($this->zkteco->journal());
        }

        if ($badgesInconnus !== []) {
            $io->warning(
                count($badgesInconnus) . ' badge(s) sans employe correspondant : renseignez le champ'
                . ' « code de pointage » dans la fiche employe (module RH > Employes).'
            );
            $io->listing(array_map(
                static fn (string $badge, string $nom) => $badge . ' — ' . $nom,
                array_keys($badgesInconnus),
                $badgesInconnus
            ));
        }

        return Command::SUCCESS;
    }

    private function diagnostic(SymfonyStyle $io): int
    {
        $rapport = $this->zkteco->verifierSource();

        $io->section('Environnement');
        $io->definitionList(
            ['Systeme' => $rapport['os']],
            ['Pilotes PDO' => implode(', ', $rapport['drivers']) ?: 'aucun'],
            ['DSN ODBC' => $rapport['dsn'] !== '' ? $rapport['dsn'] : '(non utilise)'],
            ['Fichier Access' => $rapport['fichier']['fichier'] !== '' ? $rapport['fichier']['fichier'] : '(non configure)'],
        );

        $etat = $rapport['fichier'];
        if ($etat['brut'] !== '') {
            $io->text([
                'Existe : ' . ($etat['existe'] ? 'oui' : 'NON')
                . ' | Lisible : ' . ($etat['lisible'] ? 'oui' : 'non')
                . ($etat['existe'] ? ' | ' . number_format($etat['taille'] / 1024, 0, ',', ' ') . ' Ko'
                    . ' | modifie le ' . $etat['modifie'] : ''),
            ]);
            if ($etat['message'] !== '') {
                $io->note($etat['message']);
            }
        }

        if (!$rapport['connexion']) {
            $io->error($rapport['message']);
            $io->note('Verifiez le chemin et les droits, ou exportez les pointages en CSV puis relancez avec --csv=chemin/du/fichier.csv.');

            return Command::FAILURE;
        }

        $io->success($rapport['message']);

        foreach ($rapport['volumes'] as $libelle => $volume) {
            if (isset($volume['erreur'])) {
                $io->warning('Table ' . $volume['table'] . ' (' . $libelle . ') : ' . $volume['erreur']);
            } else {
                $io->text('Table ' . $volume['table'] . ' (' . $libelle . ') : ' . number_format($volume['lignes'], 0, ',', ' ') . ' ligne(s).');
            }
        }

        if (isset($rapport['plage']['debut'])) {
            $io->text('Periode couverte par la pointeuse : du ' . $rapport['plage']['debut'] . ' au ' . $rapport['plage']['fin'] . '.');
        } elseif (isset($rapport['plage']['erreur'])) {
            $io->warning('Plage de dates illisible : ' . $rapport['plage']['erreur']);
        }

        if ($rapport['requete'] !== []) {
            if (isset($rapport['requete']['erreur'])) {
                $io->warning('Requete ' . $rapport['requete']['nom'] . ' : ' . $rapport['requete']['erreur']);
            } else {
                $io->text('Requete ' . $rapport['requete']['nom'] . ' : ' . number_format($rapport['requete']['lignes'], 0, ',', ' ') . ' ligne(s).');
            }
        }

        // Inventaire des tables (noms et colonnes reels)
        $inventaire = $this->zkteco->diagnostic();
        if ($inventaire['connecte']) {
            foreach ($inventaire['tables'] as $table => $colonnes) {
                if (in_array(strtolower($table), ['userinfo', 'checkinout'], true)) {
                    $io->section('Table ' . $table);
                    $io->listing($colonnes === [] ? ['(aucune colonne lisible)'] : $colonnes);
                }
            }

            $tables = array_map('strtolower', array_keys($inventaire['tables']));
            $configuration = $this->zkteco->configuration();
            foreach ([
                'ZKTECO_TABLE_USERS' => $configuration['ZKTECO_TABLE_USERS'],
                'ZKTECO_TABLE_POINTAGES' => $configuration['ZKTECO_TABLE_POINTAGES'],
            ] as $variable => $valeur) {
                if (!in_array(strtolower($valeur), $tables, true)) {
                    $io->warning('La table « ' . $valeur . ' » (' . $variable . ') n\'existe pas dans ce fichier.');
                }
            }
        }

        // Badges cote pointeuse et cote RH : la comparaison explique la
        // plupart des imports « vides » alors que la base contient des passages.
        if ($rapport['echantillon'] !== []) {
            $io->section('Badges lus dans la pointeuse (5 premiers)');
            $lignes = [];
            foreach ($rapport['echantillon'] as $badge) {
                if (isset($badge['erreur'])) {
                    $lignes[] = 'Lecture impossible : ' . $badge['erreur'];
                    continue;
                }
                $lignes[] = trim((string) ($badge['badge'] ?? '')) . ' — ' . trim((string) ($badge['nom'] ?? ''));
            }
            $io->listing($lignes);
        }

        $employes = $this->mapEmployes();
        $codes = [];
        foreach ($employes as $code => $employe) {
            if (count($codes) >= 8) {
                break;
            }
            $codes[] = $code . ' — ' . $employe->getNomComplet();
        }
        $io->section('Codes de pointage / matricules cote RH (8 premiers)');
        $io->listing($codes === [] ? ['Aucun employe actif avec un code de pointage ou un matricule.'] : $codes);
        $io->note('Un badge de la pointeuse doit correspondre exactement a un code de pointage (ou a un matricule) de la fiche employe. Sinon, renseignez « Code de pointage » dans la fiche.');

        foreach ($this->zkteco->journal() as $etape) {
            $io->text('• ' . $etape);
        }

        return Command::SUCCESS;
    }

    /**
     * Explique un import vide : etat du fichier, volumes, plage de dates
     * reellement presente, et suggestion de periode.
     */
    private function expliquerImportVide(SymfonyStyle $io, \DateTimeImmutable $du, \DateTimeImmutable $au): void
    {
        foreach ($this->zkteco->journal() as $etape) {
            $io->text('• ' . $etape);
        }

        $rapport = $this->zkteco->verifierSource();
        if (!$rapport['connexion']) {
            $io->error($rapport['message']);

            return;
        }

        foreach ($rapport['volumes'] as $libelle => $volume) {
            if (!isset($volume['erreur'])) {
                $io->text(ucfirst($libelle) . ' : ' . number_format($volume['lignes'], 0, ',', ' ') . ' ligne(s) dans ' . $volume['table'] . '.');
            }
        }

        $vide = true;
        foreach ($rapport['volumes'] as $volume) {
            if (!isset($volume['erreur']) && (int) $volume['lignes'] > 0) {
                $vide = false;
                break;
            }
        }
        if ($vide) {
            $io->warning('Les tables lues ne contiennent aucune ligne : le fichier Access n\'est probablement pas celui de la pointeuse.');
        }

        $debut = substr((string) ($rapport['plage']['debut'] ?? ''), 0, 10);
        $fin = substr((string) ($rapport['plage']['fin'] ?? ''), 0, 10);
        if ($debut !== '') {
            $io->text('La pointeuse contient des passages du ' . $rapport['plage']['debut'] . ' au ' . $rapport['plage']['fin'] . '.');
            $io->note(
                'Periode demandee : du ' . $du->format('d/m/Y') . ' au ' . $au->format('d/m/Y') . '.'
                . ' Relancez avec la plage reellement presente : --du=' . $debut . ' --au=' . $fin
            );
        } else {
            $io->note('Periode demandee : du ' . $du->format('d/m/Y') . ' au ' . $au->format('d/m/Y') . '.');
        }

        $io->note(
            'Si la periode est correcte : verifiez que le fichier Access est bien celui de la pointeuse'
            . ' (--verifier affiche les volumes et les badges), puis exportez un CSV depuis ZKTECO'
            . ' et relancez avec --csv=chemin/du/fichier.csv.'
        );
    }

    /**
     * Table de correspondance badge / matricule -> employe.
     *
     * @return array<string, Employe>
     */
    private function mapEmployes(): array
    {
        $employes = $this->em->getRepository(Employe::class)->createQueryBuilder('e')
            ->andWhere('e.statut != :sorti')
            ->setParameter('sorti', Employe::STATUT_SORTI)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($employes as $employe) {
            foreach ([$employe->getCodePointage(), $employe->getMatricule()] as $valeur) {
                $cle = $this->normaliserBadge($valeur);
                if ($cle !== '') {
                    $map[$cle] = $employe;
                }
            }
        }

        return $map;
    }

    /** @param array<string, Employe> $map */
    private function trouverEmploye(array $map, string $badge): ?Employe
    {
        foreach ([$badge, ltrim($badge, '0')] as $essai) {
            $cle = $this->normaliserBadge($essai);
            if ($cle !== '' && isset($map[$cle])) {
                return $map[$cle];
            }
        }

        return null;
    }

    private function normaliserBadge(?string $badge): string
    {
        return mb_strtoupper(trim((string) $badge), 'UTF-8');
    }

    private function dateOption(string $valeur, \DateTimeImmutable $defaut): \DateTimeImmutable
    {
        $valeur = trim($valeur);
        if ($valeur === '') {
            return $defaut->setTime(0, 0);
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $valeur);

        return $date instanceof \DateTimeImmutable ? $date->setTime(0, 0) : $defaut->setTime(0, 0);
    }
}
