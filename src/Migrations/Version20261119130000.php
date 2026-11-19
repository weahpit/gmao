<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Messagerie : listes de diffusion et pieces jointes (photos / vocaux).
 *
 *   1. chat_liste_diffusion (+ tables de liaison vers les groupes et les
 *      utilisateurs) ;
 *   2. message_chat : type de message, fichier joint, duree du vocal et
 *      rattachement a une liste de diffusion.
 *
 * La migration est IDEMPOTENTE : CREATE TABLE IF NOT EXISTS, et chaque
 * colonne n'est ajoutee que si elle manque. Aucune contrainte de cle
 * etrangere n'est posee : les relations sont gerees par Doctrine, ce qui
 * evite de bloquer sur une base deja remplie.
 */
final class Version20261119130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messagerie : listes de diffusion + pieces jointes (photo, vocal).';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $tables = array_map('strtolower', $sm->listTableNames());

        // ------------------------------------------------------------------
        //  1. Les listes de diffusion
        // ------------------------------------------------------------------
        if (!in_array('chat_liste_diffusion', $tables, true)) {
            $this->addSql(<<<'SQL'
                CREATE TABLE IF NOT EXISTS chat_liste_diffusion (
                    id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
                    cree_par_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:guid)',
                    nom VARCHAR(120) NOT NULL,
                    description LONGTEXT DEFAULT NULL,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                    UNIQUE INDEX uniq_chat_liste_nom (nom),
                    INDEX idx_chat_liste_cree_par (cree_par_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
                SQL);
        }

        $this->creerTableLiaison('chat_liste_diffusion_groupe', 'groupe_id', $tables);
        $this->creerTableLiaison('chat_liste_diffusion_membre', 'user_id', $tables);

        // ------------------------------------------------------------------
        //  2. Les pieces jointes des messages
        // ------------------------------------------------------------------
        if (in_array('message_chat', $tables, true)) {
            $this->ajouterColonne('message_chat', 'type', "VARCHAR(10) NOT NULL DEFAULT 'texte'");
            $this->ajouterColonne('message_chat', 'fichier', 'VARCHAR(255) DEFAULT NULL');
            $this->ajouterColonne('message_chat', 'fichier_nom', 'VARCHAR(255) DEFAULT NULL');
            $this->ajouterColonne('message_chat', 'fichier_mime', 'VARCHAR(100) DEFAULT NULL');
            $this->ajouterColonne('message_chat', 'duree', 'INT DEFAULT NULL');
            $this->ajouterColonne('message_chat', 'liste_diffusion_id', "CHAR(36) DEFAULT NULL COMMENT '(DC2Type:guid)'");
            $this->ajouterIndex('message_chat', 'idx_chat_liste', ['liste_diffusion_id']);

            // Les anciens messages sont du texte : on s'assure que la colonne
            // ne contient jamais NULL (les gabarits testent la valeur).
            $this->addSql("UPDATE message_chat SET type = 'texte' WHERE type IS NULL OR type = ''");
        }
    }

    public function down(Schema $schema): void
    {
        // Aucune suppression : retirer les colonnes couperait la messagerie
        // (messages, photos et vocaux deja echanges).
    }

    /** Cree une table de liaison (liste <-> groupe / utilisateur). */
    private function creerTableLiaison(string $table, string $colonneCible, array $tables): void
    {
        if (in_array($table, $tables, true)) {
            return;
        }

        $this->addSql(sprintf(<<<'SQL'
            CREATE TABLE IF NOT EXISTS %s (
                chat_liste_diffusion_id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
                %s CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
                INDEX idx_%s_liste (chat_liste_diffusion_id),
                INDEX idx_%s_cible (%s),
                PRIMARY KEY(chat_liste_diffusion_id, %s)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL, $table, $colonneCible, $table, $table, $colonneCible, $colonneCible));
    }

    /** Ajoute une colonne si elle n'existe pas deja (idempotent). */
    private function ajouterColonne(string $table, string $colonne, string $definition): void
    {
        if ($this->colonneExiste($table, $colonne)) {
            return;
        }

        $this->addSql(sprintf('ALTER TABLE %s ADD %s %s', $table, $colonne, $definition));
    }

    /** Ajoute un index si une colonne de reference n'en a pas encore (idempotent). */
    private function ajouterIndex(string $table, string $nom, array $colonnes): void
    {
        foreach ($colonnes as $colonne) {
            if (!$this->colonneExiste($table, $colonne)) {
                return;
            }
        }

        try {
            foreach ($this->connection->createSchemaManager()->listTableIndexes($table) as $index) {
                if (strtolower($index->getName()) === strtolower($nom)) {
                    return;
                }
            }
        } catch (\Throwable) {
            return;
        }

        $this->addSql(sprintf('CREATE INDEX %s ON %s (%s)', $nom, $table, implode(', ', $colonnes)));
    }

    /** La colonne existe-t-elle en base ? */
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
