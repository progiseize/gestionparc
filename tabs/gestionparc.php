<?php
/*
 * Copyright (C) 2018 - 2023 Anthony Damhet - Progiseize <a.damhet@progiseize.fr>
 */

$res=0;
if (! $res && file_exists("../main.inc.php")) : $res=@include '../main.inc.php';
endif;
if (! $res && file_exists("../../main.inc.php")) : $res=@include '../../main.inc.php';
endif;
if (! $res && file_exists("../../../main.inc.php")) : $res=@include '../../../main.inc.php';
endif;

// Protection if external user
if ($user->socid > 0) : accessforbidden();
endif;

if (!$user->hasRight('gestionparc','parc','read')) : accessforbidden();
endif;

// Version Dolibarr
$dolibarr_version = explode('.', DOL_VERSION);

/************************************************
*  FICHIERS NECESSAIRES
************************************************/
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';

dol_include_once('./gestionparc/class/gestionparc.class.php');

$langs->load('interventions');

/************************************************
*  TODO
************************************************/

// Voir suite dev dolibarr pour voir si integration dans ressources avec SOCID

/*******************************************************************
* VARIABLES
********************************************************************/
$action = GETPOST('action');
$cancel =  GETPOST('cancel', 'alpha');
$socid  = GETPOST('socid', 'int');

$societe = new Societe($db);
$societe->fetch($socid);
$object = $societe;
$form = new Form($db);

$soc_cats = $societe->getCategoriesCommon('customer');

$ficheinter = new Fichinter($db);
$gestionparc = new GestionParc($db);
$list_parctypes = $gestionparc->list_parcType(1, 1, $soc_cats);

$parctype = (GETPOSTISSET('parctype'))?GETPOST('parctype'): (!empty($list_parctypes)?(reset($list_parctypes))['key']:'');

$verification = new GestionParcVerif($db);
$last_intervention = $verification->getLastVerif($socid);

