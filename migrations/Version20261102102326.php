<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261102102326 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA logistique');
        $this->addSql('CREATE SCHEMA production');
        $this->addSql('CREATE TABLE logistique.approbation (id UUID NOT NULL, valeur VARCHAR(50) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE logistique.client (id UUID NOT NULL, nom_client VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE logistique.cv_deblocage (id UUID NOT NULL, numero_cv VARCHAR(25) NOT NULL, poids DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE logistique.cv_execution (id UUID NOT NULL, numero_cv_execution VARCHAR(25) NOT NULL, poids DOUBLE PRECISION DEFAULT NULL, code_cv_deblocage_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_DD82E6304EA15721 ON logistique.cv_execution (code_cv_deblocage_id)');
        $this->addSql('CREATE TABLE logistique.destination (id UUID NOT NULL, denomination VARCHAR(100) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE logistique.details_modele (id UUID NOT NULL, code_doc_id UUID DEFAULT NULL, code_modele_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9568A678F6D0101E ON logistique.details_modele (code_doc_id)');
        $this->addSql('CREATE INDEX IDX_9568A67851CF32 ON logistique.details_modele (code_modele_id)');
        $this->addSql('CREATE TABLE logistique.doc_dossier (id UUID NOT NULL, libelle VARCHAR(255) NOT NULL, filename VARCHAR(255) DEFAULT NULL, code_modele_id UUID DEFAULT NULL, code_dossier_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_315B448151CF32 ON logistique.doc_dossier (code_modele_id)');
        $this->addSql('CREATE INDEX IDX_315B448149B2F1B0 ON logistique.doc_dossier (code_dossier_id)');
        $this->addSql('CREATE TABLE logistique.doc_type (id UUID NOT NULL, libelle VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE logistique.documentation (id UUID NOT NULL, filename VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(255) DEFAULT NULL, code_dossier_id UUID DEFAULT NULL, code_type_doc_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_EC29F78649B2F1B0 ON logistique.documentation (code_dossier_id)');
        $this->addSql('CREATE INDEX IDX_EC29F786E3C0CA34 ON logistique.documentation (code_type_doc_id)');
        $this->addSql('CREATE TABLE logistique.dossier (id UUID NOT NULL, numero_ot VARCHAR(20) NOT NULL, tonnage VARCHAR(255) NOT NULL, formule BOOLEAN DEFAULT NULL, cheque BOOLEAN DEFAULT NULL, nb_tc VARCHAR(255) NOT NULL, tc_selectionnne BOOLEAN DEFAULT NULL, tc_positionne BOOLEAN DEFAULT NULL, tc_fumigue BOOLEAN DEFAULT NULL, date_empotage DATE DEFAULT NULL, date_relevage DATE DEFAULT NULL, eta DATE DEFAULT NULL, poids_sigam DOUBLE PRECISION DEFAULT NULL, poids_vgm DOUBLE PRECISION DEFAULT NULL, packing_list VARCHAR(20) DEFAULT NULL, certificat_poids VARCHAR(20) DEFAULT NULL, coa VARCHAR(20) DEFAULT NULL, certificat_phyto VARCHAR(20) DEFAULT NULL, co VARCHAR(20) DEFAULT NULL, certificat_halah VARCHAR(20) DEFAULT NULL, certificat_conformite VARCHAR(20) DEFAULT NULL, certificat_hygienne VARCHAR(20) DEFAULT NULL, certificat_fumigation VARCHAR(20) DEFAULT NULL, remise_doc DATE DEFAULT NULL, cv_deblocage VARCHAR(50) DEFAULT NULL, cv_execution VARCHAR(50) DEFAULT NULL, soutien VARCHAR(20) DEFAULT NULL, reversement VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, date_embarquement DATE DEFAULT NULL, etiquette BOOLEAN DEFAULT NULL, draft VARCHAR(50) DEFAULT NULL, filename VARCHAR(255) DEFAULT NULL, remarques TEXT DEFAULT NULL, cloture BOOLEAN DEFAULT NULL, produit_id UUID DEFAULT NULL, client_id UUID DEFAULT NULL, destination_id UUID DEFAULT NULL, proprietaire_id UUID DEFAULT NULL, code_navire_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F5E2A38EF347EFB ON logistique.dossier (produit_id)');
        $this->addSql('CREATE INDEX IDX_F5E2A38E19EB6921 ON logistique.dossier (client_id)');
        $this->addSql('CREATE INDEX IDX_F5E2A38E816C6140 ON logistique.dossier (destination_id)');
        $this->addSql('CREATE INDEX IDX_F5E2A38E76C50E4A ON logistique.dossier (proprietaire_id)');
        $this->addSql('CREATE INDEX IDX_F5E2A38E740585BA ON logistique.dossier (code_navire_id)');
        $this->addSql('CREATE TABLE production.lot (id UUID NOT NULL, numero_lot VARCHAR(20) NOT NULL, poids_net DOUBLE PRECISION DEFAULT NULL, poids_net_dispo DOUBLE PRECISION DEFAULT NULL, pn_th DOUBLE PRECISION DEFAULT NULL, date_prod DATE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(255) NOT NULL, updated_by VARCHAR(255) DEFAULT NULL, qualite VARCHAR(20) DEFAULT NULL, scission BOOLEAN DEFAULT NULL, code_magasin_id UUID DEFAULT NULL, code_proprio_id UUID DEFAULT NULL, code_produit_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_73802CC88A79705 ON production.lot (code_magasin_id)');
        $this->addSql('CREATE INDEX IDX_73802CC82E16DBE6 ON production.lot (code_proprio_id)');
        $this->addSql('CREATE INDEX IDX_73802CC8279A831D ON production.lot (code_produit_id)');
        $this->addSql('CREATE TABLE logistique.magasin (id UUID NOT NULL, libelle VARCHAR(100) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE logistique.modele_doc (id UUID NOT NULL, nom_modele VARCHAR(100) DEFAULT NULL, standard BOOLEAN DEFAULT NULL, code_produit_id UUID DEFAULT NULL, code_client_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2A34FB39279A831D ON logistique.modele_doc (code_produit_id)');
        $this->addSql('CREATE INDEX IDX_2A34FB39B5AE1119 ON logistique.modele_doc (code_client_id)');
        $this->addSql('CREATE TABLE logistique.navire (id UUID NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE logistique.photos_docs (id UUID NOT NULL, filename VARCHAR(255) DEFAULT NULL, fichier VARCHAR(255) DEFAULT NULL, extension VARCHAR(10) DEFAULT NULL, code_dossier_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_15BCB53E49B2F1B0 ON logistique.photos_docs (code_dossier_id)');
        $this->addSql('CREATE TABLE production.produit (id UUID NOT NULL, libelle VARCHAR(100) NOT NULL, code_proprio_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5C4AF0F42E16DBE6 ON production.produit (code_proprio_id)');
        $this->addSql('CREATE TABLE logistique.proprietaire (id UUID NOT NULL, denomination VARCHAR(50) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE logistique.tc (id UUID NOT NULL, type_tc VARCHAR(20) NOT NULL, numero_tc VARCHAR(50) NOT NULL, pn_th INT NOT NULL, pn_reel INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE logistique.cv_execution ADD CONSTRAINT FK_DD82E6304EA15721 FOREIGN KEY (code_cv_deblocage_id) REFERENCES logistique.cv_deblocage (id)');
        $this->addSql('ALTER TABLE logistique.details_modele ADD CONSTRAINT FK_9568A678F6D0101E FOREIGN KEY (code_doc_id) REFERENCES logistique.doc_type (id)');
        $this->addSql('ALTER TABLE logistique.details_modele ADD CONSTRAINT FK_9568A67851CF32 FOREIGN KEY (code_modele_id) REFERENCES logistique.modele_doc (id)');
        $this->addSql('ALTER TABLE logistique.doc_dossier ADD CONSTRAINT FK_315B448151CF32 FOREIGN KEY (code_modele_id) REFERENCES logistique.modele_doc (id)');
        $this->addSql('ALTER TABLE logistique.doc_dossier ADD CONSTRAINT FK_315B448149B2F1B0 FOREIGN KEY (code_dossier_id) REFERENCES logistique.dossier (id)');
        $this->addSql('ALTER TABLE logistique.documentation ADD CONSTRAINT FK_EC29F78649B2F1B0 FOREIGN KEY (code_dossier_id) REFERENCES logistique.dossier (id)');
        $this->addSql('ALTER TABLE logistique.documentation ADD CONSTRAINT FK_EC29F786E3C0CA34 FOREIGN KEY (code_type_doc_id) REFERENCES logistique.doc_type (id)');
        $this->addSql('ALTER TABLE logistique.dossier ADD CONSTRAINT FK_F5E2A38EF347EFB FOREIGN KEY (produit_id) REFERENCES production.produit (id)');
        $this->addSql('ALTER TABLE logistique.dossier ADD CONSTRAINT FK_F5E2A38E19EB6921 FOREIGN KEY (client_id) REFERENCES logistique.client (id)');
        $this->addSql('ALTER TABLE logistique.dossier ADD CONSTRAINT FK_F5E2A38E816C6140 FOREIGN KEY (destination_id) REFERENCES logistique.destination (id)');
        $this->addSql('ALTER TABLE logistique.dossier ADD CONSTRAINT FK_F5E2A38E76C50E4A FOREIGN KEY (proprietaire_id) REFERENCES logistique.proprietaire (id)');
        $this->addSql('ALTER TABLE logistique.dossier ADD CONSTRAINT FK_F5E2A38E740585BA FOREIGN KEY (code_navire_id) REFERENCES logistique.navire (id)');
        $this->addSql('ALTER TABLE production.lot ADD CONSTRAINT FK_73802CC88A79705 FOREIGN KEY (code_magasin_id) REFERENCES logistique.magasin (id)');
        $this->addSql('ALTER TABLE production.lot ADD CONSTRAINT FK_73802CC82E16DBE6 FOREIGN KEY (code_proprio_id) REFERENCES logistique.proprietaire (id)');
        $this->addSql('ALTER TABLE production.lot ADD CONSTRAINT FK_73802CC8279A831D FOREIGN KEY (code_produit_id) REFERENCES production.produit (id)');
        $this->addSql('ALTER TABLE logistique.modele_doc ADD CONSTRAINT FK_2A34FB39279A831D FOREIGN KEY (code_produit_id) REFERENCES production.produit (id)');
        $this->addSql('ALTER TABLE logistique.modele_doc ADD CONSTRAINT FK_2A34FB39B5AE1119 FOREIGN KEY (code_client_id) REFERENCES logistique.client (id)');
        $this->addSql('ALTER TABLE logistique.photos_docs ADD CONSTRAINT FK_15BCB53E49B2F1B0 FOREIGN KEY (code_dossier_id) REFERENCES logistique.dossier (id)');
        $this->addSql('ALTER TABLE production.produit ADD CONSTRAINT FK_5C4AF0F42E16DBE6 FOREIGN KEY (code_proprio_id) REFERENCES logistique.proprietaire (id)');
        $this->addSql('ALTER TABLE doc_type_produit ADD CONSTRAINT FK_B36A01201AAE044D FOREIGN KEY (doc_type_id) REFERENCES logistique.doc_type (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE doc_type_produit ADD CONSTRAINT FK_B36A0120F347EFB FOREIGN KEY (produit_id) REFERENCES production.produit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lot_dossier ADD CONSTRAINT FK_90C8A614A8CBA5F7 FOREIGN KEY (lot_id) REFERENCES production.lot (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lot_dossier ADD CONSTRAINT FK_90C8A614611C0C56 FOREIGN KEY (dossier_id) REFERENCES logistique.dossier (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tc_dossier ADD CONSTRAINT FK_55E42ED67AE3F5DD FOREIGN KEY (tc_id) REFERENCES logistique.tc (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tc_dossier ADD CONSTRAINT FK_55E42ED6611C0C56 FOREIGN KEY (dossier_id) REFERENCES logistique.dossier (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE logistique.cv_execution DROP CONSTRAINT FK_DD82E6304EA15721');
        $this->addSql('ALTER TABLE logistique.details_modele DROP CONSTRAINT FK_9568A678F6D0101E');
        $this->addSql('ALTER TABLE logistique.details_modele DROP CONSTRAINT FK_9568A67851CF32');
        $this->addSql('ALTER TABLE logistique.doc_dossier DROP CONSTRAINT FK_315B448151CF32');
        $this->addSql('ALTER TABLE logistique.doc_dossier DROP CONSTRAINT FK_315B448149B2F1B0');
        $this->addSql('ALTER TABLE logistique.documentation DROP CONSTRAINT FK_EC29F78649B2F1B0');
        $this->addSql('ALTER TABLE logistique.documentation DROP CONSTRAINT FK_EC29F786E3C0CA34');
        $this->addSql('ALTER TABLE logistique.dossier DROP CONSTRAINT FK_F5E2A38EF347EFB');
        $this->addSql('ALTER TABLE logistique.dossier DROP CONSTRAINT FK_F5E2A38E19EB6921');
        $this->addSql('ALTER TABLE logistique.dossier DROP CONSTRAINT FK_F5E2A38E816C6140');
        $this->addSql('ALTER TABLE logistique.dossier DROP CONSTRAINT FK_F5E2A38E76C50E4A');
        $this->addSql('ALTER TABLE logistique.dossier DROP CONSTRAINT FK_F5E2A38E740585BA');
        $this->addSql('ALTER TABLE production.lot DROP CONSTRAINT FK_73802CC88A79705');
        $this->addSql('ALTER TABLE production.lot DROP CONSTRAINT FK_73802CC82E16DBE6');
        $this->addSql('ALTER TABLE production.lot DROP CONSTRAINT FK_73802CC8279A831D');
        $this->addSql('ALTER TABLE logistique.modele_doc DROP CONSTRAINT FK_2A34FB39279A831D');
        $this->addSql('ALTER TABLE logistique.modele_doc DROP CONSTRAINT FK_2A34FB39B5AE1119');
        $this->addSql('ALTER TABLE logistique.photos_docs DROP CONSTRAINT FK_15BCB53E49B2F1B0');
        $this->addSql('ALTER TABLE production.produit DROP CONSTRAINT FK_5C4AF0F42E16DBE6');
        $this->addSql('DROP TABLE logistique.approbation');
        $this->addSql('DROP TABLE logistique.client');
        $this->addSql('DROP TABLE logistique.cv_deblocage');
        $this->addSql('DROP TABLE logistique.cv_execution');
        $this->addSql('DROP TABLE logistique.destination');
        $this->addSql('DROP TABLE logistique.details_modele');
        $this->addSql('DROP TABLE logistique.doc_dossier');
        $this->addSql('DROP TABLE logistique.doc_type');
        $this->addSql('DROP TABLE logistique.documentation');
        $this->addSql('DROP TABLE logistique.dossier');
        $this->addSql('DROP TABLE production.lot');
        $this->addSql('DROP TABLE logistique.magasin');
        $this->addSql('DROP TABLE logistique.modele_doc');
        $this->addSql('DROP TABLE logistique.navire');
        $this->addSql('DROP TABLE logistique.photos_docs');
        $this->addSql('DROP TABLE production.produit');
        $this->addSql('DROP TABLE logistique.proprietaire');
        $this->addSql('DROP TABLE logistique.tc');
        $this->addSql('ALTER TABLE doc_type_produit DROP CONSTRAINT FK_B36A01201AAE044D');
        $this->addSql('ALTER TABLE doc_type_produit DROP CONSTRAINT FK_B36A0120F347EFB');
        $this->addSql('ALTER TABLE lot_dossier DROP CONSTRAINT FK_90C8A614A8CBA5F7');
        $this->addSql('ALTER TABLE lot_dossier DROP CONSTRAINT FK_90C8A614611C0C56');
        $this->addSql('ALTER TABLE tc_dossier DROP CONSTRAINT FK_55E42ED67AE3F5DD');
        $this->addSql('ALTER TABLE tc_dossier DROP CONSTRAINT FK_55E42ED6611C0C56');
    }
}
