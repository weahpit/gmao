<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261029184914 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE demande_achat (id UUID NOT NULL, numero VARCHAR(50) NOT NULL, token VARCHAR(64) NOT NULL, statut VARCHAR(20) NOT NULL, service VARCHAR(255) DEFAULT NULL, demandeur VARCHAR(255) DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, lignes JSON DEFAULT NULL, motif TEXT DEFAULT NULL, enregistre_par VARCHAR(255) DEFAULT NULL, soumise_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, valide_par VARCHAR(255) DEFAULT NULL, valide_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, commentaire_dg TEXT DEFAULT NULL, type_traitement VARCHAR(30) DEFAULT NULL, fournisseur_nom VARCHAR(255) DEFAULT NULL, commande_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, servie_par VARCHAR(255) DEFAULT NULL, servie_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, bon_sortie_ref VARCHAR(50) DEFAULT NULL, historique JSON DEFAULT NULL, code_service_id UUID DEFAULT NULL, fournisseur_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D077077FF55AE19E ON demande_achat (numero)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D077077F5F37A13B ON demande_achat (token)');
        $this->addSql('CREATE INDEX IDX_D077077FC5F25400 ON demande_achat (code_service_id)');
        $this->addSql('CREATE INDEX IDX_D077077F670C757F ON demande_achat (fournisseur_id)');
        $this->addSql('ALTER TABLE demande_achat ADD CONSTRAINT FK_D077077FC5F25400 FOREIGN KEY (code_service_id) REFERENCES services (id)');
        $this->addSql('ALTER TABLE demande_achat ADD CONSTRAINT FK_D077077F670C757F FOREIGN KEY (fournisseur_id) REFERENCES fournisseur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE demande_achat DROP CONSTRAINT FK_D077077FC5F25400');
        $this->addSql('ALTER TABLE demande_achat DROP CONSTRAINT FK_D077077F670C757F');
        $this->addSql('DROP TABLE demande_achat');
    }
}
