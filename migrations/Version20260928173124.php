<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928173124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE equipement_type (id UUID NOT NULL, nom_equipement VARCHAR(255) DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, code VARCHAR(50) DEFAULT NULL, code_categorie_id UUID DEFAULT NULL, nature_equipement_id UUID DEFAULT NULL, criticite_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6302CE1977DD1548 ON equipement_type (code_categorie_id)');
        $this->addSql('CREATE INDEX IDX_6302CE19660085 ON equipement_type (nature_equipement_id)');
        $this->addSql('CREATE INDEX IDX_6302CE19C141C0A0 ON equipement_type (criticite_id)');
        $this->addSql('ALTER TABLE equipement_type ADD CONSTRAINT FK_6302CE1977DD1548 FOREIGN KEY (code_categorie_id) REFERENCES categorie (id)');
        $this->addSql('ALTER TABLE equipement_type ADD CONSTRAINT FK_6302CE19660085 FOREIGN KEY (nature_equipement_id) REFERENCES nature_equipement (id)');
        $this->addSql('ALTER TABLE equipement_type ADD CONSTRAINT FK_6302CE19C141C0A0 FOREIGN KEY (criticite_id) REFERENCES criticite (id)');
        $this->addSql('DROP INDEX idx_b8b4c6f3cf143fb5');
        $this->addSql('ALTER TABLE equipement DROP code_eq_type_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_type DROP CONSTRAINT FK_6302CE1977DD1548');
        $this->addSql('ALTER TABLE equipement_type DROP CONSTRAINT FK_6302CE19660085');
        $this->addSql('ALTER TABLE equipement_type DROP CONSTRAINT FK_6302CE19C141C0A0');
        $this->addSql('DROP TABLE equipement_type');
        $this->addSql('ALTER TABLE equipement ADD code_eq_type_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_b8b4c6f3cf143fb5 ON equipement (code_eq_type_id)');
    }
}
