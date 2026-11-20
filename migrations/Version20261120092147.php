<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120092147 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rh_absence (id UUID NOT NULL, reference VARCHAR(30) NOT NULL, type VARCHAR(30) NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, nombre_jours INT NOT NULL, motif TEXT DEFAULT NULL, justificatif VARCHAR(255) DEFAULT NULL, statut VARCHAR(20) NOT NULL, date_validation_chef TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_validation_rh TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, commentaire_validation VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, employe_id UUID NOT NULL, valide_chef_par_id UUID DEFAULT NULL, valide_rh_par_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_91BDC0B2AEA34913 ON rh_absence (reference)');
        $this->addSql('CREATE INDEX idx_absence_periode ON rh_absence (date_debut, date_fin)');
        $this->addSql('CREATE INDEX idx_absence_statut ON rh_absence (statut)');
        $this->addSql('CREATE INDEX IDX_91BDC0B21B65292 ON rh_absence (employe_id)');
        $this->addSql('CREATE INDEX IDX_91BDC0B21F8CBC31 ON rh_absence (valide_chef_par_id)');
        $this->addSql('CREATE INDEX IDX_91BDC0B298F9FD3 ON rh_absence (valide_rh_par_id)');
        $this->addSql('CREATE TABLE rh_accident_travail (id UUID NOT NULL, date DATE NOT NULL, lieu VARCHAR(150) DEFAULT NULL, description TEXT DEFAULT NULL, gravite VARCHAR(20) NOT NULL, arret_travail BOOLEAN NOT NULL, date_debut_arret DATE DEFAULT NULL, date_fin_arret DATE DEFAULT NULL, jours_arret INT DEFAULT NULL, numero_declaration VARCHAR(40) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, dossier_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_accident_date ON rh_accident_travail (date)');
        $this->addSql('CREATE INDEX IDX_FE9801D8611C0C56 ON rh_accident_travail (dossier_id)');
        $this->addSql('CREATE TABLE rh_affectation (id UUID NOT NULL, date_debut DATE NOT NULL, date_fin DATE DEFAULT NULL, motif VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, employe_id UUID NOT NULL, direction_id UUID DEFAULT NULL, service_id UUID DEFAULT NULL, poste_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_affectation_debut ON rh_affectation (date_debut)');
        $this->addSql('CREATE INDEX IDX_5FFF125E1B65292 ON rh_affectation (employe_id)');
        $this->addSql('CREATE INDEX IDX_5FFF125EAF73D997 ON rh_affectation (direction_id)');
        $this->addSql('CREATE INDEX IDX_5FFF125EED5CA9E6 ON rh_affectation (service_id)');
        $this->addSql('CREATE INDEX IDX_5FFF125EA0905086 ON rh_affectation (poste_id)');
        $this->addSql('CREATE TABLE rh_campagne_evaluation (id UUID NOT NULL, libelle VARCHAR(120) NOT NULL, annee INT NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, statut VARCHAR(20) NOT NULL, objectifs TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE rh_candidat (id UUID NOT NULL, nom VARCHAR(100) NOT NULL, prenoms VARCHAR(150) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, telephone VARCHAR(30) DEFAULT NULL, cv VARCHAR(255) DEFAULT NULL, diplomes TEXT DEFAULT NULL, experiences TEXT DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_candidat_nom ON rh_candidat (nom, prenoms)');
        $this->addSql('CREATE TABLE rh_candidature (id UUID NOT NULL, reference VARCHAR(30) NOT NULL, date_soumission DATE NOT NULL, statut VARCHAR(20) NOT NULL, observations TEXT DEFAULT NULL, note_tests INT DEFAULT NULL, date_entretien TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, evaluateurs VARCHAR(255) DEFAULT NULL, note_entretien INT DEFAULT NULL, compte_rendu_entretien TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, offre_id UUID NOT NULL, candidat_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4819A035AEA34913 ON rh_candidature (reference)');
        $this->addSql('CREATE INDEX idx_candidature_statut ON rh_candidature (statut)');
        $this->addSql('CREATE UNIQUE INDEX uniq_candidature_offre_candidat ON rh_candidature (offre_id, candidat_id)');
        $this->addSql('CREATE INDEX IDX_4819A0354CC8505A ON rh_candidature (offre_id)');
        $this->addSql('CREATE INDEX IDX_4819A0358D0EB82 ON rh_candidature (candidat_id)');
        $this->addSql('CREATE TABLE rh_contrat (id UUID NOT NULL, reference VARCHAR(30) NOT NULL, type VARCHAR(20) NOT NULL, fonction VARCHAR(150) DEFAULT NULL, date_debut DATE NOT NULL, date_fin DATE DEFAULT NULL, salaire NUMERIC(12, 2) DEFAULT NULL, statut VARCHAR(20) NOT NULL, motif_fin VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, employe_id UUID NOT NULL, poste_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_87D3B9E8AEA34913 ON rh_contrat (reference)');
        $this->addSql('CREATE INDEX idx_contrat_type ON rh_contrat (type)');
        $this->addSql('CREATE INDEX idx_contrat_fin ON rh_contrat (date_fin)');
        $this->addSql('CREATE INDEX IDX_87D3B9E81B65292 ON rh_contrat (employe_id)');
        $this->addSql('CREATE INDEX IDX_87D3B9E8A0905086 ON rh_contrat (poste_id)');
        $this->addSql('CREATE TABLE rh_critere_evaluation (id UUID NOT NULL, libelle VARCHAR(150) NOT NULL, poids INT NOT NULL, ordre INT NOT NULL, description TEXT DEFAULT NULL, campagne_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8E10791216227374 ON rh_critere_evaluation (campagne_id)');
        $this->addSql('CREATE TABLE rh_document (id UUID NOT NULL, categorie VARCHAR(20) NOT NULL, type VARCHAR(40) NOT NULL, titre VARCHAR(200) NOT NULL, chemin VARCHAR(255) NOT NULL, nom_original VARCHAR(200) NOT NULL, mime VARCHAR(120) NOT NULL, taille INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, employe_id UUID DEFAULT NULL, candidat_id UUID DEFAULT NULL, depose_par_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_document_categorie ON rh_document (categorie)');
        $this->addSql('CREATE INDEX IDX_1F59C5E21B65292 ON rh_document (employe_id)');
        $this->addSql('CREATE INDEX IDX_1F59C5E28D0EB82 ON rh_document (candidat_id)');
        $this->addSql('CREATE INDEX IDX_1F59C5E2DCFF0FC4 ON rh_document (depose_par_id)');
        $this->addSql('CREATE TABLE rh_dossier_medical (id UUID NOT NULL, numero_dossier VARCHAR(30) NOT NULL, groupe_sanguin VARCHAR(5) DEFAULT NULL, allergies TEXT DEFAULT NULL, antecedents TEXT DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, employe_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9AB36ACDFB1CFE96 ON rh_dossier_medical (numero_dossier)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9AB36ACD1B65292 ON rh_dossier_medical (employe_id)');
        $this->addSql('CREATE TABLE rh_employe (id UUID NOT NULL, matricule VARCHAR(20) NOT NULL, nom VARCHAR(100) NOT NULL, prenoms VARCHAR(150) DEFAULT NULL, date_naissance DATE DEFAULT NULL, sexe VARCHAR(1) DEFAULT NULL, situation_matrimoniale VARCHAR(20) DEFAULT NULL, nationalite VARCHAR(60) DEFAULT NULL, telephone VARCHAR(30) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, numero_cnps VARCHAR(30) DEFAULT NULL, date_embauche DATE NOT NULL, statut VARCHAR(20) NOT NULL, date_sortie DATE DEFAULT NULL, motif_sortie VARCHAR(180) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, direction_id UUID DEFAULT NULL, service_id UUID DEFAULT NULL, poste_id UUID DEFAULT NULL, manager_id UUID DEFAULT NULL, utilisateur_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1FE3F3C212B2DC9C ON rh_employe (matricule)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1FE3F3C2FB88E14F ON rh_employe (utilisateur_id)');
        $this->addSql('CREATE INDEX idx_employe_nom ON rh_employe (nom, prenoms)');
        $this->addSql('CREATE INDEX idx_employe_statut ON rh_employe (statut)');
        $this->addSql('CREATE INDEX IDX_1FE3F3C2AF73D997 ON rh_employe (direction_id)');
        $this->addSql('CREATE INDEX IDX_1FE3F3C2ED5CA9E6 ON rh_employe (service_id)');
        $this->addSql('CREATE INDEX IDX_1FE3F3C2A0905086 ON rh_employe (poste_id)');
        $this->addSql('CREATE INDEX IDX_1FE3F3C2783E3463 ON rh_employe (manager_id)');
        $this->addSql('CREATE TABLE rh_evaluation (id UUID NOT NULL, type VARCHAR(20) NOT NULL, notes JSON NOT NULL, note_globale NUMERIC(4, 2) DEFAULT NULL, commentaire TEXT DEFAULT NULL, plan_developpement TEXT DEFAULT NULL, statut VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, campagne_id UUID NOT NULL, employe_id UUID NOT NULL, evaluateur_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_eval_campagne_employe ON rh_evaluation (campagne_id, employe_id)');
        $this->addSql('CREATE INDEX IDX_E06D715A16227374 ON rh_evaluation (campagne_id)');
        $this->addSql('CREATE INDEX IDX_E06D715A1B65292 ON rh_evaluation (employe_id)');
        $this->addSql('CREATE INDEX IDX_E06D715A231F139 ON rh_evaluation (evaluateur_id)');
        $this->addSql('CREATE TABLE rh_formation (id UUID NOT NULL, intitule VARCHAR(180) NOT NULL, organisme VARCHAR(150) DEFAULT NULL, cout NUMERIC(12, 2) DEFAULT NULL, date_debut DATE DEFAULT NULL, date_fin DATE DEFAULT NULL, nombre_places INT NOT NULL, description TEXT DEFAULT NULL, statut VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE rh_offre_emploi (id UUID NOT NULL, reference VARCHAR(30) NOT NULL, intitule VARCHAR(180) NOT NULL, description TEXT DEFAULT NULL, profil TEXT DEFAULT NULL, nombre_postes INT NOT NULL, date_publication DATE DEFAULT NULL, date_cloture DATE DEFAULT NULL, statut VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, direction_id UUID DEFAULT NULL, service_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_80880D3FAEA34913 ON rh_offre_emploi (reference)');
        $this->addSql('CREATE INDEX idx_offre_statut ON rh_offre_emploi (statut)');
        $this->addSql('CREATE INDEX IDX_80880D3FAF73D997 ON rh_offre_emploi (direction_id)');
        $this->addSql('CREATE INDEX IDX_80880D3FED5CA9E6 ON rh_offre_emploi (service_id)');
        $this->addSql('CREATE TABLE rh_participation_formation (id UUID NOT NULL, resultat VARCHAR(20) NOT NULL, note INT DEFAULT NULL, commentaire TEXT DEFAULT NULL, formation_id UUID NOT NULL, employe_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_participation ON rh_participation_formation (formation_id, employe_id)');
        $this->addSql('CREATE INDEX IDX_9A369AF45200282E ON rh_participation_formation (formation_id)');
        $this->addSql('CREATE INDEX IDX_9A369AF41B65292 ON rh_participation_formation (employe_id)');
        $this->addSql('CREATE TABLE rh_pointage (id UUID NOT NULL, jour DATE NOT NULL, heure_arrivee TIME(0) WITHOUT TIME ZONE DEFAULT NULL, heure_depart TIME(0) WITHOUT TIME ZONE DEFAULT NULL, retard_minutes INT NOT NULL, heures_supplementaires INT NOT NULL, commentaire VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, employe_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_pointage_jour ON rh_pointage (jour)');
        $this->addSql('CREATE UNIQUE INDEX uniq_pointage_jour ON rh_pointage (employe_id, jour)');
        $this->addSql('CREATE INDEX IDX_C06954B41B65292 ON rh_pointage (employe_id)');
        $this->addSql('CREATE TABLE rh_vaccination (id UUID NOT NULL, vaccin VARCHAR(120) NOT NULL, date DATE DEFAULT NULL, prochain_rappel DATE DEFAULT NULL, lot VARCHAR(100) DEFAULT NULL, dossier_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B02BEA14611C0C56 ON rh_vaccination (dossier_id)');
        $this->addSql('CREATE TABLE rh_visite_medicale (id UUID NOT NULL, type VARCHAR(20) NOT NULL, date DATE NOT NULL, medecin VARCHAR(120) DEFAULT NULL, centre_medical VARCHAR(150) DEFAULT NULL, conclusion TEXT DEFAULT NULL, aptitude VARCHAR(30) NOT NULL, date_prochaine DATE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, dossier_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_visite_date ON rh_visite_medicale (date)');
        $this->addSql('CREATE INDEX IDX_19E61990611C0C56 ON rh_visite_medicale (dossier_id)');
        $this->addSql('ALTER TABLE rh_absence ADD CONSTRAINT FK_91BDC0B21B65292 FOREIGN KEY (employe_id) REFERENCES rh_employe (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_absence ADD CONSTRAINT FK_91BDC0B21F8CBC31 FOREIGN KEY (valide_chef_par_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_absence ADD CONSTRAINT FK_91BDC0B298F9FD3 FOREIGN KEY (valide_rh_par_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_accident_travail ADD CONSTRAINT FK_FE9801D8611C0C56 FOREIGN KEY (dossier_id) REFERENCES rh_dossier_medical (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_affectation ADD CONSTRAINT FK_5FFF125E1B65292 FOREIGN KEY (employe_id) REFERENCES rh_employe (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_affectation ADD CONSTRAINT FK_5FFF125EAF73D997 FOREIGN KEY (direction_id) REFERENCES directions (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_affectation ADD CONSTRAINT FK_5FFF125EED5CA9E6 FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_affectation ADD CONSTRAINT FK_5FFF125EA0905086 FOREIGN KEY (poste_id) REFERENCES poste (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_candidature ADD CONSTRAINT FK_4819A0354CC8505A FOREIGN KEY (offre_id) REFERENCES rh_offre_emploi (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_candidature ADD CONSTRAINT FK_4819A0358D0EB82 FOREIGN KEY (candidat_id) REFERENCES rh_candidat (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_contrat ADD CONSTRAINT FK_87D3B9E81B65292 FOREIGN KEY (employe_id) REFERENCES rh_employe (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_contrat ADD CONSTRAINT FK_87D3B9E8A0905086 FOREIGN KEY (poste_id) REFERENCES poste (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_critere_evaluation ADD CONSTRAINT FK_8E10791216227374 FOREIGN KEY (campagne_id) REFERENCES rh_campagne_evaluation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_document ADD CONSTRAINT FK_1F59C5E21B65292 FOREIGN KEY (employe_id) REFERENCES rh_employe (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_document ADD CONSTRAINT FK_1F59C5E28D0EB82 FOREIGN KEY (candidat_id) REFERENCES rh_candidat (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_document ADD CONSTRAINT FK_1F59C5E2DCFF0FC4 FOREIGN KEY (depose_par_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_dossier_medical ADD CONSTRAINT FK_9AB36ACD1B65292 FOREIGN KEY (employe_id) REFERENCES rh_employe (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_employe ADD CONSTRAINT FK_1FE3F3C2AF73D997 FOREIGN KEY (direction_id) REFERENCES directions (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_employe ADD CONSTRAINT FK_1FE3F3C2ED5CA9E6 FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_employe ADD CONSTRAINT FK_1FE3F3C2A0905086 FOREIGN KEY (poste_id) REFERENCES poste (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_employe ADD CONSTRAINT FK_1FE3F3C2783E3463 FOREIGN KEY (manager_id) REFERENCES rh_employe (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_employe ADD CONSTRAINT FK_1FE3F3C2FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_evaluation ADD CONSTRAINT FK_E06D715A16227374 FOREIGN KEY (campagne_id) REFERENCES rh_campagne_evaluation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_evaluation ADD CONSTRAINT FK_E06D715A1B65292 FOREIGN KEY (employe_id) REFERENCES rh_employe (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_evaluation ADD CONSTRAINT FK_E06D715A231F139 FOREIGN KEY (evaluateur_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_offre_emploi ADD CONSTRAINT FK_80880D3FAF73D997 FOREIGN KEY (direction_id) REFERENCES directions (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_offre_emploi ADD CONSTRAINT FK_80880D3FED5CA9E6 FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_participation_formation ADD CONSTRAINT FK_9A369AF45200282E FOREIGN KEY (formation_id) REFERENCES rh_formation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_participation_formation ADD CONSTRAINT FK_9A369AF41B65292 FOREIGN KEY (employe_id) REFERENCES rh_employe (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_pointage ADD CONSTRAINT FK_C06954B41B65292 FOREIGN KEY (employe_id) REFERENCES rh_employe (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_vaccination ADD CONSTRAINT FK_B02BEA14611C0C56 FOREIGN KEY (dossier_id) REFERENCES rh_dossier_medical (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rh_visite_medicale ADD CONSTRAINT FK_19E61990611C0C56 FOREIGN KEY (dossier_id) REFERENCES rh_dossier_medical (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rh_absence DROP CONSTRAINT FK_91BDC0B21B65292');
        $this->addSql('ALTER TABLE rh_absence DROP CONSTRAINT FK_91BDC0B21F8CBC31');
        $this->addSql('ALTER TABLE rh_absence DROP CONSTRAINT FK_91BDC0B298F9FD3');
        $this->addSql('ALTER TABLE rh_accident_travail DROP CONSTRAINT FK_FE9801D8611C0C56');
        $this->addSql('ALTER TABLE rh_affectation DROP CONSTRAINT FK_5FFF125E1B65292');
        $this->addSql('ALTER TABLE rh_affectation DROP CONSTRAINT FK_5FFF125EAF73D997');
        $this->addSql('ALTER TABLE rh_affectation DROP CONSTRAINT FK_5FFF125EED5CA9E6');
        $this->addSql('ALTER TABLE rh_affectation DROP CONSTRAINT FK_5FFF125EA0905086');
        $this->addSql('ALTER TABLE rh_candidature DROP CONSTRAINT FK_4819A0354CC8505A');
        $this->addSql('ALTER TABLE rh_candidature DROP CONSTRAINT FK_4819A0358D0EB82');
        $this->addSql('ALTER TABLE rh_contrat DROP CONSTRAINT FK_87D3B9E81B65292');
        $this->addSql('ALTER TABLE rh_contrat DROP CONSTRAINT FK_87D3B9E8A0905086');
        $this->addSql('ALTER TABLE rh_critere_evaluation DROP CONSTRAINT FK_8E10791216227374');
        $this->addSql('ALTER TABLE rh_document DROP CONSTRAINT FK_1F59C5E21B65292');
        $this->addSql('ALTER TABLE rh_document DROP CONSTRAINT FK_1F59C5E28D0EB82');
        $this->addSql('ALTER TABLE rh_document DROP CONSTRAINT FK_1F59C5E2DCFF0FC4');
        $this->addSql('ALTER TABLE rh_dossier_medical DROP CONSTRAINT FK_9AB36ACD1B65292');
        $this->addSql('ALTER TABLE rh_employe DROP CONSTRAINT FK_1FE3F3C2AF73D997');
        $this->addSql('ALTER TABLE rh_employe DROP CONSTRAINT FK_1FE3F3C2ED5CA9E6');
        $this->addSql('ALTER TABLE rh_employe DROP CONSTRAINT FK_1FE3F3C2A0905086');
        $this->addSql('ALTER TABLE rh_employe DROP CONSTRAINT FK_1FE3F3C2783E3463');
        $this->addSql('ALTER TABLE rh_employe DROP CONSTRAINT FK_1FE3F3C2FB88E14F');
        $this->addSql('ALTER TABLE rh_evaluation DROP CONSTRAINT FK_E06D715A16227374');
        $this->addSql('ALTER TABLE rh_evaluation DROP CONSTRAINT FK_E06D715A1B65292');
        $this->addSql('ALTER TABLE rh_evaluation DROP CONSTRAINT FK_E06D715A231F139');
        $this->addSql('ALTER TABLE rh_offre_emploi DROP CONSTRAINT FK_80880D3FAF73D997');
        $this->addSql('ALTER TABLE rh_offre_emploi DROP CONSTRAINT FK_80880D3FED5CA9E6');
        $this->addSql('ALTER TABLE rh_participation_formation DROP CONSTRAINT FK_9A369AF45200282E');
        $this->addSql('ALTER TABLE rh_participation_formation DROP CONSTRAINT FK_9A369AF41B65292');
        $this->addSql('ALTER TABLE rh_pointage DROP CONSTRAINT FK_C06954B41B65292');
        $this->addSql('ALTER TABLE rh_vaccination DROP CONSTRAINT FK_B02BEA14611C0C56');
        $this->addSql('ALTER TABLE rh_visite_medicale DROP CONSTRAINT FK_19E61990611C0C56');
        $this->addSql('DROP TABLE rh_absence');
        $this->addSql('DROP TABLE rh_accident_travail');
        $this->addSql('DROP TABLE rh_affectation');
        $this->addSql('DROP TABLE rh_campagne_evaluation');
        $this->addSql('DROP TABLE rh_candidat');
        $this->addSql('DROP TABLE rh_candidature');
        $this->addSql('DROP TABLE rh_contrat');
        $this->addSql('DROP TABLE rh_critere_evaluation');
        $this->addSql('DROP TABLE rh_document');
        $this->addSql('DROP TABLE rh_dossier_medical');
        $this->addSql('DROP TABLE rh_employe');
        $this->addSql('DROP TABLE rh_evaluation');
        $this->addSql('DROP TABLE rh_formation');
        $this->addSql('DROP TABLE rh_offre_emploi');
        $this->addSql('DROP TABLE rh_participation_formation');
        $this->addSql('DROP TABLE rh_pointage');
        $this->addSql('DROP TABLE rh_vaccination');
        $this->addSql('DROP TABLE rh_visite_medicale');
    }
}
