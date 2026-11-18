<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261118093053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_lot_dechargement (id UUID NOT NULL, poids_affecte DOUBLE PRECISION DEFAULT NULL, nbre_sacs INT DEFAULT NULL, observation VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, lot_id UUID NOT NULL, dechargement_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_stock_lot_dechargement_lot ON stock_lot_dechargement (lot_id)');
        $this->addSql('CREATE INDEX idx_stock_lot_dechargement_dech ON stock_lot_dechargement (dechargement_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_stock_lot_dechargement ON stock_lot_dechargement (lot_id, dechargement_id)');
        $this->addSql('ALTER TABLE stock_lot_dechargement ADD CONSTRAINT FK_C708F558A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES production.lot (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stock_lot_dechargement ADD CONSTRAINT FK_C708F558A06061A3 FOREIGN KEY (dechargement_id) REFERENCES stock_dechargement_cacao (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_lot_dechargement DROP CONSTRAINT FK_C708F558A8CBA5F7');
        $this->addSql('ALTER TABLE stock_lot_dechargement DROP CONSTRAINT FK_C708F558A06061A3');
        $this->addSql('DROP TABLE stock_lot_dechargement');
    }
}
