<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261020210023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE maintenance.plan_maintenance (id UUID NOT NULL, reference VARCHAR(50) DEFAULT NULL, libelle VARCHAR(255) NOT NULL, periodicite VARCHAR(30) NOT NULL, intervalle INT NOT NULL, jour_semaine INT DEFAULT NULL, date_debut DATE NOT NULL, date_fin DATE DEFAULT NULL, actif BOOLEAN NOT NULL, couleur VARCHAR(20) DEFAULT NULL, derniere_generation DATE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(255) DEFAULT NULL, zone_id UUID DEFAULT NULL, equipement_type_id UUID DEFAULT NULL, type_intervention_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_96B2D0699F2C3FAB ON maintenance.plan_maintenance (zone_id)');
        $this->addSql('CREATE INDEX IDX_96B2D069CEC1C640 ON maintenance.plan_maintenance (equipement_type_id)');
        $this->addSql('CREATE INDEX IDX_96B2D069799AAC17 ON maintenance.plan_maintenance (type_intervention_id)');
        $this->addSql('CREATE TABLE maintenance.plan_maintenance_occurrence (id UUID NOT NULL, date_prevue DATE NOT NULL, statut VARCHAR(20) NOT NULL, date_realisation DATE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, plan_id UUID NOT NULL, fiche_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_26430312E899029B ON maintenance.plan_maintenance_occurrence (plan_id)');
        $this->addSql('CREATE INDEX IDX_26430312DF522508 ON maintenance.plan_maintenance_occurrence (fiche_id)');
        $this->addSql('ALTER TABLE maintenance.plan_maintenance ADD CONSTRAINT FK_96B2D0699F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone_exploitation (id)');
        $this->addSql('ALTER TABLE maintenance.plan_maintenance ADD CONSTRAINT FK_96B2D069CEC1C640 FOREIGN KEY (equipement_type_id) REFERENCES equipement_type (id)');
        $this->addSql('ALTER TABLE maintenance.plan_maintenance ADD CONSTRAINT FK_96B2D069799AAC17 FOREIGN KEY (type_intervention_id) REFERENCES maintenance.type_intervention (id)');
        $this->addSql('ALTER TABLE maintenance.plan_maintenance_occurrence ADD CONSTRAINT FK_26430312E899029B FOREIGN KEY (plan_id) REFERENCES maintenance.plan_maintenance (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE maintenance.plan_maintenance_occurrence ADD CONSTRAINT FK_26430312DF522508 FOREIGN KEY (fiche_id) REFERENCES maintenance.fiche_intervention (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE maintenance.plan_maintenance DROP CONSTRAINT FK_96B2D0699F2C3FAB');
        $this->addSql('ALTER TABLE maintenance.plan_maintenance DROP CONSTRAINT FK_96B2D069CEC1C640');
        $this->addSql('ALTER TABLE maintenance.plan_maintenance DROP CONSTRAINT FK_96B2D069799AAC17');
        $this->addSql('ALTER TABLE maintenance.plan_maintenance_occurrence DROP CONSTRAINT FK_26430312E899029B');
        $this->addSql('ALTER TABLE maintenance.plan_maintenance_occurrence DROP CONSTRAINT FK_26430312DF522508');
        $this->addSql('DROP TABLE maintenance.plan_maintenance');
        $this->addSql('DROP TABLE maintenance.plan_maintenance_occurrence');
    }
}
