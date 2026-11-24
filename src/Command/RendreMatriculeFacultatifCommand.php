<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Corrige uniquement la contrainte NULL, sans recreer les tables RH. */
#[AsCommand(name: 'app:rh:matricule-facultatif', description: 'Verifie ou rend nullable le matricule employe, sans supprimer les donnees.')]
final class RendreMatriculeFacultatifCommand extends Command
{
    public function __construct(private Connection $connexion)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('appliquer', null, InputOption::VALUE_NONE, 'Applique le changement SQL affiche (apres sauvegarde de la base).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $schema = $this->connexion->createSchemaManager();
            if (!$schema->tablesExist(['rh_employe'])) {
                $io->error('Table rh_employe absente : installez le module RH avant cette correction.');
                return Command::FAILURE;
            }
            $avant = $schema->introspectTable('rh_employe');
            if (!$avant->hasColumn('matricule')) {
                $io->error('Colonne matricule absente de rh_employe.');
                return Command::FAILURE;
            }
            if (!$avant->getColumn('matricule')->getNotnull()) {
                $io->success('Le matricule est deja facultatif dans la base. Aucun changement.');
                return Command::SUCCESS;
            }
            $apres = $schema->introspectTable('rh_employe');
            $apres->getColumn('matricule')->setNotnull(false);
            $diff = $schema->createComparator()->compareTables($avant, $apres);
            $sql = $this->connexion->getDatabasePlatform()->getAlterTableSQL($diff);
            $io->listing($sql);
            if (!$input->getOption('appliquer')) {
                $io->note('Diagnostic seulement. Sauvegardez la base puis relancez avec --appliquer. Aucun employe ne sera supprime.');
                return Command::SUCCESS;
            }
            foreach ($sql as $requete) {
                $this->connexion->executeStatement($requete);
            }
            $controle = $schema->introspectTable('rh_employe');
            if ($controle->getColumn('matricule')->getNotnull()) {
                $io->error('La contrainte obligatoire est encore presente.');
                return Command::FAILURE;
            }
            $io->success('Matricule facultatif. Les donnees et la contrainte d’unicite sont conservees.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Modification impossible : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
