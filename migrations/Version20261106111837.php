<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261106111837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sortie_stock (id UUID NOT NULL, agent_libelle VARCHAR(180) DEFAULT NULL, service_libelle VARCHAR(100) DEFAULT NULL, emplacement_libelle VARCHAR(255) DEFAULT NULL, rubrique VARCHAR(100) NOT NULL, quantite DOUBLE PRECISION NOT NULL, unite VARCHAR(20) NOT NULL, motif VARCHAR(255) NOT NULL, photo VARCHAR(255) DEFAULT NULL, date_sortie TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, agent_id UUID DEFAULT NULL, service_id UUID DEFAULT NULL, emplacement_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D238F3643414710B ON sortie_stock (agent_id)');
        $this->addSql('CREATE INDEX IDX_D238F364ED5CA9E6 ON sortie_stock (service_id)');
        $this->addSql('CREATE INDEX IDX_D238F364C4598A51 ON sortie_stock (emplacement_id)');
        $this->addSql('ALTER TABLE sortie_stock ADD CONSTRAINT FK_D238F3643414710B FOREIGN KEY (agent_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE sortie_stock ADD CONSTRAINT FK_D238F364ED5CA9E6 FOREIGN KEY (service_id) REFERENCES services (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE sortie_stock ADD CONSTRAINT FK_D238F364C4598A51 FOREIGN KEY (emplacement_id) REFERENCES emplacement (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sortie_stock DROP CONSTRAINT FK_D238F3643414710B');
        $this->addSql('ALTER TABLE sortie_stock DROP CONSTRAINT FK_D238F364ED5CA9E6');
        $this->addSql('ALTER TABLE sortie_stock DROP CONSTRAINT FK_D238F364C4598A51');
        $this->addSql('DROP TABLE sortie_stock');
    }
}
