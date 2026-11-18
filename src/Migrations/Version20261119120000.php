<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Messagerie interne : cree les deux tables du chat.
 *
 *   message_chat : les messages (canal general quand destinataire_id est NULL,
 *                  conversation privee sinon) ;
 *   chat_lecture : jusqu'a quand chaque utilisateur a lu un canal
 *                  (sert a calculer les pastilles « non lus »).
 *
 * La migration est IDEMPOTENTE : CREATE TABLE IF NOT EXISTS + index verifies.
 * Aucune contrainte de cle etrangere n'est posee (les relations sont gerees
 * par Doctrine) afin de ne jamais bloquer sur une base deja remplie ou sur un
 * moteur qui refuse la contrainte.
 */
final class Version20261119120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messagerie interne : tables message_chat et chat_lecture.';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $tables = array_map('strtolower', $sm->listTableNames());

        // ------------------------------------------------------------------
        //  Messages
        // ------------------------------------------------------------------
        if (!in_array('message_chat', $tables, true)) {
            $this->addSql(<<<'SQL'
                CREATE TABLE IF NOT EXISTS message_chat (
                    id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
                    expediteur_id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
                    destinataire_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:guid)',
                    contenu LONGTEXT NOT NULL,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                    lu TINYINT(1) NOT NULL DEFAULT 0,
                    INDEX idx_message_chat_expediteur (expediteur_id),
                    INDEX idx_chat_destinataire_lu (destinataire_id, lu),
                    INDEX idx_chat_created (created_at),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
                SQL);
        } else {
            // Table deja presente : on s'assure seulement des index utiles.
            $this->ajouterIndex('message_chat', 'idx_message_chat_expediteur', ['expediteur_id']);
            $this->ajouterIndex('message_chat', 'idx_chat_destinataire_lu', ['destinataire_id', 'lu']);
            $this->ajouterIndex('message_chat', 'idx_chat_created', ['created_at']);
        }

        // ------------------------------------------------------------------
        //  Lectures (pastilles « non lus »)
        // ------------------------------------------------------------------
        if (!in_array('chat_lecture', $tables, true)) {
            $this->addSql(<<<'SQL'
                CREATE TABLE IF NOT EXISTS chat_lecture (
                    id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
                    utilisateur_id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
                    canal VARCHAR(40) NOT NULL,
                    dernier_lu_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                    INDEX idx_chat_lecture_utilisateur (utilisateur_id),
                    UNIQUE INDEX uniq_chat_lecture (utilisateur_id, canal),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
                SQL);
        } else {
            $this->ajouterIndex('chat_lecture', 'uniq_chat_lecture', ['utilisateur_id', 'canal'], true);
        }
    }

    public function down(Schema $schema): void
    {
        // On ne supprime rien : les messages envoyes par les utilisateurs
        // doivent survivre a un retour arriere de migration.
    }

    /** Ajoute un index s'il n'existe pas deja (idempotent). */
    private function ajouterIndex(string $table, string $nom, array $colonnes, bool $unique = false): void
    {
        foreach ($colonnes as $colonne) {
            if (!$this->colonneExiste($table, $colonne)) {
                return;
            }
        }

        try {
            foreach ($this->connection->createSchemaManager()->listTableIndexes($table) as $idx) {
                if (strtolower($idx->getName()) === strtolower($nom)) {
                    return;
                }
                if (strtolower(implode(',', $idx->getColumns())) === strtolower(implode(',', $colonnes)) && $idx->isUnique() === $unique) {
                    return; // meme index sous un autre nom
                }
            }
        } catch (\Throwable) {
            return;
        }

        $this->addSql(sprintf(
            'CREATE %sINDEX %s ON %s (%s)',
            $unique ? 'UNIQUE ' : '',
            $nom,
            $table,
            implode(', ', $colonnes)
        ));
    }

    private function colonneExiste(string $table, string $colonne): bool
    {
        try {
            $colonnes = $this->connection->createSchemaManager()->listTableColumns($table);
        } catch (\Throwable) {
            return false;
        }

        foreach (array_keys($colonnes) as $nom) {
            if (strtolower((string) $nom) === strtolower($colonne)) {
                return true;
            }
        }

        return false;
    }
}
