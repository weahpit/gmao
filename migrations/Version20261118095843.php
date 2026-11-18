<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261118095843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_ordre_usinage (id UUID NOT NULL, numero VARCHAR(30) NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, observation VARCHAR(255) DEFAULT NULL, dechargement_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_ordre_usinage_dech ON stock_ordre_usinage (dechargement_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_ordre_usinage_numero ON stock_ordre_usinage (numero)');
        $this->addSql('ALTER TABLE stock_ordre_usinage ADD CONSTRAINT FK_C01635B7A06061A3 FOREIGN KEY (dechargement_id) REFERENCES stock_dechargement_cacao (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stock_lot_dechargement ADD ordre_usinage_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_lot_dechargement ADD CONSTRAINT FK_C708F55851856087 FOREIGN KEY (ordre_usinage_id) REFERENCES stock_ordre_usinage (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_C708F55851856087 ON stock_lot_dechargement (ordre_usinage_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_ordre_usinage DROP CONSTRAINT FK_C01635B7A06061A3');
        $this->addSql('DROP TABLE stock_ordre_usinage');
        $this->addSql('ALTER TABLE stock_lot_dechargement DROP CONSTRAINT FK_C708F55851856087');
        $this->addSql('DROP INDEX IDX_C708F55851856087');
        $this->addSql('ALTER TABLE stock_lot_dechargement DROP ordre_usinage_id');
    }
}
