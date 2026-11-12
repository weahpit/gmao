<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Module de gestion du courrier (entrant / sortant).
 *  - courier        : un enregistrement par courrier recu ou emis ;
 *  - courrier_piece_jointe : fichiers joints (scan, annexe...).
 */
final class Version20261112180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree les tables du module de gestion du courrier (courrier, courrier_piece_jointe).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            'Cette migration est prevue pour PostgreSQL.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE courrier (
                id UUID NOT NULL,
                created_by_id UUID DEFAULT NULL,
                numero VARCHAR(30) NOT NULL,
                sens VARCHAR(20) NOT NULL,
                objet VARCHAR(255) NOT NULL,
                correspondant VARCHAR(180) DEFAULT NULL,
                organisme VARCHAR(180) DEFAULT NULL,
                reference VARCHAR(80) DEFAULT NULL,
                date_courrier DATE NOT NULL,
                date_accuse DATE DEFAULT NULL,
                statut VARCHAR(20) NOT NULL,
                priorite VARCHAR(20) NOT NULL,
                service VARCHAR(120) DEFAULT NULL,
                responsable VARCHAR(150) DEFAULT NULL,
                observation TEXT DEFAULT NULL,
                suite_donnee TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX uniq_courrier_numero ON courrier (numero)');
        $this->addSql('CREATE INDEX idx_courrier_sens ON courrier (sens)');
        $this->addSql('CREATE INDEX idx_courrier_date ON courrier (date_courrier)');
        $this->addSql('CREATE INDEX idx_courrier_statut ON courrier (statut)');
        $this->addSql('COMMENT ON COLUMN courrier.date_courrier IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN courrier.date_accuse IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN courrier.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN courrier.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE courrier ADD CONSTRAINT fk_courrier_created_by FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE courrier_piece_jointe (
                id UUID NOT NULL,
                courrier_id UUID NOT NULL,
                fichier VARCHAR(255) NOT NULL,
                nom_original VARCHAR(255) NOT NULL,
                type_mime VARCHAR(150) DEFAULT NULL,
                taille INT DEFAULT NULL,
                commentaire VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_courrier_pj_courrier ON courrier_piece_jointe (courrier_id)');
        $this->addSql('COMMENT ON COLUMN courrier_piece_jointe.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE courrier_piece_jointe ADD CONSTRAINT fk_courrier_pj_courrier FOREIGN KEY (courrier_id) REFERENCES courrier (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            'Cette migration est prevue pour PostgreSQL.'
        );

        $this->addSql('ALTER TABLE courrier_piece_jointe DROP CONSTRAINT fk_courrier_pj_courrier');
        $this->addSql('DROP TABLE courrier_piece_jointe');
        $this->addSql('ALTER TABLE courrier DROP CONSTRAINT fk_courrier_created_by');
        $this->addSql('DROP TABLE courrier');
    }
}
