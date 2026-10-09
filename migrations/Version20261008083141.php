<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008083141 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE alerte_anomalie (id UUID NOT NULL, sujet VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, code_equipement_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9135EBD9BD7CB279 ON alerte_anomalie (code_equipement_id)');
        $this->addSql('ALTER TABLE alerte_anomalie ADD CONSTRAINT FK_9135EBD9BD7CB279 FOREIGN KEY (code_equipement_id) REFERENCES equipement_type (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE alerte_anomalie DROP CONSTRAINT FK_9135EBD9BD7CB279');
        $this->addSql('DROP TABLE alerte_anomalie');
    }
}
