<?php

namespace App\Command;

use App\Entity\Rh\Absence;
use App\Entity\Rh\AccidentTravail;
use App\Entity\Rh\Affectation;
use App\Entity\Rh\CampagneEvaluation;
use App\Entity\Rh\Candidat;
use App\Entity\Rh\Candidature;
use App\Entity\Rh\Contrat;
use App\Entity\Rh\CritereEvaluation;
use App\Entity\Rh\DocumentRh;
use App\Entity\Rh\DossierMedical;
use App\Entity\Rh\Employe;
use App\Entity\Rh\Evaluation;
use App\Entity\Rh\Formation;
use App\Entity\Rh\OffreEmploi;
use App\Entity\Rh\ParticipationFormation;
use App\Entity\Rh\Pointage;
use App\Entity\Rh\Vaccination;
use App\Entity\Rh\VisiteMedicale;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Preparation de la base du module Ressources humaines.
 *
 *   php bin/console app:rh:installer --etat
 *   php bin/console app:rh:installer
 *   php bin/console app:rh:installer --reparer
 */
#[AsCommand(
    name: 'app:rh:installer',
    description: 'Cree (ou repare) les tables du module RH, puis affiche un diagnostic des types de colonnes.'
)]
class InstallerModuleRhCommand extends Command
{
    private const ENTITES = [
        Employe::class,
        Contrat::class,
        Affectation::class,
        OffreEmploi::class,
        Candidat::class,
        Candidature::class,
        Pointage::class,
        Absence::class,
        CampagneEvaluation::class,
        CritereEvaluation::class,
        Evaluation::class,
        Formation::class,
        ParticipationFormation::class,
        DossierMedical::class,
        VisiteMedicale::class,
        Vaccination::class,
        AccidentTravail::class,
        DocumentRh::class,
    ];

    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('etat', null, InputOption::VALUE_NONE, 'Affiche les tables et colonnes existantes, sans rien modifier.')
            ->addOption('reparer', null, InputOption::VALUE_NONE, 'Supprime les tables rh_* du module puis les recree (les donnees RH seront perdues).')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ne pose aucune question (a utiliser avec --reparer en script).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Module Ressources humaines - installation');

        if ($input->getOption('etat')) {
            return $this->afficherEtat($io);
        }

        try {
            $metadonnees = array_map(
                fn (string $classe) => $this->entityManager->getClassMetadata($classe),
                self::ENTITES
            );
        } catch (\Throwable $e) {
            $io->error('Lecture des entites RH impossible : ' . $e->getMessage());

            return Command::FAILURE;
        }

        $outil = new SchemaTool($this->entityManager);

        if ($input->getOption('reparer')) {
            if (!$input->getOption('force') && !$io->confirm('Les tables rh_* vont etre supprimees puis recreees. Continuer ?', false)) {
                $io->warning('Operation annulee. Aucune table n\'a ete modifiee.');

                return Command::SUCCESS;
            }

            try {
                $this->supprimerTables($metadonnees, $io);
                $outil->createSchema($metadonnees);
                $io->text('Tables RH recreees : ' . \count($metadonnees) . '.');
            } catch (\Throwable $e) {
                $io->error('La recreation des tables a echoue : ' . $e->getMessage());
                $io->note('Lancez « php bin/console app:rh:installer --etat » et transmettez le resultat : il indique la table dont la cle « id » n\'est pas du type attendu.');

                return Command::FAILURE;
            }
        } else {
            try {
                $outil->updateSchema($metadonnees, true);
                $io->text('Tables verifiees : ' . \count($metadonnees) . ' entites RH.');
            } catch (\Throwable $e) {
                $io->error('La creation des tables a echoue : ' . $e->getMessage());
                $io->note('Si le message parle de types incompatibles (uuid et integer), une ancienne table du meme nom existe deja. Lancez « app:rh:installer --etat » pour l\'identifier, puis « app:rh:installer --reparer » si cette ancienne table ne contient aucune donnee a garder.');

                return Command::FAILURE;
            }
        }

