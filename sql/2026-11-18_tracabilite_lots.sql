-- =====================================================================
--  TRACABILITE DES LOTS FABRIQUES (FEVES) <-> CAMIONS DECAHRGES
--  Script de secours si `php bin/console doctrine:migrations:migrate`
--  ne peut pas etre lance (acces SQL direct uniquement).
--  A executer sur la base PostgreSQL de l'application (schema public).
-- =====================================================================

BEGIN;

CREATE TABLE IF NOT EXISTS stock_lot_dechargement (
    id UUID NOT NULL,
    lot_id UUID NOT NULL,
    dechargement_id UUID NOT NULL,
    poids_affecte DOUBLE PRECISION DEFAULT NULL,
    nbre_sacs INT DEFAULT NULL,
    observation VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    created_by VARCHAR(180) DEFAULT NULL,
    PRIMARY KEY(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_stock_lot_dechargement
    ON stock_lot_dechargement (lot_id, dechargement_id);
CREATE INDEX IF NOT EXISTS idx_stock_lot_dechargement_lot
    ON stock_lot_dechargement (lot_id);
CREATE INDEX IF NOT EXISTS idx_stock_lot_dechargement_dech
    ON stock_lot_dechargement (dechargement_id);

-- Lien vers le lot fabrique : supprime avec le lot.
ALTER TABLE stock_lot_dechargement
    DROP CONSTRAINT IF EXISTS fk_stock_lot_dechargement_lot;
ALTER TABLE stock_lot_dechargement
    ADD CONSTRAINT fk_stock_lot_dechargement_lot
    FOREIGN KEY (lot_id) REFERENCES production.lot (id) ON DELETE CASCADE;

-- Lien vers le camion : refuse la suppression d'un camion deja rattache
-- (il faut d'abord retirer l'association depuis la page du lot).
ALTER TABLE stock_lot_dechargement
    DROP CONSTRAINT IF EXISTS fk_stock_lot_dechargement_dech;
ALTER TABLE stock_lot_dechargement
    ADD CONSTRAINT fk_stock_lot_dechargement_dech
    FOREIGN KEY (dechargement_id) REFERENCES stock_dechargement_cacao (id) ON DELETE RESTRICT;

COMMIT;

-- Controle :
-- SELECT COUNT(*) FROM stock_lot_dechargement;
