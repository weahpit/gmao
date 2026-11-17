<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117164333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_dechargement_cacao (id UUID NOT NULL, code VARCHAR(60) NOT NULL, date_bon TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, site VARCHAR(50) DEFAULT NULL, produit VARCHAR(100) DEFAULT NULL, cooperative VARCHAR(180) DEFAULT NULL, immatriculation VARCHAR(40) DEFAULT NULL, transporteur VARCHAR(180) DEFAULT NULL, nbre_sacs INT DEFAULT NULL, poids_net DOUBLE PRECISION DEFAULT NULL, statut VARCHAR(120) DEFAULT NULL, date_dechargement DATE DEFAULT NULL, observation VARCHAR(255) DEFAULT NULL, pnp DOUBLE PRECISION DEFAULT NULL, traitant VARCHAR(180) DEFAULT NULL, categorie VARCHAR(120) DEFAULT NULL, source VARCHAR(20) DEFAULT NULL, import_uuid VARCHAR(40) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_dechargement_date ON stock_dechargement_cacao (date_bon)');
        $this->addSql('CREATE INDEX idx_dechargement_coop ON stock_dechargement_cacao (cooperative)');
        $this->addSql('CREATE UNIQUE INDEX uniq_dechargement_code ON stock_dechargement_cacao (code)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE stock_dechargement_cacao');
    }
}
