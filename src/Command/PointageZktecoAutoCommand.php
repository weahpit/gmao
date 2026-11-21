<?php

namespace App\Command;

use App\Service\Pointage\ZktecoParametres;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Import automatique des pointages ZKTECO, pilote par la page de connexion.
 *
 *   php bin/console app:pointage:zkteco:auto
 *   php bin/console app:pointage:zkteco:auto --jours=7 --force
 *
 * A planifier avec le Planificateur de taches Windows (ou cron) sur le poste
 * qui voit le partage reseau de la pointeuse. Ne fait rien si l'import
 * automatique n'est pas active dans l'interface, sauf avec --force.
 */
#[AsCommand(
    name: 'app:pointage:zkteco:auto',
    description: 'Importe automatiquement les pointages ZKTECO selon les parametres enregistres dans l\'interface.',
    aliases: ['app:zkteco:auto']
)]
class PointageZktecoAutoCommand extends Command
{
    public function __construct(private ZktecoParametres $parametres)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('jours', null, InputOption::VALUE_REQUIRED, 'Nombre de jours a reprendre (defaut : valeur enregistree, sinon 3).')
            ->addOption('du', null, InputOption::VALUE_REQUIRED, 'Premier jour a importer (AAAA-MM-JJ), prioritaire sur --jours.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Importe meme si l\'import automatique est desactive dans l\'interface.')
            ->addOption('silence', null, InputOption::VALUE_NONE, 'N\'affiche que les erreurs (utile en tache planifiee).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $config = $this->parametres->tous();

        if (!$config['auto_actif'] && !$input->getOption('force')) {
            $io->note('Import automatique desactive dans l\'interface Connexion ZKTECO. Utilisez --force pour importer malgre tout.');

            return Command::SUCCESS;
        }

        $verrou = $this->verrou();
        if ($verrou === null) {
            $io->error('Un import ZKTECO est deja en cours (verrou var/zkteco/import.lock). Reessayez dans quelques minutes.');

            return Command::FAILURE;
        }

        try {
            $jours = max(1, (int) ($input->getOption('jours') ?: $config['jours_reprise']));
            $du = (string) $input->getOption('du');
            $au = (new \DateTimeImmutable('today'))->format('Y-m-d');
            if ($du === '') {
                $du = (new \DateTimeImmutable('today'))->modify('-' . ($jours - 1) . ' days')->format('Y-m-d');
            }

            $arguments = [
                'command' => 'app:pointage:zkteco',
                '--du' => $du,
                '--au' => $au,
                '--tolerance' => (string) (int) $config['tolerance'],
            ];

            if (trim((string) $config['fichier']) !== '') {
                $arguments['--fichier'] = (string) $config['fichier'];
            } elseif (trim((string) $config['dsn']) !== '') {
                $arguments['--dsn'] = (string) $config['dsn'];
            }

            $commande = $this->getApplication()?->find('app:pointage:zkteco');
            if ($commande === null) {
                $io->error('La commande d\'import app:pointage:zkteco est introuvable. Verifiez le dossier src/Command.');

                return Command::FAILURE;
            }

            $sortie = new BufferedOutput();
            $code = $commande->run(new ArrayInput($arguments), $sortie);
            $texte = trim($sortie->fetch());

            $this->parametres->journaliser([
                'date' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'du' => $du,
                'au' => $au,
                'succes' => $code === Command::SUCCESS,
                'code' => $code,
                'origine' => $input->getOption('force') ? 'manuelle' : 'automatique',
                'resume' => mb_substr(preg_replace('/\s+/', ' ', $texte) ?? '', 0, 600),
            ]);

            if (!$input->getOption('silence')) {
                $io->text($texte === '' ? 'Import termine sans message.' : $texte);
                $code === Command::SUCCESS
                    ? $io->success('Import ZKTECO termine : du ' . $du . ' au ' . $au . '.')
                    : $io->error('Import ZKTECO en echec. Consultez les messages ci-dessus et var/log/.');
            }

            return $code;
        } finally {
            $this->deverrouiller($verrou);
        }
    }

    /** @return resource|null */
    private function verrou()
    {
        $dossier = $this->parametres->dossier();
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            return null;
        }

        $poignee = @fopen($dossier . DIRECTORY_SEPARATOR . 'import.lock', 'c');
        if ($poignee === false) {
            return null;
        }

        if (!flock($poignee, LOCK_EX | LOCK_NB)) {
            fclose($poignee);

            return null;
        }

        return $poignee;
    }

    /** @param resource $poignee */
    private function deverrouiller($poignee): void
    {
        flock($poignee, LOCK_UN);
        fclose($poignee);
    }
}
