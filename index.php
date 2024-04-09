<?php
/* 
 * Copyright (C) 2020 Anthony Damhet - Progiseize <a.damhet@progiseize.fr>
 */


$res=0;
if (! $res && file_exists("../main.inc.php")) : $res=@include '../main.inc.php'; 
endif;
if (! $res && file_exists("../../main.inc.php")) : $res=@include '../../main.inc.php'; 
endif;

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';

// ON RECUPERE LA VERSION DE DOLIBARR
$version = explode('.', DOL_VERSION);

// Load traductions files requiredby by page
$langs->load("companies");
$langs->load("other");

// Protection if external user
if ($user->socid > 0) : accessforbidden(); 
endif;

/*******************************************************************
* VARIABLES
********************************************************************/
$action = GETPOST('action');

/*******************************************************************
* ACTIONS
********************************************************************/
//var_dump($_SESSION);

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
