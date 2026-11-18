<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261118170314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chat_lecture (id UUID NOT NULL, canal VARCHAR(40) NOT NULL, dernier_lu_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, utilisateur_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_chat_lecture ON chat_lecture (utilisateur_id, canal)');
        $this->addSql('CREATE INDEX IDX_8D69B521FB88E14F ON chat_lecture (utilisateur_id)');
        $this->addSql('CREATE TABLE message_chat (id UUID NOT NULL, contenu TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, lu BOOLEAN NOT NULL, expediteur_id UUID NOT NULL, destinataire_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_chat_created ON message_chat (created_at)');
        $this->addSql('CREATE INDEX idx_chat_destinataire_lu ON message_chat (destinataire_id, lu)');
        $this->addSql('CREATE INDEX IDX_CC08697310335F61 ON message_chat (expediteur_id)');
        $this->addSql('CREATE INDEX IDX_CC086973A4F84F6E ON message_chat (destinataire_id)');
        $this->addSql('ALTER TABLE chat_lecture ADD CONSTRAINT FK_8D69B521FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE message_chat ADD CONSTRAINT FK_CC08697310335F61 FOREIGN KEY (expediteur_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE message_chat ADD CONSTRAINT FK_CC086973A4F84F6E FOREIGN KEY (destinataire_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_lecture DROP CONSTRAINT FK_8D69B521FB88E14F');
        $this->addSql('ALTER TABLE message_chat DROP CONSTRAINT FK_CC08697310335F61');
        $this->addSql('ALTER TABLE message_chat DROP CONSTRAINT FK_CC086973A4F84F6E');
        $this->addSql('DROP TABLE chat_lecture');
        $this->addSql('DROP TABLE message_chat');
    }
}
