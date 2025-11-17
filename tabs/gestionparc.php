<?php
/*
 * Copyright (C) 2018 - 2023 Anthony Damhet - Progiseize <a.damhet@progiseize.fr>
 */

$res=0;
if (! $res && file_exists("../main.inc.php")) : $res=@include '../main.inc.php'; endif;
if (! $res && file_exists("../../main.inc.php")) : $res=@include '../../main.inc.php'; endif;
if (! $res && file_exists("../../../main.inc.php")) : $res=@include '../../../main.inc.php'; endif;

// Protection if external user
if ($user->socid > 0 || !$user->hasRight('gestionparc','parc','read')){
	accessforbidden();
}

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
dol_include_once('/gestionparc/class/gestionparc.class.php');

$langs->loadLangs(array('interventions', 'gestionparc@gestionparc'));

/*******************************************************************
* VARIABLES
********************************************************************/
$action = GETPOST('action');
$cancel =  GETPOST('cancel', 'alpha');
$socid  = GETPOST('socid', 'int');
if ((int) $socid <= 0) {
	header('Location:'.dol_buildpath('societe/list.php?restore_lastsearch_values=1', 1));
}

// Get object (Societe)
$object = new Societe($db);
$object->fetch($socid);

// Gestion parc
$gestionparc = new GestionParc($db);
$list_parctypes = $gestionparc->list_parcType(1, 1, $object->getCategoriesCommon('customer'));
$parctype = GETPOSTISSET('parctype') ? GETPOST('parctype', 'alphanohtml'): (!empty($list_parctypes) ? (reset($list_parctypes))['key'] : '');
$view = GETPOSTISSET('view') ? GETPOST('view', 'alpha') : 'cards';

$form = new Form($db);
if (isModEnabled('intervention')) {
	$ficheinter = new Fichinter($db);
	$verification = new GestionParcVerif($db);
}

// On vérifie si on est en mode verif
$isModeVerif = false;
$modeVerifID = $verification->isVerif($socid);
if ($modeVerifID && $modeVerifID > 0) {
	$isModeVerif = true;
} else if ($modeVerifID && $modeVerifID <= 0) {
	$error++; setEventMessages($langs->trans('gp_verif_error_twice'), null, 'warnings');
}

/*******************************************************************
* ACTIONS
********************************************************************/
if ($cancel) {
	header("Location: ".$_SERVER['PHP_SELF'].'?socid='.$object->id.'&parctype='.$parctype);
	exit;
}

