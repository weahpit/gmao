<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Messagerie : accuse de reception et de lecture.
 *
 * Deux colonnes ajoutees a `message_chat` :
 *
 *   remis_at : premier acces d'un destinataire au message (le message est
 *              arrive sur son telephone) ;
 *   lu_at    : ouverture effective du message (conversation privee).
 *
 * La migration est IDEMPOTENTE : elle ne fait rien si les colonnes existent
 * deja, ni si la table n'est pas encore creee.
 */
final class Version20261120120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messagerie : colonnes remis_at et lu_at (accuse de reception et de lecture).';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $tables = array_map('strtolower', $sm->listTableNames());

        if (!in_array('message_chat', $tables, true)) {
            // La table sera creee plus tard avec les bonnes colonnes.
            return;
        }

        $platform = $this->connection->getDatabasePlatform()->getName();

        foreach (['remis_at' => 'date de remise', 'lu_at' => 'date de lecture'] as $colonne => $libelle) {
            if ($this->colonneExiste('message_chat', $colonne)) {
                continue;
            }

            if ($platform === 'postgresql') {
                $this->addSql(sprintf(
                    'ALTER TABLE message_chat ADD COLUMN %s TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL',
                    $colonne
                ));
            } elseif (in_array($platform, ['mysql', 'mariadb'], true)) {
                $this->addSql(sprintf(
                    'ALTER TABLE message_chat ADD COLUMN %s DATETIME DEFAULT NULL COMMENT \'(%s)\'',
                    $colonne,
                    $libelle
                ));
            }
            // SQLite : les colonnes seront ajoutees par Doctrine si besoin.
        }

        $this->ajouterIndex('idx_chat_remis', ['remis_at']);
    }

    public function down(Schema $schema): void
    {
        // Retour arriere neutre : on ne supprime pas les accuses deja calcules.
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

    /** Ajoute un index s'il n'existe pas deja (silencieux en cas d'echec). */
    private function ajouterIndex(string $nom, array $colonnes): void
    {
        try {
            $existants = $this->connection->createSchemaManager()
                ->listTableIndexes('message_chat');
            if (isset($existants[strtolower($nom)])) {
                return;
            }
            $this->addSql(sprintf(
                'CREATE INDEX %s ON message_chat (%s)',
                $nom,
                implode(', ', $colonnes)
            ));
        } catch (\Throwable) {
            // Index deja present ou moteur qui refuse : sans consequence.
        }
    }
}
