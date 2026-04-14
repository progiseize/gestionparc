<?php
/* Copyright (C) 2025 Anthony Damhet <a.damhet@progiseize.fr>
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

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1'); // Disables token renewal
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}

// Load Dolibarr environment
require '../../../main.inc.php';
dol_include_once('/gestionparc/class/gestionparc.class.php');

$langs->load('admin');
$langs->load('gestionparc@gestionparc');

//
$gestionparc = new GestionParc($db);
$action = GETPOST('action', 'alpha');
$parckey = GETPOST('parckey', 'alphanohtml');
$results = array();

// ITEM SORT
if ($action == 'itemsort'){
	$itemSort = GETPOST('itemsort', 'array');
	if (!empty($itemSort)) {
		$i = 0;
		foreach ($itemSort as $item) {
			$i++;
			$itemID = str_replace('item-', '', $item);
			$rr = $gestionparc->setElementPosition($parckey, $itemID, $i);
			$results[$i]= $rr;
		}
	}
}

// ITEM DELETE
if ($action == 'deleteitem') {
	$itemid = GETPOSTINT('itemid');
	$sql = "DELETE FROM ".MAIN_DB_PREFIX."gestionparc__".$db->escape($parckey)." WHERE rowid = ".$itemid;
	$res = $db->query($sql);
	if (!$res) {
		$results['success'] = false;
		$results['error'] = $langs->transnoentities('ErrorInEntryDeletion');
	} else {
		$results['success'] = true;
		$results['noted'] = $langs->transnoentities('gp_field_delete_success');
	}
}

// CLONE ITEM
if ($action == 'cloneitem') {

	$itemid = GETPOSTINT('itemid');
	$isModeVerif = GETPOSTINT('ismodeverif');
	$cloneAfter = GETPOSTINT('cloneafter');

	$newElementID = $gestionparc->cloneElement($parckey, $itemid, $cloneAfter);
	if ($newElementID <= 0) {
		$results['success'] = false;
		$results['error'] = $langs->transnoentities('ErrorCloneElement');
	} else {
		$results['success'] = true;
		$results['newElementID'] = $newElementID;

		// Fetch New Element
		$newElement = $gestionparc->fetchElement($parckey, $newElementID);
		$parkID = $gestionparc->fetch_parcType(0, 0, $parckey);

		// Define url
		$parkUrl = dol_buildpath('/gestionparc/tabs/gestionparc.php', 1);

		// Write tpl
		$parkItemClass = 'park-item item-open'; // Open by default
		if ($isModeVerif) {
			$parkItemClass .= ' unverified';
		}
		$results['newElement'] = '<div id="item-'.$newElement->rowid.'" class="'.$parkItemClass.'" data-itemid="'.$newElement->rowid.'" data-ismodeverif="'.($isModeVerif ? 1 : 0) .'" data-long-press-delay="600">';
			$results['newElement'] .= '<div class="park-item-header">';
				$results['newElement'] .= '<div>';
					$results['newElement'] .= '<span class="paddingright fas fa-grip-vertical opacitylow grabbable" style="padding-right:6px;"></span>';
					$results['newElement'] .= 'ID #'.$newElement->rowid;
					if ($isModeVerif && !$newElement->verif) {
						$results['newElement'] .= ' <span style="margin-left:4px;" class="text-warning"><span class="fas fa-exclamation-circle"></span></span>';
					}
				$results['newElement'] .= '</div>';
				$results['newElement'] .= '<div class="park-item-actions">';
					$results['newElement'] .= '<span class="fas fa-ellipsis-v icon-submenu"></span>';
					$results['newElement'] .= '<ul class="park-submenu-actions">';
						$results['newElement'] .= '<li><a class="item-action action-clone" data-cloneafter="1" href="'.$parkUrl.'?socid='.$newElement->socid.'&parctype='.$parckey.'&action=duplicate&itemid='.$newElement->rowid.'&parcid='.$parkID.'&token='.newToken().'"><span class="fas fa-clone paddingright"></span> '.$langs->trans('ToClone').'</a></li>';
						$results['newElement'] .= '<li><a class="item-action action-clone" data-cloneafter="0" href="'.$parkUrl.'?socid='.$newElement->socid.'&parctype='.$parckey.'&action=duplicateafter&itemid='.$newElement->rowid.'&parcid='.$parkID.'&token='.newToken().'"><span class="far fa-clone paddingright"></span> '.$langs->trans('gp_CloneAtEnd').'</a></li>';
						$results['newElement'] .= '<li class="separator"></li>';
						$results['newElement'] .= '<li><a class="item-action" href="'.$parkUrl.'?socid='.$newElement->socid.'&parctype='.$parckey.'&action=edit&itemid='.$newElement->rowid.'&parcid='.$parkID.'&token='.newToken().'#item-'.$newElement->rowid.'"><span class="fas fa-pencil-alt paddingright"></span> '.$langs->trans('Edit').'</a></li>';
						if ($user->hasRight('gestionparc', 'parc', 'delete') || $user->admin) {
							$results['newElement'] .= '<li><a class="item-action action-delete" href="'.$parkUrl.'?socid='.$newElement->socid.'&parctype='.$parckey.'&action=delete&itemid='.$newElement->rowid.'&parcid='.$parkID.'&token='.newToken().'"><span class="fas fa-trash-alt paddingright"></span> '.$langs->trans('Delete').'</a></li>';
						}
					$results['newElement'] .= '</ul>';
				$results['newElement'] .= '</div>';
			$results['newElement'] .= '</div>';
			$results['newElement'] .= '<div class="park-item-content">';
			foreach($gestionparc->fields as $parcfield_key => $parcfield){
				if ($parcfield->enabled) {
					if ($parcfield->only_verif && !$isModeVerif) {
						continue;
					}
					$results['newElement'] .= '<div class="park-field">';
						$results['newElement'] .= '<div class="park-field-label">'.$parcfield->label.'</div>';
						$results['newElement'] .= '<div class="park-field-value">';
						if (!empty($newElement->{$parcfield->field_key})) {
							if ($parcfield->type == 'prodserv') {
								if ((int) $newElement->{$parcfield->field_key} > 0) {
									$p = new Product($db);
									if ($p->fetch($newElement->{$parcfield->field_key})) {
										$results['newElement'] .= '<a href="'.dol_buildpath('product/card.php?id='.$newElement->{$parcfield->field_key}, 1).'" >'.$p->label.'</a>';
									} else {
										$results['newElement'] .= $langs->trans('gp_product_unknown');
									}
								}
							} else if ($parcfield->type == 'date') {
								if ($newElement->{$parcfield->field_key} != '0000-00-00') {
									$results['newElement'] .= dol_print_date($newElement->{$parcfield->field_key},'%d/%m/%Y');
								}
							} else {
								$results['newElement'] .= $newElement->{$parcfield->field_key};
							}
						} else {
							$results['newElement'] .= '<span class="opacitymedium">--</span>';
						}
						$results['newElement'] .= '</div>';
					$results['newElement'] .= '</div>';
				}
			}
			if ($isModeVerif) {
				$verifLink = $parkUrl.'?socid='.$newElement->socid.'&parctype='.$parckey.'&itemid='.$newElement->rowid.'&action=set_line_verify&parcid='.$parkID.'&token='.newToken();
				$results['newElement'] .= '<a class="button-verify unverified" href="'.$verifLink.'" >Vérifier</a>';
			}
			$results['newElement'] .= '</div>';
		$results['newElement'] .= '</div>';

		// Reset Order
		if ($cloneAfter) {
			$results['newElementPosition'] .= $newElement->position;
			$parcLines = $gestionparc->getSocParcContent($newElement->socid, $parckey);
			foreach ($parcLines as $lineID => $line) {
				if ((int) $line->position < $newElement->position || (int) $line->rowid == (int) $newElementID) {
					continue;
				}
				$gestionparc->setElementPosition($parckey, $line->rowid, (int) $line->position + 1);
			}
		}
	}
}

echo json_encode($results);