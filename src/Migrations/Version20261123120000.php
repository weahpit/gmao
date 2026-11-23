<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Messagerie : identifiant d'envoi (client_id).
 *
 * L'application genere un identifiant unique par message. Si l'envoi est
 * repete apres une coupure reseau, le serveur reconnait le message deja
 * enregistre au lieu de le creer une seconde fois.
 *
 * La migration est IDEMPOTENTE : elle ne fait rien si la colonne existe deja,
 * ni si la table `message_chat` n'est pas encore creee.
 */
final class Version20261123120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messagerie : colonne client_id (anti-doublon a l\'envoi).';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $tables = array_map('strtolower', $sm->listTableNames());

        if (!in_array('message_chat', $tables, true)) {
            return;
        }

        if (!$this->colonneExiste('message_chat', 'client_id')) {
            $this->addSql('ALTER TABLE message_chat ADD client_id VARCHAR(64) DEFAULT NULL');
        }

        try {
            $existants = $sm->listTableIndexes('message_chat');
            if (!isset($existants['idx_chat_client'])) {
                $this->addSql('CREATE INDEX idx_chat_client ON message_chat (client_id)');
            }
        } catch (\Throwable) {
            // Index deja present ou moteur qui refuse : sans consequence.
        }
    }

    public function down(Schema $schema): void
    {
        // Retour arriere neutre : on ne supprime pas les identifiants deja stockes.
    }

    private function colonneExiste(string $table, string $colonne): bool
    {
        try {
            $existe = $this->connection->fetchOne(
                'SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ?',
                [$table, $colonne]
            );
        } catch (\Throwable) {
            return false;
        }

        return $existe !== false && $existe !== null;
    }
}
