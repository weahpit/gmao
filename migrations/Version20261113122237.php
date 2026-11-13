<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113122237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_initialisation (id UUID NOT NULL, rubrique VARCHAR(100) NOT NULL, nature VARCHAR(255) DEFAULT NULL, quantite DOUBLE PRECISION NOT NULL, unite VARCHAR(20) NOT NULL, date_inventaire TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, stock_theorique DOUBLE PRECISION DEFAULT NULL, ecart DOUBLE PRECISION DEFAULT NULL, observation TEXT DEFAULT NULL, remplace_precedent BOOLEAN NOT NULL, desactive BOOLEAN NOT NULL, auteur_libelle VARCHAR(180) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, auteur_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_STOCK_INIT_RUBRIQUE_DATE ON stock_initialisation (rubrique, date_inventaire)');
        $this->addSql('CREATE INDEX IDX_6696CABD60BB6FE6 ON stock_initialisation (auteur_id)');
        $this->addSql('ALTER TABLE stock_initialisation ADD CONSTRAINT FK_6696CABD60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES "user" (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_initialisation DROP CONSTRAINT FK_6696CABD60BB6FE6');
        $this->addSql('DROP TABLE stock_initialisation');
    }
}
