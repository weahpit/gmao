<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124080521 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE message_chat ADD remis_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE message_chat ADD lu_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE message_chat ADD client_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_chat_client ON message_chat (client_id)');
        $this->addSql('ALTER TABLE rh_employe ALTER matricule DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_chat_client');
        $this->addSql('ALTER TABLE message_chat DROP remis_at');
        $this->addSql('ALTER TABLE message_chat DROP lu_at');
        $this->addSql('ALTER TABLE message_chat DROP client_id');
        $this->addSql('ALTER TABLE rh_employe ALTER matricule SET NOT NULL');
    }
}
