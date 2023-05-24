<?php
/* 
 * Copyright (C) 2021 Anthony Damhet - Progiseize <a.damhet@progiseize.fr>
*/


$res=0;
if (! $res && file_exists("../main.inc.php")): $res=@include '../main.inc.php'; endif;
if (! $res && file_exists("../../main.inc.php")): $res=@include '../../main.inc.php'; endif;
if (! $res && file_exists("../../../main.inc.php")): $res=@include '../../../main.inc.php'; endif;


// ON CHARGE LES FICHIERS NECESSAIRES
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
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
* VARIABLES
********************************************************************/

$form = new Form($db);
$soc_tags = $form->select_all_categories('customer', '', 'parent', 64, 0, 1);

$gestionparc = new GestionParc($db);

/*******************************************************************
* ACTIONS
********************************************************************/

$action = GETPOST('action');

// AJOUTER UN NOUVEAU TYPE DE PARC
if ($action == 'add_parctype'):

    // VARIABLES
    $error = 0;

    // ON VERIFIE LES CHAMPS
    if(GETPOST('token') != $_SESSION['token']): $error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings'); endif;
    if(empty(GETPOST('gpnewtype-label','alphanohtml'))): $error++; setEventMessages($langs->trans('ErrorFieldRequired','Label'), null, 'errors'); endif;

    // SI IL N'Y A PAS D'ERREURS
    if(!$error):

        $gestionparc->label = GETPOST('gpnewtype-label','alphanohtml');
        $gestionparc->description = GETPOST('gpnewtype-description');

        $soc_tags_selected = GETPOST('gpnewtype-soctags');
        if(!empty($soc_tags_selected)): $gestionparc->tags = json_encode($soc_tags_selected);
        else: $gestionparc->tags = NULL; endif;

        if(empty(GETPOST('gpnewtype-position','int'))): $gestionparc->position = 100; 
        else: $gestionparc->position = GETPOST('gpnewtype-position','int'); endif;

        // CREATION DU TYPE DE PARC
        if(!$gestionparc->add_parcType($user)):
            $error++; setEventMessages($langs->trans('gp_addnewparc_error'), null, 'errors');
        endif;

    endif;

    if(!$error):$db->commit(); setEventMessages($langs->trans('gp_addnewparc_success'), null, 'mesgs'); unset($_POST);
    else: $db->rollback(); 
    endif;

// SUPPRIMER UN TYPE DE PARC
elseif ($action == 'confirm_delete'):

    $error = 0;

    if(GETPOST('token') != $_SESSION['token']): $error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings'); endif;
    if(empty(GETPOST('rowid','int'))): $error++; setEventMessages($langs->trans('gp_deleteparc_needId'), null, 'errors'); endif;

    // SI IL N'Y A PAS D'ERREURS
    if(!$error):
        if(!$gestionparc->remove_parcType(GETPOST('rowid','int'),$user)):
            $error++; setEventMessages($langs->trans('gp_deleteparc_needRights'), null, 'errors'); 
        endif;
    endif;

    if(!$error):$db->commit(); setEventMessages($langs->trans('gp_deleteparc_success'), null, 'mesgs');
    else: $db->rollback(); 
    endif;

// EDITER TYPE DE PARC
elseif ($action == 'edit_parctype'):

    // VARIABLES
    $error = 0;
    $majtype_label = GETPOST('gpedittype-label','alphanohtml');
    $majtype_description = GETPOST('gpedittype-description');
    $majtype_tags = GETPOST('gpedittype-soctags');
    $majtype_position = GETPOST('gpedittype-position','int');
    $majtype_rowid = GETPOST('rowid','int');

    // ON VERIFIE LES CHAMPS
    if(GETPOST('token') != $_SESSION['token']): $error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings'); endif;
    if(empty(GETPOST('gpedittype-label','alphanohtml'))): $error++; setEventMessages($langs->trans('ErrorFieldRequired','Label'), null, 'errors'); endif;

    // SI IL N'Y A PAS D'ERREURS
    if(!$error):

        $gestionparc->fetch_parcType(GETPOST('rowid','int'));

        $gestionparc->old_label = $gestionparc->label;
        $gestionparc->label = GETPOST('gpedittype-label','alphanohtml');
        $gestionparc->description = GETPOST('gpedittype-description');

        if(!empty($majtype_tags)): $gestionparc->tags = json_encode($majtype_tags);
        else: $gestionparc->tags = NULL; endif;


        if(empty(GETPOST('gpedittype-position','int'))): $gestionparc->position = 100; 
        else: $gestionparc->position = GETPOST('gpedittype-position','int'); endif;

        // CREATION DU TYPE DE PARC
        if(!$gestionparc->update_parcType($user)):
            $error++; setEventMessages($langs->trans('gp_updateparctype_error'), null, 'errors');
        endif;

    endif;

    if(!$error):$db->commit(); setEventMessages($langs->trans('gp_updateparctype_success'), null, 'mesgs');
    else: $db->rollback(); $action = 'edit';
    endif;

