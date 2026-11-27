-- PostgreSQL. Idempotent, sans suppression. Transaction annulée si un solde de sacs est négatif.
BEGIN;
CREATE SCHEMA IF NOT EXISTS logistique;

CREATE TABLE IF NOT EXISTS logistique.transitaire (id UUID NOT NULL, libelle VARCHAR(150) NOT NULL, PRIMARY KEY(id));

CREATE TABLE IF NOT EXISTS logistique.compagnie_navigation (id UUID NOT NULL, libelle VARCHAR(150) NOT NULL, PRIMARY KEY(id));

ALTER TABLE logistique.dossier ADD COLUMN IF NOT EXISTS date_ot DATE;

ALTER TABLE logistique.dossier ADD COLUMN IF NOT EXISTS banque_nantissement_id UUID;

ALTER TABLE logistique.dossier ADD COLUMN IF NOT EXISTS transitaire_id UUID;

ALTER TABLE logistique.dossier ADD COLUMN IF NOT EXISTS compagnie_navigation_id UUID;

ALTER TABLE logistique.dossier ADD COLUMN IF NOT EXISTS booking VARCHAR(100);

ALTER TABLE logistique.dossier ADD COLUMN IF NOT EXISTS port_embarquement VARCHAR(150);

ALTER TABLE logistique.dossier ADD COLUMN IF NOT EXISTS numero_bl VARCHAR(100);

ALTER TABLE logistique.dossier ADD COLUMN IF NOT EXISTS date_bl DATE;

DO $bloc$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='fk_dossier_banque_nantissement_id' AND conrelid='logistique.dossier'::regclass) THEN ALTER TABLE logistique.dossier ADD CONSTRAINT fk_dossier_banque_nantissement_id FOREIGN KEY (banque_nantissement_id) REFERENCES logistique.banque(id) ON DELETE RESTRICT; END IF; END; $bloc$;

DO $bloc$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='fk_dossier_transitaire_id' AND conrelid='logistique.dossier'::regclass) THEN ALTER TABLE logistique.dossier ADD CONSTRAINT fk_dossier_transitaire_id FOREIGN KEY (transitaire_id) REFERENCES logistique.transitaire(id) ON DELETE RESTRICT; END IF; END; $bloc$;

DO $bloc$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='fk_dossier_compagnie_navigation_id' AND conrelid='logistique.dossier'::regclass) THEN ALTER TABLE logistique.dossier ADD CONSTRAINT fk_dossier_compagnie_navigation_id FOREIGN KEY (compagnie_navigation_id) REFERENCES logistique.compagnie_navigation(id) ON DELETE RESTRICT; END IF; END; $bloc$;

ALTER TABLE logistique.cv_deblocage ADD COLUMN IF NOT EXISTS periode VARCHAR(100);

ALTER TABLE logistique.cv_execution ADD COLUMN IF NOT EXISTS periode VARCHAR(100);

ALTER TABLE production.lot ADD COLUMN IF NOT EXISTS sacs_restants INTEGER;

ALTER TABLE lg_suivi_embarquement ADD COLUMN IF NOT EXISTS photos_empotage JSON NOT NULL DEFAULT '[]';

DO $bloc$ BEGIN
WITH charges AS (
 SELECT ligne->>'id' AS lot_id, SUM(COALESCE(NULLIF(ligne->>'nbCartons',''),'0')::INTEGER) AS sacs
 FROM lg_suivi_embarquement s
 CROSS JOIN LATERAL json_array_elements(COALESCE(s.conteneurs::json,'[]'::json)) AS tc
 CROSS JOIN LATERAL json_array_elements(COALESCE(tc->'lots','[]'::json)) AS ligne
 GROUP BY ligne->>'id'
)
UPDATE production.lot l SET sacs_restants = TRUNC(COALESCE(l.nb_sacs,0))::INTEGER - COALESCE((SELECT sacs FROM charges WHERE lot_id=l.id::text),0)::INTEGER
WHERE l.sacs_restants IS NULL;
END; $bloc$;

DO $bloc$ BEGIN IF EXISTS(SELECT 1 FROM production.lot WHERE sacs_restants<0) THEN RAISE EXCEPTION 'Soldes sacs negatifs : rapprochez les lots et les historiques avant installation. Aucune quantite ne sera supprimee.'; END IF; END; $bloc$;

DO $bloc$ BEGIN
UPDATE logistique.doc_dossier SET statut='APPROUVE' WHERE statut='APPROVE';
END; $bloc$;

DO $bloc$ BEGIN
UPDATE logistique.doc_dossier SET statut='NON_APPROUVE' WHERE statut='NON_APPROVE';
END; $bloc$;

DO $bloc$ BEGIN
UPDATE logistique.dossier SET draft=CASE draft WHEN 'APPROVE' THEN 'APPROUVE' WHEN 'NON_APPROVE' THEN 'NON_APPROUVE' ELSE draft END WHERE draft IN ('APPROVE','NON_APPROVE');
END; $bloc$;

