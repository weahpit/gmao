<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260918184728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE nature_equipement (id UUID NOT NULL, libelle VARCHAR(100) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE equipement ADD nature_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement ADD CONSTRAINT FK_B8B4C6F33BCB2E4B FOREIGN KEY (nature_id) REFERENCES nature_equipement (id)');
        $this->addSql('CREATE INDEX IDX_B8B4C6F33BCB2E4B ON equipement (nature_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE nature_equipement');
        $this->addSql('ALTER TABLE equipement DROP CONSTRAINT FK_B8B4C6F33BCB2E4B');
        $this->addSql('DROP INDEX IDX_B8B4C6F33BCB2E4B');
        $this->addSql('ALTER TABLE equipement DROP nature_id');
    }
}