        return $this->afficherEtat($io);
    }

    /**
     * Supprime uniquement les tables du module (DROP ... CASCADE).
     *
     * @param array<int, \Doctrine\ORM\Mapping\ClassMetadata> $metadonnees
     */
    private function supprimerTables(array $metadonnees, SymfonyStyle $io): void
    {
        $connexion = $this->entityManager->getConnection();
        $noms = array_map(static fn ($meta) => $meta->getTableName(), $metadonnees);
        $noms = array_values(array_unique(array_filter($noms)));

        if ($noms === []) {
            return;
        }

        $liste = implode(', ', array_map([$this, 'citer'], $noms));

        $connexion->executeStatement('DROP TABLE IF EXISTS ' . $liste . ' CASCADE');
        $io->text('Tables supprimees : ' . implode(', ', $noms));
    }

    /**
     * Entoure un nom de table de guillemets doubles (syntaxe PostgreSQL).
     */
    private function citer(string $nom): string
    {
        return '"' . str_replace('"', '""', $nom) . '"';
    }

    private function afficherEtat(SymfonyStyle $io): int
    {
        /** @var Connection $connexion */
        $connexion = $this->entityManager->getConnection();

        $io->section('Colonnes « employe » et types reels en base');

        try {
            $colonnes = $connexion->fetchAllAssociative(
                "SELECT table_name, column_name, data_type
                   FROM information_schema.columns
                  WHERE table_name LIKE '%employe%'
                     OR column_name LIKE '%employe%'
                  ORDER BY table_name, column_name"
            );

            if ($colonnes === []) {
                $io->text('Aucune colonne « employe » trouvee en base.');
            } else {
                $io->table(
                    ['table', 'colonne', 'type'],
                    array_map(static fn (array $l) => [$l['table_name'], $l['column_name'], $l['data_type']], $colonnes)
                );
            }

            $io->section('Cles etrangeres liees aux employes');

            $relations = $connexion->fetchAllAssociative(
                "SELECT tc.table_name AS table_source,
                        kcu.column_name AS colonne_source,
                        cs.data_type AS type_source,
                        ccu.table_name AS table_cible,
                        ccu.column_name AS colonne_cible,
                        ct.data_type AS type_cible
                   FROM information_schema.table_constraints tc
                   JOIN information_schema.key_column_usage kcu
                     ON kcu.constraint_name = tc.constraint_name
                    AND kcu.table_schema = tc.table_schema
                   JOIN information_schema.constraint_column_usage ccu
                     ON ccu.constraint_name = tc.constraint_name
                    AND ccu.table_schema = tc.table_schema
                   JOIN information_schema.columns cs
                     ON cs.table_name = tc.table_name
                    AND cs.column_name = kcu.column_name
                   JOIN information_schema.columns ct
                     ON ct.table_name = ccu.table_name
                    AND ct.column_name = ccu.column_name
                  WHERE tc.constraint_type = 'FOREIGN KEY'
                    AND (kcu.column_name LIKE '%employe%' OR ccu.table_name LIKE '%employe%')
                  ORDER BY 1, 2"
            );

            if ($relations === []) {
                $io->text('Aucune cle etrangere liee aux employes (la base est peut-etre vide sur ce point).');
            } else {
                $io->table(
                    ['table source', 'colonne', 'type', 'table cible', 'colonne cible', 'type cible'],
                    array_map(static fn (array $l) => [
                        $l['table_source'], $l['colonne_source'], $l['type_source'],
                        $l['table_cible'], $l['colonne_cible'], $l['type_cible'],
                    ], $relations)
                );

                $incoherentes = array_filter($relations, static fn (array $l) => $l['type_source'] !== $l['type_cible']);
                if ($incoherentes !== []) {
                    $io->warning('Types incompatibles detectes : ces lignes doivent etre corrigees (voir LISEZ-MOI-RH.md).');
                }
            }
        } catch (\Throwable $e) {
            $io->error('Diagnostic impossible : ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->section('Contenu des tables du module (nombre de lignes)');

        try {
            $tables = $connexion->fetchFirstColumn(
                "SELECT table_name FROM information_schema.tables
                  WHERE table_schema = current_schema() AND table_name LIKE 'rh\\_%'
                  ORDER BY table_name"
            );

            if ($tables === []) {
                $io->text('Aucune table rh_* en base : le module n\'est pas encore installe.');
            } else {
                $lignes = [];
                foreach ($tables as $table) {
                    $quoted = $this->citer($table);
                    $total = (int) $connexion->fetchOne('SELECT COUNT(*) FROM ' . $quoted);
                    $lignes[] = [$table, $total];
                }
                $io->table(['table rh_*', 'lignes'], $lignes);
                $io->note('Une table a 0 ligne peut etre recreee sans risque avec --reparer. Si une table contient des donnees a garder, sauvegardez-la avant toute suppression.');
            }
        } catch (\Throwable $e) {
            $io->text('Nombre de lignes indisponible : ' . $e->getMessage());
        }

        $io->success('Diagnostic termine.');

        return Command::SUCCESS;
    }
}
