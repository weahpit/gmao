<?php

namespace App\Command;

use App\Services\PlanMaintenanceScheduler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Genere automatiquement les fiches d'intervention pour les echeances dues
 * des plans de maintenance preventive.
 *
 * A planifier dans un cron quotidien, par exemple :
 *   0 6 * * *  cd /chemin/du/projet && php bin/console app:plans:generer-fiches >> var/log/plans.log 2>&1
 */
#[AsCommand(
    name: 'app:plans:generer-fiches',
    description: 'Genere les fiches d\'intervention des plans de maintenance preventive arrives a echeance'
)]
class GenererFichesPreventivesCommand extends Command
{
    public function __construct(private PlanMaintenanceScheduler $scheduler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('date', null, InputOption::VALUE_OPTIONAL, 'Date butoir (Y-m-d), par defaut aujourd\'hui');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dateOpt = $input->getOption('date');
        $jusquAu = null;
        if ($dateOpt) {
            $jusquAu = \DateTime::createFromFormat('Y-m-d', $dateOpt) ?: null;
            if (!$jusquAu) {
                $io->error('Date invalide. Format attendu : Y-m-d');
                return Command::FAILURE;
            }
        }

        try {
            $nb = $this->scheduler->genererFichesDues($jusquAu, 'CRON (plan preventif)');
            $io->success($nb . ' fiche(s) d\'intervention generee(s).');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Echec : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
