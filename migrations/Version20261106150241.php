<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261106150241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE saisie_stock ADD source VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE saisie_stock ADD emplacement_libelle VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE saisie_stock ADD motif VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE saisie_stock ADD emplacement_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE saisie_stock ADD CONSTRAINT FK_3B61CFB4C4598A51 FOREIGN KEY (emplacement_id) REFERENCES emplacement (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_3B61CFB4C4598A51 ON saisie_stock (emplacement_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE saisie_stock DROP CONSTRAINT FK_3B61CFB4C4598A51');
        $this->addSql('DROP INDEX IDX_3B61CFB4C4598A51');
        $this->addSql('ALTER TABLE saisie_stock DROP source');
        $this->addSql('ALTER TABLE saisie_stock DROP emplacement_libelle');
        $this->addSql('ALTER TABLE saisie_stock DROP motif');
        $this->addSql('ALTER TABLE saisie_stock DROP emplacement_id');
    }
}
