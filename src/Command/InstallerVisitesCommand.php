<?php

namespace App\Command;

use App\Entity\Visite\Visite;
use App\Entity\Visite\Visiteur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cree les deux tables du module Visites : visite et visite_visiteur.
 *
 *   php bin/console app:visites:installer
 *
 * La commande est rejouable : les tables deja presentes ne sont pas modifiees.
 */
#[AsCommand(
    name: 'app:visites:installer',
    description: 'Cree les tables du module Visites (visite, visite_visiteur).'
)]
class InstallerVisitesCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Module Visites - installation');

        try {
            $metadonnees = [
                $this->entityManager->getClassMetadata(Visiteur::class),
                $this->entityManager->getClassMetadata(Visite::class),
            ];

            $outil = new SchemaTool($this->entityManager);
            $outil->updateSchema($metadonnees, true);
        } catch (\Throwable $e) {
            $io->error('La creation des tables a echoue : ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->text('Tables verifiees : visite, visite_visiteur.');
        $io->success('Module Visites pret. Ouvrez /visites dans le navigateur.');

        return Command::SUCCESS;
    }
}
