<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Logistique\{FormuleExport, SuiviEmbarquement, DocsDossier, Dossier, Tc, CvDeblocage};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Migration ciblee et rejouable ; aucun DROP ni mise a jour globale du schema. */
#[AsCommand(name: 'app:export:installer', description: 'Prepare le suivi export : CV, formules, rapports et documents. Aucun effacement.')]
final class InstallerExportCommand extends Command
{
    public function __construct(private EntityManagerInterface $em) { parent::__construct(); }
    protected function configure(): void
    {
        $this->addOption('appliquer', null, InputOption::VALUE_NONE, 'Execute les requetes affichees. Sans cette option : diagnostic uniquement.');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $c = $this->em->getConnection(); $p = $c->getDatabasePlatform();
        if (!str_contains(strtolower(get_class($p)), 'postgresql')) {
            $io->error('Commande ciblee pour PostgreSQL (schemas logistique). Aucune modification effectuee.');
            return Command::FAILURE;
        }
        $q = static fn (string $nom): string => $p->quoteIdentifier($nom);
        $nomTable = static fn ($meta): string => $meta->getSchemaName() && !str_contains($meta->getTableName(), '.') ? $meta->getSchemaName() . '.' . $meta->getTableName() : $meta->getTableName();
        try {
            $sm = $c->createSchemaManager(); $sql = []; $metas = [];
            foreach ([FormuleExport::class, SuiviEmbarquement::class] as $classe) {
                $m = $this->em->getClassMetadata($classe);
                if (!$sm->tablesExist([$nomTable($m)])) $metas[] = $m;
            }
            if ($metas) $sql = (new SchemaTool($this->em))->getCreateSchemaSql($metas);
            // Mise a niveau v4 d'un suivi deja installe : conserver les volumes prepares.
            $suiviMeta = $this->em->getClassMetadata(SuiviEmbarquement::class);
            $suiviTable = $nomTable($suiviMeta);
            if ($sm->tablesExist([$suiviTable])) {
                $suiviColonnes = $sm->listTableColumns($suiviTable);
                $preparees = $suiviMeta->getColumnName('formulesPreparees');
                if (!isset($suiviColonnes[$preparees])) $sql[] = 'ALTER TABLE ' . $q($suiviTable) . ' ADD COLUMN ' . $q($preparees) . " JSON NOT NULL DEFAULT '[]'";
            }
            $m = $this->em->getClassMetadata(DocsDossier::class); $table = $nomTable($m);
            if (!$sm->tablesExist([$table])) throw new \RuntimeException('Table documentaire historique absente. Installez d’abord la base du projet.');
            $colonnes = $sm->listTableColumns($table);
            foreach (['statut' => "VARCHAR(30) NOT NULL DEFAULT 'POUR_APPROBATION'", 'nomOriginal' => 'VARCHAR(255) DEFAULT NULL', 'transmisLe' => 'TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL', 'transmisPar' => 'VARCHAR(180) DEFAULT NULL'] as $champ => $definition) {
                $nom = $m->getColumnName($champ);
                if (!isset($colonnes[$nom])) $sql[] = 'ALTER TABLE ' . $q($table) . ' ADD COLUMN ' . $q($nom) . ' ' . $definition;
            }
            foreach ([Dossier::class => ['tonnage'=>'DOUBLE PRECISION', 'nb_tc'=>'INTEGER'], Tc::class => ['pn_th'=>'DOUBLE PRECISION', 'pn_reel'=>'DOUBLE PRECISION']] as $classe => $champs) {
                $meta = $this->em->getClassMetadata($classe); $t = $nomTable($meta);
                foreach ($champs as $champ => $type) {
                    $nom = $meta->getColumnName($champ);
                    if ($type === 'INTEGER') {
                        $fraction = $c->fetchOne('SELECT COUNT(*) FROM ' . $q($t) . ' WHERE ' . $q($nom) . '::numeric <> TRUNC(' . $q($nom) . '::numeric)');
                        if ((int) $fraction > 0) throw new \RuntimeException('Nombre de TC fractionnaire dans les donnees historiques : corrigez-le avant l’installation.');
                    }
                    $sql[] = 'ALTER TABLE ' . $q($t) . ' ALTER COLUMN ' . $q($nom) . ' TYPE ' . $type . ' USING ' . $q($nom) . '::' . $type;
                }
            }
            foreach ([Dossier::class => ['numero_ot','ux_export_numero_ot'], CvDeblocage::class => ['numero_cv','ux_export_numero_cv']] as $classe => [$champ,$index]) {
                $m = $this->em->getClassMetadata($classe); $t = $nomTable($m); $col = $m->getColumnName($champ);
                $doublons = $c->fetchAllAssociative('SELECT ' . $q($col) . ' AS numero, COUNT(*) AS nombre FROM ' . $q($t) . ' WHERE ' . $q($col) . ' IS NOT NULL GROUP BY ' . $q($col) . ' HAVING COUNT(*) > 1');
                if ($doublons) { $io->table(['Numero en doublon','Nombre'], array_map(static fn ($r) => [$r['numero'],$r['nombre']], $doublons)); throw new \RuntimeException('Unicite impossible : rapprochez les doublons historiques, sans les effacer automatiquement.'); }
                $sql[] = 'CREATE UNIQUE INDEX IF NOT EXISTS ' . $q($index) . ' ON ' . $q($t) . ' (' . $q($col) . ')';
            }
            $io->title('Installation export — requetes ciblees');
            foreach ($sql as $requete) $io->writeln($requete . ';');
            if (!$input->getOption('appliquer')) { $io->note('Diagnostic seulement. Sauvegardez la base, puis relancez avec --appliquer.'); return Command::SUCCESS; }
            $c->transactional(function () use ($c, $sql): void { foreach ($sql as $requete) $c->executeStatement($requete); });
            $io->success('Schema export prepare. Aucun dossier, CV ou fichier supprime.');
            $io->note('Les dossiers historiques participent au solde quand leur CV d’execution est identifie par UUID ou numero. Rapprochez les CV manquants et les anciens volumes NULL avant l’exploitation.');
            return Command::SUCCESS;
        } catch (\Throwable $e) { $io->error($e->getMessage()); return Command::FAILURE; }
    }
}
