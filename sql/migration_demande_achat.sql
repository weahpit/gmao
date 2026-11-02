-- ============================================================================
--  Module Demande d'Achat (DA) - CITRAC GMAO
--  Base : PostgreSQL (gmaobd)
--  A executer une seule fois :  psql -U <user> -d gmaobd -f migration_demande_achat.sql
-- ============================================================================

CREATE TABLE IF NOT EXISTS demande_achat (
    id                UUID            NOT NULL,
    numero            VARCHAR(50)     NOT NULL,
    token             VARCHAR(64)     NOT NULL,
    statut            VARCHAR(20)     NOT NULL DEFAULT 'NOUVELLE',

    -- lien facultatif vers le service demandeur (table "services")
    code_service_id   UUID            DEFAULT NULL,
    service           VARCHAR(255)    DEFAULT NULL,
    demandeur         VARCHAR(255)    DEFAULT NULL,
    created_by        VARCHAR(255)    DEFAULT NULL,
    created_at        TIMESTAMP(0)    NOT NULL,

    lignes            JSON            DEFAULT NULL,
    motif             TEXT            DEFAULT NULL,

    -- etape magasinier (enregistrement + soumission DG)
    enregistre_par    VARCHAR(255)    DEFAULT NULL,
    soumise_at        TIMESTAMP(0)    DEFAULT NULL,

    -- etape direction (validation / refus)
    valide_par        VARCHAR(255)    DEFAULT NULL,
    valide_at         TIMESTAMP(0)    DEFAULT NULL,
    commentaire_dg    TEXT            DEFAULT NULL,

    -- traitement magasinier apres validation
    type_traitement   VARCHAR(30)     DEFAULT NULL,
    fournisseur_id    UUID            DEFAULT NULL,
    fournisseur_nom   VARCHAR(255)    DEFAULT NULL,
    commande_at       TIMESTAMP(0)    DEFAULT NULL,

    -- sortie finale des articles
    servie_par        VARCHAR(255)    DEFAULT NULL,
    servie_at         TIMESTAMP(0)    DEFAULT NULL,
    bon_sortie_ref    VARCHAR(50)     DEFAULT NULL,

    historique        JSON            DEFAULT NULL,

    PRIMARY KEY (id)
);

-- Unicite reference / jeton de suivi
CREATE UNIQUE INDEX IF NOT EXISTS uniq_da_numero ON demande_achat (numero);
CREATE UNIQUE INDEX IF NOT EXISTS uniq_da_token  ON demande_achat (token);

-- Index de filtrage usuel
CREATE INDEX IF NOT EXISTS idx_da_statut     ON demande_achat (statut);
CREATE INDEX IF NOT EXISTS idx_da_created_by ON demande_achat (created_by);

-- Cles etrangeres (adapter les noms de tables si besoin : services / fournisseur)
-- Mises en ON DELETE SET NULL pour ne jamais perdre une demande si un
-- service / fournisseur est supprime.
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'services') THEN
        BEGIN
            ALTER TABLE demande_achat
                ADD CONSTRAINT fk_da_service
                FOREIGN KEY (code_service_id) REFERENCES services (id) ON DELETE SET NULL;
        EXCEPTION WHEN duplicate_object THEN NULL;
        END;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'fournisseur') THEN
        BEGIN
            ALTER TABLE demande_achat
                ADD CONSTRAINT fk_da_fournisseur
                FOREIGN KEY (fournisseur_id) REFERENCES fournisseur (id) ON DELETE SET NULL;
        EXCEPTION WHEN duplicate_object THEN NULL;
        END;
    END IF;
END$$;

-- ============================================================================
--  NB : si vos tables "services" / "fournisseur" utilisent une colonne de cle
--  primaire differente de "id", adaptez les REFERENCES ci-dessus.
-- ============================================================================
