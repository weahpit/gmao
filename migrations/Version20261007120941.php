<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007120941 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE poste (id UUID NOT NULL, libelle VARCHAR(100) NOT NULL, code_service_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7C890FABC5F25400 ON poste (code_service_id)');
        $this->addSql('ALTER TABLE poste ADD CONSTRAINT FK_7C890FABC5F25400 FOREIGN KEY (code_service_id) REFERENCES services (id)');
        $this->addSql('ALTER TABLE "user" ADD code_poste_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D649591128AD FOREIGN KEY (code_poste_id) REFERENCES poste (id)');
        $this->addSql('CREATE INDEX IDX_8D93D649591128AD ON "user" (code_poste_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE poste DROP CONSTRAINT FK_7C890FABC5F25400');
        $this->addSql('DROP TABLE poste');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649591128AD');
        $this->addSql('DROP INDEX IDX_8D93D649591128AD');
        $this->addSql('ALTER TABLE "user" DROP code_poste_id');
    }
}
