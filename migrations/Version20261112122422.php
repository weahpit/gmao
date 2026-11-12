<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112122422 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE participant_reunion (id UUID NOT NULL, nom VARCHAR(100) NOT NULL, prenoms VARCHAR(150) NOT NULL, service VARCHAR(120) DEFAULT NULL, qualite VARCHAR(120) DEFAULT NULL, telephone VARCHAR(40) DEFAULT NULL, signature VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ip VARCHAR(45) DEFAULT NULL, reunion_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C10D6B8A4E9B7368 ON participant_reunion (reunion_id)');
        $this->addSql('CREATE TABLE reunion (id UUID NOT NULL, titre VARCHAR(180) NOT NULL, objet TEXT DEFAULT NULL, lieu VARCHAR(150) DEFAULT NULL, date_debut TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_fin TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, statut VARCHAR(20) NOT NULL, jeton VARCHAR(40) NOT NULL, compte_rendu VARCHAR(255) DEFAULT NULL, compte_rendu_nom_original VARCHAR(180) DEFAULT NULL, compte_rendu_ajoute_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, compte_rendu_publie BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, cloturee_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5B00A4822CF647B ON reunion (jeton)');
        $this->addSql('ALTER TABLE participant_reunion ADD CONSTRAINT FK_C10D6B8A4E9B7368 FOREIGN KEY (reunion_id) REFERENCES reunion (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE participant_reunion DROP CONSTRAINT FK_C10D6B8A4E9B7368');
        $this->addSql('DROP TABLE participant_reunion');
        $this->addSql('DROP TABLE reunion');
    }
}
