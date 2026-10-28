<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028130939 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bon_sortie (id UUID NOT NULL, reference VARCHAR(50) NOT NULL, token VARCHAR(64) NOT NULL, statut VARCHAR(20) NOT NULL, service VARCHAR(255) DEFAULT NULL, demandeur VARCHAR(255) DEFAULT NULL, etabli_par VARCHAR(255) DEFAULT NULL, lignes JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(255) DEFAULT NULL, valide_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, valide_par VARCHAR(255) DEFAULT NULL, poste_controle VARCHAR(255) DEFAULT NULL, code_service_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2843ABC8AEA34913 ON bon_sortie (reference)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2843ABC85F37A13B ON bon_sortie (token)');
        $this->addSql('CREATE INDEX IDX_2843ABC8C5F25400 ON bon_sortie (code_service_id)');
        $this->addSql('ALTER TABLE bon_sortie ADD CONSTRAINT FK_2843ABC8C5F25400 FOREIGN KEY (code_service_id) REFERENCES services (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bon_sortie DROP CONSTRAINT FK_2843ABC8C5F25400');
        $this->addSql('DROP TABLE bon_sortie');
    }
}
