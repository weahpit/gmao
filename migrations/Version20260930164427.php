<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260930164427 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mouvement_equipement (id UUID NOT NULL, type_mvt VARCHAR(20) DEFAULT NULL, value DOUBLE PRECISION DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, qte DOUBLE PRECISION DEFAULT NULL, code_equipement_type_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C6F680235C70A5E4 ON mouvement_equipement (code_equipement_type_id)');
        $this->addSql('ALTER TABLE mouvement_equipement ADD CONSTRAINT FK_C6F680235C70A5E4 FOREIGN KEY (code_equipement_type_id) REFERENCES equipement_type (id)');
        $this->addSql('ALTER TABLE equipement_type ADD qte DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mouvement_equipement DROP CONSTRAINT FK_C6F680235C70A5E4');
        $this->addSql('DROP TABLE mouvement_equipement');
        $this->addSql('ALTER TABLE equipement_type DROP qte');
    }
}