// On vérifie si on est en mode verif
$is_mode_verif = false;
$id_mode_verif = $verification->isVerif($socid);
if ($id_mode_verif && $id_mode_verif > 0) {
	$is_mode_verif = true;
} else if ($id_mode_verif && $id_mode_verif < 0) {
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
				setEventMessages($langs->trans('gp_verif_success_oncancel'), null, 'mesgs'); $action=''; $is_mode_verif = false;
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

			$t2s = convertTime2Seconds(GETPOST('durationhour'), GETPOST('durationmin'));
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
					$is_mode_verif = false;
					setEventMessages($langs->trans('gp_verif_success_onclose', $ficheinter->ref), null, 'mesgs');
					if ($conf->global->MAIN_MODULE_GESTIONPARC_VERIFREDIRECT) {
						header('Location: '.dol_buildpath('fichinter/card.php?id='.$id_intervention, 1));
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
				$id_mode_verif = $verif_id;
				$is_mode_verif = true;
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

					// On check les autonumber
					if ($parcfield->type == 'autonumber' && !$parcfield->enabled) {
						$nextnum = $parcfield->getNextAutoNumber($socid, $gestionparc->parc_key, $parcfield->field_key);
						$_POST['gpfield_'.$parcfield->field_key] = $nextnum;
					}
					//
					if ($parcfield->only_verif && $parcfield->required) {
						if ($is_mode_verif  && empty(GETPOST('gpfield_'.$parcfield->field_key))) {
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
						$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."gestionparc__".$gestionparc->parc_key;
						$sql .= " WHERE ".$parcfield->field_key." = ".GETPOST('gpfield_'.$parcfield->field_key);
						$sql .= " AND socid=".GETPOST('socid');
						$res = $db->query($sql);
						if ($res->num_rows > 0) {
							$error++;
							setEventMessages($langs->trans('gp_error_autonumber_exist'), null, 'warnings');
						}
					}
				}
			}

			// SI PAS D'ERREUR ON CONTINUE
			if (!$error) {
				$db->begin();
				$sql_insert = "INSERT INTO ".MAIN_DB_PREFIX."gestionparc__".$gestionparc->parc_key." (socid, author";
				foreach ($gestionparc->fields as $parcfield) {
					if ($parcfield->enabled || $parcfield->type == 'autonumber') {
						$sql_insert .= ", ".$parcfield->field_key;
					}
				}
				$sql_insert .= ") VALUES (".GETPOST('socid').", ".$user->id;
				foreach ($gestionparc->fields as $parcfield) {
					if ($parcfield->enabled || $parcfield->type == 'autonumber') {
						$sql_insert .= ", '".$db->escape(GETPOST('gpfield_'.$parcfield->field_key))."'";
					}
				}
				$sql_insert .= ")";

				$result_insert = $db->query($sql_insert);

				if ($result_insert) {
					$db->commit();
					setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
					foreach ($gestionparc->fields as $parcfield) {
						unset($_POST['gpfield_'.$parcfield->field_key]);
					}
				} else {
					$error++; setEventMessages($langs->trans('gp_error'), null, 'warnings');
					$db->rollback();
				}
			}
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
		if (empty(GETPOST('parcid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needTypeId'), null, 'warnings');
		}
		if (empty(GETPOST('itemid'))) {
			$error++;
			setEventMessages($langs->trans('gp_error_needItemId'), null, 'warnings');
		}

		if (!$error) {
			$gestionparc->fetch_parcType(GETPOST('parcid'));

			$db->begin();
			$sql_dup = "INSERT INTO ".MAIN_DB_PREFIX."gestionparc__".$gestionparc->parc_key." (socid, author";
			foreach ($gestionparc->fields as $parcfield) {
				$sql_dup .= ", ".$parcfield->field_key;
			}
			$sql_dup .= ")";
			$sql_dup .= " SELECT '".GETPOST('socid')."', '".$user->id."' ";
			foreach ($gestionparc->fields as $parcfield) {
				if ($parcfield->type == 'autonumber') {
					$nxt_autonum = $parcfield->getNextAutoNumber(GETPOST('socid'), $gestionparc->parc_key, $parcfield->field_key);
					$sql_dup .= ", '".$nxt_autonum."'";
				} else {
					$sql_dup .= ", ".$parcfield->field_key;
				}
			}
			$sql_dup .= " FROM ".MAIN_DB_PREFIX."gestionparc__".$gestionparc->parc_key;
			$sql_dup .= " WHERE rowid = ".GETPOST('itemid');

			$result = $db->query($sql_dup);

			if ($result) {
				$db->commit(); setEventMessages($langs->trans('gp_duplicate_success'), null, 'mesgs');
			} else {
				$error++; setEventMessages($langs->trans('gp_error'), null, 'warnings');
				$db->rollback();
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
						if ($is_mode_verif  && empty(GETPOST('gpfield_'.$parcfield->field_key))) {
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

		// ON VERIFIE SI ON PEUT AFFICHER LE PARC SUR CE TYPE DE TIERS
		// Ajoutée à la requête

		// ON VERIFIE S'IL CONTIENT DES CHAMPS
		if (empty($gestionparc->fields)) {
			$show_parc = false;
		}

		// SI ON PEUT AFFICHER
		if ($show_parc) {
			// ON COMPTE LES LIGNES
			$nb_lines = $gestionparc->getSocParcCount($societe->id, $gestionparc->parc_key);

			$color_class = '';

			// SI ON EST EN MODE VERIF
			if ($is_mode_verif) {
				$nb_verifs = $gestionparc->getSocParcCount($societe->id, $gestionparc->parc_key, true);

				if (intval($nb_verifs) > 0) {
					if (intval($nb_verifs) == intval($nb_lines)) {
						$color_class = 'dolpgs-bg-success';
					} else {
						$color_class = 'dolpgs-bg-warning';
					}
				} else {
					if (intval($nb_lines) == 0) {
						$color_class = 'dolpgs-bg-success';
					} else {
						$color_class = 'dolpgs-bg-danger';
					}
				}

				$label_count = $nb_verifs.' / '.$nb_lines;
			} else {
				$label_count = $nb_lines;
			}

			// ON AJOUTE LE LIEN
			$tabs[$nb_tabs][0] = $_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype_infos['key']; //dol_buildpath("/gestionparc/admin/manager.php", 1);
			$tabs[$nb_tabs][1] = $parctype_infos['label'].' <span class="badge marginleftonlyshort '.$color_class.'">'.$label_count.'</span>'; // $langs->trans("gp_options_tab_manager");
			$tabs[$nb_tabs][2] = $parctype_infos['key']; // key
			$nb_tabs++;
		}
	}
}

if ($keyparc) {
	$parc = $gestionparc->fetch_parcType($keyparc, true);
	$parc_lines = $gestionparc->getSocParcContent($societe->id, $parc->parc_key);
}


/***************************************************
* VIEW
****************************************************/
$array_js = array('/gestionparc/assets/js/gestionparc.js');
$array_css = array(
	'/gestionparc/assets/css/gestionparc.css',
	'/gestionparc/assets/css/dolpgs.css',
);

llxHeader('', $societe->name.' - '.$langs->trans('gp_clientparc'), '', '', '', '', $array_js, $array_css);

// ACTIONS NECESSITANT LE HEADER
if ($action == 'delete') {
	$error = 0;
	if (GETPOST('token') != $_SESSION['token']) {
		$error++;
		setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
	}
	if (!$error) {
		echo $form->formconfirm($_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&itemid='.GETPOST('itemid').'&parcid='.GETPOST('parcid'), $langs->trans('gp_confirmDeleteTitle'), $langs->trans('gp_confirmDelete'), 'confirm_delete', '', '', 1, 0, 500, 0);
	}
} else if ($action == 'verifall') {
	$error = 0;
	if (GETPOST('token') != $_SESSION['token']) {
		$error++;
		setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
	}
	if (!$error) {
		echo $form->formconfirm($_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&parcid='.GETPOST('parcid'), $langs->trans('gp_verifall'), $langs->trans('gp_confirmVerifAll'), 'verifall_confirm', '', '', 1, 0, 500, 0);
	}
}

// AFFICHAGE DES ONGLETS THIRDPARTY
$head = societe_prepare_head($societe, $user);
echo dol_get_fiche_head($head, 'gestionparc', $langs->trans("ThirdParty"), -1, 'company');

print '<div class="gestionparc-full-wrapper">';

	//
	dol_banner_tab($societe, 'socid', '', ($user->socid ? 0 : 1), 'rowid', 'nom');

	// AFFICHAGE CODES CLIENT & FOURNISSEUR
	print '<div class="fichecenter">';
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border centpercent tableforfield">';
		if ($societe->client && !empty($societe->code_client)) {
			print '<tr>';
				print '<td class="titlefield">'.$langs->trans('CustomerCode').'</td>';
				print '<td>';
					print $societe->code_client;
					$tmpcheck = $societe->check_codeclient();
					if ($tmpcheck != 0 && $tmpcheck != -5){
						print '<font class="error">('.$langs->trans("WrongCustomerCode").')</font>';
					}
				print '</td>';
			print '</tr>';
		}
		if ($societe->fournisseur && !empty($societe->code_fournisseur)) {
			print '<tr>';
				print '<td class="titlefield">'.$langs->trans('SupplierCode').'</td>';
				print '<td>';
					print $societe->code_fournisseur;
					$tmpcheck = $societe->check_codefournisseur();
					if ($tmpcheck != 0 && $tmpcheck != -5) {
						print '<font class="error">'.$langs->trans("WrongSupplierCode").')</font>';
					}
				print '</td>';
			print '</tr>';
		}
		if ($last_intervention) {
			$ficheinter->fetch($last_intervention);
			print '<tr>';
				print '<td>'.$langs->trans('gp_client_lastverif').'</td>';
				print '<td><a href="'.dol_buildpath('fichinter/card.php?id='.$ficheinter->id, 1).'">'.$ficheinter->ref.'</a></td>';
			print '</tr>';
		}
		if (getDolGlobalInt('MAIN_MODULE_GESTIONPARC_USEVERIF')) {
			print '<tr>';
				print '<td valign="middle">'.$langs->trans('gp_client_verifmode').'</td>';
				print '<td>';
				if ($is_mode_verif) {
					print '<form action="'.$_SERVER["PHP_SELF"].'?socid='.$societe->id.'&parctype='.$parctype.'" method="POST">';
						print '<input type="hidden" name="token" value="'.newToken().'">';
						print '<input type="hidden" name="verif_id" value="'.$id_mode_verif.'">';
						if ($action == 'initclose_verif') {

							if (!$conf->global->MAIN_MODULE_GESTIONPARC_VERIFUSETIME) {
								print '<div style="font-weight: bold;text-decoration: underline;margin-bottom: 3px">'.$langs->trans('InterDuration').'<span class="required">*</span></div>';
								print '<div style="margin-bottom: 8px;">'.$form->select_duration('duration', (!GETPOST('durationhour', 'int') && !GETPOST('durationmin', 'int')) ? 3600 : (60 * 60 * GETPOST('durationhour', 'int') + 60 * GETPOST('durationmin', 'int')), 0, 'select').'</div>';
							} else {
								print '<input type="hidden" name="durationhour" value="'.$conf->global->MAIN_MODULE_GESTIONPARC_VERIFUSETIME.'">';
								print '<input type="hidden" name="durationmin" value="0">';
							}

							print '<div style="font-weight: bold;text-decoration: underline;margin-bottom: 3px">';
								print $langs->trans('gp_verifcom');
								if ($verification->nb_verified != $verification->nb_total) {
									print '<span class="required">*</span>';
								}
							print '</div>';

							print '<div>';
								print '<textarea name="intercom" class="minwidth400 minheight" style="min-height: 96px;">'.GETPOST('intercom').'</textarea>';
							print '</div>';
							print '<input type="hidden" name="action" value="close_verif">';
							print '<input type="submit" name="close" value="'.$langs->trans('gp_verif_close').'" class="button smallpaddingimp small nomarginleft">';
							print '<input type="submit" name="cancel" value="'.$langs->trans('Cancel').'" class="button butActionDelete cancel smallpaddingimp small nomarginleft" >';

						} else {
							print '<input type="hidden" name="action" value="initclose_verif">';
							print '<input type="submit" name="close" value="'.$langs->trans('gp_verif_close').'" class="button smallpaddingimp small nomarginleft">';
							print '<input type="submit" name="cancel_verif" value="'.$langs->trans('gp_verif_cancel').'" class="button butActionDelete cancel smallpaddingimp small nomarginleft" >';
						}
					print '</form>';
				} else {
					print '<a class="button smallpaddingimp small nomarginleft" href="'.$_SERVER["PHP_SELF"].'?socid='.$societe->id.'&parctype='.$parctype.'&action=mode_verif&token='.newToken().'">'.$langs->trans('gp_verif_open').'</a>';
				}
				print '</td>';
			print '</tr>';
		}

		print '</table>';
		print '<div class="clearboth"></div>';
	print '</div>';
	print '<div style="border-top:1px solid rgb(215, 215, 215);margin-bottom:16px;"></div>';


	print '<div class="dolpgs-main-wrapper">';
	if (!empty($tabs)) {
		print dol_fiche_head($tabs, $parctype, '', 1, '', 0, '', '', 5);
	}
	print '<div style="border-top:1px solid #bbb;margin-bottom:16px;"></div>';

	// Count verified lines
	if (getDolGlobalInt('MAIN_MODULE_GESTIONPARC_USEVERIF')) {
		$nb_verified = 0;
		if (!empty($parc_lines)) {
			foreach($parc_lines as $lineid => $linecontent) {
				if ($linecontent->verif): $nb_verified++; endif;
			}
		}
	}

	if (!empty($parc->description)) {
		print '<div class="justify opacitymedium" style="margin-bottom: 16px;">';
			print img_info('').' '.$parc->description;
		print '</div>';
	}

	if ((int) $gestionparc->rowid > 0) {
		print '<form enctype="multipart/form-data" action="'.$_SERVER["PHP_SELF"].'?socid='.$societe->id.'&parctype='.$parctype.'" method="POST" id="">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="parcid" value="'.$parc->rowid.'">';

			if ($is_mode_verif && $user->admin && !empty($parc_lines) && $nb_verified < count($parc_lines)) {
				print '<div class="gestionparc-verifall">';
					print '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?socid='.$societe->id.'&parctype='.$parctype.'&parcid='.$parc->rowid.'&action=verifall&token='.newToken().'">'.img_picto($langs->trans("gp_verifall"), 'switch_off').'</a> <span class="veriftxt">'.$langs->trans('gp_verifall').'</span>';
				print '</div>';
			}

			print '<table class="dolpgs-table gestionparc-table" style="border-top:none;" id="gestionparc-table-'.$gestionparc->rowid.'">';
			print '<tbody>';

				// Columns name
				print '<tr class="dolpgs-thead noborderside">';
				if ($is_mode_verif) {
					print '<th>'.$langs->trans('gp_verif_label').'</th>';
				}
				foreach ($parc->fields as $parcfield_key => $parcfield) {
					if ($parcfield->enabled) {
						if ($parcfield->only_verif && !$is_mode_verif) {
							continue;
						}
						print '<th>'.$parcfield->label.($parcfield->required ? ' <span class="required">*</span>' : '').'</th>';
					}
				}
				print '<th class="right">';
					if ($action != 'edit'){
						print '<button class="dolpgs-btn btn-primary btn-sm gestionparc-add" onclick="event.preventDefault();"><i class="fas fa-plus"></i></button>';
					}
				print '</th>';
				print '</tr>';

				// NEW LINE
				if ($action != 'edit') {
					print '<tr class="dolpgs-tbody gestionparc-newline" '.(($action == "add" && $error && GETPOST('parcid') == $parc->rowid) ? 'style="display: table-row;"' : '').'>';
					if ($is_mode_verif) {
						print '<td></td>';
					}
					foreach ($parc->fields as $parcfield_key => $parcfield) {
						if ($parcfield->enabled) {
							if ($parcfield->only_verif && !$is_mode_verif) {
								continue;
							}
							print '<td>'.$parcfield->construct_field($parc, $societe->id).'</td>';
						}
					}
					print '<td class="right">';
						print '<input type="hidden" name="action" value="add">';
						print '<input type="submit" value="'.$langs->trans('Add').'" class="dolpgs-btn btn-secondary btn-sm">';
					print '</td>';
					print '</tr>';
				}

				//
				foreach($parc_lines as $lineid => $linecontent){
					print '<tr class="dolpgs-tbody gestionparc-line '.(($is_mode_verif && $linecontent->verif) ? 'parcline-ok' : '').'">';

					if ($is_mode_verif) {
						print '<td>';
						if ($action != "edit" || $action == "edit" && $editItem_id != $linecontent->rowid) {
							if ($linecontent->verif) {
								echo img_picto($langs->trans("Activated"), 'check-square');
							} else {
								echo '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?socid='.$societe->id.'&parctype='.$parctype.'&itemid='.$linecontent->rowid.'&action=set_line_verify&parcid='.$parc->rowid.'&token='.newToken().'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
							}
						}
						print '</td>';
					}

					foreach($parc->fields as $parcfield_key => $parcfield){
						if ($parcfield->enabled) {
							if ($parcfield->only_verif && !$is_mode_verif) {
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
											print '<a href="'.dol_buildpath('product/card.php?id='.$linecontent->{$parcfield->field_key}, 1).'" >'.$prodserv->label.'</a>';
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
						print '<input type="button" class="dolpgs-btn btn-danger btn-sm" value="'.$langs->trans('Cancel').'" onClick="window.location=\''.urlencode($_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key).'\'">';
						print '<input type="submit" class="dolpgs-btn btn-primary btn-sm" value="'.$langs->trans('Save').'">';
					}
					print '</td>';
					print '</tr>';
				}

			print '</tbody>';
			print '</table>';
		print '</form>';
		print '</form>';
	}
	print '</div>';
print '</div>';

llxFooter();
$db->close();
?>