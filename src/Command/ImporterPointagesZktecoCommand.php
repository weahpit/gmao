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
            ->addOption('simulation', null, InputOption::VALUE_NONE, 'Affiche ce qui serait importe sans enregistrer en base.')
            ->addOption('tolerance', null, InputOption::VALUE_REQUIRED, 'Minutes de retard ignorees apres l\'heure d\'arrivee (defaut 5).', '5')
            ->addOption('lot', null, InputOption::VALUE_REQUIRED, 'Nombre de pointages enregistres par flush (defaut 200).', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pointages ZKTECO');

        if ((bool) $input->getOption('diagnostic')) {
            return $this->diagnostic($io);
        }

        $configuration = $this->zkteco->configuration();
        $io->definitionList(
            ['Base / DSN' => $configuration['ZKTECO_ACCESS_DSN'] ?: ($configuration['ZKTECO_ACCESS_FICHIER'] ?: 'non configure')],
            ['Table employes' => $configuration['ZKTECO_TABLE_USERS']],
            ['Table pointages' => $configuration['ZKTECO_TABLE_POINTAGES']],
            ['Heure d\'arrivee' => $configuration['ZKTECO_HEURE_ARRIVEE']],
            ['Heure de depart' => $configuration['ZKTECO_HEURE_DEPART']],
        );

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
                : $this->zkteco->pointages($du, $au);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            $io->note('Pour verifier la connexion et les noms de colonnes : php bin/console app:pointage:zkteco --diagnostic');

            return Command::FAILURE;
        }

        if ($csv !== '') {
            $passages = array_values(array_filter($passages, static fn (array $p) => $p['quand'] >= $du && $p['quand'] <= $au->modify('+1 day')));
        }

        if ($passages === []) {
            $io->warning('Aucun passage trouve entre le ' . $du->format('d/m/Y') . ' et le ' . $au->format('d/m/Y') . '.');
            $io->note('Si la pointeuse enregistre bien des passages, verifiez les noms de colonnes avec --diagnostic, ou exportez un CSV.');

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
            'Passages lus : ' . count($passages),
            'Journees traitees : ' . $traites,
            $simulation ? 'Simulation : aucune ecriture en base.' : 'Pointages crees : ' . $crees . ' / mis a jour : ' . $maj,
        ]);

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
        $rapport = $this->zkteco->diagnostic();

        if (!$rapport['connecte']) {
            $io->error($rapport['message']);
            $io->note('Solution de repli : exportez les pointages en CSV depuis le logiciel ZKTECO, puis lancez la commande avec --csv=chemin/du/fichier.csv');

            return Command::FAILURE;
        }

        $io->success($rapport['message']);

        foreach ($rapport['tables'] as $table => $colonnes) {
            $io->section('Table ' . $table);
            $io->listing($colonnes === [] ? ['(aucune colonne lisible)'] : $colonnes);
        }

        $configuration = $this->zkteco->configuration();
        $tables = array_map('strtolower', array_keys($rapport['tables']));

        foreach ([
            'ZKTECO_TABLE_USERS' => $configuration['ZKTECO_TABLE_USERS'],
            'ZKTECO_TABLE_POINTAGES' => $configuration['ZKTECO_TABLE_POINTAGES'],
        ] as $variable => $valeur) {
            if (!in_array(strtolower($valeur), $tables, true)) {
                $io->warning('La table « ' . $valeur . ' » (' . $variable . ') n\'existe pas dans ce fichier. Choisissez un nom parmi ceux affiches ci-dessus, puis mettez a jour le fichier .env.local.');
            }
        }

        $io->note('Si les noms de colonnes different, ajustez ZKTECO_COL_USERID, ZKTECO_COL_BADGE, ZKTECO_COL_NOM, ZKTECO_COL_CHECKTIME et ZKTECO_COL_CHECKTYPE dans .env.local.');

        return Command::SUCCESS;
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
