<?php
/* 
 * Copyright (C) 2020 Anthony Damhet - Progiseize <a.damhet@progiseize.fr>
 */


$res=0;
if (! $res && file_exists("../main.inc.php")) : $res=@include '../main.inc.php'; endif;
if (! $res && file_exists("../../main.inc.php")) : $res=@include '../../main.inc.php'; endif;

// Protection if external user
if ($user->socid > 0) : accessforbidden(); endif;

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
dol_include_once('./gestionparc/class/gestionparc.class.php');

// Load traductions files requiredby by page
$langs->load("gestionparc@gestionparc");

/*******************************************************************
* VARIABLES
********************************************************************/
$action = GETPOST('action');
$gestionparc = new GestionParc($db);

/*******************************************************************
* ACTIONS
********************************************************************/

/***************************************************
* VIEW
****************************************************/

llxHeader('', $langs->trans('gp_manager_pagetitle'), ''); ?>

<!-- CONTENEUR GENERAL -->
<div id="pg-wrapper">
</div>

<?php
// End of page
llxFooter();
$db->close(); ?>