switch ($action):

	// INIT CLOSE VERIF
	case 'initclose_verif':

		if (GETPOST('token') != $_SESSION['token']) {
			$error++;
			setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
		}
		if (empty(GETPOST('socid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings');
		}

		if (GETPOSTISSET('cancel_verif') && GETPOSTISSET('verif_id') && !$error) {
			if ($verification->cancelVerif(GETPOST('verif_id', 'int'))) {
				setEventMessages($langs->trans('gp_verif_success_oncancel'), null, 'mesgs'); $action=''; $isModeVerif = false;
				// Redirect to first item
				$gestionparc->fetch_parcType(0, 0, $parctype);
				$firstLines = $gestionparc->getSocParcContent($socid, $parctype);
				$firstItemId = !empty($firstLines) ? reset($firstLines)->rowid : '';
				$anchor = $firstItemId ? '#item-'.$firstItemId : '';
				header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&view='.$view.$anchor);
				exit;
			}
		}
		break;

	// CLOSE VERIF
	case 'close_verif':

		include_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
		include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

		if (GETPOST('token') != $_SESSION['token']) {
			$error++;
			setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
		}
		if (empty(GETPOST('socid'))) {
			$error++; setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings');
		}

		if (isset($verification->rowid)) {
			if ($verification->nb_verified != $verification->nb_total) {
				if (empty(GETPOST('intercom'))) {
					$error++; $action = 'initclose_verif';
					setEventMessages($langs->trans('gp_verif_error_needIntercom'), null, 'errors');
				}
			}

			$t2s = convertTime2Seconds(GETPOST('durationhour', 'int'), GETPOST('durationmin', 'int'));
			if ($t2s <= 0) {
				$error++; $action = 'initclose_verif';
				setEventMessages($langs->trans('gp_verif_error_needDuration'), null, 'errors');
			}

			if (!$error) {
				if (getDolGlobalInt('GESTIONPARC_ADVANCED_EXPORT')) {
					$id_intervention = $verification->advancedCloseVerif($socid, GETPOST('intercom', 'restricthtml'), $t2s);
				} else {
					$id_intervention = $verification->closeVerif($verification->rowid, $socid, GETPOST('intercom', 'restricthtml'), $t2s);
				}
				if ($id_intervention) {
					$ficheinter->fetch($id_intervention); $last_intervention = $id_intervention;
					$isModeVerif = false;
					setEventMessages($langs->trans('gp_verif_success_onclose', $ficheinter->ref), null, 'mesgs');
					if ($conf->global->MAIN_MODULE_GESTIONPARC_VERIFREDIRECT) {
						header('Location: '.dol_buildpath('fichinter/card.php?id='.$id_intervention, 1));
					} else {
						// Redirect to first item if not redirecting to intervention
						$gestionparc->fetch_parcType(0, 0, $parctype);
						$firstLines = $gestionparc->getSocParcContent($socid, $parctype);
						$firstItemId = !empty($firstLines) ? reset($firstLines)->rowid : '';
						$anchor = $firstItemId ? '#item-'.$firstItemId : '';
						header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&view='.$view.$anchor);
						exit;
					}
				} else {
					setEventMessages($langs->trans('gp_verif_error_onclose'), null, 'errors');
				}
			}
		}
		break;

	// Activer le mode verifiation
	case 'mode_verif':

		//$_SESSION['verif_'.$socid] = 'active';
		if (GETPOST('token') != $_SESSION['token']) {
			$error++;
			setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
		}
		if (empty(GETPOST('socid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings');
		}

		if (!$error) {
			if ($verif_id = $verification->openVerif($socid)) {
				$modeVerifID = $verif_id;
				// Get first item ID to anchor
				$gestionparc->fetch_parcType(0, 0, $parctype);
				$firstLines = $gestionparc->getSocParcContent($socid, $parctype);
				$firstItemId = !empty($firstLines) ? reset($firstLines)->rowid : '';
				$anchor = $firstItemId ? '#item-'.$firstItemId : '';
				header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&view='.$view.$anchor);
				exit;
				$isModeVerif = true;
				$verification->fetch($verif_id);
			}
		}
		break;

	// VERIF ALL LINES
	case 'verifall_confirm':

		if (!$user->admin) {
			$error++;
			setEventMessages($langs->trans('NotEnoughPermissions'), null, 'warnings');
		}
		if (GETPOST('token') != $_SESSION['token']) {
			$error++;
			setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
		}
		if (empty(GETPOST('socid'))) {
			$error++; setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings');
		}
		if (empty(GETPOST('parcid'))) {
			$error++; setEventMessages($langs->trans('gp_error_needTypeId'), null, 'warnings');
		}

		if (!$error) {
			$gestionparc->fetch_parcType(GETPOST('parcid'));
			$verification->setParcCheck($socid,$gestionparc->parc_key,1,$verification->rowid);
			// Get first item ID to anchor
			$firstLines = $gestionparc->getSocParcContent($socid, $gestionparc->parc_key);
			$firstItemId = !empty($firstLines) ? reset($firstLines)->rowid : '';
			$anchor = $firstItemId ? '#item-'.$firstItemId : '';
			header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&view='.$view.$anchor);
			exit;
		}
		break;

	// VERIFICATION LIGNE
	case 'set_line_verify':

		if (GETPOST('token') != $_SESSION['token']) {
			$error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
		}
		if (empty(GETPOST('socid'))) {
			$error++; setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings');
		}
		if (empty(GETPOST('itemid'))) {
			$error++; setEventMessages($langs->trans('gp_error_needItemId'), null, 'warnings');
		}
		if (empty(GETPOST('parcid'))) {
			$error++; setEventMessages($langs->trans('gp_error_needTypeId'), null, 'warnings');
		}

		if (!$error) {
			$gestionparc->fetch_parcType(GETPOST('parcid'));
			if ($verification->setLineCheck($socid, $gestionparc->parc_key, GETPOST('itemid'), 1, $verification->rowid)) {
				setEventMessages($langs->trans('gp_verifline_success'), null, 'mesgs');
				// Redirect to keep position
				header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&view='.$view.'#item-'.GETPOST('itemid'));
				exit;
			} else {
				$error++; setEventMessages($langs->trans('gp_error'), null, 'warnings');
			}
		}
		break;

	// COOKIE
	case 'set_cookie_parc':

		// SI LE COOKIE EXISTE
		if (isset($_COOKIE['gestionparc_empty_views'])) {

			$cookie_val = json_decode($_COOKIE['gestionparc_empty_views']);
			$cookie_val = (array) $cookie_val;

			if (GETPOSTISSET('view_empty_parc') && !in_array($socid, $cookie_val) ) {
				array_push($cookie_val, $socid);
				$cookie_val = json_encode($cookie_val);
				setcookie('gestionparc_empty_views', $cookie_val, time()+(60*60*24*30));

			} else {
				if (($key = array_search($socid, $cookie_val)) !== false) {
					unset($cookie_val[$key]);
				}
				$cookie_val = json_encode($cookie_val);
				setcookie('gestionparc_empty_views', $cookie_val, time()+(60*60*24*30));
			}
			header('Location:'.$_SERVER["PHP_SELF"].'?socid='.$socid);

		// SI LE COOKIE N'EXISTE PAS ON LE CREE
		} else  {
			$cookie_val = array('0',$socid);
			$cookie_val = json_encode($cookie_val);
			setcookie('gestionparc_empty_views', $cookie_val, time() + (60 * 60 * 24 * 30));
			header('Location:'.$_SERVER["PHP_SELF"].'?socid='.$socid);
		}
		break;

	// AJOUTER
	case 'add':

		//var_dump($_POST); // ON VERIFIE LES CHAMPS
		if (GETPOST('token') != $_SESSION['token']) {
			$error++;
			setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
		}
		if (empty(GETPOST('socid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings');
		}
		if (empty(GETPOST('parcid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needTypeId'), null, 'warnings');
		}

		if (!$error) {
			$gestionparc->fetch_parcType(GETPOST('parcid'));

			// ON VERIFIE LES CHAMPS DYNAMIQUES
			foreach ($gestionparc->fields as $parcfield) {
				if ($parcfield->enabled || $parcfield->type == 'autonumber') {

					// autonumber even if disabled
					if ($parcfield->type == 'autonumber' && !$parcfield->enabled) {
						$nextnum = $parcfield->getNextAutoNumber($socid, $gestionparc->parc_key, $parcfield->field_key);
						$_POST['gpfield_'.$parcfield->field_key] = $nextnum;
					}
					//
					if ($parcfield->only_verif && $parcfield->required) {
						if ($isModeVerif  && empty(GETPOST('gpfield_'.$parcfield->field_key))) {
							$error++;
							setEventMessages($langs->trans('ErrorFieldRequired', $parcfield->label), null, 'warnings');
						}
					// ON VERIFIE SI LES CHAMPS OBLIGATOIRES SONT REMPLIS
					} else if ($parcfield->required && empty(GETPOST('gpfield_'.$parcfield->field_key))) {
						$error++;
						setEventMessages($langs->trans('ErrorFieldRequired', $parcfield->label), null, 'warnings');
					}

					// ON VERIFIE SI AUTONUMBER -> non attribué
					if ($parcfield->type == 'autonumber' && !$error) {
						$checknum = $parcfield->checkAutoNumber($socid, $gestionparc->parc_key, $parcfield->field_key, GETPOST('gpfield_'.$parcfield->field_key));
						if (!$checknum) {
							$error++;
							setEventMessages($langs->trans('gp_error_autonumber_exist'), null, 'warnings');
						}
					}
				}
			}

			// SI PAS D'ERREUR ON CONTINUE
			if (!$error) {
				$db->begin();

				// Get Next position
				$sqlpos = "SELECT MAX(position) as maxpos FROM ".MAIN_DB_PREFIX."gestionparc__".$gestionparc->parc_key."";
				$sqlpos .= " WHERE socid = ".$object->id;
				$respos = $db->query($sqlpos);
				$objpos = $db->fetch_object($respos);

				$previouspos = !is_null($objpos->maxpos) ? (int) $objpos->maxpos : 0;
				$nextpos = $previouspos + 1;

				$sql_insert = "INSERT INTO ".MAIN_DB_PREFIX."gestionparc__".$gestionparc->parc_key." (socid, author, position";
				foreach ($gestionparc->fields as $parcfield) {
					if ($parcfield->enabled || $parcfield->type == 'autonumber') {
						$sql_insert .= ", ".$parcfield->field_key;
					}
				}
				$sql_insert .= ") VALUES (".GETPOST('socid', 'int').", ".$user->id.", ".$nextpos;
				foreach ($gestionparc->fields as $parcfield) {
					if ($parcfield->enabled || $parcfield->type == 'autonumber') {
						$sql_insert .= ", '".$db->escape(GETPOST('gpfield_'.$parcfield->field_key))."'";
					}
				}
				$sql_insert .= ")";

				$result_insert = $db->query($sql_insert);

				if ($result_insert) {
					$newItemId = $db->last_insert_id(MAIN_DB_PREFIX."gestionparc__".$gestionparc->parc_key);
					$db->commit();
					setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
					header('Location:'.dol_buildpath('/gestionparc/tabs/gestionparc.php?socid='.$object->id.'&parctype='.$parctype.'&view='.$view.'#item-'.$newItemId, 1));
					exit;
				} else {
					$error++; setEventMessages($langs->trans('gp_error'), null, 'warnings');
					$db->rollback();
				}
			} else {
				$action = 'additem';
			}
		} else {
			$action = 'additem';
		}
		break;

	// DUPLICATA
	case 'duplicate':

		// ON VERIFIE LES CHAMPS
		if (GETPOST('token') != $_SESSION['token']) {
			$error++;
			setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
		}
		if (empty(GETPOST('socid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings');
		}
		if (GETPOSTINT('parcid') <= 0) {
			$error++;
			setEventMessages($langs->trans('gp_error_needTypeId'), null, 'warnings');
		}
		if (GETPOSTINT('itemid') <= 0) {
			$error++;
			setEventMessages($langs->trans('gp_error_needItemId'), null, 'warnings');
		}

		if (!$error) {
			$after = GETPOSTINT('after');
			$gestionparc->fetch_parcType(GETPOSTINT('parcid'));
			$newElementID = $gestionparc->cloneElement($gestionparc->parc_key, GETPOSTINT('itemid'), $after);
			if ($newElementID > 0) {
				setEventMessages($langs->trans('gp_duplicate_success'), null, 'mesgs');
				// Redirect to new cloned element
				header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&view='.$view.'#item-'.$newElementID);
				exit;
			} else {
				$error++;
				setEventMessages($langs->trans('gp_error'), null, 'warnings');
			}
		}
		break;

	// SUPPRESSION
	case 'confirm_delete':

		$error = 0;

		if (GETPOST('token') != $_SESSION['token']) {
			$error++;
			setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
		}
		if (empty(GETPOST('socid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings');
		}
		if (empty(GETPOST('itemid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needItemId'), null, 'warnings');
		}
		if (empty(GETPOST('parcid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needTypeId'), null, 'warnings');
		}
		if (GETPOST('confirm') != 'yes') {
			$error++;
			setEventMessages($langs->trans('gp_error_needActionConfirm'), null, 'warnings');
		}

		if (!$error) {
			$gestionparc->fetch_parcType(GETPOST('parcid'));

			$db->begin();
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."gestionparc__".$gestionparc->parc_key;
			$sql .= " WHERE rowid=".GETPOST('itemid')." AND socid=".GETPOST('socid');

			$result = $db->query($sql);
			if (!$result) {
				$error++; setEventMessages($langs->trans('gp_error'), null, 'warnings');
				$db->rollback();
			} else {
				setEventMessages($langs->trans('gp_field_delete_success'), null, 'mesgs');
				$db->commit();
				// Redirect after delete (without anchor)
				header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&view='.$view);
				exit;
			}
		}
		break;

	// EDITION
	case 'edit':

		$editItem_id = 0;
		$error = 0;

		// ON VERIFIE LES CHAMPS
		if (GETPOST('token') != $_SESSION['token']) {
			$error++;
			setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings'); $action = '';
		}
		if (empty(GETPOST('socid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings');
		}
		if (empty(GETPOST('itemid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needItemId'), null, 'warnings');
		}
		if (empty(GETPOST('parcid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needTypeId'), null, 'warnings');
		}

		if (!$error) {
			$editItem_id = GETPOST('itemid');
		}
		break;

	// EDITION
	case 'edit_item':

		$error = 0;

		if (GETPOST('token') != $_SESSION['token']) {
			$error++;
			setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
		}
		if (empty(GETPOST('socid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings');
		}
		if (empty(GETPOST('itemid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needItemId'), null, 'warnings');
		}
		if (empty(GETPOST('parcid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needTypeId'), null, 'warnings');
		}

		if (!$error) :
			$gestionparc->fetch_parcType(GETPOST('parcid'));

			// ON VERIFIE LES CHAMPS DYNAMIQUES
			foreach ($gestionparc->fields as $parcfield) {
				if ($parcfield->enabled) {
					//
					if ($parcfield->only_verif && $parcfield->required) {
						if ($isModeVerif  && empty(GETPOST('gpfield_'.$parcfield->field_key))) {
							$error++;
							setEventMessages($langs->trans('ErrorFieldRequired', $parcfield->label), null, 'warnings');
						}
					// ON VERIFIE SI LES CHAMPS OBLIGATOIRES SONT REMPLIS
					} else if ($parcfield->required && empty(GETPOST('gpfield_'.$parcfield->field_key))) {
						$error++;
						setEventMessages($langs->trans('ErrorFieldRequired', $parcfield->label), null, 'warnings');
					}
				}
			}
		endif;

		// SI IL Y A DES ERREURS, ON RESTE EN MODE EDITION
		if ($error) {
			$action = 'edit';
			$editItem_id = GETPOST('itemid');
		} else {
			$db->begin();

			$sql_update = "UPDATE ".MAIN_DB_PREFIX."gestionparc__".$gestionparc->parc_key;
			$sql_update .= " SET author_maj = '".$user->id."'";
			foreach ($gestionparc->fields as $parcfield) {
				if ($parcfield->enabled) {
					$sql_update .= ", ".$parcfield->field_key." = '".$db->escape(GETPOST('gpfield_'.$parcfield->field_key))."'";
				}
			}
			$sql_update .= " WHERE rowid = '".GETPOST('itemid')."' AND socid = '".$socid."'";

			$result = $db->query($sql_update);
			if ($result) {
				$db->commit(); $action = '';
				setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
				foreach ($gestionparc->fields as $parcfield) {
					unset($_POST['gpfield_'.$parcfield->field_key]);
				}
				// Redirect to keep position
				header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&view='.$view.'#item-'.GETPOST('itemid'));
				exit;
			} else {
				$error++; setEventMessages($langs->trans('gp_error'), null, 'warnings');
				$db->rollback();
			}
		}
		break;

endswitch;


/*******************************************************************
* PREPARATION DES DONNEES
********************************************************************/
$tabs = array(); $nb_tabs = 0; $abc = '';
if (!empty($list_parctypes)) {
	foreach($list_parctypes as $parctype_key => $parctype_infos) {

		$gestionparc->fetch_parcType($parctype_key);
		$show_parc = true;

		if ($parctype == $parctype_infos['key']) {
			$keyparc = $parctype_key;
		}

		// ON VERIFIE SI ON PEUT AFFICHER LE PARC EST ACTIF
		if (!$gestionparc->enabled) {
			$show_parc = false;
		}

		// ON VERIFIE S'IL CONTIENT DES CHAMPS
		if (empty($gestionparc->fields)) {
			$show_parc = false;
		} else {
			$nbEnabled = 0;
			foreach ($gestionparc->fields as $field) {
				if ($field->enabled && !$field->only_verif) {
					$nbEnabled++;
				}
			}
			if ($nbEnabled == 0) {
				$show_parc = false;
			}
		}

		// SI ON PEUT AFFICHER
		if ($show_parc) {
			// ON COMPTE LES LIGNES
			$nb_lines = $gestionparc->getSocParcCount($object->id, $gestionparc->parc_key);

			$color_class = '';

			// SI ON EST EN MODE VERIF
			if ($isModeVerif) {
				$nb_verifs = $gestionparc->getSocParcCount($object->id, $gestionparc->parc_key, true);

				if (intval($nb_verifs) > 0) {
					if (intval($nb_verifs) == intval($nb_lines)) {
						$color_class = 'badge-success';
					} else {
						$color_class = 'badge-warning';
					}
				} else {
					if (intval($nb_lines) == 0) {
						$color_class = 'badge-success';
					} else {
						$color_class = 'badge-danger';
					}
				}

				$label_count = $nb_verifs.' / '.$nb_lines;
			} else {
				$label_count = $nb_lines;
			}


		// ON AJOUTE LE LIEN
		$tabs[$nb_tabs][0] = $_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype_infos['key'].'&view='.$view.'#parc-content'; //dol_buildpath("/gestionparc/admin/manager.php", 1);
		$tabs[$nb_tabs][1] = $parctype_infos['label'].' <span class="badge marginleftonlyshort '.$color_class.'">'.$label_count.'</span>'; // $langs->trans("gp_options_tab_manager");
		$tabs[$nb_tabs][2] = $parctype_infos['key']; // key
		$nb_tabs++;
	}
}
}if ($keyparc) {
	$parc = $gestionparc->fetch_parcType($keyparc, true);
	$parcLines = $gestionparc->getSocParcContent($object->id, $parc->parc_key);
}


/***************************************************
* VIEW
****************************************************/
$array_js = array(
	'/gestionparc/assets/js/longpress.min.js',
	'/gestionparc/assets/js/jquery.ui.touch-punch.min.js',
	'/gestionparc/assets/js/gestionparc.js',
);
$array_css = array(
	'/gestionparc/assets/css/gestionparc.css',
);
if ($view == 'list') {
	$array_css[] = '/gestionparc/assets/css/dolpgs.css';
}

llxHeader('', $object->name.' - '.$langs->trans('gp_clientparc'), '', '', '', '', $array_js, $array_css, '', 'mod-gestionparc page-socparc');

// Form Confirm
$formconfirm = '';
if ($action == 'initclose_verif') {
	$formarray = array();
	if (!getDolGlobalInt('MAIN_MODULE_GESTIONPARC_VERIFUSETIME')) {
		$listHours = array('0','1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23');
		$listMins = array('00','05','10','15','20','25','30','35','40','45','50','55');
		$formDuration = '<label class="park-dialog-label">'.$langs->trans('InterDuration').' <span class="required">*</span></label>';
		$formDuration .= $form->selectarray('durationhour', $listHours, $id = '', $show_empty = 0, $key_in_label = 0, $value_as_key = 1, $moreparam = '', $translate = 0, $maxlen = 0, $disabled = 0, $sort = '', $morecss = 'minwidth75').' H';
		$formDuration .= $form->selectarray('durationmin', $listMins, $id = '', $show_empty = 0, $key_in_label = 0, $value_as_key = 1, $moreparam = '', $translate = 0, $maxlen = 0, $disabled = 0, $sort = '', $morecss = 'minwidth75').' mn';
		$formarray[] = array('type'=> 'onecolumn', 'name'=> 'durationhour', 'value' => '');
		$formarray[] = array('type'=> 'onecolumn', 'name'=> 'durationmin', 'value' => $formDuration);
	} else {
		$formarray[] = array('type'=> 'hidden', 'name'=> 'durationhour', 'value' => getDolGlobalInt('MAIN_MODULE_GESTIONPARC_VERIFUSETIME'));
		$formarray[] = array('type'=> 'hidden', 'name'=> 'durationmin', 'value' => 0);
	}

	$formComment = '<label class="park-dialog-label">';
	$formComment .= $langs->trans('gp_verifcom');
	if ($verification->nb_verified != $verification->nb_total) {
		$formComment .= '<span class="required">*</span>';
	}
	$formComment .= '</label>';
	$formComment .= '<textarea name="intercom" id="intercom" style="width:100%;resize:vertical;min-height:200px;">'.GETPOST('intercom', 'alphanohtml').'</textarea>';

	$formarray[] = array('type'=> 'onecolumn', 'name'=> 'intercom', 'value' => $formComment);

	$urlformconfirm = $_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype.'&view='.$view;
	$formconfirm = $form->formconfirm($urlformconfirm, $langs->transnoentities('gp_verif_close'), '', 'close_verif', $formarray, '', 1, 500, 500, 0, $langs->transnoentities('gp_verif_close'), $langs->transnoentities('Cancel'));
}
if ($action == 'delete') {
	$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&view='.$view.'&itemid='.GETPOST('itemid').'&parcid='.GETPOST('parcid'), $langs->trans('gp_confirmDeleteTitle'), $langs->trans('gp_confirmDelete'), 'confirm_delete', '', '', 1, 0, 500, 0);
}
if ($action == 'verifall') {
	$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&view='.$view.'&parcid='.GETPOST('parcid'), $langs->trans('gp_verifall'), $langs->trans('gp_confirmVerifAll'), 'verifall_confirm', '', '', 1, 0, 500, 0);
}
if ($action == 'additem') {

	$formarray = array();
	$formarray[] = array('type'=> 'hidden', 'name'=> 'parcid', 'value' => $parc->rowid);
	$formheight = 200;
	foreach ($parc->fields as $parcfield_key => $parcfield) {
		if ($parcfield->enabled) {
			if ($parcfield->only_verif && !$isModeVerif) {
				continue;
			}
			$formheight += 80;
			$outputform = '<label class="park-dialog-label">'.$parcfield->label.($parcfield->required ? ' <span class="required">*</span>' : '').'</label>';
			$outputform .= $parcfield->construct_field($parc, $object->id);
			$formarray[] = array('type'=> 'onecolumn', 'name'=> 'gpfield_'.$parcfield->field_key, 'value' => $outputform);
		}
	}
	$urlformconfirm = $_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype.'&view='.$view;
	$formconfirm = $form->formconfirm($urlformconfirm, $langs->transnoentities('gp_parcfield_addItem'), '', 'add', $formarray, '', 1, $formheight, 500, 0, $langs->transnoentities('Add'), $langs->transnoentities('Cancel'));
}
print $formconfirm;

// AFFICHAGE DES ONGLETS THIRDPARTY
$head = societe_prepare_head($object, $user);
echo dol_get_fiche_head($head, 'gestionparc', $langs->trans("ThirdParty"), -1, 'company');

print '<div class="park-main-wrapper">';

	//
	$linkback = '<a href="'.DOL_URL_ROOT.'/societe/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
	dol_banner_tab($object, 'socid', $linkback, ($user->socid ? 0 : 1), 'rowid', 'nom');

	//
	print '<div class="fichecenter">';
		print '<div class="fichehalfleft">';
			print '<div class="underbanner clearboth"></div>';
			print '<table class="border tableforfield centpercent">';
				print '<tbody>';
					print '<tr>';
						print '<td class="titlefieldmiddle">'.$langs->trans('NatureOfThirdParty').'</td>';
						print '<td>'.$object->getTypeUrl(1).'</td>';
					print '</tr>';
					if ($object->client) {
						print '<tr>';
							print '<td>'.$langs->trans('CustomerCode').'</td>';
							print '<td>';
								print showValueWithClipboardCPButton(dol_escape_htmltag($object->code_client));
								$tmpcheck = $object->check_codeclient();
								if ($tmpcheck != 0 && $tmpcheck != -5) {
									print ' <span class="error">('.$langs->trans("WrongCustomerCode").')</span>';
								}
							print '</td>';

						print '</tr>';
					}
					if (((isModEnabled("fournisseur") && $user->hasRight('fournisseur', 'lire') && !getDolGlobalString('MAIN_USE_NEW_SUPPLIERMOD')) || (isModEnabled("supplier_order") && $user->hasRight('supplier_order', 'lire')) || (isModEnabled("supplier_invoice") && $user->hasRight('supplier_invoice', 'lire'))) && $object->fournisseur) {
						print '<tr>';
							print '<td>'.$langs->trans('SupplierCode').'</td>';
							print '<td>';
								print showValueWithClipboardCPButton(dol_escape_htmltag($object->code_fournisseur));
								$tmpcheck = $object->check_codefournisseur();
								if ($tmpcheck != 0 && $tmpcheck != -5) {
									print ' <span class="error">('.$langs->trans("WrongSupplierCode").')</span>';
								}
							print '</td>';
						print '</tr>';
					}
				print '</tbody>';
			print '</table>';
		print '</div>';
		print '<div class="fichehalfright">';
			print '<div class="underbanner clearboth"></div>';
			print '<table class="border tableforfield centpercent">';
				print '<tbody>';
				if (isModEnabled('intervention') && getDolGlobalInt('MAIN_MODULE_GESTIONPARC_USEVERIF')) {
					// todo: link intervention with table element_element
					$last_intervention = $verification->getLastVerif($socid);
					if ($last_intervention) {
						$ficheinter->fetch($last_intervention);
						print '<tr>';
							print '<td>'.$langs->trans('gp_client_lastverif').'</td>';
							print '<td><a href="'.dol_buildpath('fichinter/card.php?id='.$ficheinter->id, 1).'">'.$ficheinter->ref.'</a></td>';
						print '</tr>';
					}
					//
					if ($user->hasRight('gestionparc', 'parc', 'verif') || $user->admin) {
						print '<tr>';
							print '<td valign="middle">'.$langs->trans('gp_client_verifmode').'</td>';
							print '<td>';
							if ($isModeVerif) {
								print '<form action="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype.'" method="POST">';
									print '<input type="hidden" name="token" value="'.newToken().'">';
									print '<input type="hidden" name="verif_id" value="'.$modeVerifID.'">';
									print '<input type="hidden" name="action" value="initclose_verif">';
									print '<input type="hidden" name="view" value="'.$view.'">';
									print '<input type="submit" name="close" value="'.$langs->trans('gp_verif_close').'" class="button smallpaddingimp small nomarginleft">';
									print '<input type="submit" name="cancel_verif" value="'.$langs->trans('gp_verif_cancel').'" class="button butActionDelete cancel smallpaddingimp small nomarginleft" >';
								print '</form>';
							} else {
								print '<a class="button smallpaddingimp small nomarginleft" href="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype.'&view='.$view.'&action=mode_verif&token='.newToken().'">'.$langs->trans('gp_verif_open').'</a>';
							}
							print '</td>';
						print '</tr>';
					}
				}
				print '</tbody>';
			print '</table>';
		print '</div>';
	print '</div>';
	print '<div class="clearboth"></div>';

	/***********************************/
	// Tabs
	if (!empty($tabs)) {
		$limit = 5;
		$typeview = dolGetButtonTitle($langs->trans('GestionParcListView'), '', 'fas fa-bars', $_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&view=list#parc-content', '', ($view == 'list') ? 2 : 1);
		$typeview .= dolGetButtonTitle($langs->trans('GestionParcCardView'), '', 'fas fa-th-large', $_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&view=cards#parc-content', '', ($view == 'cards') ? 2 : 1);
		print dol_fiche_head($tabs, $parctype, '', -1, '', 0, $typeview, '', $limit);
} else {
	print '<div class="warning">'.$langs->trans('gp_empty_parclist_message').'</div>';
}

if ((int) $gestionparc->rowid > 0) {

	// Anchor point for tab navigation
	print '<div id="parc-content"></div>';

	// New item
	print '<div id="park-add-item">';
		print '<a href="'.$_SERVER['PHP_SELF'].'?socid='.$object->id.'&parctype='.$parctype.'&view='.$view.'&action=additem&token='.newToken().'"><span class="fas fa-plus"></span></a>';
	print '</div>';		// Count verified lines
		if (getDolGlobalInt('MAIN_MODULE_GESTIONPARC_USEVERIF') && isModEnabled('intervention') && $isModeVerif) {
			$nb_verified = 0;
			if (!empty($parcLines)) {
				foreach($parcLines as $lineid => $linecontent) {
					if ($linecontent->verif): $nb_verified++; endif;
				}
			}
		}
		// Description
		if (!empty($parc->description)) {
			print '<div class="justify opacitymedium" style="margin-bottom: 16px;">';
				print img_info('').' '.$parc->description;
			print '</div>';
		}

		if ($isModeVerif && ($user->hasRight('gestionparc', 'parc', 'verifall') || $user->admin) && !empty($parcLines) && $nb_verified < count($parcLines)) {
			print '<div class="gestionparc-verifall">';
				print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?socid='.$object->id.'&parctype='.$parctype.'&parcid='.$parc->rowid.'&view='.$view.'&action=verifall&token='.newToken().'">'.img_picto($langs->trans("gp_verifall"), 'switch_off').'</a> <span class="veriftxt">'.$langs->trans('gp_verifall').'</span>';
			print '</div>';
		}

		if ($view == 'cards') {
			print '<div class="park-item-wrapper">';
			foreach ($parcLines as $lineid => $linecontent) {
				// Item
				$parkItemClass = 'park-item item-open'; // Open by default
				if ($isModeVerif) {
					$parkItemClass .= ($linecontent->verif ? ' verified' : ' unverified');
				}
				if ($action == 'edit' && $editItem_id == $linecontent->rowid) {
					$parkItemClass .= ' editing';
				}
				print '<div id="item-'.$linecontent->rowid.'" class="'.$parkItemClass.'" data-itemid="'.$linecontent->rowid.'" data-ismodeverif="'.($isModeVerif ? 1 : 0) .'" data-long-press-delay="600">';
					// Item header
					print '<div class="park-item-header">';
						print '<div>';
							print '<span class="paddingright fas fa-grip-vertical opacitylow grabbable" style="padding-right:6px;"></span>';
							print 'ID #'.$linecontent->rowid;
							if ($isModeVerif && $linecontent->verif) {
								print ' <span style="margin-left:4px;" class="text-success"><span class="fas fa-check-circle"></span></span>';
							} else if ($isModeVerif && !$linecontent->verif) {
								print ' <span style="margin-left:4px;" class="text-warning"><span class="fas fa-exclamation-circle"></span></span>';
							}
						print '</div>';
						print '<div class="park-item-actions">';
						if ($action != 'edit' || $action == 'edit' && $editItem_id != $linecontent->rowid) {
							print '<span class="fas fa-ellipsis-v icon-submenu"></span>';
							print '<ul class="park-submenu-actions">';
								print '<li><a class="item-action action-clone" data-cloneafter="1" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&action=duplicate&after=1&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&token='.newToken().'"><span class="fas fa-clone paddingright"></span> '.$langs->trans('ToClone').'</a></li>';
								print '<li><a class="item-action action-clone" data-cloneafter="0" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&action=duplicate&after=0&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&token='.newToken().'"><span class="far fa-clone paddingright"></span> '.$langs->trans('gp_CloneAtEnd').'</a></li>';
								print '<li class="separator"></li>';
								print '<li><a class="item-action" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&action=edit&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&token='.newToken().'#item-'.$linecontent->rowid.'"><span class="fas fa-pencil-alt paddingright"></span> '.$langs->trans('Edit').'</a></li>';
								if ($user->hasRight('gestionparc', 'parc', 'delete') || $user->admin) {
									print '<li><a class="item-action action-delete" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&action=delete&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&token='.newToken().'"><span class="fas fa-trash-alt paddingright"></span> '.$langs->trans('Delete').'</a></li>';
								}
							print '</ul>';
						}
						print '</div>';
					print '</div>';
					// Item content
					print '<div class="park-item-content">';

					if ($action == 'edit' && $editItem_id == $linecontent->rowid) {
						print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype.'">';
						print '<input type="hidden" name="action" value="edit_item">';
						print '<input type="hidden" name="token" value="'.newToken().'">';
						print '<input type="hidden" name="parcid" value="'.$parc->rowid.'">';
						print '<input type="hidden" name="itemid" value="'.$linecontent->rowid.'">';
					}
					foreach($parc->fields as $parcfield_key => $parcfield){
						if ($parcfield->enabled) {
							if ($parcfield->only_verif && !$isModeVerif) {
								continue;
							}
							print '<div class="park-field">';
								print '<div class="park-field-label">'.$parcfield->label.'</div>';
								print '<div class="park-field-value">';
								if ($action == 'edit' && $editItem_id == $linecontent->rowid) {
									print $parcfield->construct_field($parc, $object->id, $linecontent->{$parcfield->field_key});
								} else {
									if (!empty($linecontent->{$parcfield->field_key})) {
										if ($parcfield->type == 'prodserv') {
											if ((int) $linecontent->{$parcfield->field_key} > 0) {
												$p = new Product($db);
												if ($p->fetch($linecontent->{$parcfield->field_key})) {
													print '<a href="'.dol_buildpath('product/card.php?id='.$linecontent->{$parcfield->field_key}, 1).'" >'.$p->label.'</a>';
												} else {
													print $langs->trans('gp_product_unknown');
												}
											}
										} else if ($parcfield->type == 'date') {
											if ($linecontent->{$parcfield->field_key} != '0000-00-00') {
												print dol_print_date($linecontent->{$parcfield->field_key},'%d/%m/%Y');
											}
										} else {
											print $linecontent->{$parcfield->field_key};
										}
									} else {
										print '<span class="opacitymedium">--</span>';
									}
								}
								print '</div>';
							print '</div>';
						}
					}
					// Buttons
					if ($action == 'edit' && $editItem_id == $linecontent->rowid) {
						print '<div class="button-sets">';
							print '<a class="button-edit button-cancel" href="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype.'&view='.$view.'#item-'.$linecontent->rowid.'"><span class="fas fa-times"></span></a>';
							print '<button class="button-edit button-valid" type="submit"><span class="fas fa-check"></span></button>';
						print '</div>';
						print '</form>';
					} else if ($isModeVerif && $linecontent->verif) {
						print '<div class="button-verify verified" >Élement vérifié</div>';
					} else if ($isModeVerif && !$linecontent->verif) {
						print '<a class="button-verify unverified js-verify-item" href="#" data-itemid="'.$linecontent->rowid.'" data-parcid="'.$parc->rowid.'" data-socid="'.$object->id.'" data-parctype="'.$parctype.'">Vérifier</a>';
					}
					print '</div>';
					// Item footer
				print '</div>';
			}
			print '</div>';
		} else if ($view == 'list') {

			print '<form enctype="multipart/form-data" action="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype.'" method="POST" id="">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="parcid" value="'.$parc->rowid.'">';
				print '<input type="hidden" name="view" value="'.$view.'">';
				print '<table class="dolpgs-table gestionparc-table" style="border-top:none;" id="gestionparc-table-'.$gestionparc->rowid.'">';
				print '<tbody>';

					// Columns name
					print '<tr class="dolpgs-thead noborderside">';
					if ($isModeVerif) {
						print '<th>'.$langs->trans('gp_verif_label').'</th>';
					}
					foreach ($parc->fields as $parcfield_key => $parcfield) {
						if ($parcfield->enabled) {
							if ($parcfield->only_verif && !$isModeVerif) {
								continue;
							}
							print '<th>'.$parcfield->label.($parcfield->required ? ' <span class="required">*</span>' : '').'</th>';
						}
					}
					print '<th class="right"></th>';
					print '</tr>';

					//
					foreach($parcLines as $lineid => $linecontent){
						print '<tr id="item-'.$linecontent->rowid.'" class="dolpgs-tbody gestionparc-line '.(($isModeVerif && $linecontent->verif) ? 'parcline-ok' : '').'" data-itemid="'.$linecontent->rowid.'">';

						if ($isModeVerif) {
							print '<td class="verify-cell">';
							if ($action != "edit" || $action == "edit" && $editItem_id != $linecontent->rowid) {
								if ($linecontent->verif) {
									echo img_picto($langs->trans("Activated"), 'check-square');
								} else {
									echo '<a class="reposition js-verify-item-list" href="#" data-itemid="'.$linecontent->rowid.'" data-parcid="'.$parc->rowid.'" data-socid="'.$object->id.'" data-parctype="'.$parctype.'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
								}
							}
							print '</td>';
						}

						foreach($parc->fields as $parcfield_key => $parcfield){
							if ($parcfield->enabled) {
								if ($parcfield->only_verif && !$isModeVerif) {
									continue;
								}
								print '<td class="pgsz-optiontable-fielddesc">';

								if ($action == 'edit' && $editItem_id == $linecontent->rowid) {
									print '<span class="gp-infos-label">'.$parcfield->label.' : </span>';
									if ($parcfield->type == 'autonumber') {
										print $linecontent->{$parcfield->field_key};
										print '<input type="hidden" name="gpfield_'.$parcfield->field_key.'" id="gpfield_'.$parcfield->field_key.'" value="'.$linecontent->{$parcfield->field_key}.'">';
									} else {
										print $parcfield->construct_field($parc, $societe->id, $linecontent->{$parcfield->field_key});
									}
								} else {
									echo '<span class="gp-infos-label">'.$parcfield->label.' : </span>';
									if ($parcfield->type == 'prodserv') {
										if (!empty($linecontent->{$parcfield->field_key})) {
											$prodserv = new Product($db);
											$check_prodserv = $prodserv->fetch($linecontent->{$parcfield->field_key});
											if ($check_prodserv) {
												//print '<a href="'.dol_buildpath('product/card.php?id='.$linecontent->{$parcfield->field_key}, 1).'" >'.$prodserv->label.'</a>';
												print $prodserv->getNomUrl(1, '', 0, -1, 0, '', 1);
											} else {
												print $langs->trans('gp_product_unknown');
											}
										}
									} else if ($parcfield->type == 'dblist') {
										if (!empty($linecontent->{$parcfield->field_key})) {
											$l_content = $parc->getContentForDbList($linecontent->{$parcfield->field_key}, $parcfield->params);
											if ($l_content) {
												print $l_content;
											}
										}
									} else if ($parcfield->type == 'date') {
										if (!empty($linecontent->{$parcfield->field_key}) && $linecontent->{$parcfield->field_key} != '0000-00-00') {
											print dol_print_date($linecontent->{$parcfield->field_key},'%d/%m/%Y');
										}
									// ON AFFICHE LA VALEUR DU CHAMP
									} else {
										print $linecontent->{$parcfield->field_key};
									}
								}
								print '</td>';
							}
						}

						//
						print '<td class="right">';
						if ($action != "edit") {
							print '<a class="parclink gp-duplicate" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&view='.$view.'&action=duplicate&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&token='.newToken().'"><i class="fas fa-clone"></i></a> &nbsp; ';
							print '<a class="parclink gp-edit paddingrightonly" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&view='.$view.'&action=edit&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&token='.newToken().'"><i class="fas fa-pencil-alt"></i></a> &nbsp; ';
							print '<a class="parclink gp-trash paddingrightonly" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&view='.$view.'&action=delete&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&token='.newToken().'"><i class="fas fa-trash-alt"></i></a>';
						} else if ($action == "edit" && GETPOST('itemid') == $linecontent->rowid) {
							print '<input type="hidden" name="action" value="edit_item">';
							print '<input type="hidden" name="itemid" value="'.$linecontent->rowid.'">';
							print '<a href="'.dol_buildpath('/gestionparc/tabs/gestionparc.php?socid='.$socid.'&parctype='.$parc->parc_key.'&view='.$view, 1).'" class="dolpgs-btn btn-danger btn-sm">'.$langs->trans('Cancel').'</a>';
							print '<input type="submit" class="dolpgs-btn btn-primary btn-sm" value="'.$langs->trans('Save').'">';
						}
						print '</td>';
						print '</tr>';
					}
				print '</tbody>';
				print '</table>';
			print '</form>';
		}
	}
print '</div>';

?>
<script nonce="<?php echo getNonce(); ?>" type="text/javascript">
// Empêcher le scroll automatique AVANT le chargement de la page
(function() {
	if (window.location.hash) {
		window.scrollTo(0, 0);
		setTimeout(function() { window.scrollTo(0, 0); }, 1);
	}
})();

$(function() {

	// Gérer le scroll vers l'ancre de manière contrôlée
	if (window.location.hash) {
		var hash = window.location.hash;
		
		setTimeout(function() {
			var $target = $(hash);
			if ($target.length) {
				var targetTop = $target.offset().top;
				var currentScroll = $(window).scrollTop();
				
				// Ne scroller que si on n'est pas déjà au bon endroit (avec marge de 150px)
				if (Math.abs(currentScroll - (targetTop - 120)) > 150) {
					$('html, body').scrollTop(targetTop - 120);
				}
			}
		}, 100);
	}

	// Restore card states from localStorage
	var storageKey = 'gestionparc_card_states_<?php echo $socid; ?>_<?php echo $parctype; ?>';
	var savedStates = localStorage.getItem(storageKey);
	if (savedStates) {
		try {
			var states = JSON.parse(savedStates);
			$.each(states, function(itemId, isOpen) {
				var $item = $('#item-' + itemId);
				if ($item.length) {
					if (isOpen) {
						$item.addClass('item-open');
					} else {
						$item.removeClass('item-open');
					}
				}
			});
		} catch(e) {
			console.error('Error restoring card states:', e);
		}
	}

	// Save card state to localStorage
	function saveCardState(itemId, isOpen) {
		var storageKey = 'gestionparc_card_states_<?php echo $socid; ?>_<?php echo $parctype; ?>';
		var states = {};
		try {
			var saved = localStorage.getItem(storageKey);
			if (saved) {
				states = JSON.parse(saved);
			}
		} catch(e) {
			states = {};
		}
		states[itemId] = isOpen;
		localStorage.setItem(storageKey, JSON.stringify(states));
	}

	// Empêcher le scroll vers le haut lors du clic sur le bouton moretab
	$('.tab.moretab').on('click', function(e) {
		e.preventDefault();
	});

	// LongPress
	/*$(document).on('long-press', '.park-item', function(e) {
		if (!$(e.target).closest('.grabbable').length) {
	        $(this).addClass('selected');
	    }
	});*/

	//
	$(document).on('click', '.park-item-header', function(e) {
		if (
			!$(e.target).closest('.grabbable').length &&
			!$(e.target).closest('.park-item-actions').length &&
			!$(e.target).closest('.icon-submenu').length &&
			!$(e.target).closest('.action-cancel').length &&
			!$(e.target).closest('.park-submenu-actions *').length)
		{
			var $item = $(this).parents('.park-item');
			$item.toggleClass('item-open');
			// Save state
			var itemId = $item.data('itemid');
			var isOpen = $item.hasClass('item-open');
			saveCardState(itemId, isOpen);
	    }
	});

	// Toggle SubMenus
	$(document).on('click', '.park-item .icon-submenu', function(e) {
		let itemHeader = $(this).parents('.park-item').find('.park-item-header');
		itemHeader.toggleClass('menu-open');
	});

	// Vérifier un élément en AJAX (vue cards)
	$(document).on('click', '.js-verify-item', function(e) {
		e.preventDefault();
		
		var $btn = $(this);
		var itemId = $btn.data('itemid');
		var parcId = $btn.data('parcid');
		var socId = $btn.data('socid');
		var parcType = $btn.data('parctype');
		
		// Désactiver le bouton pendant le traitement
		$btn.prop('disabled', true).text('Vérification...');
		
		$.ajax({
			url: '<?php echo dol_buildpath('/gestionparc/ajax/verify-items.php', 1); ?>',
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'set_line_verify',
				itemid: itemId,
				parcid: parcId,
				socid: socId,
				parctype: parcType,
				token: '<?php echo newToken(); ?>'
			},
			success: function(response) {
				if (response.success) {
					// Remplacer le bouton par le statut vérifié
					$btn.replaceWith('<div class="button-verify verified">Élement vérifié</div>');
					
					// Mettre à jour l'icône dans le header de la carte
					var $item = $('#item-' + itemId);
					$item.removeClass('unverified').addClass('verified');
					
					// Changer l'icône de warning à check
					var $icon = $item.find('.park-item-header .fa-exclamation-circle');
					if ($icon.length) {
						$icon.removeClass('fa-exclamation-circle text-warning')
							.addClass('fa-check-circle text-success');
					}
				} else {
					$btn.prop('disabled', false).text('Vérifier');
					console.error('Erreur:', response.error);
				}
			},
			error: function(xhr, status, error) {
				$btn.prop('disabled', false).text('Vérifier');
				console.error('Erreur AJAX:', error);
			}
		});
	});

	// Vérifier un élément en AJAX (vue liste)
	$(document).on('click', '.js-verify-item-list', function(e) {
		e.preventDefault();
		
		var $link = $(this);
		var itemId = $link.data('itemid');
		var parcId = $link.data('parcid');
		var socId = $link.data('socid');
		var parcType = $link.data('parctype');
		
		// Désactiver le lien pendant le traitement
		$link.css('pointer-events', 'none').css('opacity', '0.5');
		
		$.ajax({
			url: '<?php echo dol_buildpath('/gestionparc/ajax/verify-items.php', 1); ?>',
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'set_line_verify',
				itemid: itemId,
				parcid: parcId,
				socid: socId,
				parctype: parcType,
				token: '<?php echo newToken(); ?>'
			},
			success: function(response) {
				if (response.success) {
					// Remplacer l'icône par check-square
					var $cell = $link.closest('.verify-cell');
					$cell.html('<?php echo img_picto($langs->trans("Activated"), "check-square"); ?>');
					
					// Ajouter la classe parcline-ok à la ligne
					var $row = $('#item-' + itemId);
					$row.addClass('parcline-ok');
				} else {
					$link.css('pointer-events', 'auto').css('opacity', '1');
					console.error('Erreur:', response.error);
				}
			},
			error: function(xhr, status, error) {
				$link.css('pointer-events', 'auto').css('opacity', '1');
				console.error('Erreur AJAX:', error);
			}
		});
	});

	// Clone element
	$(document).on('click', 'a.action-clone', function(e) {
		e.preventDefault();
		let itemWrapper = $(this).parents('.park-item-wrapper');
		let item = $(this).parents('.park-item');
		let itemHeader = item.find('.park-item-header');
		let itemActions = itemHeader.find('.park-item-actions');
		let contentBefore = itemActions.html();
		let cloneAfter = $(this).data('cloneafter');

		itemHeader.append('<div class="action-progress-bar progress-info"></div>');
		itemActions.html('<div class="item-action action-cancel"><span class="fas fa-times"></span></div>');
		itemHeader.find('.action-progress-bar').animate({
			width: '100%'
		}, 1500, function() {
            $.ajax({
                url: "<?php echo dol_buildpath('/gestionparc/ajax/manage-items.php', 1); ?>",
                type: 'POST',
                dataType: 'json',
                data: {
                	action: 'cloneitem',
                	parckey: '<?php echo $parc->parc_key; ?>',
                	itemid: item.data('itemid'),
                	ismodeverif: item.data('ismodeverif'),
                	token: '<?php echo newToken(); ?>',
                	cloneafter: cloneAfter,
                },
                success: function(response) {
                	if (response.success) {
                		if (cloneAfter) {
                			item.after(response.newElement);
                		} else {
                			itemWrapper.append(response.newElement);
                		}
                		itemHeader.find('.action-progress-bar').stop().remove();
						itemActions.html(contentBefore);
						itemHeader.removeClass('menu-open');
						// Save new card as open by default
						saveCardState(response.newElementID, true);
						// Scroll to new element
						$([document.documentElement, document.body]).animate({
					        scrollTop: $("#item-" + response.newElementID).offset().top
					    }, 200);

                	} else {
                		itemHeader.find('.action-progress-bar').stop().remove();
						itemActions.html(contentBefore);
						item.addClass('action-error');
                		console.error(response.error);
                	}
                },
                error: function(xhr, status, error) {
                    console.error(error);
                }
            });
		});

		$(document).on('click', '.item-action.action-cancel', function(e) {
			itemHeader.find('.action-progress-bar').stop().remove();
			itemActions.html(contentBefore);
		});
	});

	// Delete element
	$(document).on('click', 'a.action-delete', function(e) {
		e.preventDefault();
		let item = $(this).parents('.park-item');
		let itemHeader = item.find('.park-item-header');
		let itemActions = itemHeader.find('.park-item-actions');
		let contentBefore = itemActions.html();

		itemHeader.append('<div class="action-progress-bar progress-danger"></div>');
		itemActions.html('<div class="item-action action-cancel"><span class="fas fa-times"></span></div>');
		itemHeader.find('.action-progress-bar').animate({
			width: '100%'
		}, 3000, function() {
            $.ajax({
                url: "<?php echo dol_buildpath('/gestionparc/ajax/manage-items.php', 1); ?>",
                type: 'POST',
                dataType: 'json',
                data: {
                	action: 'deleteitem',
                	parckey: '<?php echo $parc->parc_key; ?>',
                	itemid: item.data('itemid'),
                	token: '<?php echo newToken(); ?>',
                },
                success: function(response) {
                	if (response.success) {
                		item.fadeOut(500);
                	} else {
                		itemHeader.find('.action-progress-bar').stop().remove();
						itemActions.html(contentBefore);
						item.addClass('action-error');
                		console.error(response.error);
                	}
                },
                error: function(xhr, status, error) {
                    console.error(error);
                }
            });
		});

		$(document).on('click', '.item-action.action-cancel', function(e) {
			itemHeader.find('.action-progress-bar').stop().remove();
			itemActions.html(contentBefore);
		});
	});

	// Sort items
    $(".park-item-wrapper").each(function() {
        $(this).sortable({
            cursor: "grabbing",
            handle: '.grabbable',
            placeholder: "park-placeholder",
            stop: function(event, ui) {
	            // Récupérer l'ordre des éléments après le drag & drop
	            var sortedIDs = $(this).sortable("toArray");

	            // Appel AJAX pour sauvegarder l'ordre
	            $.ajax({
	                url: "<?php echo dol_buildpath('/gestionparc/ajax/manage-items.php', 1); ?>",
	                type: 'POST',
	                data: {
	                	action: 'itemsort',
	                	itemsort: sortedIDs,
	                	parckey: '<?php echo $parc->parc_key; ?>',
	                	token: '<?php echo newToken(); ?>',
	                },
	                success: function(response) {
	                },
	                error: function(xhr, status, error) {
	                    console.error("Error : ", error);
	                }
	            });
	        }
        }).disableSelection();
    });
});
</script>
<?php

llxFooter();
$db->close();
?>