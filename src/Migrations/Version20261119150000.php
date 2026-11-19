<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Notifications push : table `device_token`.
 *
 * Un enregistrement par appareil (jeton Firebase) et par utilisateur.
 * La migration est idempotente : rien ne casse si elle est relancee.
 */
final class Version20261119150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notifications push : jetons Firebase des appareils (device_token).';
    }

    public function up(Schema $schema): void
    {
        $tables = array_map('strtolower', $this->connection->createSchemaManager()->listTableNames());

        if (!in_array('device_token', $tables, true)) {
            $this->addSql(<<<'SQL'
                CREATE TABLE IF NOT EXISTS device_token (
                    id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
                    user_id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
                    token VARCHAR(255) NOT NULL,
                    plateforme VARCHAR(20) NOT NULL DEFAULT 'android',
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                    updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                    UNIQUE INDEX uniq_device_token (token),
                    INDEX idx_device_token_user (user_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
                SQL);
        }
    }

    public function down(Schema $schema): void
    {
        // On ne supprime pas la table : les jetons seraient perdus et les
        // telephones cesseraient de recevoir leurs notifications.
    }
}
