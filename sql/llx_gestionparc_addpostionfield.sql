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


ALTER TABLE llx_gestionparc__alarmes ADD COLUMN IF NOT EXISTS position INT(11) NOT NULL DEFAULT 0;
ALTER TABLE llx_gestionparc__blocs ADD COLUMN IF NOT EXISTS position INT(11) NOT NULL DEFAULT 0;
ALTER TABLE llx_gestionparc__colonnesseches ADD COLUMN IF NOT EXISTS position INT(11) NOT NULL DEFAULT 0;
ALTER TABLE llx_gestionparc__defibrillateur ADD COLUMN IF NOT EXISTS position INT(11) NOT NULL DEFAULT 0;
ALTER TABLE llx_gestionparc__desenfumage ADD COLUMN IF NOT EXISTS position INT(11) NOT NULL DEFAULT 0;
ALTER TABLE llx_gestionparc__detecteurdefumee_1 ADD COLUMN IF NOT EXISTS position INT(11) NOT NULL DEFAULT 0;
ALTER TABLE llx_gestionparc__extincteurs ADD COLUMN IF NOT EXISTS position INT(11) NOT NULL DEFAULT 0;
ALTER TABLE llx_gestionparc__formation ADD COLUMN IF NOT EXISTS position INT(11) NOT NULL DEFAULT 0;
ALTER TABLE llx_gestionparc__portecoupefeu ADD COLUMN IF NOT EXISTS position INT(11) NOT NULL DEFAULT 0;
ALTER TABLE llx_gestionparc__poteauincendie ADD COLUMN IF NOT EXISTS position INT(11) NOT NULL DEFAULT 0;
ALTER TABLE llx_gestionparc__ria ADD COLUMN IF NOT EXISTS position INT(11) NOT NULL DEFAULT 0;
ALTER TABLE llx_gestionparc__systemedextinctionautomatique ADD COLUMN IF NOT EXISTS position INT(11) NOT NULL DEFAULT 0;