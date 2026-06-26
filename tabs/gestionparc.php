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
$view = getDolGlobalString('GESTIONPARC_DEFAULT_VIEW') ? getDolGlobalString('GESTIONPARC_DEFAULT_VIEW') : 'cards';

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
				header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.$anchor);
				exit;
			}
		}
		break;

	// ENREGISTRER UN CHAMP (commercial ou intervenant) - campagne en cours ou dernière intervention
	case 'gp_set_user':

		if (GETPOST('token') != $_SESSION['token']) {
			$error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
		}
		if (!$user->hasRight('gestionparc', 'parc', 'verif') && !$user->admin) {
			$error++; setEventMessages($langs->trans('NotEnoughPermissions'), null, 'warnings');
		}

		$gp_field = GETPOST('gp_field', 'aZ09');
		if (!in_array($gp_field, array('commercial', 'intervenant'))) {
			$error++;
		}

		if (!$error) {
			$gp_value = GETPOST('gp_value', 'int');
			$target_verif = GETPOST('gp_verif_id', 'int');
			$target_fichinter = GETPOST('gp_fichinter_id', 'int');

			if ($target_verif > 0) {
				$verification->updateVerifUserField($target_verif, $gp_field, $gp_value);
			} elseif ($target_fichinter > 0) {
				$fi = new Fichinter($db);
				if ($fi->fetch($target_fichinter) > 0) {
					$fi->fetch_optionals();
					$fi->array_options['options_gestionparc_'.$gp_field] = $gp_value ? $gp_value : '';
					$fi->updateExtraField('gestionparc_'.$gp_field);
				}
			}
			setEventMessages($langs->trans('gp_users_saved'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype);
			exit;
		}
		break;

	// ENREGISTRER UN CHAMP PERSONNALISE TIERS (extrafield societe, cases à cocher)
	case 'gp_set_socfield':

		if (GETPOST('token') != $_SESSION['token']) {
			$error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
		}
		if (!$user->hasRight('gestionparc', 'parc', 'write') && !$user->admin) {
			$error++; setEventMessages($langs->trans('NotEnoughPermissions'), null, 'warnings');
		}
		$gp_fieldkey = GETPOST('gp_fieldkey', 'aZ09');
		$gp_socfields = GestionParcVerif::getCustomSocFields();
		if (!isset($gp_socfields[$gp_fieldkey])) {
			$error++; setEventMessages($langs->trans('gp_error'), null, 'warnings');
		}

		if (!$error) {
			// S'assure que les extrafields existent (upgrade-safe)
			$gpverif_ensure = new GestionParcVerif($db);
			$gpverif_ensure->ensureSocieteExtrafields();

			$valid_keys = array_keys($gp_socfields[$gp_fieldkey]['options']);
			$posted = GETPOST('gp_socfield', 'array');
			$selected = array();
			foreach ((array) $posted as $optk) {
				if (in_array($optk, $valid_keys, true)) $selected[] = $optk;
			}

			$object->fetch_optionals();
			$object->array_options['options_'.$gp_fieldkey] = implode(',', $selected);
			if ($object->updateExtraField($gp_fieldkey) > 0) {
				setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			} else {
				setEventMessages($object->error, null, 'errors');
			}
			header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype);
			exit;
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

			// Au moins une case doit être cochée sur les champs requis pour valider l'intervention
			$object->fetch_optionals();
			foreach (GestionParcVerif::getCustomSocFields() as $gp_fk => $gp_fdef) {
				if (empty($gp_fdef['required'])) continue;
				$gp_val = isset($object->array_options['options_'.$gp_fk]) ? trim((string) $object->array_options['options_'.$gp_fk]) : '';
				if ($gp_val === '') {
					$error++; $action = 'initclose_verif';
					setEventMessages($langs->trans('gp_verif_error_needSocField', $langs->transnoentities($gp_fdef['label'])), null, 'errors');
				}
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
						header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.$anchor);
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
				header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.$anchor);
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
			header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.$anchor);
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
				header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'#item-'.GETPOST('itemid'));
				exit;
			} else {
				$error++; setEventMessages($langs->trans('gp_error'), null, 'warnings');
			}
		}
		break;

	// REVERT : revenir en arrière sur le contrôle d'une ligne (dévérifier)
	case 'set_line_unverify':

		if (GETPOST('token') != $_SESSION['token']) {
			$error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
		}
		if (empty(GETPOST('socid')))  { $error++; setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings'); }
		if (empty(GETPOST('itemid'))) { $error++; setEventMessages($langs->trans('gp_error_needItemId'), null, 'warnings'); }
		if (empty(GETPOST('parcid'))) { $error++; setEventMessages($langs->trans('gp_error_needTypeId'), null, 'warnings'); }

		if (!$error) {
			$gestionparc->fetch_parcType(GETPOST('parcid'));
			if ($verification->setLineCheck($socid, $gestionparc->parc_key, GETPOST('itemid'), 0, $verification->rowid)) {
				setEventMessages($langs->trans('gp_verifline_reverted'), null, 'mesgs');
				header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'#item-'.GETPOST('itemid'));
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
					header('Location:'.dol_buildpath('/gestionparc/tabs/gestionparc.php?socid='.$object->id.'&parctype='.$parctype.'#item-'.$newItemId, 1));
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
				header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'#item-'.$newElementID);
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
				header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype);
				exit;
			}
		}
		break;

	// EDITION
	case 'edit':

		$editItem_id = 0;
		$error = 0;
		$isFromVerif = GETPOSTINT('fromverif');

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
		$isFromVerif = GETPOSTINT('fromverif');

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
			$fieldsWithError = array(); // Pour stocker les champs en erreur

			// ON VERIFIE LES CHAMPS DYNAMIQUES
			foreach ($gestionparc->fields as $parcfield) {
				if ($parcfield->enabled) {
					// VERIFICATION DES CHAMPS OBLIGATOIRES EN MODE VERIFICATION MANUELLE (prioritaire)
					if ($isModeVerif && $isFromVerif && getDolGlobalString('GESTIONPARC_VERIF_MODE') == 'manual' && $parcfield->required_manual_verif) {
						$fieldValue = GETPOST('gpfield_'.$parcfield->field_key);
						if (empty($fieldValue) || $fieldValue === '0' || $fieldValue === 0) {
							$error++;
							$fieldsWithError[] = $parcfield->field_key; // Marquer le champ comme en erreur
							setEventMessages($langs->trans('ErrorFieldRequired', $parcfield->label), null, 'errors');
						}
					}
					// ON VERIFIE SI LES CHAMPS OBLIGATOIRES SONT REMPLIS (sauf si déjà vérifié en required_manual_verif)
					else if ($parcfield->only_verif && $parcfield->required) {
						if ($isModeVerif  && empty(GETPOST('gpfield_'.$parcfield->field_key))) {
							$error++;
							setEventMessages($langs->trans('ErrorFieldRequired', $parcfield->label), null, 'warnings');
						}
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
			// Stocker les champs en erreur dans une variable de session ou GET pour le CSS
			if (isset($fieldsWithError) && !empty($fieldsWithError)) {
				$_GET['fields_error'] = implode(',', $fieldsWithError);
			}
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
				// Si on est en mode vérification manuelle et qu'on vient d'une vérification, on marque l'élément comme vérifié
				if ($isModeVerif && $isFromVerif && getDolGlobalString('GESTIONPARC_VERIF_MODE') == 'manual') {
					if ($verification->setLineCheck($socid, $gestionparc->parc_key, GETPOST('itemid'), 1, $verification->rowid)) {
						setEventMessages($langs->trans('RecordSaved').' - '.$langs->trans('gp_verifline_success'), null, 'mesgs');
					} else {
						setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
					}
				} else {
					setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
				}
				
				$db->commit(); $action = '';
				foreach ($gestionparc->fields as $parcfield) {
					unset($_POST['gpfield_'.$parcfield->field_key]);
				}
				// Redirect to keep position
				header('Location: '.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'#item-'.GETPOST('itemid'));
				exit;
			} else {
				$error++; setEventMessages($langs->trans('gp_error'), null, 'warnings');
				$db->rollback();
			}
		}
		break;

	// GENERATE EXCEL EXPORT
	case 'generate_export_excel':
		$id_inter = GETPOST('id_inter', 'int');
		if ($id_inter > 0) {
			$file_path = $verification->generateExcelExport($id_inter);
			if ($file_path && file_exists($file_path)) {
				// Native download for compatibility with all versions (including v23+)
				header('Content-Description: File Transfer');
				header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
				header('Content-Disposition: attachment; filename="'.basename($file_path).'"');
				header('Content-Transfer-Encoding: binary');
				header('Expires: 0');
				header('Cache-Control: must-revalidate');
				header('Pragma: public');
				header('Content-Length: ' . filesize($file_path));
				
				// Clear buffer to avoid corruption
				if (ob_get_level()) ob_end_clean();
				
				readfile($file_path);
				exit;


				exit;
			} else {
				setEventMessages($langs->trans('gp_error'), null, 'errors');
			}
		}
		break;

	// GENERATE PDF EXPORT
	case 'generate_export_pdf':
		$id_inter = GETPOST('id_inter', 'int');
		if ($id_inter > 0) {
			$file_path = $verification->generatePDFExport($id_inter);
			if ($file_path && file_exists($file_path)) {
				// Native download for compatibility
				header('Content-Description: File Transfer');
				header('Content-Type: application/pdf');
				header('Content-Disposition: attachment; filename="'.basename($file_path).'"');
				header('Content-Transfer-Encoding: binary');
				header('Expires: 0');
				header('Cache-Control: must-revalidate');
				header('Pragma: public');
				header('Content-Length: ' . filesize($file_path));
				
				// Clear buffer to avoid corruption
				if (ob_get_level()) ob_end_clean();
				
				readfile($file_path);
				exit;
			} else {
				setEventMessages($langs->trans('gp_error'), null, 'errors');
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
		$tabs[$nb_tabs][0] = $_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype_infos['key'].'#parc-content'; //dol_buildpath("/gestionparc/admin/manager.php", 1);
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

	$urlformconfirm = $_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype;
	$formconfirm = $form->formconfirm($urlformconfirm, $langs->transnoentities('gp_verif_close'), '', 'close_verif', $formarray, '', 1, 500, 500, 0, $langs->transnoentities('gp_verif_close'), $langs->transnoentities('Cancel'));
}
if ($action == 'delete') {
	$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&itemid='.GETPOST('itemid').'&parcid='.GETPOST('parcid'), $langs->trans('gp_confirmDeleteTitle'), $langs->trans('gp_confirmDelete'), 'confirm_delete', '', '', 1, 0, 500, 0);
}
if ($action == 'verifall') {
	$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&parcid='.GETPOST('parcid'), $langs->trans('gp_verifall'), $langs->trans('gp_confirmVerifAll'), 'verifall_confirm', '', '', 1, 0, 500, 0);
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
	$urlformconfirm = $_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype;
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

	// Contexte commercial / intervenant : cible = campagne en cours, sinon dernière intervention
	$gp_show_users     = false;
	$gp_can_edit_users = ($user->hasRight('gestionparc', 'parc', 'verif') || $user->admin);
	$gp_last_inter_id  = 0;
	$gp_sel_com = 0; $gp_sel_int = 0;
	$gp_target_hidden = '';
	if (isModEnabled('intervention') && getDolGlobalInt('MAIN_MODULE_GESTIONPARC_USEVERIF')) {
		$gp_last_inter_id = $verification->getLastVerif($socid);
		if ($isModeVerif) {
			$gp_sel_com = !empty($verification->commercial) ? $verification->commercial : 0;
			$gp_sel_int = !empty($verification->intervenant) ? $verification->intervenant : 0;
			$gp_target_hidden = '<input type="hidden" name="gp_verif_id" value="'.$modeVerifID.'">';
			$gp_show_users = true;
		} elseif ($gp_last_inter_id) {
			$gp_inter_tmp = new Fichinter($db);
			if ($gp_inter_tmp->fetch($gp_last_inter_id) > 0) {
				$gp_inter_tmp->fetch_optionals();
				$gp_sel_com = !empty($gp_inter_tmp->array_options['options_gestionparc_commercial']) ? $gp_inter_tmp->array_options['options_gestionparc_commercial'] : 0;
				$gp_sel_int = !empty($gp_inter_tmp->array_options['options_gestionparc_intervenant']) ? $gp_inter_tmp->array_options['options_gestionparc_intervenant'] : 0;
			}
			$gp_target_hidden = '<input type="hidden" name="gp_fichinter_id" value="'.$gp_last_inter_id.'">';
			$gp_show_users = true;
		}
	}

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
					// Champs personnalisés tiers (extrafields societe, gérés uniquement depuis cet onglet)
					$object->fetch_optionals();
					$gp_can_edit_socf = ($user->hasRight('gestionparc', 'parc', 'write') || $user->admin);
					foreach (GestionParcVerif::getCustomSocFields() as $gp_fk => $gp_fdef) {
						$gp_f_options  = $gp_fdef['options'];
						$gp_f_value    = isset($object->array_options['options_'.$gp_fk]) ? $object->array_options['options_'.$gp_fk] : '';
						$gp_f_selected = array_filter(array_map('trim', explode(',', (string) $gp_f_value)));
						$gp_f_editing  = ($action == 'gp_edit_socfield' && GETPOST('gp_fieldkey', 'aZ09') == $gp_fk);
						print '<tr>';
							print '<td class="titlefieldmiddle">'.$langs->trans($gp_fdef['label']).'</td>';
							print '<td>';
							if ($gp_f_editing && $gp_can_edit_socf) {
								print '<form action="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype.'" method="POST">';
								print '<input type="hidden" name="token" value="'.newToken().'">';
								print '<input type="hidden" name="action" value="gp_set_socfield">';
								print '<input type="hidden" name="gp_fieldkey" value="'.$gp_fk.'">';
								foreach ($gp_f_options as $gp_optk => $gp_optlabel) {
									$gp_chk = in_array($gp_optk, $gp_f_selected, true) ? ' checked' : '';
									print '<label class="marginrightonly"><input type="checkbox" name="gp_socfield[]" value="'.$gp_optk.'"'.$gp_chk.'> '.$gp_optlabel.'</label> ';
								}
								print '<input type="submit" class="button smallpaddingimp small nomarginleft" value="'.$langs->trans('Save').'">';
								print '</form>';
							} else {
								if (!empty($gp_f_selected)) {
									$gp_f_labels = array();
									foreach ($gp_f_selected as $gp_optk) {
										$gp_f_labels[] = isset($gp_f_options[$gp_optk]) ? $gp_f_options[$gp_optk] : $gp_optk;
									}
									print dol_escape_htmltag(implode(', ', $gp_f_labels));
								} else {
									print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
								}
								if ($gp_can_edit_socf) {
									print ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype.'&action=gp_edit_socfield&gp_fieldkey='.$gp_fk.'">'.img_edit().'</a>';
								}
							}
							print '</td>';
						print '</tr>';
					}
					// Commercial / Intervenant : un champ par ligne, édition inline au crayon (comme sur la fiche d'intervention)
					if ($gp_show_users) {
						$gp_fields = array(
							'commercial'  => array($langs->trans('gp_extrafieldFichInter_commercial'),  $gp_sel_com),
							'intervenant' => array($langs->trans('gp_extrafieldFichInter_intervenant'), $gp_sel_int),
						);
						foreach ($gp_fields as $gp_fk => $gp_fd) {
							$gp_editing = ($action == 'gp_edit_user' && GETPOST('gp_field', 'aZ09') == $gp_fk);
							print '<tr>';
								print '<td class="titlefieldmiddle">'.$gp_fd[0].'</td>';
								print '<td>';
								if ($gp_editing && $gp_can_edit_users) {
									print '<form action="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype.'" method="POST">';
									print '<input type="hidden" name="token" value="'.newToken().'">';
									print '<input type="hidden" name="action" value="gp_set_user">';
									print '<input type="hidden" name="gp_field" value="'.$gp_fk.'">';
									print $gp_target_hidden;
									print $form->select_dolusers($gp_fd[1], 'gp_value', 1, null, 0, '', '', $conf->entity, 0, 0, '', 0, '', 'maxwidth200');
									print ' <input type="submit" class="button smallpaddingimp small nomarginleft" value="'.$langs->trans('Save').'">';
									print '</form>';
								} else {
									if (!empty($gp_fd[1])) {
										$gp_u = new User($db);
										if ($gp_u->fetch($gp_fd[1]) > 0) { print $gp_u->getNomUrl(1); }
										else { print '&mdash;'; }
									} else {
										print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
									}
									if ($gp_can_edit_users) {
										print ' <a class="editfielda" href="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype.'&action=gp_edit_user&gp_field='.$gp_fk.'">'.img_edit().'</a>';
									}
								}
								print '</td>';
							print '</tr>';
						}
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
					$last_intervention = $gp_last_inter_id;
					if ($last_intervention) {
						$ficheinter->fetch($last_intervention);
						print '<tr>';
							print '<td>'.$langs->trans('gp_client_lastverif').'</td>';
							print '<td>';
								print '<a href="'.dol_buildpath('fichinter/card.php?id='.$ficheinter->id, 1).'">'.$ficheinter->ref.'</a>';
								// Export Button
								print ' <a href="javascript:void(0)" class="button smallpaddingimp small gp-btn-export gp-open-export-modal" data-id="'.$ficheinter->id.'">';
								print '   <i class="fa fa-download"></i> ' . $langs->trans('Export');
								print ' </a>';
							print '</td>';
						print '</tr>';
					}
					//
					if ($user->hasRight('gestionparc', 'parc', 'verif') || $user->admin) {
						print '<tr>';
							print '<td valign="middle">'.$langs->trans('gp_client_verifmode').'</td>';
							print '<td>';
							if ($isModeVerif) {
								// Si tous les organes ne sont pas contrôlés, le bouton ouvre une
								// modale de confirmation (gérée en JS) au lieu de clôturer directement.
								$gp_uncontrolled = ($verification->nb_verified < $verification->nb_total) ? 1 : 0;
								print '<form id="gp-close-verif-form" action="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype.'" method="POST">';
									print '<input type="hidden" name="token" value="'.newToken().'">';
									print '<input type="hidden" name="verif_id" value="'.$modeVerifID.'">';
									print '<input type="hidden" name="action" value="initclose_verif">';
									print '<input type="submit" name="close" value="'.$langs->trans('gp_verif_close').'" class="button smallpaddingimp small nomarginleft gp-close-verif-btn" data-uncontrolled="'.$gp_uncontrolled.'">';
									print '<input type="submit" name="cancel_verif" value="'.$langs->trans('gp_verif_cancel').'" class="button butActionDelete cancel smallpaddingimp small nomarginleft" >';
								print '</form>';
							} else {
								print '<a class="button smallpaddingimp small nomarginleft" href="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype.'&action=mode_verif&token='.newToken().'">'.$langs->trans('gp_verif_open').'</a>';
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
		print dol_fiche_head($tabs, $parctype, '', -1, '', 0, '', '', $limit);
} else {
	print '<div class="warning">'.$langs->trans('gp_empty_parclist_message').'</div>';
}

if ((int) $gestionparc->rowid > 0) {

	// Anchor point for tab navigation
	print '<div id="parc-content"></div>';

	// New item
	print '<div id="park-add-item">';
		print '<a href="'.$_SERVER['PHP_SELF'].'?socid='.$object->id.'&parctype='.$parctype.'&action=additem&token='.newToken().'"><span class="fas fa-plus"></span></a>';
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

		if ($isModeVerif && getDolGlobalInt('GESTIONPARC_VERIF_ALLOW_VERIFALL') && ($user->hasRight('gestionparc', 'parc', 'verifall') || $user->admin) && !empty($parcLines) && $nb_verified < count($parcLines)) {
			print '<div class="gestionparc-verifall">';
				print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?socid='.$object->id.'&parctype='.$parctype.'&parcid='.$parc->rowid.'&action=verifall&token='.newToken().'">'.img_picto($langs->trans("gp_verifall"), 'switch_off').'</a> <span class="veriftxt">'.$langs->trans('gp_verifall').'</span>';
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
								print '<li><a class="item-action action-edit" href="#"><span class="fas fa-pencil-alt paddingright"></span> '.$langs->trans('Edit').'</a></li>';
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
						if ($isFromVerif) {
							print '<input type="hidden" name="fromverif" value="1">';
						}
					}
					foreach($parc->fields as $parcfield_key => $parcfield){
						if ($parcfield->enabled) {
							if ($parcfield->only_verif && !$isModeVerif) {
								continue;
							}
							// Vérifier si ce champ est en erreur
							$fieldErrorClass = '';
							if (isset($_GET['fields_error']) && strpos($_GET['fields_error'], $parcfield->field_key) !== false) {
								$fieldErrorClass = ' field-error';
							}
							print '<div class="park-field'.$fieldErrorClass.'">';
								print '<div class="park-field-label">'.$parcfield->label.'</div>';
								print '<div class="park-field-value">';
								if ($action == 'edit' && $editItem_id == $linecontent->rowid) {
									// Si on est en mode vérification manuelle et que le champ est obligatoire en vérif manuelle, on le vide
									// Si GETPOST existe (même vide), on l'utilise pour préserver la valeur après erreur
									if (GETPOSTISSET('gpfield_'.$parcfield->field_key)) {
										$fieldValue = GETPOST('gpfield_'.$parcfield->field_key);
									} else {
										$fieldValue = $linecontent->{$parcfield->field_key};
										if ($isFromVerif && getDolGlobalString('GESTIONPARC_VERIF_MODE') == 'manual' && $parcfield->required_manual_verif) {
											$fieldValue = '';
										}
									}
									$fieldHtml = $parcfield->construct_field($parc, $object->id, $fieldValue);
									// Ajouter la classe field-error aux inputs/selects si le champ est en erreur
									if ($fieldErrorClass) {
										// Ajouter la classe au select-wrapper
										$fieldHtml = str_replace('<div class="select-wrapper">', '<div class="select-wrapper field-error-wrapper">', $fieldHtml);
										// Ajouter la classe aux inputs
										$fieldHtml = str_replace('name="gpfield_'.$parcfield->field_key.'"', 'name="gpfield_'.$parcfield->field_key.'" class="field-error-input"', $fieldHtml);
										$fieldHtml = str_replace('id="gpfield_'.$parcfield->field_key.'"', 'id="gpfield_'.$parcfield->field_key.'" class="field-error-input"', $fieldHtml);
									}
									print $fieldHtml;
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
							print '<a class="button-edit button-cancel" href="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&parctype='.$parctype.'#item-'.$linecontent->rowid.'"><span class="fas fa-times"></span></a>';
							print '<button class="button-edit button-valid" type="submit"><span class="fas fa-check"></span></button>';
						print '</div>';
						print '</form>';
					} else if ($isModeVerif && $linecontent->verif) {
						// Élément contrôlé : cliquer permet de revenir en arrière (revert)
						$gp_is_manual = (getDolGlobalString('GESTIONPARC_VERIF_MODE') == 'manual');
						$gp_editurl = $_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&action=edit&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&fromverif=1&token='.newToken().'#item-'.$linecontent->rowid;
						print '<a class="button-verify verified js-revert-item" href="#" title="'.dol_escape_htmltag($langs->trans('gp_verif_revert')).'" data-itemid="'.$linecontent->rowid.'" data-parcid="'.$parc->rowid.'" data-socid="'.$object->id.'" data-parctype="'.$parctype.'" data-mode="'.($gp_is_manual ? 'manual' : 'instant').'" data-editurl="'.dol_escape_htmltag($gp_editurl).'">';
							print '<span class="gp-verified-label"><span class="fas fa-check"></span> '.$langs->trans('gp_verif_verified').'</span>';
							print '<span class="gp-revert-hint"><span class="fas fa-undo"></span> '.$langs->trans('gp_verif_revert').'</span>';
						print '</a>';
					} else if ($isModeVerif && !$linecontent->verif) {
						// Mode de vérification : manuel ou instantané
						if (getDolGlobalString('GESTIONPARC_VERIF_MODE') == 'manual') {
							// Mode manuel : redirection vers la page d'édition
							print '<a class="button-verify unverified" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&action=edit&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&fromverif=1&token='.newToken().'#item-'.$linecontent->rowid.'">Vérifier</a>';
						} else {
							// Mode instantané : AJAX
							print '<a class="button-verify unverified js-verify-item" href="#" data-itemid="'.$linecontent->rowid.'" data-parcid="'.$parc->rowid.'" data-socid="'.$object->id.'" data-parctype="'.$parctype.'">Vérifier</a>';
						}
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
									// Cliquer permet de revenir en arrière (revert)
									$gp_is_manual = (getDolGlobalString('GESTIONPARC_VERIF_MODE') == 'manual');
									$gp_editurl = $_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&action=edit&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&fromverif=1&token='.newToken().'#item-'.$linecontent->rowid;
									echo '<a class="reposition js-revert-item-list" href="#" title="'.dol_escape_htmltag($langs->trans('gp_verif_revert')).'" data-itemid="'.$linecontent->rowid.'" data-parcid="'.$parc->rowid.'" data-socid="'.$object->id.'" data-parctype="'.$parctype.'" data-mode="'.($gp_is_manual ? 'manual' : 'instant').'" data-editurl="'.dol_escape_htmltag($gp_editurl).'">'.img_picto($langs->trans("Activated"), 'check-square').'</a>';
								} else {
									// Mode de vérification : manuel ou instantané
									if (getDolGlobalString('GESTIONPARC_VERIF_MODE') == 'manual') {
										// Mode manuel : redirection vers la page d'édition
										echo '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&action=edit&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&fromverif=1&token='.newToken().'#item-'.$linecontent->rowid.'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
									} else {
										// Mode instantané : AJAX
										echo '<a class="reposition js-verify-item-list" href="#" data-itemid="'.$linecontent->rowid.'" data-parcid="'.$parc->rowid.'" data-socid="'.$object->id.'" data-parctype="'.$parctype.'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
									}
								}
							}
							print '</td>';
						}

						foreach($parc->fields as $parcfield_key => $parcfield){
							if ($parcfield->enabled) {
								if ($parcfield->only_verif && !$isModeVerif) {
									continue;
								}
								// Vérifier si ce champ est en erreur
								$fieldErrorClass = '';
								if (isset($_GET['fields_error']) && strpos($_GET['fields_error'], $parcfield->field_key) !== false) {
									$fieldErrorClass = ' field-error';
								}
								print '<td class="pgsz-optiontable-fielddesc'.$fieldErrorClass.'">';

								if ($action == 'edit' && $editItem_id == $linecontent->rowid) {
									print '<span class="gp-infos-label">'.$parcfield->label.' : </span>';
									if ($parcfield->type == 'autonumber') {
										print $linecontent->{$parcfield->field_key};
										print '<input type="hidden" name="gpfield_'.$parcfield->field_key.'" id="gpfield_'.$parcfield->field_key.'" value="'.$linecontent->{$parcfield->field_key}.'">';
									} else {
										// Si GETPOST existe (même vide), on l'utilise pour préserver la valeur après erreur
										if (GETPOSTISSET('gpfield_'.$parcfield->field_key)) {
											$fieldValue = GETPOST('gpfield_'.$parcfield->field_key);
										} else {
											$fieldValue = $linecontent->{$parcfield->field_key};
											if ($isFromVerif && getDolGlobalString('GESTIONPARC_VERIF_MODE') == 'manual' && $parcfield->required_manual_verif) {
												$fieldValue = '';
											}
										}
										$fieldHtml = $parcfield->construct_field($parc, $societe->id, $fieldValue);
										// Ajouter la classe field-error aux inputs/selects si le champ est en erreur
										if ($fieldErrorClass) {
											// Ajouter la classe au select-wrapper
											$fieldHtml = str_replace('<div class="select-wrapper">', '<div class="select-wrapper field-error-wrapper">', $fieldHtml);
											// Ajouter la classe aux inputs
											$fieldHtml = str_replace('name="gpfield_'.$parcfield->field_key.'"', 'name="gpfield_'.$parcfield->field_key.'" class="field-error-input"', $fieldHtml);
											$fieldHtml = str_replace('id="gpfield_'.$parcfield->field_key.'"', 'id="gpfield_'.$parcfield->field_key.'" class="field-error-input"', $fieldHtml);
										}
										print $fieldHtml;
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
							print '<a class="parclink gp-duplicate" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&action=duplicate&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&token='.newToken().'"><i class="fas fa-clone"></i></a> &nbsp; ';
							print '<a class="parclink gp-edit paddingrightonly" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&action=edit&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&token='.newToken().'"><i class="fas fa-pencil-alt"></i></a> &nbsp; ';
							print '<a class="parclink gp-trash paddingrightonly" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&action=delete&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&token='.newToken().'"><i class="fas fa-trash-alt"></i></a>';
						} else if ($action == "edit" && GETPOST('itemid') == $linecontent->rowid) {
							print '<input type="hidden" name="action" value="edit_item">';
							print '<input type="hidden" name="itemid" value="'.$linecontent->rowid.'">';
							if ($isFromVerif) {
								print '<input type="hidden" name="fromverif" value="1">';
							}
							print '<a href="'.dol_buildpath('/gestionparc/tabs/gestionparc.php?socid='.$socid.'&parctype='.$parc->parc_key, 1).'" class="dolpgs-btn btn-danger btn-sm">'.$langs->trans('Cancel').'</a>';
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

	// Libellés (i18n)
	var GP_TXT_VERIFY   = <?php echo json_encode($langs->transnoentities('gp_verif_verify')); ?>;
	var GP_TXT_VERIFIED = <?php echo json_encode($langs->transnoentities('gp_verif_verified')); ?>;
	var GP_TXT_REVERT   = <?php echo json_encode($langs->transnoentities('gp_verif_revert')); ?>;
	var GP_VERIFY_URL   = '<?php echo dol_buildpath('/gestionparc/ajax/verify-items.php', 1); ?>';
	var GP_TOKEN        = '<?php echo newToken(); ?>';

	// Construit le bouton "élément vérifié" (cliquable pour revert)
	function gpVerifiedBtn(d) {
		var $b = $('<a class="button-verify verified js-revert-item" href="#"></a>')
			.attr('title', GP_TXT_REVERT)
			.attr('data-itemid', d.itemId).attr('data-parcid', d.parcId)
			.attr('data-socid', d.socId).attr('data-parctype', d.parcType)
			.attr('data-mode', d.mode).attr('data-editurl', d.editUrl || '');
		$b.append($('<span class="gp-verified-label"><span class="fas fa-check"></span> </span>').append(document.createTextNode(GP_TXT_VERIFIED)));
		$b.append($('<span class="gp-revert-hint"><span class="fas fa-undo"></span> </span>').append(document.createTextNode(GP_TXT_REVERT)));
		return $b;
	}
	// Construit le bouton "Vérifier" (non vérifié) selon le mode
	function gpUnverifiedBtn(d) {
		if (d.mode === 'manual') {
			return $('<a class="button-verify unverified"></a>').attr('href', d.editUrl).text(GP_TXT_VERIFY);
		}
		return $('<a class="button-verify unverified js-verify-item" href="#"></a>')
			.attr('data-itemid', d.itemId).attr('data-parcid', d.parcId)
			.attr('data-socid', d.socId).attr('data-parctype', d.parcType).text(GP_TXT_VERIFY);
	}

	// Vérifier un élément en AJAX (vue cards) — mode instantané
	$(document).on('click', '.js-verify-item', function(e) {
		e.preventDefault();

		var $btn = $(this);
		var d = { itemId: $btn.data('itemid'), parcId: $btn.data('parcid'), socId: $btn.data('socid'), parcType: $btn.data('parctype'), mode: 'instant', editUrl: '' };

		// Désactiver le bouton pendant le traitement
		$btn.css('pointer-events', 'none').text('Vérification...');

		$.ajax({
			url: GP_VERIFY_URL,
			type: 'POST',
			dataType: 'json',
			data: { action: 'set_line_verify', itemid: d.itemId, parcid: d.parcId, socid: d.socId, parctype: d.parcType, token: GP_TOKEN },
			success: function(response) {
				if (response.success) {
					$btn.replaceWith(gpVerifiedBtn(d));
					var $item = $('#item-' + d.itemId);
					$item.removeClass('unverified').addClass('verified');
					var $icon = $item.find('.park-item-header .fa-exclamation-circle');
					if ($icon.length) {
						$icon.removeClass('fa-exclamation-circle text-warning').addClass('fa-check-circle text-success');
					}
				} else {
					$btn.css('pointer-events', 'auto').text(GP_TXT_VERIFY);
					console.error('Erreur:', response.error);
				}
			},
			error: function(xhr, status, error) {
				$btn.css('pointer-events', 'auto').text(GP_TXT_VERIFY);
				console.error('Erreur AJAX:', error);
			}
		});
	});

	// Revert (revenir en arrière) sur un élément contrôlé — vue cards (tous modes)
	$(document).on('click', '.js-revert-item', function(e) {
		e.preventDefault();

		var $btn = $(this);
		var d = { itemId: $btn.data('itemid'), parcId: $btn.data('parcid'), socId: $btn.data('socid'), parcType: $btn.data('parctype'), mode: $btn.data('mode'), editUrl: $btn.data('editurl') };

		$btn.css('pointer-events', 'none').css('opacity', '0.6');

		$.ajax({
			url: GP_VERIFY_URL,
			type: 'POST',
			dataType: 'json',
			data: { action: 'set_line_unverify', itemid: d.itemId, parcid: d.parcId, socid: d.socId, parctype: d.parcType, token: GP_TOKEN },
			success: function(response) {
				if (response.success) {
					$btn.replaceWith(gpUnverifiedBtn(d));
					var $item = $('#item-' + d.itemId);
					$item.removeClass('verified').addClass('unverified');
					var $icon = $item.find('.park-item-header .fa-check-circle');
					if ($icon.length) {
						$icon.removeClass('fa-check-circle text-success').addClass('fa-exclamation-circle text-warning');
					}
				} else {
					$btn.css('pointer-events', 'auto').css('opacity', '1');
					console.error('Erreur:', response.error);
				}
			},
			error: function(xhr, status, error) {
				$btn.css('pointer-events', 'auto').css('opacity', '1');
				console.error('Erreur AJAX:', error);
			}
		});
	});

	// Vérifier un élément en AJAX (vue liste)
	// Icônes (vue liste)
	var GP_ICON_CHECK  = <?php echo json_encode(img_picto($langs->transnoentities('Activated'), 'check-square')); ?>;
	var GP_ICON_SWITCH = <?php echo json_encode(img_picto($langs->transnoentities('Disabled'), 'switch_off')); ?>;

	function gpListVerifiedLink(d) {
		return $('<a class="reposition js-revert-item-list" href="#"></a>')
			.attr('title', GP_TXT_REVERT)
			.attr('data-itemid', d.itemId).attr('data-parcid', d.parcId)
			.attr('data-socid', d.socId).attr('data-parctype', d.parcType)
			.attr('data-mode', d.mode).attr('data-editurl', d.editUrl || '')
			.html(GP_ICON_CHECK);
	}
	function gpListUnverifiedLink(d) {
		if (d.mode === 'manual') {
			return $('<a class="reposition"></a>').attr('href', d.editUrl).html(GP_ICON_SWITCH);
		}
		return $('<a class="reposition js-verify-item-list" href="#"></a>')
			.attr('data-itemid', d.itemId).attr('data-parcid', d.parcId)
			.attr('data-socid', d.socId).attr('data-parctype', d.parcType)
			.html(GP_ICON_SWITCH);
	}

	$(document).on('click', '.js-verify-item-list', function(e) {
		e.preventDefault();

		var $link = $(this);
		var d = { itemId: $link.data('itemid'), parcId: $link.data('parcid'), socId: $link.data('socid'), parcType: $link.data('parctype'), mode: 'instant', editUrl: '' };

		$link.css('pointer-events', 'none').css('opacity', '0.5');

		$.ajax({
			url: GP_VERIFY_URL,
			type: 'POST',
			dataType: 'json',
			data: { action: 'set_line_verify', itemid: d.itemId, parcid: d.parcId, socid: d.socId, parctype: d.parcType, token: GP_TOKEN },
			success: function(response) {
				if (response.success) {
					$link.closest('.verify-cell').empty().append(gpListVerifiedLink(d));
					$('#item-' + d.itemId).addClass('parcline-ok');
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

	// Revert (revenir en arrière) — vue liste (tous modes)
	$(document).on('click', '.js-revert-item-list', function(e) {
		e.preventDefault();

		var $link = $(this);
		var $cell = $link.closest('.verify-cell');
		var d = { itemId: $link.data('itemid'), parcId: $link.data('parcid'), socId: $link.data('socid'), parcType: $link.data('parctype'), mode: $link.data('mode'), editUrl: $link.data('editurl') };

		$link.css('pointer-events', 'none').css('opacity', '0.5');

		$.ajax({
			url: GP_VERIFY_URL,
			type: 'POST',
			dataType: 'json',
			data: { action: 'set_line_unverify', itemid: d.itemId, parcid: d.parcId, socid: d.socId, parctype: d.parcType, token: GP_TOKEN },
			success: function(response) {
				if (response.success) {
					$cell.empty().append(gpListUnverifiedLink(d));
					$('#item-' + d.itemId).removeClass('parcline-ok');
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

	// Open item edit form via AJAX
	function openItemEditForm(item, fromVerif) {
		$.ajax({
			url: "<?php echo dol_buildpath('/gestionparc/ajax/manage-items.php', 1); ?>",
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'getitemform',
				parckey: '<?php echo $parc->parc_key; ?>',
				itemid: item.data('itemid'),
				socid: <?php echo $socid; ?>,
				ismodeverif: item.data('ismodeverif'),
				fromverif: fromVerif ? 1 : 0,
				token: '<?php echo newToken(); ?>',
			},
			success: function(response) {
				if (response.success) {
					item.replaceWith(response.formHtml);
					let newItem = $('#item-' + response.itemID);
					initGestionParcSelect2(newItem, true);
					$([document.documentElement, document.body]).animate({
						scrollTop: newItem.offset().top
					}, 200);
				} else {
					console.error(response.error);
				}
			},
			error: function(xhr, status, error) {
				console.error(error);
			}
		});
	}

	// Edit element
	$(document).on('click', 'a.action-edit', function(e) {
		e.preventDefault();
		let item = $(this).parents('.park-item');
		openItemEditForm(item, false);
	});

	// Cancel edit (restore view via page reload)
	$(document).on('click', 'a.js-cancel-edit', function(e) {
		e.preventDefault();
		window.location.href = '<?php echo $_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype; ?>';
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
		let isModeVerif = item.data('ismodeverif');

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
                	ismodeverif: isModeVerif,
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
						saveCardState(response.newElementID, true);

						let newItem = $('#item-' + response.newElementID);
						openItemEditForm(newItem, isModeVerif ? true : false);

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
	            var movedItem = ui.item.attr('id'); // élément réellement déplacé

	            // Appel AJAX pour sauvegarder l'ordre
	            $.ajax({
	                url: "<?php echo dol_buildpath('/gestionparc/ajax/manage-items.php', 1); ?>",
	                type: 'POST',
	                data: {
	                	action: 'itemsort',
	                	itemsort: sortedIDs,
	                	moveditem: movedItem,
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
        });
    });
});
</script>

<!-- Custom Export Modal -->
<div id="gp-export-modal-overlay" class="gp-modal-overlay" style="display:none;">
	<div class="gp-modal-container">
		<div class="gp-modal-header">
			<h3><?php echo $langs->trans('gp_export_choice_title'); ?></h3>
			<span class="gp-modal-close">&times;</span>
		</div>
		<div class="gp-modal-body">
			<p class="gp-modal-desc">
				<i class="fa fa-info-circle"></i> <?php echo $langs->trans('gp_export_choice_desc'); ?>
			</p>
			<div class="gp-modal-cards">
				<!-- Excel -->
				<a href="#" id="gp-btn-excel-confirm" class="gp-modal-card excel">
					<i class="fas fa-file-excel"></i>
					<span><?php echo $langs->trans('ExportExcel'); ?></span>
				</a>
				
				<!-- PDF -->
				<a href="#" id="gp-btn-pdf-confirm" class="gp-modal-card pdf">
					<i class="fas fa-file-pdf"></i>
					<span><?php echo $langs->trans('ExportPDF'); ?></span>
				</a>

			</div>
		</div>
		<div class="gp-modal-footer">
			<button class="button gp-modal-close-btn"><?php echo $langs->trans('Cancel'); ?></button>
		</div>
	</div>
</div>

<!-- Modale de confirmation : clôture avec organes non contrôlés -->
<div id="gp-confirm-close-overlay" class="gp-modal-overlay" style="display:none;">
	<div class="gp-modal-container gp-modal-sm">
		<div class="gp-modal-header">
			<h3><?php echo $langs->trans('gp_verif_uncontrolled_title'); ?></h3>
			<span class="gp-modal-close">&times;</span>
		</div>
		<div class="gp-modal-body">
			<p class="gp-modal-desc"><i class="fas fa-exclamation-triangle"></i> <?php echo $langs->trans('gp_verif_confirm_uncontrolled'); ?></p>
		</div>
		<div class="gp-modal-footer gp-modal-footer-split">
			<button type="button" class="button gp-modal-close-btn" id="gp-confirm-close-no"><?php echo $langs->trans('No'); ?></button>
			<button type="button" class="button gp-modal-btn-primary" id="gp-confirm-close-yes"><?php echo $langs->trans('gp_verif_continue_anyway'); ?></button>
		</div>
	</div>
</div>

<script>
	const GP_EXPORT_BASE_URL = '<?php echo $_SERVER['PHP_SELF'].'?socid='.$socid.'&action=generate_export_excel&token='.newToken().'&id_inter='; ?>';
	const GP_EXPORT_PDF_BASE_URL = '<?php echo $_SERVER['PHP_SELF'].'?socid='.$socid.'&action=generate_export_pdf&token='.newToken().'&id_inter='; ?>';

	// Modale de confirmation de clôture (organes non contrôlés)
	jQuery(document).on('click', '.gp-close-verif-btn', function(e) {
		if (parseInt(jQuery(this).attr('data-uncontrolled'), 10) === 1) {
			e.preventDefault();
			jQuery('#gp-confirm-close-overlay').fadeIn(200);
			jQuery('body').css('overflow', 'hidden');
		}
	});
	jQuery(document).on('click', '#gp-confirm-close-yes', function() {
		jQuery('#gp-confirm-close-overlay').fadeOut(100);
		jQuery('body').css('overflow', '');
		jQuery('#gp-close-verif-form').trigger('submit');
	});
	jQuery(document).on('click', '#gp-confirm-close-no, #gp-confirm-close-overlay .gp-modal-close', function() {
		jQuery('#gp-confirm-close-overlay').fadeOut(200);
		jQuery('body').css('overflow', '');
	});
	jQuery(document).on('click', '#gp-confirm-close-overlay', function(e) {
		if (jQuery(e.target).hasClass('gp-modal-overlay')) {
			jQuery(this).fadeOut(200);
			jQuery('body').css('overflow', '');
		}
	});
</script>

<?php
llxFooter();
$db->close();
?>