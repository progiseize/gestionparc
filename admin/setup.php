<?php
/* 
 * Copyright (C) 2021 Anthony Damhet - Progiseize <a.damhet@progiseize.fr>
*/

$res=0;
if (! $res && file_exists("../main.inc.php")): $res=@include '../main.inc.php'; endif;
if (! $res && file_exists("../../main.inc.php")): $res=@include '../../main.inc.php'; endif;
if (! $res && file_exists("../../../main.inc.php")): $res=@include '../../../main.inc.php'; endif;

// ON CHARGE LES FICHIERS NECESSAIRES
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

// ON CHARGE LA LIBRAIRIE DU MODULE
dol_include_once('./gestionparc/class/gestionparc.class.php');
dol_include_once('./gestionparc/lib/gestionparc.lib.php');

// ON CHARGE LA LANGUE DU MODULE
$langs->load("gestionparc@gestionparc");

// Protection if external user
if ($user->societe_id > 0): accessforbidden(); endif;
if (!$user->rights->gestionparc->configurer): accessforbidden(); endif;

/*******************************************************************
* FONCTIONS
********************************************************************/
/*function copyparc($typeOld,$typeNew){

    global $db;

    $sql_slct = "SELECT * FROM ".MAIN_DB_PREFIX."parc_client";
    $sql_slct .= " WHERE type = '".$typeOld."'";
    $sql_slct .= " ORDER BY rowid ASC";
    $result = $db->query($sql_slct);

    $success = 0;
    $errors = 0;
    $error_tab = 0;

    $db->begin();

    while ($obj = $db->fetch_object($result)): $error_obj = 0;

        $sql_insrt = "INSERT INTO ".MAIN_DB_PREFIX."gestionparc__".$typeNew;
        $sql_insrt.= " (socid,author,author_maj,date_creation,tms,numero,produit,marque,anneedemiseenservice,emplacement,observations)";
        $sql_insrt.= " VALUES (";
        $sql_insrt.= "'".$obj->tiers_id."',";
        $sql_insrt.= "'".$obj->author."',";
        $sql_insrt.= "'0',";
        $sql_insrt.= "'".$obj->date_creation."',";
        $sql_insrt.= "'".$obj->tms."',";
        $sql_insrt.= "'".$obj->numero."',";
        $sql_insrt.= "'".$obj->product_id."',";
        $sql_insrt.= "'".$obj->marque."',";
        $sql_insrt.= "'".$obj->yearmes."',";
        $sql_insrt.= "'".$db->escape($obj->emplacement)."',";
        $sql_insrt.= "'".$db->escape($obj->observations)."'";
        $sql_insrt.= ")";

        $result_insrt = $db->query($sql_insrt);
        if($result_insrt): $db->commit(); $success++;
        else: 
            $errors++;  $db->rollback();
            echo $sql_insrt.'<br/>';
        endif;

    endwhile;

    var_dump($result->num_rows);
    var_dump($success);
    var_dump($errors);
}*/

/*******************************************************************
* ACTIONS
********************************************************************/

$action = GETPOST('action');

if ($action == 'set_options'):

    $error = 0;
    $db->begin(); 

    $gestionparc = new GestionParc($db);

    // ON VERIFIE LE TOKEN
    if(GETPOST('token') == $_SESSION['token']):

        // Si l'option en cochée
        if(GETPOSTISSET('gp-use-verif')): 
            dolibarr_set_const($db, "MAIN_MODULE_GESTIONPARC_USEVERIF",true,'chaine',0,'',$conf->entity);
            dolibarr_set_const($db, "MAIN_MODULE_GESTIONPARC_VERIFUSETIME",GETPOST('gp-verifusetime'),'chaine',0,'',$conf->entity);
            dolibarr_set_const($db, "MAIN_MODULE_GESTIONPARC_VERIFREDIRECT",GETPOST('gp-verifredirect'),'chaine',0,'',$conf->entity);
            dolibarr_set_const($db, "MAIN_MODULE_GESTIONPARC_VERIFMODEL",'simple','chaine',0,'',$conf->entity);

            $extras_fichinter = $extrafields->fetch_name_optionals_label('fichinter');
            if(!array_key_exists('gestionparc_isverif', $extras_fichinter)): 
                $extrafields->addExtraField('gestionparc_isverif','gp_extrafieldFichInter_isverif','int','100','','fichinter',0,0,'null','',0,'','0','','',$conf->entity,'gestionparc@gestionparc');
            endif;

            if(!$gestionparc->setVerifMode('add')): $error++; endif;

            if(!$conf->ficheinter->enabled):
                $res_act = activateModule('modFicheinter');
                setEventMessages($langs->trans('gp_modFicheInterEnabled'), null, 'mesgs');
            endif;
        else: 
            dolibarr_set_const($db, "MAIN_MODULE_GESTIONPARC_USEVERIF",false,'chaine',0,'',$conf->entity);
            if(!$gestionparc->setVerifMode('remove')): $error++; endif;

            $dir = DOL_DATA_ROOT.'/gestionparc';
            if (!is_dir($dir)): if(!mkdir($dir,0755)): $error++; setEventMessages($langs->trans('gp_error_creafolder'), null, 'errors'); endif; endif;

        endif;

        if(!$error):$db->commit(); setEventMessages($langs->trans('gp_setup_saved'), null, 'mesgs');
        else: $db->rollback(); setEventMessages($langs->trans('gp_error'), null, 'errors');
        endif;

    else: $error++;setEventMessages("SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry", null, 'warnings');
    endif;

