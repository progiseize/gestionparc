<?php
/* Copyright (C) 2026 Progiseize
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
 * Upload / suppression / listing des photos prises pendant une vérification.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) : $res = @include '../main.inc.php'; endif;
if (!$res && file_exists("../../main.inc.php")) : $res = @include '../../main.inc.php'; endif;
if (!$res && file_exists("../../../main.inc.php")) : $res = @include '../../../main.inc.php'; endif;

dol_include_once('/gestionparc/class/gestionparc.class.php');
dol_include_once('/gestionparc/class/gestionparcphoto.class.php');

$langs->loadLangs(array('gestionparc@gestionparc'));

header('Content-Type: application/json');

if ($user->socid > 0 || !$user->hasRight('gestionparc', 'parc', 'write')) {
	echo json_encode(array('success' => false, 'error' => $langs->transnoentities('NotEnoughPermissions')));
	exit;
}
if (GETPOST('token') != $_SESSION['token']) {
	echo json_encode(array('success' => false, 'error' => 'Invalid token'));
	exit;
}

$action   = GETPOST('action', 'alpha');
$socid    = GETPOSTINT('socid');
$parcid   = GETPOSTINT('parcid');
$parctype = GETPOST('parctype', 'aZ09');
$itemid   = GETPOSTINT('itemid');

$gpphoto = new GestionParcPhoto($db);

/**
 * Sérialise une photo pour le front.
 */
function gpPhotoToArray($photo)
{
	return array(
		'id'    => (int) $photo->rowid,
		'name'  => $photo->filename,
		'url'   => GestionParcPhoto::getViewUrl($photo->rowid),
		'thumb' => GestionParcPhoto::getViewUrl($photo->rowid, true),
	);
}

// La vérif en cours du tiers porte les photos
$verification = new GestionParcVerif($db);
$verif_id = $socid ? $verification->isVerif($socid) : 0;

switch ($action) {

	case 'upload':
		if (empty($verif_id)) {
			echo json_encode(array('success' => false, 'error' => $langs->transnoentities('gp_photo_error_noverif')));
			exit;
		}
		if (empty($parctype) || empty($itemid)) {
			echo json_encode(array('success' => false, 'error' => 'Missing parameters'));
			exit;
		}
		if (empty($_FILES['photo'])) {
			echo json_encode(array('success' => false, 'error' => $langs->transnoentities('gp_photo_error_nofile')));
			exit;
		}

		$added  = array();
		$errors = array();

		// Le champ accepte plusieurs fichiers : $_FILES['photo'] est alors un
		// tableau de tableaux, sinon un simple tableau.
		$files = array();
		if (is_array($_FILES['photo']['name'])) {
			foreach (array_keys($_FILES['photo']['name']) as $i) {
				$files[] = array(
					'name'     => $_FILES['photo']['name'][$i],
					'tmp_name' => $_FILES['photo']['tmp_name'][$i],
					'size'     => $_FILES['photo']['size'][$i],
					'error'    => $_FILES['photo']['error'][$i],
				);
			}
		} else {
			$files[] = $_FILES['photo'];
		}

		foreach ($files as $file) {
			$newid = $gpphoto->add($user, $verif_id, $socid, $parcid, $parctype, $itemid, $file);
			if ($newid > 0) {
				$added[] = gpPhotoToArray($gpphoto->fetch($newid));
			} else {
				$errors[] = $gpphoto->error;
			}
		}

		if (empty($added)) {
			echo json_encode(array('success' => false, 'error' => $langs->transnoentities('gp_photo_error_upload').' ('.implode(', ', $errors).')'));
			exit;
		}

		echo json_encode(array(
			'success' => true,
			'photos'  => $added,
			'count'   => $gpphoto->countByItem($verif_id, $parctype, $itemid),
			'partial' => !empty($errors),
		));
		break;

	case 'delete':
		$photoid = GETPOSTINT('photoid');
		$photo = $gpphoto->fetch($photoid);
		if (!$photo) {
			echo json_encode(array('success' => false, 'error' => 'Unknown photo'));
			exit;
		}
		// On ne touche qu'aux photos de la vérif en cours : celles des campagnes
		// closes font partie du rapport archivé.
		if (empty($verif_id) || (int) $photo->verif_id !== (int) $verif_id) {
			echo json_encode(array('success' => false, 'error' => $langs->transnoentities('gp_photo_error_closed')));
			exit;
		}

		if ($gpphoto->delete($photoid)) {
			echo json_encode(array(
				'success' => true,
				'count'   => $gpphoto->countByItem($verif_id, $photo->parc_key, $photo->item_id),
			));
		} else {
			echo json_encode(array('success' => false, 'error' => $langs->transnoentities('gp_error')));
		}
		break;

	case 'list':
		if (empty($verif_id)) {
			echo json_encode(array('success' => true, 'photos' => array(), 'count' => 0));
			exit;
		}
		$photos = array();
		foreach ($gpphoto->listByItem($verif_id, $parctype, $itemid) as $photo) {
			$photos[] = gpPhotoToArray($photo);
		}
		echo json_encode(array('success' => true, 'photos' => $photos, 'count' => count($photos)));
		break;

	default:
		echo json_encode(array('success' => false, 'error' => 'Unknown action'));
		break;
}
