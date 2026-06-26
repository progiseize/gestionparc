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

dol_include_once('/gestionparc/class/gestionparc.class.php');

$langs->loadLangs(array('gestionparc@gestionparc'));

// Get parameters
$action = GETPOST('action', 'alpha');
$itemid = GETPOST('itemid', 'int');
$parcid = GETPOST('parcid', 'int');
$socid = GETPOST('socid', 'int');

// Security check
if (GETPOST('token') != $_SESSION['token']) {
	header('Content-Type: application/json');
	echo json_encode(array('success' => false, 'error' => 'Invalid token'));
	exit;
}

$gestionparc = new GestionParc($db);
if (isModEnabled('intervention')) {
	$verification = new GestionParcVerif($db);
	// Charge la vérif en cours pour que rowid soit défini (compteur nb_verified à jour)
	$verification->isVerif($socid);
}

header('Content-Type: application/json');

switch ($action) {
	case 'set_line_verify':
		$error = 0;

		// Vérifications
		if (empty($socid)) {
			$error++;
			echo json_encode(array('success' => false, 'error' => 'Missing socid'));
			exit;
		}
		if (empty($itemid)) {
			$error++;
			echo json_encode(array('success' => false, 'error' => 'Missing itemid'));
			exit;
		}
		if (empty($parcid)) {
			$error++;
			echo json_encode(array('success' => false, 'error' => 'Missing parcid'));
			exit;
		}

		if (!$error) {
			$gestionparc->fetch_parcType($parcid);
			if ($verification->setLineCheck($socid, $gestionparc->parc_key, $itemid, 1, $verification->rowid)) {
				echo json_encode(array(
					'success' => true,
					'message' => $langs->trans('gp_verifline_success')
				));
			} else {
				echo json_encode(array('success' => false, 'error' => 'Verification failed'));
			}
		}
		break;

	case 'set_line_unverify':
		$error = 0;
		if (empty($socid))  { echo json_encode(array('success' => false, 'error' => 'Missing socid')); exit; }
		if (empty($itemid)) { echo json_encode(array('success' => false, 'error' => 'Missing itemid')); exit; }
		if (empty($parcid)) { echo json_encode(array('success' => false, 'error' => 'Missing parcid')); exit; }

		$gestionparc->fetch_parcType($parcid);
		if ($verification->setLineCheck($socid, $gestionparc->parc_key, $itemid, 0, $verification->rowid)) {
			echo json_encode(array(
				'success' => true,
				'message' => $langs->trans('gp_verifline_reverted')
			));
		} else {
			echo json_encode(array('success' => false, 'error' => 'Revert failed'));
		}
		break;

	default:
		echo json_encode(array('success' => false, 'error' => 'Unknown action'));
		break;
}