// ACTIVER CHAMP
elseif ($action == 'enable_parc'):

    if(GETPOST('token') == $_SESSION['token']):

        $error = 0;

        // IDENTIFIANT DU PARC
        $parc_id = GETPOST('rowid','int');
        if(empty($parc_id)): $error++; setEventMessages($langs->trans('gp_error_needId'), null, 'warnings'); endif;

        // ON MET A JOUR LE CHAMP
        if(!$error):
            if($gestionparc->setParcStatus($parc_id, true)): setEventMessages($langs->trans('gp_parc_activated'), null, 'mesgs');
            else: setEventMessages($langs->trans('gp_error'), null, 'errors');
            endif;
        endif;
    else:
        setEventMessages("SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry", null, 'warnings');
    endif;

    // DESACTIVER CHAMP
elseif ($action == 'disable_parc'):
    

    if(GETPOST('token') == $_SESSION['token']):
        $error = 0;

        // IDENTIFIANT DU PARC
        $parc_id = GETPOST('rowid','int');
        if(empty($parc_id)): $error++; setEventMessages($langs->trans('gp_error_needId'), null, 'warnings'); endif;

        // ON MET A JOUR LE CHAMP
        if(!$error):
            if($gestionparc->setParcStatus($parc_id, '0')): setEventMessages($langs->trans('gp_parc_disactivated'), null, 'mesgs');
            else: setEventMessages($langs->trans('gp_error'), null, 'errors');
            endif;
        endif;
    else:
        setEventMessages("SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry", null, 'warnings');
    endif;
endif;

/*******************************************************************
* VARIABLES (AFTER ACTION)
********************************************************************/

$list_parctypes = $gestionparc->list_parcType();

/***************************************************
* VIEW
****************************************************/
$array_js = array();
$array_css = array('custom/gestionparc/assets/css/dolpgs.css');

llxHeader('',$langs->transnoentities('gp_manager_pagetitle').' :: '.$langs->transnoentities('Module300320Name'),'','','','',$array_js,$array_css,'','gestionparc parc-manager');

// ACTIONS NECESSITANT LE HEADER
if ($action == 'delete'):
    $gestionparc->fetch_parcType(GETPOST('rowid','int'));
    if(!empty($gestionparc->fields)): $nb_items = count($gestionparc->fields); else: $nb_items = 0; endif;
    echo $form->formconfirm($_SERVER['PHP_SELF'].'?rowid='.GETPOST('rowid','int'),$langs->trans('gp_confirm'),$langs->trans('gp_confirmDeleteParc_xItems',$nb_items),'confirm_delete','','',1,0,500,0);
endif;
?>
    
