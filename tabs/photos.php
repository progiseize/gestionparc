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
 * Onglet "Photos" d'une intervention : les clichés pris pendant la vérification
 * qui a produit cette intervention, légendés par l'élément de parc concerné,
 * remplaçables et supprimables.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) : $res = @include '../main.inc.php'; endif;
if (!$res && file_exists("../../main.inc.php")) : $res = @include '../../main.inc.php'; endif;
if (!$res && file_exists("../../../main.inc.php")) : $res = @include '../../../main.inc.php'; endif;

require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/fichinter.lib.php';
dol_include_once('/gestionparc/class/gestionparc.class.php');
dol_include_once('/gestionparc/class/gestionparcphoto.class.php');

$langs->loadLangs(array('interventions', 'companies', 'gestionparc@gestionparc'));

$id     = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

// Droits dédiés, désactivés par défaut : seul l'admin passe sans attribution
$can_read  = ($user->admin || $user->hasRight('gestionparc', 'photo', 'read'));
$can_write = ($user->admin || $user->hasRight('gestionparc', 'photo', 'write'));

if ($user->socid > 0 || !$can_read || !$user->hasRight('ficheinter', 'lire')) {
	accessforbidden();
}

$object = new Fichinter($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}
$object->fetch_thirdparty();

$gpverif = new GestionParcVerif($db);
$gpphoto = new GestionParcPhoto($db);
$verif_id = $gpverif->getVerifIdByFichinter($id);

// Répertoire documents de l'intervention : la copie visible dans l'onglet
// Documents doit rester alignée sur les modifications faites ici.
$base_dir = !empty($conf->ficheinter->dir_output) ? $conf->ficheinter->dir_output : (!empty($conf->fichinter->dir_output) ? $conf->fichinter->dir_output : DOL_DATA_ROOT.'/fichinter');
$upload_dir = $base_dir.'/'.dol_sanitizeFileName($object->ref);

/*******************************************************************
* ACTIONS
********************************************************************/

