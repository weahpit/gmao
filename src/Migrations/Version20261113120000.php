<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table stock_initialisation : stock initial par type de stock, saisi a une
 * date d'inventaire physique.
 */
final class Version20261113120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creation de la table stock_initialisation (initialisation des stocks apres inventaire physique)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE stock_initialisation (
            id CHAR(36) NOT NULL,
            rubrique VARCHAR(100) NOT NULL,
            nature VARCHAR(255) DEFAULT NULL,
            quantite DOUBLE PRECISION NOT NULL,
            unite VARCHAR(20) NOT NULL,
            date_inventaire DATETIME NOT NULL,
            stock_theorique DOUBLE PRECISION DEFAULT NULL,
            ecart DOUBLE PRECISION DEFAULT NULL,
            observation LONGTEXT DEFAULT NULL,
            remplace_precedent TINYINT(1) NOT NULL DEFAULT 0,
            desactive TINYINT(1) NOT NULL DEFAULT 0,
            auteur_libelle VARCHAR(180) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            auteur_id INT DEFAULT NULL,
            INDEX IDX_STOCK_INIT_RUBRIQUE_DATE (rubrique, date_inventaire),
            INDEX IDX_STOCK_INIT_AUTEUR (auteur_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS stock_initialisation');
    }
}
