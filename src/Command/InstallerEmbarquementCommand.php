<?php

declare(strict_types=1);
namespace App\Command;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
#[AsCommand(name:'app:embarquement:installer',description:'Diagnostic et installation idempotente CITRAC V5, sans suppression de données.')]
final class InstallerEmbarquementCommand extends Command
{
    public function __construct(private EntityManagerInterface $em,#[Autowire('%kernel.project_dir%')] private string $projectDir){parent::__construct();}
    protected function configure(): void {$this->addOption('appliquer',null,InputOption::VALUE_NONE,'Applique les requêtes ; sinon diagnostic seulement.');}
    protected function execute(InputInterface $input,OutputInterface $output): int {
        $io=new SymfonyStyle($input,$output);$c=$this->em->getConnection();
        try {
            if(!str_contains(strtolower(get_class($c->getDatabasePlatform())),'postgresql'))throw new \RuntimeException('PostgreSQL requis.');
            foreach(['logistique.dossier','logistique.banque','logistique.cv_deblocage','logistique.cv_execution','logistique.doc_dossier','production.lot','lg_suivi_embarquement','rh_candidat'] as $t)
                if(!$c->fetchOne('SELECT to_regclass(?)',[$t]))throw new \RuntimeException('Table absente : '.$t.'. Installez la base et exécutez app:export:installer --appliquer avant V5.');
            $sql=require $this->projectDir.'/migrations/evolutions_v5_sql.php';
            $io->title('CITRAC — installation ciblée V5');foreach($sql as $q)$io->writeln($q.';');
            if(!$input->getOption('appliquer')){$io->note('Diagnostic seulement. Sauvegardez la base et les fichiers puis relancez avec --appliquer. Les soldes historiques sont contrôlés dans la transaction.');return Command::SUCCESS;}
            $c->transactional(function()use($c,$sql){foreach($sql as $q)$c->executeStatement($q);});
            $io->success('Évolutions V5 installées. Aucun dossier ni fichier supprimé.');
            $io->note('Les dossiers historiques sans ventilation des lots nécessitent un rapprochement manuel. Vérifiez les soldes sacs des lots concernés.');return Command::SUCCESS;
        }catch(\Throwable $e){$io->error($e->getMessage());return Command::FAILURE;}
    }
}
