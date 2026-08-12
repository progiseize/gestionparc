<?php
/*
 * Copyright (C) 2025 Progiseize
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Enregistrement des champs personnalisés tiers (cases à cocher de l'onglet
 * GestionParc). Les cases sont toujours visibles et sauvegardées au clic.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) : $res = @include '../main.inc.php'; endif;
if (!$res && file_exists("../../main.inc.php")) : $res = @include '../../main.inc.php'; endif;
if (!$res && file_exists("../../../main.inc.php")) : $res = @include '../../../main.inc.php'; endif;

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/gestionparc/class/gestionparc.class.php');

$langs->loadLangs(array('gestionparc@gestionparc'));

header('Content-Type: application/json');

// Protection if external user
if ($user->socid > 0 || (!$user->hasRight('gestionparc', 'parc', 'write') && !$user->admin)) {
	echo json_encode(array('success' => false, 'error' => $langs->transnoentities('NotEnoughPermissions')));
	exit;
}

// Security check
if (GETPOST('token') != $_SESSION['token']) {
	echo json_encode(array('success' => false, 'error' => 'Invalid token'));
	exit;
}

$action   = GETPOST('action', 'alpha');
$socid    = GETPOSTINT('socid');
$fieldkey = GETPOST('fieldkey', 'aZ09');
$selected = GETPOST('selected', 'array');

if ($action != 'set_socfield') {
	echo json_encode(array('success' => false, 'error' => 'Unknown action'));
	exit;
}

if (empty($socid)) {
	echo json_encode(array('success' => false, 'error' => 'Missing socid'));
	exit;
}

$socfields = GestionParcVerif::getCustomSocFields();
if (!isset($socfields[$fieldkey])) {
	echo json_encode(array('success' => false, 'error' => 'Unknown field'));
	exit;
}

$societe = new Societe($db);
if ($societe->fetch($socid) <= 0) {
	echo json_encode(array('success' => false, 'error' => 'Unknown thirdparty'));
	exit;
}

$verification = new GestionParcVerif($db);
if ($verification->setCustomSocField($societe, $fieldkey, $selected)) {
	echo json_encode(array('success' => true, 'message' => $langs->transnoentities('RecordSaved')));
} else {
	echo json_encode(array('success' => false, 'error' => $societe->error ? $societe->error : $langs->transnoentities('gp_error')));
}
