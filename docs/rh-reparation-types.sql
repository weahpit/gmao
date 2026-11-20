-- Module RH - diagnostic et reparation des types de cles (PostgreSQL)
-- A executer dans psql ou pgAdmin, en etant connecte a la base de l'application.
-- Aucune commande de ce fichier ne modifie quoi que ce soit avant la partie 3.

-- =============================================================
-- 1. DIAGNOSTIC : quelles tables parlent des employes et avec quel type ?
-- =============================================================
SELECT table_name, column_name, data_type
FROM information_schema.columns
WHERE table_name LIKE '%employe%'
   OR column_name LIKE '%employe%'
ORDER BY table_name, column_name;

-- Cles etrangeres liees aux employes, avec le type des deux cotes.
-- Une ligne ou type_source <> type_cible est la cause de l'erreur
-- "incompatible types: uuid and integer".
SELECT tc.table_name        AS table_source,
       kcu.column_name      AS colonne_source,
       cs.data_type         AS type_source,
       ccu.table_name       AS table_cible,
       ccu.column_name      AS colonne_cible,
       ct.data_type         AS type_cible
FROM information_schema.table_constraints tc
JOIN information_schema.key_column_usage kcu
  ON kcu.constraint_name = tc.constraint_name
 AND kcu.table_schema = tc.table_schema
JOIN information_schema.constraint_column_usage ccu
  ON ccu.constraint_name = tc.constraint_name
 AND ccu.table_schema = tc.table_schema
JOIN information_schema.columns cs
  ON cs.table_name = tc.table_name
 AND cs.column_name = kcu.column_name
JOIN information_schema.columns ct
  ON ct.table_name = ccu.table_name
 AND ct.column_name = ccu.column_name
WHERE tc.constraint_type = 'FOREIGN KEY'
  AND (kcu.column_name LIKE '%employe%' OR ccu.table_name LIKE '%employe%')
ORDER BY 1, 2;

-- Quelles tables rh_* existent deja et contiennent-elles des donnees ?
SELECT t.table_name
FROM information_schema.tables t
WHERE t.table_schema = current_schema()
  AND t.table_name LIKE 'rh\_%'
ORDER BY t.table_name;

-- (repeter pour chaque table listee ci-dessus)
-- SELECT COUNT(*) FROM rh_employe;
-- SELECT COUNT(*) FROM rh_contrat;


-- =============================================================
-- 2. LIRE LE RESULTAT
-- =============================================================
-- Cas A - la table cible est rh_employe et elle est VIDE (0 ligne) :
--          -> passer a la partie 3 (suppression puis recreation).
--
-- Cas B - la table cible est rh_employe et elle contient les VRAIS employes
--          (ancien module de pointage par exemple) :
--          -> ne rien supprimer. Faire une sauvegarde, puis me transmettre
--             le resultat de la partie 1 : la reprise des donnees se fait
--             par un script d'import, pas par un DROP.
--
-- Cas C - la table cible porte un autre nom (employe, employes, ...) :
--          -> c'est une table ancienne. La supprimer n'a de sens que si elle
--             est vide ; sinon, meme remarque que le cas B.


-- =============================================================
-- 3. REPARATION (cas A uniquement) - LES DONNEES RH SERONT PERDUES
-- =============================================================
-- Equivalent en ligne de commande, sans psql :
--   php bin/console app:rh:installer --reparer
--
-- Apres sauvegarde de la base :
-- DROP TABLE IF EXISTS
--     rh_document, rh_evaluation, rh_critere_evaluation, rh_campagne_evaluation,
--     rh_participation_formation, rh_formation, rh_vaccination, rh_accident_travail,
--     rh_visite_medicale, rh_dossier_medical, rh_absence, rh_pointage,
--     rh_candidature, rh_candidat, rh_offre_emploi, rh_affectation,
--     rh_contrat, rh_employe
-- CASCADE;
--
-- Puis, cote application :
--   php bin/console app:rh:installer
--   php bin/console doctrine:schema:validate