<div class="dolpgs-main-wrapper">

    <h1 class="has-before"><?php echo $langs->transnoentities('gp_manager_pagetitle'); ?></h1>    
    <?php $head = GestionParcAdminPrepareHead(); dol_fiche_head($head, 'manager','GestionParc', 1,'fa-boxes_fas_#fb2a52'); ?>

    <div class="tabBar">
        <div class="justify opacitymedium"><?php echo img_info().' '.$langs->trans("gp_manager_pagedescription"); ?></div>
        <form enctype="multipart/form-data" action="<?php echo $_SERVER["PHP_SELF"]; ?>" method="POST" id="">

            <input type="hidden" name="action" value="add_parctype">
            <input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">

            <h3 class="dolpgs-table-title"><?php echo $langs->trans('gp_options_tab_manager'); ?></h3>
            <table class="dolpgs-table">
                <tbody>
                    
                    <?php // TITRES COLONNES TABLEAU ?>
                    <tr class="dolpgs-thead noborderside">
                        <th><?php echo $langs->trans('gp_manager_newparclabel'); ?></th>
                        <th><?php echo $langs->trans('gp_manager_newparcdescription'); ?></th>
                        <th><?php echo $form->textwithpicto($langs->trans('gp_manager_newparctags'),$langs->trans('gp_manager_newparctags_help')); ?></th>
                        <th class="right"><?php echo $form->textwithpicto($langs->trans('gp_manager_newparcposition'),$langs->trans('gp_manager_newparcposition_help')); ?></th>
                        <th class="center"><?php echo $langs->trans('gp_manager_newparcNb'); ?></th>
                        <th class="center"><?php echo $langs->trans('Statut'); ?></th>
                        <th class="right"></th>
                    </tr>
                    <?php // CREATION D'UN NOUVEAU PARC  ?>
                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname"><input class="quatrevingtpercent" type="text" name="gpnewtype-label" <?php if($action == 'edit'): echo 'disabled="disabled"'; endif;?> value="<?php echo GETPOST('gpnewtype-label'); ?>"></td>
                        <td><input class="quatrevingtpercent" type="text" name="gpnewtype-description" <?php if($action == 'edit'): echo 'disabled="disabled"'; endif;?> value="<?php echo GETPOST('gpnewtype-description'); ?>"></td>
                        <td><?php echo $form->multiselectarray('gpnewtype-soctags', $soc_tags, GETPOST('gpnewtype-soctags'), '', 0, '', 0, '100%'); ?></td>
                        <td class="right"><input class="" type="text" name="gpnewtype-position" style="text-align: right;" size="5" <?php if($action == 'edit'): echo 'disabled="disabled"'; endif;?> value="<?php echo (GETPOST('gpnewtype-position'))?GETPOST('gpnewtype-position'):'100'; ?>"></td>
                        <td class="center"><span class="opacitymedium">0</span></td>
                        <td class="center"></td>
                        <td class="right"><input type="submit" class="dolpgs-btn btn-secondary" value="<?php echo $langs->trans('Add'); ?>" <?php if($action == 'edit'): echo 'disabled="disabled"'; endif;?>></td>
                    </tr>
                    <?php foreach($list_parctypes as $parctype_id => $parctype_infos): $gestionparc->fetch_parcType($parctype_id); ?>
                    <tr class="dolpgs-tbody">
                    <?php if($action == 'edit' && GETPOST('rowid','int') == $gestionparc->rowid): ?>
                        <td>
                            <input type="hidden" name="action" value="edit_parctype">
                            <input type="hidden" name="rowid" value="<?php echo $gestionparc->rowid; ?>">
                            <input class="quatrevingtpercent" type="text" name="gpedittype-label" value="<?php echo (GETPOST('gpedittype-label'))?GETPOST('gpedittype-label'):$gestionparc->label; ?>">
                        </td>
                        <td><input class="quatrevingtpercent" type="text" name="gpedittype-description" value="<?php echo (GETPOST('gpedittype-description'))?GETPOST('gpedittype-description'):$gestionparc->description; ?>"></td>
                        <?php $slct_tags_edit = array(); if(!empty($gestionparc->tags)): $slct_tags_edit = array_keys($gestionparc->tags); endif; ?>
                        <td><?php echo $form->multiselectarray('gpedittype-soctags', $soc_tags, (GETPOST('gpedittype-soctags'))?GETPOST('gpedittype-soctags'):$slct_tags_edit, '', 0, '', 0, '100%'); ?></td>
                        
                        <td class="right"><input class="" type="text" name="gpedittype-position" size="5" value="<?php echo (GETPOST('gpedittype-position'))?GETPOST('gpedittype-position'):$gestionparc->position; ?>"></td>
                        <td class="center">0</td>
                        <td class="center"></td>
                        <td class="right">
                            <input type="button" class="dolpgs-btn btn-danger btn-sm" value="<?php echo $langs->trans('Cancel'); ?>" onClick="window.location='<?php echo $_SERVER['PHP_SELF']; ?>'">
                            <input type="submit" class="dolpgs-btn btn-primary btn-sm" value="<?php echo $langs->trans('Save'); ?>"> 
                        </td>
                    <?php else: ?>
                        <td class="bold pgsz-optiontable-fieldname"><?php echo $langs->trans($gestionparc->label); ?></td>
                        <td class="pgsz-optiontable-fielddesc"><?php echo $gestionparc->description; ?></td>
                        <td><?php if(!empty($gestionparc->tags)): echo implode(', ', $gestionparc->tags); else: echo $langs->trans('gp_manager_allsocs'); endif; ?></td>
                        <td class="right"><?php echo $gestionparc->position; ?></td>
                        <td class="center">
                            <?php if(!empty($gestionparc->fields)): echo count($gestionparc->fields); else: echo '0'; endif; ?>
                        </td>
                        <td class="center">
                            <?php if($gestionparc->enabled): echo '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?rowid='.$gestionparc->rowid.'&action=disable_parc&token='.newToken().'">'.img_picto($langs->trans("Activated"), 'switch_on').'</a>';
                            else: echo '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?rowid='.$gestionparc->rowid.'&action=enable_parc&token='.newToken().'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>'; endif; ?>
                        </td>
                        <td class="right">
                            <?php if($action != "edit"): ?>
                                <?php echo '<a class="reposition editfielda paddingrightonly" href="'.$_SERVER['PHP_SELF'].'?rowid='.$gestionparc->rowid.'&action=edit&token='.newToken().'">'.img_edit().'</a> &nbsp; '; ?>
                                <?php echo '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?rowid='.$gestionparc->rowid.'&action=delete&token='.newToken().'">'.img_delete().'</a>'; ?>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </div>


</div>

<?php dol_fiche_end(); llxFooter(); $db->close(); ?>