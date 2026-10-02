<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002094244 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE directions (id UUID NOT NULL, libelle VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE services (id UUID NOT NULL, libelle VARCHAR(100) DEFAULT NULL, code_direction_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7332E169645B2BF2 ON services (code_direction_id)');
        $this->addSql('ALTER TABLE services ADD CONSTRAINT FK_7332E169645B2BF2 FOREIGN KEY (code_direction_id) REFERENCES directions (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE services DROP CONSTRAINT FK_7332E169645B2BF2');
        $this->addSql('DROP TABLE directions');
        $this->addSql('DROP TABLE services');
    }
}