DO $bloc$ BEGIN
UPDATE logistique.dossier SET packing_list=CASE packing_list WHEN 'APPROVE' THEN 'APPROUVE' WHEN 'NON_APPROVE' THEN 'NON_APPROUVE' ELSE packing_list END WHERE packing_list IN ('APPROVE','NON_APPROVE');
END; $bloc$;

DO $bloc$ BEGIN
UPDATE logistique.dossier SET certificat_poids=CASE certificat_poids WHEN 'APPROVE' THEN 'APPROUVE' WHEN 'NON_APPROVE' THEN 'NON_APPROUVE' ELSE certificat_poids END WHERE certificat_poids IN ('APPROVE','NON_APPROVE');
END; $bloc$;

DO $bloc$ BEGIN
UPDATE logistique.dossier SET coa=CASE coa WHEN 'APPROVE' THEN 'APPROUVE' WHEN 'NON_APPROVE' THEN 'NON_APPROUVE' ELSE coa END WHERE coa IN ('APPROVE','NON_APPROVE');
END; $bloc$;

DO $bloc$ BEGIN
UPDATE logistique.dossier SET certificat_phyto=CASE certificat_phyto WHEN 'APPROVE' THEN 'APPROUVE' WHEN 'NON_APPROVE' THEN 'NON_APPROUVE' ELSE certificat_phyto END WHERE certificat_phyto IN ('APPROVE','NON_APPROVE');
END; $bloc$;

DO $bloc$ BEGIN
UPDATE logistique.dossier SET co=CASE co WHEN 'APPROVE' THEN 'APPROUVE' WHEN 'NON_APPROVE' THEN 'NON_APPROUVE' ELSE co END WHERE co IN ('APPROVE','NON_APPROVE');
END; $bloc$;

DO $bloc$ BEGIN
UPDATE logistique.dossier SET certificat_conformite=CASE certificat_conformite WHEN 'APPROVE' THEN 'APPROUVE' WHEN 'NON_APPROVE' THEN 'NON_APPROUVE' ELSE certificat_conformite END WHERE certificat_conformite IN ('APPROVE','NON_APPROVE');
END; $bloc$;

DO $bloc$ BEGIN
UPDATE logistique.dossier SET certificat_hygienne=CASE certificat_hygienne WHEN 'APPROVE' THEN 'APPROUVE' WHEN 'NON_APPROVE' THEN 'NON_APPROUVE' ELSE certificat_hygienne END WHERE certificat_hygienne IN ('APPROVE','NON_APPROVE');
END; $bloc$;

DO $bloc$ BEGIN
UPDATE logistique.dossier SET certificat_fumigation=CASE certificat_fumigation WHEN 'APPROVE' THEN 'APPROUVE' WHEN 'NON_APPROVE' THEN 'NON_APPROUVE' ELSE certificat_fumigation END WHERE certificat_fumigation IN ('APPROVE','NON_APPROVE');
END; $bloc$;

DO $bloc$ BEGIN
UPDATE logistique.dossier SET certificat_halah=CASE certificat_halah WHEN 'APPROVE' THEN 'APPROUVE' WHEN 'NON_APPROVE' THEN 'NON_APPROUVE' ELSE certificat_halah END WHERE certificat_halah IN ('APPROVE','NON_APPROVE');
END; $bloc$;

DO $bloc$ BEGIN
UPDATE lg_suivi_embarquement SET certificats = REPLACE(REPLACE(certificats::text, '"NON_APPROVE"', '"NON_APPROUVE"'), '"APPROVE"', '"APPROUVE"')::json WHERE certificats::text LIKE '%APPROVE%';
END; $bloc$;

DO $bloc$ BEGIN
ALTER TABLE rh_candidat ADD COLUMN IF NOT EXISTS poste_recherche VARCHAR(150);
END; $bloc$;

DO $bloc$ BEGIN
ALTER TABLE rh_candidat ADD COLUMN IF NOT EXISTS observations_accueil TEXT;
END; $bloc$;

DO $bloc$ BEGIN
ALTER TABLE rh_candidat ADD COLUMN IF NOT EXISTS dossier_spontane BOOLEAN NOT NULL DEFAULT FALSE;
END; $bloc$;

CREATE TABLE IF NOT EXISTS rh_zkteco_passage (id BIGSERIAL PRIMARY KEY, empreinte CHAR(64) NOT NULL UNIQUE, source VARCHAR(60) NOT NULL, badge VARCHAR(60) NOT NULL, quand TIMESTAMP NOT NULL, recu_le TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP);

CREATE INDEX IF NOT EXISTS idx_zkteco_badge_jour ON rh_zkteco_passage (badge, quand);
COMMIT;
