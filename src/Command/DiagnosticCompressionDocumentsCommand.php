<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'app:documents:compression', description: 'Verifie les outils de compression des pieces jointes (sans modifier de fichier).')]
final class DiagnosticCompressionDocumentsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $zip = class_exists(\ZipArchive::class);
        $process = class_exists(Process::class);
        $pdf = false;
        $detail = 'Non disponible';
        if ($process) {
            try {
                $outil = new Process(['qpdf', '--version']);
                $outil->setTimeout(5);
                $outil->run();
                $pdf = $outil->isSuccessful();
                if ($pdf) $detail = trim($outil->getOutput());
            } catch (\Throwable $e) {
                $detail = $e->getMessage();
            }
        }
        $io->table(['Element', 'Etat'], [
            ['PHP zip / ZipArchive (Word, Excel, PowerPoint, ODF)', $zip ? 'Disponible' : 'Extension a activer'],
            ['symfony/process', $process ? 'Disponible' : 'Dependance a installer'],
            ['qpdf (PDF sans perte)', $detail],
        ]);
        $io->note('Un outil absent ne bloque pas les uploads : le fichier original est conserve. Consultez le journal « Optimisation piece jointe. » pour le poids avant/apres et le statut.');
        return $zip && $pdf ? Command::SUCCESS : Command::FAILURE;
    }
}
