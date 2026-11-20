-- =============================================================================
-- CITRAC - GMAO : passage en MAJUSCULES des donnees deja enregistrees
-- PostgreSQL 12 et plus
--
-- A quoi sert ce script ?
--   Les nouvelles saisies sont deja mises en majuscules par l'application
--   (sauf l'email, volontairement conserve tel quel). Ce script aligne les
--   fiches ANCIENNES, deja presentes en base.
--
-- A lire avant de lancer :
--   1. FAITES UNE SAUVEGARDE. Exemple :
--        pg_dump -h localhost -U postgres -Fc citrac_gmao > citrac_gmao_avant_majuscules.dump
--      Restauration si besoin :
--        pg_restore -h localhost -U postgres -d citrac_gmao -c citrac_gmao_avant_majuscules.dump
--   2. Le script ne touche QUE les colonnes de texte listees plus bas.
--      Il ne modifie jamais :
--        - les adresses email (rh_employe.email, rh_candidat.email, visite_visiteur.email)
--        - les valeurs techniques comparees par l'application : statut, sexe,
--          situation_matrimoniale, type, categorie, gravite, aptitude, resultat,
--          motif de visite, type_piece
--        - les references, codes, matricules, numeros et chemins de fichiers
--   3. Il peut etre relance sans risque : une valeur deja en majuscules n'est
--      pas reecrite (la clause WHERE l'exclut).
--   4. Les tables absentes de la base sont simplement signalees, pas bloquantes.
--
-- Execution :
--   psql -h localhost -U postgres -d citrac_gmao -f docs/majuscules-donnees.sql
--   ou : copier tout le contenu dans pgAdmin > Query Tool, puis Executer.
-- =============================================================================

\set ON_ERROR_STOP on

-- -----------------------------------------------------------------------------
-- 1. ETAT DES LIEUX : combien de lignes seraient modifiees, colonne par colonne ?
--    Lecture seule : aucune modification n'est faite ici.
-- -----------------------------------------------------------------------------
DO $$
DECLARE
    cible record;
    nb integer;
    total integer := 0;
BEGIN
    RAISE NOTICE '--- ETAT DES LIEUX (avant modification) ---';

    FOR cible IN
        SELECT * FROM (VALUES
            ('rh_employe', 'nom'),
            ('rh_employe', 'prenoms'),
            ('rh_employe', 'nationalite'),
            ('rh_employe', 'adresse'),
            ('rh_employe', 'motif_sortie'),
            ('rh_contrat', 'fonction'),
            ('rh_contrat', 'motif_fin'),
            ('rh_affectation', 'motif'),
            ('rh_candidat', 'nom'),
            ('rh_candidat', 'prenoms'),
            ('rh_candidat', 'adresse'),
            ('rh_candidat', 'diplomes'),
            ('rh_candidat', 'experiences'),
            ('rh_candidature', 'observations'),
            ('rh_candidature', 'compte_rendu_entretien'),
            ('rh_offre_emploi', 'intitule'),
            ('rh_offre_emploi', 'description'),
            ('rh_offre_emploi', 'profil'),
            ('rh_campagne_evaluation', 'libelle'),
            ('rh_campagne_evaluation', 'objectifs'),
            ('rh_critere_evaluation', 'libelle'),
            ('rh_critere_evaluation', 'description'),
            ('rh_evaluation', 'commentaire'),
            ('rh_evaluation', 'plan_developpement'),
            ('rh_formation', 'intitule'),
            ('rh_formation', 'organisme'),
            ('rh_formation', 'description'),
            ('rh_participation_formation', 'commentaire'),
            ('rh_pointage', 'commentaire'),
            ('rh_absence', 'motif'),
            ('rh_absence', 'commentaire_validation'),
            ('rh_accident_travail', 'lieu'),
            ('rh_accident_travail', 'description'),
            ('rh_dossier_medical', 'allergies'),
            ('rh_dossier_medical', 'antecedents'),
            ('rh_dossier_medical', 'notes'),
            ('rh_visite_medicale', 'medecin'),
            ('rh_visite_medicale', 'centre_medical'),
            ('rh_visite_medicale', 'conclusion'),
            ('rh_vaccination', 'vaccin'),
            ('rh_vaccination', 'lot'),
            ('rh_document', 'titre'),
            ('visite_visiteur', 'nom'),
            ('visite_visiteur', 'prenoms'),
            ('visite_visiteur', 'societe'),
            ('visite_visiteur', 'fonction'),
            ('visite_visiteur', 'observations'),
            ('visite', 'personne_visitee'),
            ('visite', 'observations')
        ) AS t(table_name, column_name)
    LOOP
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = cible.table_name
              AND column_name = cible.column_name
        ) THEN
            CONTINUE;
        END IF;

        EXECUTE format(
            'SELECT count(*) FROM public.%I WHERE %I IS NOT NULL AND %I <> upper(%I)',
            cible.table_name, cible.column_name, cible.column_name, cible.column_name
        ) INTO nb;

        total := total + nb;

        IF nb > 0 THEN
            RAISE NOTICE 'A MODIFIER  %.% : % ligne(s)', cible.table_name, cible.column_name, nb;
        END IF;
    END LOOP;

    RAISE NOTICE 'Total a modifier : % ligne(s).', total;
END $$;

-- -----------------------------------------------------------------------------
-- 2. MISE EN MAJUSCULES
--    Le bloc verifie l'existence de chaque table et de chaque colonne avant
--    d'executer le UPDATE : aucune erreur si votre base est plus ancienne.
--    La colonne "lignes" du journal indique le nombre de lignes reellement
--    modifiees.
-- -----------------------------------------------------------------------------
DO $$
DECLARE
    cible record;
    nb integer;
    total integer := 0;
    tables_absentes text[] := '{}';
BEGIN
    FOR cible IN
        SELECT * FROM (VALUES
            ('rh_employe', 'nom'),
            ('rh_employe', 'prenoms'),
            ('rh_employe', 'nationalite'),
            ('rh_employe', 'adresse'),
            ('rh_employe', 'motif_sortie'),
            ('rh_contrat', 'fonction'),
            ('rh_contrat', 'motif_fin'),
            ('rh_affectation', 'motif'),
            ('rh_candidat', 'nom'),
            ('rh_candidat', 'prenoms'),
            ('rh_candidat', 'adresse'),
            ('rh_candidat', 'diplomes'),
            ('rh_candidat', 'experiences'),
            ('rh_candidature', 'observations'),
            ('rh_candidature', 'compte_rendu_entretien'),
            ('rh_offre_emploi', 'intitule'),
            ('rh_offre_emploi', 'description'),
            ('rh_offre_emploi', 'profil'),
            ('rh_campagne_evaluation', 'libelle'),
            ('rh_campagne_evaluation', 'objectifs'),
            ('rh_critere_evaluation', 'libelle'),
            ('rh_critere_evaluation', 'description'),
            ('rh_evaluation', 'commentaire'),
            ('rh_evaluation', 'plan_developpement'),
            ('rh_formation', 'intitule'),
            ('rh_formation', 'organisme'),
            ('rh_formation', 'description'),
            ('rh_participation_formation', 'commentaire'),
            ('rh_pointage', 'commentaire'),
            ('rh_absence', 'motif'),
            ('rh_absence', 'commentaire_validation'),
            ('rh_accident_travail', 'lieu'),
            ('rh_accident_travail', 'description'),
            ('rh_dossier_medical', 'allergies'),
            ('rh_dossier_medical', 'antecedents'),
            ('rh_dossier_medical', 'notes'),
            ('rh_visite_medicale', 'medecin'),
            ('rh_visite_medicale', 'centre_medical'),
            ('rh_visite_medicale', 'conclusion'),
            ('rh_vaccination', 'vaccin'),
            ('rh_vaccination', 'lot'),
            ('rh_document', 'titre'),
            ('visite_visiteur', 'nom'),
            ('visite_visiteur', 'prenoms'),
            ('visite_visiteur', 'societe'),
            ('visite_visiteur', 'fonction'),
            ('visite_visiteur', 'observations'),
            ('visite', 'personne_visitee'),
            ('visite', 'observations')
        ) AS t(table_name, column_name)
    LOOP
        -- La table existe-t-elle ?
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = 'public' AND table_name = cible.table_name
        ) THEN
            IF NOT (cible.table_name = ANY (tables_absentes)) THEN
                tables_absentes := tables_absentes || cible.table_name;
            END IF;
            CONTINUE;
        END IF;

        -- La colonne existe-t-elle ?
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = cible.table_name
              AND column_name = cible.column_name
        ) THEN
            RAISE NOTICE 'Ignore : %.% (colonne absente)', cible.table_name, cible.column_name;
            CONTINUE;
        END IF;

        EXECUTE format(
            'UPDATE public.%I SET %I = upper(%I) WHERE %I IS NOT NULL AND %I <> upper(%I)',
            cible.table_name, cible.column_name, cible.column_name,
            cible.column_name, cible.column_name, cible.column_name
        );

        GET DIAGNOSTICS nb = ROW_COUNT;
        total := total + nb;

        IF nb > 0 THEN
            RAISE NOTICE 'OK  %.% : % ligne(s) mise(s) en majuscules', cible.table_name, cible.column_name, nb;
        END IF;
    END LOOP;

    IF cardinality(tables_absentes) > 0 THEN
        RAISE NOTICE 'Tables absentes de cette base (ignorees) : %', array_to_string(tables_absentes, ', ');
    END IF;

    RAISE NOTICE 'TERMINE : % ligne(s) modifiee(s) au total.', total;