endif;


// $form=new Form($db);

/***************************************************
* VIEW
****************************************************/

$array_js = array();
$array_css = array('/gestionparc/assets/css/dolpgs.css');

llxHeader('',$langs->transnoentities('Setup').' :: '.$langs->transnoentities('Module300320Name'),'','','','',$array_js,$array_css,'','gestionparc setup'); ?>

<div class="dolpgs-main-wrapper">

    <h1 class="has-before"><?php echo $langs->transnoentities('gp_options_setup_pagetitle'); ?></h1>
    <?php $head = GestionParcAdminPrepareHead(); dol_fiche_head($head, 'setup','GestionParc', 1,'fa-boxes_fas_#fb2a52'); ?>

    <div class="tabBar">
        <div class="justify opacitymedium"><?php echo img_info().' '.$langs->trans("gp_setup_desc"); ?></div>

        <h3 class="dolpgs-table-title"><?php echo $langs->trans('gp_setup_title'); ?></h3>
        <form enctype="multipart/form-data" action="<?php print $_SERVER["PHP_SELF"]; ?>" method="post" id="">
            <input type="hidden" name="action" value="set_options">
            <input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">

            <table class="dolpgs-table">
                <tbody>
                    <tr class="dolpgs-thead noborderside" >
                        <th><?php echo $langs->trans('Parameter'); ?></th>
                        <th><?php echo $langs->trans('Description'); ?></th>
                        <th class="right"><?php echo $langs->trans('Value'); ?></th>
                    </tr>
                    <tr></tr>
                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname" valign="top"><?php echo $langs->trans('gp_setup_verif'); ?></td>               
                        <td class="pgsz-optiontable-fielddesc "><?php echo $langs->transnoentities('gp_setup_verif_desc'); ?></td>
                        <td class="right pgsz-optiontable-field ">
                            <input type="checkbox" name="gp-use-verif" <?php if($conf->global->MAIN_MODULE_GESTIONPARC_USEVERIF): ?>checked="checked"<?php endif; ?> />
                        </td>
                    </tr>

                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname" valign="top"><?php echo $langs->trans('gp_setup_verif_usetime'); ?></td>               
                        <td class="pgsz-optiontable-fielddesc "><?php echo $langs->transnoentities('gp_setup_verif_usetime_desc'); ?></td>
                        <td class="right pgsz-optiontable-field ">
                            <input type="number" name="gp-verifusetime" step="1" min="0" value="<?php echo $conf->global->MAIN_MODULE_GESTIONPARC_VERIFUSETIME; ?>" />
                        </td>
                    </tr>

                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname" valign="top"><?php echo $langs->trans('gp_setup_verif_redirect'); ?></td>               
                        <td class="pgsz-optiontable-fielddesc "><?php echo $langs->transnoentities('gp_setup_verif_redirect_desc'); ?></td>
                        <td class="right pgsz-optiontable-field ">
                            <input type="checkbox" name="gp-verifredirect" <?php if($conf->global->MAIN_MODULE_GESTIONPARC_VERIFREDIRECT): ?>checked="checked"<?php endif; ?> />
                        </td>
                    </tr>  
                </tbody>
            </table>
            <div class="right">
                <input type="submit" class="dolpgs-btn btn-primary" name="" value="<?php print $langs->trans('Save'); ?>">
            </div>

        </form>
    </div>
</div>


<?php dol_fiche_end(); llxFooter(); $db->close(); ?>