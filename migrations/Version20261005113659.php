<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005113659 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mouvement_equipement ADD numero_bon VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE mouvement_equipement ADD precision_emplacement VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE mouvement_equipement ADD observation TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE mouvement_equipement ADD emplacement_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE mouvement_equipement ADD CONSTRAINT FK_C6F68023C4598A51 FOREIGN KEY (emplacement_id) REFERENCES emplacement (id)');
        $this->addSql('CREATE INDEX IDX_C6F68023C4598A51 ON mouvement_equipement (emplacement_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mouvement_equipement DROP CONSTRAINT FK_C6F68023C4598A51');
        $this->addSql('DROP INDEX IDX_C6F68023C4598A51');
        $this->addSql('ALTER TABLE mouvement_equipement DROP numero_bon');
        $this->addSql('ALTER TABLE mouvement_equipement DROP precision_emplacement');
        $this->addSql('ALTER TABLE mouvement_equipement DROP observation');
        $this->addSql('ALTER TABLE mouvement_equipement DROP emplacement_id');
    }
}