END $$;

-- -----------------------------------------------------------------------------
-- 3. CONTROLE APRES PASSAGE
--    Doit afficher 0 ligne restante. Le bloc signale aussi les tables absentes.
-- -----------------------------------------------------------------------------
DO $$
DECLARE
    cible record;
    nb integer;
    restant integer := 0;
BEGIN
    RAISE NOTICE '--- CONTROLE (apres modification) ---';

    FOR cible IN
        SELECT * FROM (VALUES
            ('rh_employe', 'nom'),
            ('rh_employe', 'prenoms'),
            ('rh_employe', 'nationalite'),
            ('rh_employe', 'adresse'),
            ('rh_employe', 'motif_sortie'),
            ('rh_contrat', 'fonction'),
            ('rh_contrat', 'motif_fin'),
            ('rh_affectation', 'motif'),
            ('rh_candidat', 'nom'),
            ('rh_candidat', 'prenoms'),
            ('rh_candidat', 'adresse'),
            ('rh_candidat', 'diplomes'),
            ('rh_candidat', 'experiences'),
            ('rh_candidature', 'observations'),
            ('rh_candidature', 'compte_rendu_entretien'),
            ('rh_offre_emploi', 'intitule'),
            ('rh_offre_emploi', 'description'),
            ('rh_offre_emploi', 'profil'),
            ('rh_campagne_evaluation', 'libelle'),
            ('rh_campagne_evaluation', 'objectifs'),
            ('rh_critere_evaluation', 'libelle'),
            ('rh_critere_evaluation', 'description'),
            ('rh_evaluation', 'commentaire'),
            ('rh_evaluation', 'plan_developpement'),
            ('rh_formation', 'intitule'),
            ('rh_formation', 'organisme'),
            ('rh_formation', 'description'),
            ('rh_participation_formation', 'commentaire'),
            ('rh_pointage', 'commentaire'),
            ('rh_absence', 'motif'),
            ('rh_absence', 'commentaire_validation'),
            ('rh_accident_travail', 'lieu'),
            ('rh_accident_travail', 'description'),
            ('rh_dossier_medical', 'allergies'),
            ('rh_dossier_medical', 'antecedents'),
            ('rh_dossier_medical', 'notes'),
            ('rh_visite_medicale', 'medecin'),
            ('rh_visite_medicale', 'centre_medical'),
            ('rh_visite_medicale', 'conclusion'),
            ('rh_vaccination', 'vaccin'),
            ('rh_vaccination', 'lot'),
            ('rh_document', 'titre'),
            ('visite_visiteur', 'nom'),
            ('visite_visiteur', 'prenoms'),
            ('visite_visiteur', 'societe'),
            ('visite_visiteur', 'fonction'),
            ('visite_visiteur', 'observations'),
            ('visite', 'personne_visitee'),
            ('visite', 'observations')
        ) AS t(table_name, column_name)
    LOOP
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = cible.table_name
              AND column_name = cible.column_name
        ) THEN
            CONTINUE;
        END IF;

        EXECUTE format(
            'SELECT count(*) FROM public.%I WHERE %I IS NOT NULL AND %I <> upper(%I)',
            cible.table_name, cible.column_name, cible.column_name, cible.column_name
        ) INTO nb;

        IF nb > 0 THEN
            restant := restant + nb;
            RAISE NOTICE 'RESTE  %.% : % ligne(s)', cible.table_name, cible.column_name, nb;
        END IF;
    END LOOP;

    IF restant = 0 THEN
        RAISE NOTICE 'Controle OK : toutes les colonnes suivies sont en majuscules.';
    ELSE
        RAISE NOTICE 'Controle : % ligne(s) restent en minuscules (relancer la partie 2).', restant;
    END IF;
