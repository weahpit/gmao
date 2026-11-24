<?php
namespace App\Command;

use App\Entity\Visite\{Visite, Visiteur};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Installation ciblee et rejouable : aucun DROP ni updateSchema global. */
#[AsCommand(name:'app:visites:installer',description:'Cree les tables Visites et ajoute l’enquete/signature, sans effacement.')]
final class InstallerVisitesCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager) { parent::__construct(); }
    protected function configure(): void { $this->addOption('simulation',null,InputOption::VALUE_NONE,'Affiche les requetes sans les executer.'); }
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        $io=new SymfonyStyle($input,$output);
        try {
            $c=$this->entityManager->getConnection(); $sm=$c->createSchemaManager(); $p=$c->getDatabasePlatform();
            $sql=[]; $absentes=[];
            foreach ([Visiteur::class,Visite::class] as $classe) {
                $meta=$this->entityManager->getClassMetadata($classe);
                if (!$sm->tablesExist([$meta->getTableName()])) $absentes[]=$meta;
            }
            if ($absentes) $sql=(new SchemaTool($this->entityManager))->getCreateSchemaSql($absentes);
            $meta=$this->entityManager->getClassMetadata(Visite::class); $table=$meta->getTableName();
            if ($sm->tablesExist([$table])) {
                $colonnes=$sm->listTableColumns($table);
                foreach (['enqueteSanitaire','signatureVisiteur'] as $champ) {
                    $nom=$meta->getColumnName($champ);
                    if (!isset($colonnes[$nom])) $sql[]='ALTER TABLE '.$p->quoteIdentifier($table).' ADD COLUMN '.$p->quoteIdentifier($nom).' '.$p->getJsonTypeDeclarationSQL(['notnull'=>false]);
                }
            }
            foreach ($sql as $requete) $io->writeln($requete.';');
            if ($input->getOption('simulation')) { $io->note('Simulation seulement : aucune modification en base.'); return Command::SUCCESS; }
            $c->transactional(function () use ($c,$sql): void {foreach ($sql as $requete) $c->executeStatement($requete);});
            $io->success('Module Visites pret : donnees historiques conservees.');
            return Command::SUCCESS;
        } catch (\Throwable $e) { $io->error('Installation des visites impossible : '.$e->getMessage()); return Command::FAILURE; }
    }
}
