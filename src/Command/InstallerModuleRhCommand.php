<?php

namespace App\Command;

use App\Entity\Rh\Absences;
use App\Entity\Rh\Annee;
use App\Entity\Rh\Departement;
use App\Entity\Rh\Employe;
use App\Entity\Rh\Equipe;
use App\Entity\Rh\Feries;
use App\Entity\Rh\Historique;
use App\Entity\Rh\Horaires;
use App\Entity\Rh\HorairesPersonnalises;
use App\Entity\Rh\HorairesQuarts;
use App\Entity\Rh\Mois;
use App\Entity\Rh\Options;
use App\Entity\Rh\Poste;
use App\Entity\Rh\Programmation;
use App\Entity\Rh\TypeHoraire;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prepare la base de donnees du module Ressources humaines.
 *
 *   php bin/console app:rh:installer
 *   php bin/console app:rh:installer --sans-donnees
 *
 * L'operation est rejouable : les tables deja presentes ne sont pas touchees
 * et les donnees de reference ne sont creees que si elles manquent.
 */
#[AsCommand(
    name: 'app:rh:installer',
    description: 'Cree les tables du module RH et charge les donnees de reference (horaires, parametres).'
)]
class InstallerModuleRhCommand extends Command
{
    private const ENTITES = [
        Departement::class,
        Poste::class,
        Equipe::class,
        Employe::class,
        Absences::class,
        Feries::class,
        Annee::class,
        Mois::class,
        Horaires::class,
        HorairesQuarts::class,
        HorairesPersonnalises::class,
        Programmation::class,
        Historique::class,
        TypeHoraire::class,
        Options::class,
    ];

    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('sans-donnees', null, InputOption::VALUE_NONE, 'Creer uniquement les tables, sans donnees de reference.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Module Ressources humaines - installation');

        try {
            $metadonnees = array_map(
                fn (string $classe) => $this->entityManager->getClassMetadata($classe),
                self::ENTITES
            );

            $outil = new SchemaTool($this->entityManager);
            $outil->updateSchema($metadonnees, true);
            $io->text('Tables verifiees : ' . \count($metadonnees) . ' entites RH.');
        } catch (\Throwable $e) {
            $io->error('La creation des tables a echoue : ' . $e->getMessage());

            return Command::FAILURE;
        }

        if ($input->getOption('sans-donnees')) {
            $io->success('Tables pretes. Aucune donnee de reference ajoutee (option --sans-donnees).');

            return Command::SUCCESS;
        }

        $this->donneesDeReference($io);

        $io->success('Module RH pret. Ouvrez /rh dans le navigateur.');

        return Command::SUCCESS;
    }

    private function donneesDeReference(SymfonyStyle $io): void
    {
        $types = [
            1 => 'Horaire normal',
            2 => 'Quarts',
            3 => 'Horaire personnalise',
        ];

        foreach ($types as $id => $libelle) {
            if (null === $this->entityManager->getRepository(TypeHoraire::class)->find($id)) {
                $type = new TypeHoraire();
                $type->setLibelle($libelle);
                $this->entityManager->persist($type);
            }
        }

        if (null === $this->entityManager->getRepository(Options::class)->find(1)) {
            $options = new Options();
            $options->setTolerance(0);
            $this->entityManager->persist($options);
            $io->text('Parametres de pointage crees (tolerance : 0 minute).');
        }

        $quarts = [
            1 => ['06:00', '14:00'],
            2 => ['14:00', '22:00'],
            3 => ['22:00', '06:00'],
        ];

        foreach ($quarts as $id => $heures) {
            if (null === $this->entityManager->getRepository(HorairesQuarts::class)->find($id)) {
                $quart = new HorairesQuarts();
                $quart->setLibelle('Quart ' . $id);
                $quart->setHeure1(new \DateTime($heures[0]));
                $quart->setHeure2(new \DateTime($heures[1]));
                $this->entityManager->persist($quart);
            }
        }

        $this->entityManager->flush();
    }
}
