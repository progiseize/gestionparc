-- Copyright (C) 2025 ProgiSeize <contact@progiseize.fr>
--
-- Script de correction pour ajouter la colonne 'position' manquante
-- dans TOUTES les tables de parc (llx_gestionparc__*) qui ne l'ont pas
--
-- Ce script corrige l'erreur: Unknown column 'position' in 'ORDER BY'
--
-- IMPORTANT: Ce script traite automatiquement TOUTES les tables de type parc
-- et préserve l'ordre actuel basé sur les rowid (ordre de création)

-- ============================================================
-- ÉTAPE 1: Créer une procédure stockée pour ajouter la colonne
-- ============================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS add_position_to_all_parc_tables$$

CREATE PROCEDURE add_position_to_all_parc_tables()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE table_name VARCHAR(255);
    DECLARE sql_check TEXT;
    DECLARE sql_alter TEXT;
    DECLARE sql_update TEXT;
    DECLARE column_exists INT;
    
    -- Curseur pour parcourir toutes les tables llx_gestionparc__*
    DECLARE cur CURSOR FOR 
        SELECT TABLE_NAME 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME LIKE 'llx_gestionparc\__%';
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO table_name;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Vérifier si la colonne 'position' existe déjà
        SELECT COUNT(*) INTO column_exists
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = table_name
        AND COLUMN_NAME = 'position';
        
        -- Si la colonne n'existe pas, on l'ajoute
        IF column_exists = 0 THEN
            -- Ajouter la colonne position
            SET @sql_alter = CONCAT('ALTER TABLE ', table_name, ' ADD COLUMN position INT(11) NOT NULL DEFAULT 0');
            PREPARE stmt FROM @sql_alter;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
            
            SELECT CONCAT('✓ Colonne position ajoutée à la table: ', table_name) AS message;
            
            -- Mettre à jour les positions basées sur rowid (ordre de création)
            -- Cela préserve l'ordre actuel des éléments
            SET @sql_update = CONCAT(
                'UPDATE ', table_name, ' t1 ',
                'JOIN (',
                    'SELECT rowid, socid, ',
                    '@rn := IF(@prev_socid = socid, @rn + 1, 1) AS new_position, ',
                    '@prev_socid := socid ',
                    'FROM ', table_name, ', ',
                    '(SELECT @rn := 0, @prev_socid := NULL) vars ',
                    'ORDER BY socid, rowid',
                ') t2 ON t1.rowid = t2.rowid ',
                'SET t1.position = t2.new_position'
            );
            
            PREPARE stmt FROM @sql_update;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
            
            SELECT CONCAT('✓ Positions initialisées pour la table: ', table_name) AS message;
        ELSE
            SELECT CONCAT('→ La colonne position existe déjà dans: ', table_name) AS message;
        END IF;
        
    END LOOP;
    
    CLOSE cur;
    
    SELECT '========================================' AS message;
    SELECT '✓ Traitement terminé avec succès!' AS message;
    SELECT '========================================' AS message;
END$$

DELIMITER ;

-- ============================================================
-- ÉTAPE 2: Exécuter la procédure
-- ============================================================

CALL add_position_to_all_parc_tables();

-- ============================================================
-- ÉTAPE 3: Supprimer la procédure (nettoyage)
-- ============================================================

DROP PROCEDURE IF EXISTS add_position_to_all_parc_tables;

-- ============================================================
-- FIN DU SCRIPT
-- ============================================================
-- 
-- Ce script a:
-- 1. Recherché automatiquement TOUTES les tables llx_gestionparc__*
-- 2. Ajouté la colonne 'position' si elle n'existait pas
-- 3. Initialisé les positions basées sur l'ordre de création (rowid)
--    pour préserver l'affichage actuel
-- 4. Traité chaque client (socid) séparément
--
-- Les positions commencent à 1 pour chaque client et suivent
-- l'ordre chronologique de création des éléments (rowid ASC)
