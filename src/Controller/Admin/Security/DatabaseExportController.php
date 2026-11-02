<?php

namespace App\Controller\Admin\Security;

use App\Entity\Directions;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;          // <-- changé (Attribute, pas Annotation)
use Symfony\Component\Security\Http\Attribute\IsGranted; // optionnel (voir plus bas)

class DatabaseExportController extends AbstractController
{


    #[Route('/AdminSys/DBManager', name: 'app_admin_db_manager')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        if (!$this->getUser()){return  $this->redirectToRoute("app_login");}
        return $this->render('admin/security/database.html.twig');
    }
    #[Route('/adminSecurity/databaseExport', name: 'app_db_export', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]   // alternative élégante au denyAccessUnlessGranted
    public function export(Connection $connection): Response
    {
        $params   = $connection->getParams();
        $host     = $params['host']     ?? '127.0.0.1';
        $port     = (string) ($params['port'] ?? 5432);
        $user     = $params['user']     ?? 'postgres';
        $password = $params['password'] ?? '';
        $dbname   = $params['dbname']   ?? '';

        // dd($dbname);
        if ($dbname === '') {
            return new Response('Nom de base introuvable dans la configuration.', 500);
        }

        $filename = sprintf('citrac_%s_%s.sql', $dbname, date('Ymd_His'));

        $command = [
            'pg_dump',
            '--host=' . $host,
            '--port=' . $port,
            '--username=' . $user,
            '--no-password',
            '--format=plain',
            '--clean',
            '--if-exists',
            '--encoding=UTF8',
            $dbname,
        ];

        $response = new StreamedResponse(function () use ($command, $password) {
            $process = new Process($command);
            $process->setEnv(['PGPASSWORD' => $password]);
            $process->setTimeout(600);

            $process->run(function ($type, $buffer) {
                if ($type === Process::OUT) {
                    echo $buffer;
                    flush();
                }
            });

            if (!$process->isSuccessful()) {
                echo "\n-- ERREUR pg_dump : " . $process->getErrorOutput() . "\n";
            }
        });

        $response->headers->set('Content-Type', 'application/sql; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
