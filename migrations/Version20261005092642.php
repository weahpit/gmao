<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005092642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE emplacement (id UUID NOT NULL, libelle VARCHAR(255) DEFAULT NULL, code_type_emplacement_id UUID DEFAULT NULL, code_zone_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C0CF65F643B37557 ON emplacement (code_type_emplacement_id)');
        $this->addSql('CREATE INDEX IDX_C0CF65F6A78B7B37 ON emplacement (code_zone_id)');
        $this->addSql('CREATE TABLE equipement_type_emplacement (equipement_type_id UUID NOT NULL, emplacement_id UUID NOT NULL, PRIMARY KEY (equipement_type_id, emplacement_id))');
        $this->addSql('CREATE INDEX IDX_9D076A4FCEC1C640 ON equipement_type_emplacement (equipement_type_id)');
        $this->addSql('CREATE INDEX IDX_9D076A4FC4598A51 ON equipement_type_emplacement (emplacement_id)');
        $this->addSql('CREATE TABLE type_emplacement (id UUID NOT NULL, libelle VARCHAR(100) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE type_zone_exploitation (id UUID NOT NULL, libelle VARCHAR(100) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE emplacement ADD CONSTRAINT FK_C0CF65F643B37557 FOREIGN KEY (code_type_emplacement_id) REFERENCES type_emplacement (id)');
        $this->addSql('ALTER TABLE emplacement ADD CONSTRAINT FK_C0CF65F6A78B7B37 FOREIGN KEY (code_zone_id) REFERENCES zone_exploitation (id)');
        $this->addSql('ALTER TABLE equipement_type_emplacement ADD CONSTRAINT FK_9D076A4FCEC1C640 FOREIGN KEY (equipement_type_id) REFERENCES equipement_type (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipement_type_emplacement ADD CONSTRAINT FK_9D076A4FC4598A51 FOREIGN KEY (emplacement_id) REFERENCES emplacement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE zone_exploitation ADD CONSTRAINT FK_D12B5D62B70D505E FOREIGN KEY (type_zone_id) REFERENCES type_zone_exploitation (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE emplacement DROP CONSTRAINT FK_C0CF65F643B37557');
        $this->addSql('ALTER TABLE emplacement DROP CONSTRAINT FK_C0CF65F6A78B7B37');
        $this->addSql('ALTER TABLE equipement_type_emplacement DROP CONSTRAINT FK_9D076A4FCEC1C640');
        $this->addSql('ALTER TABLE equipement_type_emplacement DROP CONSTRAINT FK_9D076A4FC4598A51');
        $this->addSql('DROP TABLE emplacement');
        $this->addSql('DROP TABLE equipement_type_emplacement');
        $this->addSql('DROP TABLE type_emplacement');
        $this->addSql('DROP TABLE type_zone_exploitation');
        $this->addSql('ALTER TABLE zone_exploitation DROP CONSTRAINT FK_D12B5D62B70D505E');
    }
}
