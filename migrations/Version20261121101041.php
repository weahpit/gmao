<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261121101041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE dashboard_preference_gadget (id UUID NOT NULL, role_key VARCHAR(40) NOT NULL, code VARCHAR(60) NOT NULL, visible BOOLEAN NOT NULL, actif BOOLEAN NOT NULL, ordre INT NOT NULL, maj_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, utilisateur_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_dashboard_pref ON dashboard_preference_gadget (utilisateur_id, role_key, code)');
        $this->addSql('CREATE INDEX IDX_EB141C45FB88E14F ON dashboard_preference_gadget (utilisateur_id)');
        $this->addSql('ALTER TABLE dashboard_preference_gadget ADD CONSTRAINT FK_EB141C45FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE message_chat DROP remis_at');
        $this->addSql('ALTER TABLE message_chat DROP lu_at');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dashboard_preference_gadget DROP CONSTRAINT FK_EB141C45FB88E14F');
        $this->addSql('DROP TABLE dashboard_preference_gadget');
        $this->addSql('ALTER TABLE message_chat ADD remis_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE message_chat ADD lu_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }
}
