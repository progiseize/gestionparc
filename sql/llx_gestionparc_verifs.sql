-- Copyright (C) 2022 ProgiSeize <contact@progiseize.fr>
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


CREATE TABLE IF NOT EXISTS `llx_gestionparc_verifs` (
  `rowid` int NOT NULL AUTO_INCREMENT,
  `socid` int NOT NULL,
  `author` int NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_close` datetime NOT NULL,
  `nb_verified` int NOT NULL,
  `nb_total` int NOT NULL,
  `commentaires` text NOT NULL,
  `fichinter_id` int NOT NULL,
  `is_close` tinyint(1) NOT NULL,
  `files_list` JSON NOT NULL,
  PRIMARY KEY (`rowid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
