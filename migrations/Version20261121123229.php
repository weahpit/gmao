<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121123229 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rh_arret_travail (id UUID NOT NULL, type VARCHAR(20) NOT NULL, date_debut DATE NOT NULL, date_fin DATE DEFAULT NULL, jours INT DEFAULT NULL, motif TEXT DEFAULT NULL, medecin VARCHAR(120) DEFAULT NULL, statut VARCHAR(20) NOT NULL, justificatif VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, dossier_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_arret_debut ON rh_arret_travail (date_debut)');
        $this->addSql('CREATE INDEX idx_arret_statut ON rh_arret_travail (statut)');
        $this->addSql('CREATE INDEX IDX_EE6DC5E0611C0C56 ON rh_arret_travail (dossier_id)');
        $this->addSql('CREATE TABLE rh_plan_vaccinal (id UUID NOT NULL, vaccin VARCHAR(150) NOT NULL, periodicite_mois INT DEFAULT NULL, obligatoire BOOLEAN NOT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE rh_arret_travail ADD CONSTRAINT FK_EE6DC5E0611C0C56 FOREIGN KEY (dossier_id) REFERENCES rh_dossier_medical (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rh_arret_travail DROP CONSTRAINT FK_EE6DC5E0611C0C56');
        $this->addSql('DROP TABLE rh_arret_travail');
        $this->addSql('DROP TABLE rh_plan_vaccinal');
    }
}
