<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928174048 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_type DROP CONSTRAINT fk_6302ce1977dd1548');
        $this->addSql('DROP INDEX idx_6302ce1977dd1548');
        $this->addSql('ALTER TABLE equipement_type DROP code_categorie_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement_type ADD code_categorie_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement_type ADD CONSTRAINT fk_6302ce1977dd1548 FOREIGN KEY (code_categorie_id) REFERENCES categorie (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_6302ce1977dd1548 ON equipement_type (code_categorie_id)');
    }
}
