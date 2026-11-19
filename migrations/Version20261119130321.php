<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261119130321 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE device_token (id UUID NOT NULL, token VARCHAR(255) NOT NULL, plateforme VARCHAR(20) DEFAULT \'android\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_device_token_user ON device_token (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_device_token ON device_token (token)');
        $this->addSql('ALTER TABLE device_token ADD CONSTRAINT FK_99B2415CA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE chat_lecture ALTER canal TYPE VARCHAR(64)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE device_token DROP CONSTRAINT FK_99B2415CA76ED395');
        $this->addSql('DROP TABLE device_token');
        $this->addSql('ALTER TABLE chat_lecture ALTER canal TYPE VARCHAR(40)');
    }
}
