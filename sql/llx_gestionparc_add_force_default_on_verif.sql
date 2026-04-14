-- Copyright (C) 2026 ProgiSeize <contact@progiseize.fr>
--
-- Ajout du champ force_default_on_verif pour forcer la remise à zéro
-- des champs à leur valeur par défaut lors de l'ouverture d'une vérification

ALTER TABLE llx_gestionparc_fields ADD COLUMN force_default_on_verif BOOLEAN NOT NULL DEFAULT 0;