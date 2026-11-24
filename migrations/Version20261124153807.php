<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124153807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE logistique.dossier (id UUID NOT NULL, numero_ot VARCHAR(20) NOT NULL, tonnage DOUBLE PRECISION NOT NULL, formule BOOLEAN DEFAULT NULL, cheque BOOLEAN DEFAULT NULL, nb_tc INT NOT NULL, tc_selectionnne BOOLEAN DEFAULT NULL, tc_positionne BOOLEAN DEFAULT NULL, tc_fumigue BOOLEAN DEFAULT NULL, date_empotage DATE DEFAULT NULL, date_relevage DATE DEFAULT NULL, eta DATE DEFAULT NULL, poids_sigam DOUBLE PRECISION DEFAULT NULL, poids_vgm DOUBLE PRECISION DEFAULT NULL, packing_list VARCHAR(20) DEFAULT NULL, certificat_poids VARCHAR(20) DEFAULT NULL, coa VARCHAR(20) DEFAULT NULL, certificat_phyto VARCHAR(20) DEFAULT NULL, co VARCHAR(20) DEFAULT NULL, certificat_halah VARCHAR(20) DEFAULT NULL, certificat_conformite VARCHAR(20) DEFAULT NULL, certificat_hygienne VARCHAR(20) DEFAULT NULL, certificat_fumigation VARCHAR(20) DEFAULT NULL, remise_doc DATE DEFAULT NULL, cv_deblocage VARCHAR(50) DEFAULT NULL, cv_execution VARCHAR(50) DEFAULT NULL, soutien VARCHAR(20) DEFAULT NULL, reversement VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, date_embarquement DATE DEFAULT NULL, etiquette BOOLEAN DEFAULT NULL, draft VARCHAR(50) DEFAULT NULL, filename VARCHAR(255) DEFAULT NULL, remarques TEXT DEFAULT NULL, cloture BOOLEAN DEFAULT NULL, produit_id UUID DEFAULT NULL, client_id UUID DEFAULT NULL, destination_id UUID DEFAULT NULL, proprietaire_id UUID DEFAULT NULL, code_navire_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F5E2A38E16FA9D9A ON logistique.dossier (numero_ot)');
        $this->addSql('CREATE INDEX IDX_F5E2A38EF347EFB ON logistique.dossier (produit_id)');
        $this->addSql('CREATE INDEX IDX_F5E2A38E19EB6921 ON logistique.dossier (client_id)');
        $this->addSql('CREATE INDEX IDX_F5E2A38E816C6140 ON logistique.dossier (destination_id)');
        $this->addSql('CREATE INDEX IDX_F5E2A38E76C50E4A ON logistique.dossier (proprietaire_id)');
        $this->addSql('CREATE INDEX IDX_F5E2A38E740585BA ON logistique.dossier (code_navire_id)');
        $this->addSql('CREATE TABLE lg_formule_export (id UUID NOT NULL, numero VARCHAR(60) NOT NULL, volume NUMERIC(14, 3) NOT NULL, utilise NUMERIC(14, 3) NOT NULL, fichier VARCHAR(255) DEFAULT NULL, nom_original VARCHAR(255) DEFAULT NULL, execution_id UUID NOT NULL, dossier_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2BF92FC5F55AE19E ON lg_formule_export (numero)');
        $this->addSql('CREATE INDEX IDX_2BF92FC557125544 ON lg_formule_export (execution_id)');
        $this->addSql('CREATE INDEX IDX_2BF92FC5611C0C56 ON lg_formule_export (dossier_id)');
        $this->addSql('CREATE TABLE lg_suivi_embarquement (id UUID NOT NULL, conteneurs JSON NOT NULL, rapport_empotage TEXT DEFAULT NULL, fichier_rapport VARCHAR(255) DEFAULT NULL, nom_rapport VARCHAR(255) DEFAULT NULL, certificats JSON NOT NULL, traitements JSON NOT NULL, contrat_africao VARCHAR(100) DEFAULT NULL, port_destination VARCHAR(150) DEFAULT NULL, type_montant VARCHAR(30) DEFAULT NULL, montant NUMERIC(16, 2) DEFAULT NULL, dossier_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8900BE5B611C0C56 ON lg_suivi_embarquement (dossier_id)');
        $this->addSql('ALTER TABLE logistique.dossier ADD CONSTRAINT FK_F5E2A38EF347EFB FOREIGN KEY (produit_id) REFERENCES production.produit (id)');
        $this->addSql('ALTER TABLE logistique.dossier ADD CONSTRAINT FK_F5E2A38E19EB6921 FOREIGN KEY (client_id) REFERENCES logistique.client (id)');
        $this->addSql('ALTER TABLE logistique.dossier ADD CONSTRAINT FK_F5E2A38E816C6140 FOREIGN KEY (destination_id) REFERENCES logistique.destination (id)');
        $this->addSql('ALTER TABLE logistique.dossier ADD CONSTRAINT FK_F5E2A38E76C50E4A FOREIGN KEY (proprietaire_id) REFERENCES logistique.proprietaire (id)');
        $this->addSql('ALTER TABLE logistique.dossier ADD CONSTRAINT FK_F5E2A38E740585BA FOREIGN KEY (code_navire_id) REFERENCES logistique.navire (id)');
        $this->addSql('ALTER TABLE lg_formule_export ADD CONSTRAINT FK_2BF92FC557125544 FOREIGN KEY (execution_id) REFERENCES logistique.cv_execution (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE lg_formule_export ADD CONSTRAINT FK_2BF92FC5611C0C56 FOREIGN KEY (dossier_id) REFERENCES logistique.dossier (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE lg_suivi_embarquement ADD CONSTRAINT FK_8900BE5B611C0C56 FOREIGN KEY (dossier_id) REFERENCES logistique.dossier (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AF30B8EF5441B3BA ON logistique.cv_deblocage (numero_cv)');
        $this->addSql('ALTER TABLE logistique.doc_dossier ADD statut VARCHAR(30) DEFAULT \'POUR_APPROBATION\' NOT NULL');
        $this->addSql('ALTER TABLE logistique.doc_dossier ADD nom_original VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE logistique.doc_dossier ADD transmis_le TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE logistique.doc_dossier ADD transmis_par VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE logistique.doc_dossier ADD CONSTRAINT FK_315B448149B2F1B0 FOREIGN KEY (code_dossier_id) REFERENCES logistique.dossier (id)');
        $this->addSql('ALTER TABLE logistique.documentation ADD CONSTRAINT FK_EC29F78649B2F1B0 FOREIGN KEY (code_dossier_id) REFERENCES logistique.dossier (id)');
        $this->addSql('ALTER TABLE lot_dossier ADD CONSTRAINT FK_90C8A614611C0C56 FOREIGN KEY (dossier_id) REFERENCES logistique.dossier (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE logistique.photos_docs ADD CONSTRAINT FK_15BCB53E49B2F1B0 FOREIGN KEY (code_dossier_id) REFERENCES logistique.dossier (id)');
        $this->addSql('ALTER TABLE logistique.tc ALTER pn_th TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE logistique.tc ALTER pn_reel TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE tc_dossier ADD CONSTRAINT FK_55E42ED6611C0C56 FOREIGN KEY (dossier_id) REFERENCES logistique.dossier (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE logistique.dossier DROP CONSTRAINT FK_F5E2A38EF347EFB');
        $this->addSql('ALTER TABLE logistique.dossier DROP CONSTRAINT FK_F5E2A38E19EB6921');
        $this->addSql('ALTER TABLE logistique.dossier DROP CONSTRAINT FK_F5E2A38E816C6140');
        $this->addSql('ALTER TABLE logistique.dossier DROP CONSTRAINT FK_F5E2A38E76C50E4A');
        $this->addSql('ALTER TABLE logistique.dossier DROP CONSTRAINT FK_F5E2A38E740585BA');
        $this->addSql('ALTER TABLE lg_formule_export DROP CONSTRAINT FK_2BF92FC557125544');
        $this->addSql('ALTER TABLE lg_formule_export DROP CONSTRAINT FK_2BF92FC5611C0C56');
        $this->addSql('ALTER TABLE lg_suivi_embarquement DROP CONSTRAINT FK_8900BE5B611C0C56');
        $this->addSql('DROP TABLE logistique.dossier');
        $this->addSql('DROP TABLE lg_formule_export');
        $this->addSql('DROP TABLE lg_suivi_embarquement');
        $this->addSql('DROP INDEX logistique.UNIQ_AF30B8EF5441B3BA');
        $this->addSql('ALTER TABLE logistique.doc_dossier DROP CONSTRAINT FK_315B448149B2F1B0');
        $this->addSql('ALTER TABLE logistique.doc_dossier DROP statut');
        $this->addSql('ALTER TABLE logistique.doc_dossier DROP nom_original');
        $this->addSql('ALTER TABLE logistique.doc_dossier DROP transmis_le');
        $this->addSql('ALTER TABLE logistique.doc_dossier DROP transmis_par');
        $this->addSql('ALTER TABLE logistique.documentation DROP CONSTRAINT FK_EC29F78649B2F1B0');
        $this->addSql('ALTER TABLE logistique.photos_docs DROP CONSTRAINT FK_15BCB53E49B2F1B0');
        $this->addSql('ALTER TABLE logistique.tc ALTER pn_th TYPE INT');
        $this->addSql('ALTER TABLE logistique.tc ALTER pn_reel TYPE INT');
        $this->addSql('ALTER TABLE lot_dossier DROP CONSTRAINT FK_90C8A614611C0C56');
        $this->addSql('ALTER TABLE tc_dossier DROP CONSTRAINT FK_55E42ED6611C0C56');
    }
}