END $$;

-- Apercu du resultat
SELECT matricule, nom, prenoms, email FROM rh_employe ORDER BY nom LIMIT 20;

-- -----------------------------------------------------------------------------
-- 4. AUTRES MODULES : equipements, achats, courrier, reunions, stock
--    Meme principe : chaque table et chaque colonne est verifiee avant le
--    UPDATE, les elements absents sont ignores sans erreur.
--    Sont volontairement laissees de cote : les codes, numeros, references,
--    jetons, adresses email et chemins de fichiers.
-- -----------------------------------------------------------------------------
DO $$
DECLARE
    cible record;
    nb integer;
    total integer := 0;
BEGIN
    FOR cible IN
        SELECT * FROM (VALUES
            -- Parc et equipements
            ('equipement', 'nom'),
            ('equipement', 'modele'),
            ('emplacement', 'libelle'),
            ('marque', 'nom'),
            ('famille', 'libelle'),
            -- Fournisseurs et achats
            ('fournisseur', 'raison_sociale'),
            ('fournisseur', 'sigle'),
            ('fournisseur', 'personne_ressource'),
            ('fournisseur', 'contact_personne'),
            ('fournisseur', 'adresse'),
            ('fournisseur', 'notes'),
            ('demande_achat', 'service'),
            ('demande_achat', 'demandeur'),
            ('demande_achat', 'motif'),
            ('demande_achat', 'enregistre_par'),
            ('demande_achat', 'valide_par'),
            ('demande_achat', 'commentaire_dg'),
            ('demande_achat', 'fournisseur_nom'),
            ('demande_achat', 'servie_par'),
            ('bon_sortie', 'service'),
            ('bon_sortie', 'demandeur'),
            ('bon_sortie', 'etabli_par'),
            ('bon_sortie', 'poste_controle'),
            ('bon_sortie', 'signe_par'),
            ('bon_sortie', 'cloture_par'),
            ('bon_sortie', 'refus_par'),
            ('bon_sortie', 'refus_motif'),
            -- Courrier
            ('courrier', 'objet'),
            ('courrier', 'correspondant'),
            ('courrier', 'organisme'),
            ('courrier', 'service'),
            ('courrier', 'responsable'),
            ('courrier', 'observation'),
            ('courrier', 'suite_donnee'),
            -- Reunions
            ('reunion', 'titre'),
            ('reunion', 'objet'),
            ('reunion', 'lieu'),
            ('reunion', 'compte_rendu'),
            -- Referentiels communs
            ('services', 'libelle'),
            ('poste', 'libelle'),
            ('directions', 'libelle')
        ) AS t(table_name, column_name)
    LOOP
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = cible.table_name
              AND column_name = cible.column_name
        ) THEN
            CONTINUE;
        END IF;

        EXECUTE format(
            'UPDATE public.%I SET %I = upper(%I) WHERE %I IS NOT NULL AND %I <> upper(%I)',
            cible.table_name, cible.column_name, cible.column_name,
            cible.column_name, cible.column_name, cible.column_name
        );

        GET DIAGNOSTICS nb = ROW_COUNT;
        total := total + nb;

        IF nb > 0 THEN
            RAISE NOTICE 'OK  %.% : % ligne(s) mise(s) en majuscules', cible.table_name, cible.column_name, nb;
        END IF;
    END LOOP;

    RAISE NOTICE 'Autres modules : % ligne(s) modifiee(s).', total;
END $$;

-- -----------------------------------------------------------------------------
-- 5. POUR MEMOIRE : colonnes volontairement NON modifiees
-- -----------------------------------------------------------------------------
--   rh_employe.email, rh_candidat.email, visite_visiteur.email
--       -> une adresse email est en minuscules par convention.
--   rh_employe.matricule, rh_employe.code_pointage, rh_employe.telephone,
--   rh_employe.numero_cnps, rh_candidat.telephone, visite_visiteur.telephone,
--   visite_visiteur.numero_piece, visite.piece_identite
--       -> identifiants et numeros : la casse d'origine est conservee.
--   rh_employe.photo, rh_formation.support, rh_formation.liste_presences,
--   rh_document.chemin, rh_document.nom_original, rh_candidat.cv
--       -> chemins de fichiers : les modifier casserait les liens.
--   Toutes les colonnes de type statut / type / sexe / situation / categorie /
--   gravite / aptitude / resultat / motif (visite) / type_piece
--       -> valeurs techniques comparees telles quelles par l'application.
-- =============================================================================
