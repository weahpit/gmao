-- Table des preferences de gadgets du tableau de bord par role.
-- A executer une seule fois sur la base PostgreSQL du site.
-- Equivalent a : php bin/console doctrine:schema:update --force

CREATE TABLE IF NOT EXISTS dashboard_preference_gadget (
    id              UUID            NOT NULL,
    utilisateur_id  UUID            NOT NULL,
    role_key        VARCHAR(40)     NOT NULL,
    code            VARCHAR(60)     NOT NULL,
    visible         BOOLEAN         NOT NULL DEFAULT TRUE,
    actif           BOOLEAN         NOT NULL DEFAULT TRUE,
    ordre           INTEGER         NOT NULL DEFAULT 0,
    maj_at          TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
);

CREATE UNIQUE INDEX IF NOT EXISTS uniq_dashboard_pref
    ON dashboard_preference_gadget (utilisateur_id, role_key, code);

CREATE INDEX IF NOT EXISTS idx_dashboard_pref_role
    ON dashboard_preference_gadget (utilisateur_id, role_key);

ALTER TABLE dashboard_preference_gadget
    DROP CONSTRAINT IF EXISTS fk_dashboard_pref_utilisateur;

ALTER TABLE dashboard_preference_gadget
    ADD CONSTRAINT fk_dashboard_pref_utilisateur
    FOREIGN KEY (utilisateur_id) REFERENCES "user" (id) ON DELETE CASCADE;

COMMENT ON TABLE dashboard_preference_gadget IS
    'Affichage, activation et ordre des gadgets du tableau de bord par role et par utilisateur.';
