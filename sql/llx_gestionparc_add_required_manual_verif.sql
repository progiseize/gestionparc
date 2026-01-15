-- Copyright (C) 2026 ProgiSeize <contact@progiseize.fr>
--
-- Ajout du champ required_manual_verif pour gérer les champs obligatoires
-- lors des vérifications manuelles

ALTER TABLE llx_gestionparc_fields ADD COLUMN required_manual_verif BOOLEAN NOT NULL DEFAULT 0;
