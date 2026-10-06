<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261006103151 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mouvement_equipement ADD code_equipement_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE mouvement_equipement ADD CONSTRAINT FK_C6F68023BD7CB279 FOREIGN KEY (code_equipement_id) REFERENCES equipement (id)');
        $this->addSql('CREATE INDEX IDX_C6F68023BD7CB279 ON mouvement_equipement (code_equipement_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mouvement_equipement DROP CONSTRAINT FK_C6F68023BD7CB279');
        $this->addSql('DROP INDEX IDX_C6F68023BD7CB279');
        $this->addSql('ALTER TABLE mouvement_equipement DROP code_equipement_id');
    }
}
