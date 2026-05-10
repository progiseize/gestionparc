-- Copyright (C) 2025 ProgiSeize <contact@progiseize.fr>
--
-- Version SIMPLE du script de correction (sans procédure stockée)
-- À utiliser si la version avec procédure stockée ne fonctionne pas
--
-- ATTENTION: Ce script contient des exemples pour quelques tables.
-- Vous devrez l'adapter selon VOS tables de parc existantes.
--
-- Pour voir toutes vos tables de parc, exécutez d'abord:
-- SHOW TABLES LIKE 'llx_gestionparc\__%';

-- ============================================================
-- EXEMPLE 1: Table extincteurs
-- ============================================================

-- Vérifier si la colonne existe
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ La colonne position existe déjà dans llx_gestionparc__extincteurs'
        ELSE '→ La colonne position doit être ajoutée à llx_gestionparc__extincteurs'
    END AS status
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'llx_gestionparc__extincteurs'
AND COLUMN_NAME = 'position';

-- Ajouter la colonne si elle n'existe pas
-- Décommenter la ligne suivante si nécessaire:
-- ALTER TABLE llx_gestionparc__extincteurs ADD COLUMN position INT(11) NOT NULL DEFAULT 0;

-- Initialiser les positions (préserve l'ordre de création)
UPDATE llx_gestionparc__extincteurs t1
JOIN (
    SELECT 
        rowid, 
        socid,
        ROW_NUMBER() OVER (PARTITION BY socid ORDER BY rowid) AS new_position
    FROM llx_gestionparc__extincteurs
) t2 ON t1.rowid = t2.rowid
SET t1.position = t2.new_position;

-- ============================================================
-- EXEMPLE 2: Autre table de parc (remplacer "nomduparc" par le vrai nom)
-- ============================================================

-- ALTER TABLE llx_gestionparc__nomduparc ADD COLUMN position INT(11) NOT NULL DEFAULT 0;

-- UPDATE llx_gestionparc__nomduparc t1
-- JOIN (
--     SELECT 
--         rowid, 
--         socid,
--         ROW_NUMBER() OVER (PARTITION BY socid ORDER BY rowid) AS new_position
--     FROM llx_gestionparc__nomduparc
-- ) t2 ON t1.rowid = t2.rowid
-- SET t1.position = t2.new_position;

-- ============================================================
-- INSTRUCTIONS
-- ============================================================
-- 
-- 1. Listez toutes vos tables de parc:
--    SHOW TABLES LIKE 'llx_gestionparc\__%';
--
-- 2. Pour chaque table trouvée, ajoutez un bloc comme ci-dessus
--
-- 3. L'ordre sera préservé basé sur le rowid (ordre de création)
--    Chaque client (socid) aura ses positions numérotées de 1 à N