if ($action == 'replace_photo' && $can_write) {
	if (GETPOST('token') != $_SESSION['token']) {
		setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
	} else {
		$photo_id = GETPOSTINT('photoid');
		$photo = $gpphoto->fetch($photo_id);

		if (!$photo || (int) $photo->verif_id !== (int) $verif_id) {
			setEventMessages($langs->trans('gp_photo_error_notonthisinter'), null, 'errors');
		} elseif (empty($_FILES['photo']) || !empty($_FILES['photo']['error'])) {
			setEventMessages($langs->trans('gp_photo_error_nofile'), null, 'errors');
		} else {
			$previous = $photo->filename;
			if ($gpphoto->replaceFile($user, $photo_id, $_FILES['photo'])) {
				$gpphoto->syncInterventionCopy($gpphoto->fetch($photo_id), $upload_dir, $previous);
				setEventMessages($langs->trans('gp_photo_replaced'), null, 'mesgs');
			} else {
				setEventMessages($langs->trans('gp_photo_error_upload').' ('.$gpphoto->error.')', null, 'errors');
			}
		}
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
	exit;
}

if ($action == 'confirm_delete_photo' && $can_write) {
	if (GETPOST('token') != $_SESSION['token']) {
		setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
	} else {
		$photo_id = GETPOSTINT('photoid');
		$photo = $gpphoto->fetch($photo_id);

		if (!$photo || (int) $photo->verif_id !== (int) $verif_id) {
			setEventMessages($langs->trans('gp_photo_error_notonthisinter'), null, 'errors');
		} else {
			$previous = $photo->filename;
			if ($gpphoto->delete($photo_id)) {
				$gpphoto->syncInterventionCopy(null, $upload_dir, $previous);
				setEventMessages($langs->trans('gp_photo_deleted'), null, 'mesgs');
			} else {
				setEventMessages($langs->trans('gp_error'), null, 'errors');
			}
		}
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
	exit;
}

/*******************************************************************
* VIEW
********************************************************************/

$array_css = array('/gestionparc/assets/css/gestionparc.css');
llxHeader('', $object->ref.' - '.$langs->trans('gp_photo_label'), '', '', '', '', array(), $array_css);

$head = fichinter_prepare_head($object);
print dol_get_fiche_head($head, 'gestionparc_photos', $langs->trans('Intervention'), -1, 'intervention');

$linkback = '<a href="'.DOL_URL_ROOT.'/fichinter/list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
$morehtmlref = '<div class="refidno">';
if (!empty($object->thirdparty->id)) {
	$morehtmlref .= $object->thirdparty->getNomUrl(1, 'customer');
}
$morehtmlref .= '</div>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

print '<div class="fichecenter"></div>';
print dol_get_fiche_end();

// Confirmation de suppression
if ($action == 'delete_photo' && $can_write) {
	$form = new Form($db);
	print $form->formconfirm(
		$_SERVER['PHP_SELF'].'?id='.$id.'&photoid='.GETPOSTINT('photoid'),
		$langs->trans('gp_photo_delete_title'),
		$langs->trans('gp_photo_delete_confirm_inter'),
		'confirm_delete_photo',
		'',
		'',
		1
	);
}

$photos = $verif_id ? $gpphoto->listByVerif($verif_id) : array();

if (empty($photos)) {
	print '<div class="opacitymedium">'.$langs->trans('gp_photo_none_on_inter').'</div>';
	llxFooter();
	$db->close();
	return;
}

// Regroupement par élément de parc : une carte par élément, ses clichés dessous
$items = array();
foreach ($photos as $photo) {
	$key = $photo->parc_key.'#'.$photo->item_id;
	if (!isset($items[$key])) {
		$items[$key] = array(
			'desc'   => $gpphoto->describeItem($photo->parc_key, $photo->item_id),
			'socid'  => $photo->socid,
			'parc'   => $photo->parc_key,
			'photos' => array(),
		);
	}
	$items[$key]['photos'][] = $photo;
}

print '<div class="gp-interphotos">';
foreach ($items as $item) {
	$desc = $item['desc'];

	print '<div class="gp-interphoto-item">';
		print '<div class="gp-interphoto-head">';
			print '<span class="gp-interphoto-organ">'.dol_escape_htmltag($desc['organ']).'</span>';
			if ($desc['number'] !== '') {
				print '<span class="gp-interphoto-num">'.$langs->trans('gp_photo_item_number').' '.dol_escape_htmltag($desc['number']).'</span>';
			}
			if ($desc['product'] !== '') {
				print '<span class="gp-interphoto-product">'.dol_escape_htmltag($desc['product']).'</span>';
			}
			if (empty($desc['exists'])) {
				print '<span class="gp-interphoto-gone">'.$langs->trans('gp_photo_item_removed').'</span>';
			} else {
				$parc_url = dol_buildpath('/gestionparc/tabs/gestionparc.php', 1).'?socid='.(int) $item['socid'].'&parctype='.urlencode($item['parc']);
				print '<a class="gp-interphoto-link" href="'.$parc_url.'">'.$langs->trans('gp_photo_see_in_parc').'</a>';
			}
		print '</div>';

		print '<div class="gp-interphoto-list">';
		foreach ($item['photos'] as $photo) {
			print '<div class="gp-interphoto-card">';
				print '<a href="'.dol_escape_htmltag(GestionParcPhoto::getViewUrl($photo->rowid)).'" target="_blank" rel="noopener">';
				print '<img src="'.dol_escape_htmltag(GestionParcPhoto::getViewUrl($photo->rowid, true)).'" alt="'.dol_escape_htmltag($photo->filename).'" loading="lazy">';
				print '</a>';
				print '<div class="gp-interphoto-meta">'.dol_print_date($db->jdate($photo->date_creation), 'dayhour').'</div>';

				if ($can_write) {
					print '<div class="gp-interphoto-actions">';
						print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'?id='.$id.'">';
							print '<input type="hidden" name="token" value="'.newToken().'">';
							print '<input type="hidden" name="action" value="replace_photo">';
							print '<input type="hidden" name="photoid" value="'.(int) $photo->rowid.'">';
							print '<label class="gp-interphoto-replace">';
								print '<input type="file" name="photo" accept="image/*" onchange="this.form.submit();">';
								print '<span class="fas fa-sync-alt"></span> '.$langs->trans('gp_photo_replace');
							print '</label>';
						print '</form>';
						print '<a class="gp-interphoto-del" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=delete_photo&photoid='.(int) $photo->rowid.'&token='.newToken().'">';
						print '<span class="fas fa-trash-alt"></span> '.$langs->trans('Delete');
						print '</a>';
					print '</div>';
				}
			print '</div>';
		}
		print '</div>';
	print '</div>';
}
print '</div>';

llxFooter();
$db->close();
