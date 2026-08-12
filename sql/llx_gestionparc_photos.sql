-- Copyright (C) 2026 ProgiSeize <contact@progiseize.fr>
--
-- This program and files/directory inner it is free software: you can
-- redistribute it and/or modify it under the terms of the
-- GNU Affero General Public License (AGPL) as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU AGPL for more details.
--
-- You should have received a copy of the GNU AGPL
-- along with this program.  If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.


CREATE TABLE IF NOT EXISTS `llx_gestionparc_photos` (
  `rowid` int NOT NULL AUTO_INCREMENT,
  `verif_id` int NOT NULL DEFAULT 0,
  `socid` int NOT NULL DEFAULT 0,
  `parc_id` int NOT NULL DEFAULT 0,
  `parc_key` varchar(32) NOT NULL,
  `item_id` int NOT NULL DEFAULT 0,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `filesize` int NOT NULL DEFAULT 0,
  `position` int NOT NULL DEFAULT 0,
  `author` int NOT NULL DEFAULT 0,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `entity` int NOT NULL DEFAULT 1,
  PRIMARY KEY (`rowid`),
  KEY `idx_gpphotos_verif` (`verif_id`),
  KEY `idx_gpphotos_item` (`parc_key`, `item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Photo obligatoire pour clore la vérification d'un élément de cet organe
ALTER TABLE llx_gestionparc ADD photo_required BOOLEAN NOT NULL DEFAULT 0;
