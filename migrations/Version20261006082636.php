<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261006082636 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement ADD code_equipement_type_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE equipement ADD CONSTRAINT FK_B8B4C6F35C70A5E4 FOREIGN KEY (code_equipement_type_id) REFERENCES equipement_type (id)');
        $this->addSql('CREATE INDEX IDX_B8B4C6F35C70A5E4 ON equipement (code_equipement_type_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement DROP CONSTRAINT FK_B8B4C6F35C70A5E4');
        $this->addSql('DROP INDEX IDX_B8B4C6F35C70A5E4');
        $this->addSql('ALTER TABLE equipement DROP code_equipement_type_id');
    }
}
