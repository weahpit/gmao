<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tables du module Courrier (si elles n'existent pas encore en base).
 *
 * Les entites Courrier / PieceJointeCourrier ont ete livrees sans migration :
 * sur une base neuve, l'enregistrement d'un courrier depuis le mobile echoue
 * (table absente) ou les pieces jointes sont perdues. Cette migration cree
 * les deux tables de facon IDEMPOTENTE : sur une base ou elles existent deja
 * (creation par doctrine:schema:update), elle ne fait rien.
 */
final class Version20261114100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creation idempotente des tables courrier et courrier_piece_jointe (pieces jointes mobiles).';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        if (!in_array('courrier', $tables, true)) {
            $this->addSql(<<<'SQL'
                CREATE TABLE courrier (
                    id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
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
                    service VARCHAR(150) DEFAULT NULL,
                    responsable VARCHAR(150) DEFAULT NULL,
                    observation LONGTEXT DEFAULT NULL,
                    suite_donnee LONGTEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                    updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                    code_service_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:guid)',
                    created_by_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:guid)',
                    INDEX idx_courrier_sens (sens),
                    INDEX idx_courrier_date (date_courrier),
                    INDEX IDX_COURRIER_SERVICE (code_service_id),
                    INDEX IDX_COURRIER_AUTEUR (created_by_id),
                    UNIQUE INDEX UNIQ_COURRIER_NUMERO (numero),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
                SQL);
            $this->addSql('ALTER TABLE courrier ADD CONSTRAINT FK_COURRIER_SERVICE FOREIGN KEY (code_service_id) REFERENCES `services` (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE courrier ADD CONSTRAINT FK_COURRIER_AUTEUR FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        }

        if (!in_array('courrier_piece_jointe', $tables, true)) {
            $this->addSql(<<<'SQL'
                CREATE TABLE courrier_piece_jointe (
                    id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
                    courrier_id CHAR(36) NOT NULL COMMENT '(DC2Type:guid)',
                    fichier VARCHAR(255) NOT NULL,
                    nom_original VARCHAR(255) NOT NULL,
                    type_mime VARCHAR(150) DEFAULT NULL,
                    taille INT DEFAULT NULL,
                    commentaire VARCHAR(255) DEFAULT NULL,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                    INDEX IDX_PIECE_COURRIER (courrier_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
                SQL);
            $this->addSql('ALTER TABLE courrier_piece_jointe ADD CONSTRAINT FK_PIECE_COURRIER FOREIGN KEY (courrier_id) REFERENCES courrier (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        // Migration de securite : on ne supprime volontairement rien, afin de
        // ne jamais detruire des courriers deja enregistres.
    }
}
