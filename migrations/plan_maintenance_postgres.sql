-- =====================================================================
--  Plans de maintenance preventive - PostgreSQL (schema "maintenance")
--  A n'utiliser QUE si vous ne passez pas par Doctrine Migrations.
--  Methode recommandee a la place :
--      php bin/console make:migration
--      php bin/console doctrine:migrations:migrate
-- =====================================================================

CREATE TABLE IF NOT EXISTS maintenance.plan_maintenance (
    id                  UUID         NOT NULL,
    zone_id             UUID         NULL,
    equipement_type_id  UUID         NULL,
    type_intervention_id UUID        NULL,
    reference           VARCHAR(50)  NULL,
    libelle             VARCHAR(255) NOT NULL,
    periodicite         VARCHAR(30)  NOT NULL,
    intervalle          INT          NOT NULL DEFAULT 1,
    jour_semaine        INT          NULL,
    date_debut          DATE         NOT NULL,
    date_fin            DATE         NULL,
    actif               BOOLEAN      NOT NULL DEFAULT TRUE,
    couleur             VARCHAR(20)  NULL,
    derniere_generation DATE         NULL,
    created_at          TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    created_by          VARCHAR(255) NULL,
    PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS maintenance.plan_maintenance_occurrence (
    id               UUID        NOT NULL,
    plan_id          UUID        NOT NULL,
    fiche_id         UUID        NULL,
    date_prevue      DATE        NOT NULL,
    statut           VARCHAR(20) NOT NULL DEFAULT 'A_FAIRE',
    date_realisation DATE        NULL,
    created_at       TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    PRIMARY KEY (id)
);

-- Index
CREATE INDEX IF NOT EXISTS idx_pm_occ_plan   ON maintenance.plan_maintenance_occurrence (plan_id);
CREATE INDEX IF NOT EXISTS idx_pm_occ_statut ON maintenance.plan_maintenance_occurrence (statut);
CREATE INDEX IF NOT EXISTS idx_pm_occ_date   ON maintenance.plan_maintenance_occurrence (date_prevue);

-- Cles etrangeres (adaptez les schemas si besoin : zone_exploitation / equipement_type sont en schema public)
ALTER TABLE maintenance.plan_maintenance
    ADD CONSTRAINT fk_pm_zone              FOREIGN KEY (zone_id)              REFERENCES public.zone_exploitation (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_pm_equipement_type   FOREIGN KEY (equipement_type_id)   REFERENCES public.equipement_type (id)  ON DELETE SET NULL,
    ADD CONSTRAINT fk_pm_type_intervention FOREIGN KEY (type_intervention_id) REFERENCES maintenance.type_intervention (id) ON DELETE SET NULL;

ALTER TABLE maintenance.plan_maintenance_occurrence
    ADD CONSTRAINT fk_pmo_plan  FOREIGN KEY (plan_id)  REFERENCES maintenance.plan_maintenance (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_pmo_fiche FOREIGN KEY (fiche_id) REFERENCES maintenance.fiche_intervention (id) ON DELETE SET NULL;
