<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261016150517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE capp_tdb (id UUID NOT NULL, ordre INT DEFAULT NULL, code_gadget_id UUID DEFAULT NULL, code_user_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_86CCA3C12888A41E ON capp_tdb (code_gadget_id)');
        $this->addSql('CREATE INDEX IDX_86CCA3C19FC99709 ON capp_tdb (code_user_id)');
        $this->addSql('ALTER TABLE capp_tdb ADD CONSTRAINT FK_86CCA3C12888A41E FOREIGN KEY (code_gadget_id) REFERENCES widget (id)');
        $this->addSql('ALTER TABLE capp_tdb ADD CONSTRAINT FK_86CCA3C19FC99709 FOREIGN KEY (code_user_id) REFERENCES "user" (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE capp_tdb DROP CONSTRAINT FK_86CCA3C12888A41E');
        $this->addSql('ALTER TABLE capp_tdb DROP CONSTRAINT FK_86CCA3C19FC99709');
        $this->addSql('DROP TABLE capp_tdb');
    }
}
