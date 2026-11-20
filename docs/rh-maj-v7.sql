-- =============================================================================
-- Module RH - mise a jour v7 (PostgreSQL)
--
-- Ajoute uniquement deux choses, sans toucher aux donnees existantes :
--   1. les documents de formation (support de presentation + liste de presences)
--   2. le code de pointage (badge) de l'employe, pour l'import ZKTECO
--
-- A executer dans psql ou pgAdmin, connecte a la base de l'application.
-- Le script est re-executable sans risque (ADD COLUMN IF NOT EXISTS).
--
-- Alternative : php bin/console app:rh:installer  (met les tables a niveau)
-- =============================================================================

BEGIN;

-- 1. Documents de formation ---------------------------------------------------
ALTER TABLE rh_formation ADD COLUMN IF NOT EXISTS support VARCHAR(255);
ALTER TABLE rh_formation ADD COLUMN IF NOT EXISTS support_nom VARCHAR(200);
ALTER TABLE rh_formation ADD COLUMN IF NOT EXISTS liste_presences VARCHAR(255);
ALTER TABLE rh_formation ADD COLUMN IF NOT EXISTS liste_presences_nom VARCHAR(200);

COMMENT ON COLUMN rh_formation.support IS 'Chemin relatif du support de presentation (public/data/rh/Formations/...)';
COMMENT ON COLUMN rh_formation.support_nom IS 'Nom d''origine du support, pour l''affichage';
COMMENT ON COLUMN rh_formation.liste_presences IS 'Chemin relatif de la liste de presences signee';
COMMENT ON COLUMN rh_formation.liste_presences_nom IS 'Nom d''origine de la liste de presences';

-- 2. Code de pointage (badge ZKTECO) ------------------------------------------
ALTER TABLE rh_employe ADD COLUMN IF NOT EXISTS code_pointage VARCHAR(30);

CREATE INDEX IF NOT EXISTS idx_employe_code_pointage ON rh_employe (code_pointage);

COMMENT ON COLUMN rh_employe.code_pointage IS 'Numero de badge enregistre sur la pointeuse ZKTECO (Badgenumber)';

-- 3. Le dossier de stockage doit exister et etre inscriptible -----------------
--    (a creer cote serveur : public/data/rh/Formations/)
--    mkdir -p public/data/rh/Formations && chmod 775 public/data/rh/Formations

COMMIT;

-- Controle
SELECT column_name, data_type, character_maximum_length
FROM information_schema.columns
WHERE table_name = 'rh_formation'
  AND column_name IN ('support', 'support_nom', 'liste_presences', 'liste_presences_nom')
ORDER BY column_name;

SELECT column_name, data_type, character_maximum_length
FROM information_schema.columns
WHERE table_name = 'rh_employe'
  AND column_name = 'code_pointage';
