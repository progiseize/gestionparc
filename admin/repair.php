<?php
/* 
 * Copyright (C) 2021 Anthony Damhet - Progiseize <a.damhet@progiseize.fr>
*/


$res=0;
if (! $res && file_exists("../main.inc.php")) : $res=@include '../main.inc.php'; 
endif;
if (! $res && file_exists("../../main.inc.php")) : $res=@include '../../main.inc.php'; 
endif;
if (! $res && file_exists("../../../main.inc.php")) : $res=@include '../../../main.inc.php'; 
endif;

// ON CHARGE LES FICHIERS NECESSAIRES
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

// ON CHARGE LA LIBRAIRIE DU MODULE
dol_include_once('./gestionparc/class/gestionparc.class.php');
dol_include_once('./gestionparc/lib/gestionparc.lib.php');

// ON CHARGE LA LANGUE DU MODULE
$langs->load("gestionparc@gestionparc");

// Protection if external user
if ($user->socid > 0) : accessforbidden(); 
endif;
if (!$user->rights->gestionparc->configurer) : accessforbidden(); 
endif;


/*******************************************************************
* VARIABLES
********************************************************************/
$action = GETPOST('action', 'aZ09');

//
$array_repair = array();

/**
* 
 * ----------------------------- 
**/
/**
* 
 * -- llx_gestionparc_fields  -- 
**/
/**
* 
 * ----------------------------- 
**/
$array_repair[] = "ALTER TABLE llx_gestionparc_fields CHANGE position position int NOT NULL DEFAULT '100'";
$array_repair[] = "ALTER TABLE llx_gestionparc_fields CHANGE enabled enabled int NOT NULL DEFAULT '0'";

// Only_verif
$sql = "SHOW COLUMNS FROM llx_gestionparc_fields LIKE 'only_verif'";
$res = $db->query($sql);
if($res->num_rows == 0) : $array_repair[] = "ALTER TABLE llx_gestionparc_fields ADD only_verif BOOLEAN NOT NULL DEFAULT 0";
else: $array_repair[] = "ALTER TABLE llx_gestionparc_fields CHANGE only_verif only_verif BOOLEAN NOT NULL DEFAULT 0";
endif;


/**
* 
 * ----------------------------- 
**/
/**
* 
 * -- llx_gestionparc_verifs  -- 
**/
/**
* 
 * ----------------------------- 
**/
$array_repair[] = "ALTER TABLE llx_gestionparc_verifs CHANGE nb_verified nb_verified int NOT NULL DEFAULT '0'";
$array_repair[] = "ALTER TABLE llx_gestionparc_verifs CHANGE nb_total nb_total int NOT NULL DEFAULT '0'";
$array_repair[] = "ALTER TABLE llx_gestionparc_verifs CHANGE date_close date_close datetime NULL DEFAULT NULL";
$array_repair[] = "ALTER TABLE llx_gestionparc_verifs CHANGE commentaires commentaires text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''";
$array_repair[] = "ALTER TABLE llx_gestionparc_verifs CHANGE fichinter_id fichinter_id int NOT NULL DEFAULT '0'";
$array_repair[] = "ALTER TABLE llx_gestionparc_verifs CHANGE is_close is_close int NOT NULL DEFAULT '0'";
$array_repair[] = "ALTER TABLE llx_gestionparc_verifs CHANGE files_list files_list JSON NULL DEFAULT NULL";

/*******************************************************************
* ACTIONS
********************************************************************/
$results_repair = array();

if($action == 'repairmoduletable') :

    $success = 0;
    $error = 0;
    $i = 0;

    foreach ($array_repair as $repair_sql): $i++;
        
        //
        $res = $db->query($repair_sql);

        //
        if($res) : $success++; $is_success = 1;            
        else: $error++; $is_success = 0;
        endif;

        //
        $results_repair[$i] = array('request' => $repair_sql, 'success' => $is_success);

    endforeach;
endif;

/***************************************************
* VIEW
****************************************************/
$array_js = array();
$array_css = array('custom/gestionparc/assets/css/dolpgs.css');

llxHeader('', $langs->transnoentities('gp_repairTitle').' :: '.$langs->transnoentities('Module300320Name'), '', '', '', '', $array_js, $array_css, '', 'gestionparc parc-manager');
?>
    
<div class="dolpgs-main-wrapper">

    <h1 class="has-before"><?php echo $langs->transnoentities('gp_repairTitle'); ?></h1>
   
    <form enctype="multipart/form-data" action="<?php print $_SERVER["PHP_SELF"]; ?>" method="post" id="">
        <input type="hidden" name="action" value="repairmoduletable">
        <input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">

        <table class="dolpgs-table">
            <tbody>
                <tr class="dolpgs-thead noborderside" >
                    <th colspan="2"><?php echo $langs->trans('gp_repairText'); ?></th>
                    <th class="right"><input type="submit" class="dolpgs-btn btn-primary btn-sm" value="<?php echo $langs->trans('gp_repairButton'); ?>"></th>
                </tr>
                <?php if($action == 'repairmoduletable') : foreach($results_repair as $num_request => $resrepair): ?>

                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname" valign="top"><?php echo $num_request; ?></td>               
                        <td class="pgsz-optiontable-fielddesc "><?php echo $resrepair['request']; ?></td>
                        <td class="right pgsz-optiontable-field ">
                            <?php if($resrepair['success']) : ?>
                                <i class="fas fa-check dolpgs-color-success paddingright"></i>
                            <?php else: ?>
                                <i class="fas fa-check dolpgs-color-danger paddingright"></i>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endforeach; 
                endif; ?>
                <!-- 

                <tr class="dolpgs-tbody">
                    <td class="bold pgsz-optiontable-fieldname" valign="top"><?php echo $langs->trans('gp_setup_verif_usetime'); ?></td>               
                    <td class="pgsz-optiontable-fielddesc "><?php echo $langs->transnoentities('gp_setup_verif_usetime_desc'); ?></td>
                    <td class="right pgsz-optiontable-field ">
                        <input type="number" name="gp-verifusetime" step="1" min="0" value="<?php echo $conf->global->MAIN_MODULE_GESTIONPARC_VERIFUSETIME; ?>" />
                    </td>
                </tr> -->
            </tbody>
        </table>
    </form>

    <?php if($action == 'repairmoduletable' && $success == count($array_repair)) : ?>
        <div class="dolpgs-messagebox box-success right"><?php echo $langs->trans('gp_repairSuccessMsg'); ?> <i class="paddingleft fas fa-check"></i></div>
    <?php endif; ?>

</div>


<?php dol_fiche_end(); llxFooter(); $db->close(); ?>
