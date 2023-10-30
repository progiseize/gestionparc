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

/*******************************************************************
* CHECKS
********************************************************************/
if ($user->socid > 0): accessforbidden(); endif;
if (!$user->rights->gestionparc->configurer): accessforbidden(); endif;

$rowid = GETPOST('id','int'); if(empty($rowid)): header('Location: '.$dolibarr_main_url_root.'/custom/gestionparc/admin/manager'); endif;

$gestionparc = new GestionParc($db);

$check_id = $gestionparc->fetch_parcType($rowid);
if($check_id <= 0): header('Location: '.$dolibarr_main_url_root.'/custom/gestionparc/admin/manager'); endif;


/*******************************************************************
* VARIABLES
********************************************************************/
$form = new Form($db);
$langs->load("admin");
$langs->load("gestionparc@gestionparc");

$gestionparc_fields = GestionParcGetFieldsType();

/*******************************************************************
* ACTIONS
********************************************************************/

$action = GETPOST('action');

switch ($action):

    // ACTIVER CHAMP
    case 'enable_field':

        if(GETPOST('token') == $_SESSION['token']):

            $error = 0;

            // IDENTIFIANT DU CHAMP
            $field_id = GETPOST('field_id','int');
            if(empty($field_id)): $error++; setEventMessages($langs->trans('gp_error_needId'), null, 'warnings'); endif;

            // ON MET A JOUR LE CHAMP
            if(!$error):
                if($gestionparc->setFieldStatus($field_id, true)): setEventMessages($langs->trans('gp_parcfield_activated'), null, 'mesgs');
                else: setEventMessages($langs->trans('gp_error'), null, 'errors');
                endif;
            endif;
        else:
            setEventMessages("SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry", null, 'warnings');
        endif;
    break;

    // DESACTIVER CHAMP
    case 'disable_field':

        if(GETPOST('token') == $_SESSION['token']):
            $error = 0;

            // IDENTIFIANT DU CHAMP
            $field_id = GETPOST('field_id','int');
            if(empty($field_id)): $error++; setEventMessages($langs->trans('gp_error_needId'), null, 'warnings'); endif;

            // ON MET A JOUR LE CHAMP
            if(!$error):
                if($gestionparc->setFieldStatus($field_id, '0')): setEventMessages($langs->trans('gp_parcfield_disactivated'), null, 'mesgs');
                else: setEventMessages($langs->trans('gp_error'), null, 'errors');
                endif;
            endif;
        else:
            setEventMessages("SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry", null, 'warnings');
        endif;
    break;

    // SUPPRIMER UN CHAMP
    case 'confirm_delete':
        
        if(GETPOST('token') == $_SESSION['token']):

            $error = 0;

            // IDENTIFIANT DU CHAMP
            $field_id = GETPOST('field_id','int');
            if(empty($field_id)): $error++; setEventMessages($langs->trans('gp_error_needId'), null, 'warnings'); endif;
            if(!$error):
                if($gestionparc->removeField($field_id, $user)): setEventMessages($langs->trans('gp_parcfield_delete_success'), null, 'mesgs');
                else: setEventMessages($langs->trans('gp_error'), null, 'errors');
                endif;
            endif;

        else:
            setEventMessages("SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry", null, 'warnings');
        endif;
    break;

    // PREPARER L'AJOUT D'UN CHAMP
    case 'prepare_parcfield':

        // ON VERIFIE LE TOKEN
        if(GETPOST('token') == $_SESSION['token']):

            $error = 0;

            // IDENTIFIANT DU CHAMP
            $field_type = GETPOST('gpnewfield_type','alpha');
            if(empty($field_type)): $error++; setEventMessages($langs->trans('gp_parcfield_new_needType'), null, 'errors'); endif;

        else: $error++;setEventMessages("SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry", null, 'warnings');
        endif;
    break;

    // PREPARER L'EDITION D'UN CHAMP
    case 'edit':

        // ON VERIFIE LE TOKEN
        if(GETPOST('token') == $_SESSION['token']):

            // IDENTIFIANT DU CHAMP
            $field_id = GETPOST('field_id','int');
            if(empty($field_id)): $error++; setEventMessages($langs->trans('gp_error_needId'), null, 'errors'); endif;

            $field_to_update = new GestionParcField($db);
            $field_to_update->fetch_parcField($field_id);

        else: $error++;setEventMessages("SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry", null, 'warnings');
        endif;
    break;

    // AJOUTER / EDITER UN CHAMP
    case 'edit_parcfield':
    case 'add_parcfield':

        if(GETPOST('token') == $_SESSION['token']):

            $error = 0;
            $gpf = new GestionParcField($db);

            if($action == 'add_parcfield'): 

                $fieldname = 'newfield';
                $field_type = GETPOST($fieldname.'_type','alpha');
                if(empty($field_type)):$error++; setEventMessages($langs->trans('gp_fieldtype_unknown'), null, 'errors'); endif;
                $gpf->parc_id = $gestionparc->rowid;
                $gpf->type = $field_type;

            elseif($action == 'edit_parcfield'): 

                $fieldname = 'editfield';

                // IDENTIFIANT DU CHAMP
                $field_id = GETPOST('field_id','int');
                if(empty($field_id)): $error++; setEventMessages($langs->trans('gp_error_needId'), null, 'errors'); endif;

                //$field_to_update = new GestionParcField($db);
                $gpf->fetch_parcField($field_id);
                $field_to_update = $gpf;
                $field_type = $gpf->type;
                $gpf->old_label = $gpf->label;

            endif;

            // VERIFICATIONS COMMUNES            
            if(empty(GETPOST($fieldname.'_label','alpha'))): $error++; setEventMessages($langs->trans('ErrorFieldRequired',$langs->transnoentities('Label')), null, 'errors'); endif; 
            if(empty(GETPOST($fieldname.'_position','int'))): $newfield_position = 100; else: $newfield_position = GETPOST($fieldname.'_position','int'); endif;

            
            $gpf->label = GETPOST($fieldname.'_label','alpha');            
            $gpf->required = (GETPOSTISSET($fieldname.'_required'))?1:0;
            $gpf->default_value = GETPOST($fieldname.'_default_value','alpha');
            $gpf->position = $newfield_position;
            if(GETPOSTISSET($fieldname.'_onlyverif') && GETPOST($fieldname.'_onlyverif','aZ09') == 'on'): $gpf->only_verif = 1;
            else: $gpf->only_verif = 0;
            endif;
            
            switch ($field_type):

                case 'autonumber': $gpf->required = true; break;

                case 'dblist':
                    if(empty(GETPOST($fieldname.'_param_dblist_table'))): $error++; setEventMessages($langs->trans('ErrorFieldRequired',$langs->transnoentities('gp_field_dblist')), null, 'errors'); endif;
                    if(empty(GETPOST($fieldname.'_param_dblist_keyval'))): $error++; setEventMessages($langs->trans('ErrorFieldRequired',$langs->transnoentities('gp_field_dblist_keyval')), null, 'errors'); endif;
                
                    if(!$error):

                        // ON CONSTRUIT LE TABLEAU DES PARAMETRES
                        $gpf->params = array(
                            'dblist_table' => GETPOST($fieldname.'_param_dblist_table'),
                            'dblist_keyval' => GETPOST($fieldname.'_param_dblist_keyval'),
                            'dblist_filter' => GETPOST($fieldname.'_param_dblist_filter'),
                        );

                    endif;

                break;

                case 'yearlist':

                    // VERIFICATIONS
                    if(empty(GETPOST($fieldname.'_param_yearstart'))): $error++; setEventMessages($langs->trans('ErrorFieldRequired',$langs->transnoentities('gp_field_yearstart')), null, 'errors'); endif;
                    if(empty(GETPOST($fieldname.'_param_yearstop'))): $error++; setEventMessages($langs->trans('ErrorFieldRequired',$langs->transnoentities('gp_field_yearstop')), null, 'errors'); endif;
                    if(empty(GETPOST($fieldname.'_param_yearsort'))): $error++; setEventMessages($langs->trans('ErrorFieldRequired',$langs->transnoentities('gp_field_yearsort')), null, 'errors'); endif;

                    if(!$error):

                        // ON CONSTRUIT LE TABLEAU DES PARAMETRES
                        $gpf->params = array(
                            'yearstart' => GETPOST($fieldname.'_param_yearstart'),
                            'yearstop' => GETPOST($fieldname.'_param_yearstop'),
                            'yearsort' => GETPOST($fieldname.'_param_yearsort'),
                            'yearcustom' => GETPOST($fieldname.'_param_yearcustom'),
                        );

                    endif;
                break;

                case 'customlist':

                    // VERIFICATIONS
                    if(empty(GETPOST($fieldname.'_param_listvalues'))): $error++; setEventMessages($langs->trans('ErrorFieldRequired',$langs->transnoentities('gp_field_listvalues')), null, 'errors'); endif;
                    if(empty(GETPOST($fieldname.'_param_listsort'))): $error++; setEventMessages($langs->trans('ErrorFieldRequired',$langs->transnoentities('gp_field_listsort')), null, 'errors'); endif;

                    if(!$error):

                        // ON CONSTRUIT LE TABLEAU DES PARAMETRES
                        $gpf->params = array(
                            'listvalues' => GETPOST($fieldname.'_param_listvalues'),
                            'listsort' => GETPOST($fieldname.'_param_listsort'),
                            'listcustom' => GETPOST($fieldname.'_param_listcustom'),
                        );
                    endif;
                break;

                case 'prodserv':
                    $gpf->params =  array(
                        'prodservtags' => GETPOST($fieldname.'_param_prodservtags'),
                        'prodservref' => GETPOST($fieldname.'_param_prodservref')
                    );
                break;

            endswitch;

            if(!$error):
                if($action == 'add_parcfield' && $gpf->add_parcField($user)): setEventMessages($langs->trans('gp_addparcfield_success'), null, 'mesgs');
                elseif($action == 'edit_parcfield' && $gpf->update_parcField($user)): setEventMessages($langs->trans('gp_updateparcfield_success'), null, 'mesgs');
                else: setEventMessages($langs->trans('gp_error'), null, 'errors'); $error++; var_dump($gpf->db->lasterror);
                endif;
            endif;
        else:
            setEventMessages("SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry", null, 'warnings');
        endif;
    break;
    
endswitch;


/***************************************************
* VIEW
****************************************************/
//ON RECHARGE LE PARC
$gestionparc->fetch_parcType($rowid);

$array_js = array('/gestionparc/assets/js/gestionparc.js');
$array_css = array('/gestionparc/assets/css/gestionparc.css','/gestionparc/assets/css/dolpgs.css');

llxHeader('',$gestionparc->label.' :: '.$langs->transnoentities('Module300320Name'),'','','','',$array_js,$array_css,'','gestionparc parc-manager');

// ACTIONS NECESSITANT LE HEADER
if ($action == 'delete'):
    echo $form->formconfirm($_SERVER['PHP_SELF'].'?id='.GETPOST('id','int').'&field_id='.GETPOST('field_id','aZ09'),$langs->trans('gp_confirm'),$langs->trans('gp_confirmDeleteParcField'),'confirm_delete','','',1,0,500,0);
endif;

?>
    
<div class="dolpgs-main-wrapper">

    <h1 class="has-before"><?php echo $langs->transnoentities('Module300320Name').' : '.$gestionparc->label; ?></h1>
    <?php $head = GestionParcAdminPrepareHead(); dol_fiche_head($head, 'parc'.$rowid,'GestionParc', 1,'fa-boxes_fas_#fb2a52'); ?>

    <div class="tabBar">
        <?php if(!empty($gestionparc->description)): ?>
            <div class="justify opacitymedium"><?php print img_info().' '.$gestionparc->description; ?></div>
        <?php endif; ?>

        <table class="dolpgs-table">
            <tbody>                

                <tr class="titre" style="background:#fff">
                    <td class="nobordernopadding valignmiddle col-title" style="" colspan="4">
                        <div class="titre inline-block">
                             <h3 class="dolpgs-table-title"><?php echo $langs->trans('gp_parc_titlepage',$langs->trans($gestionparc->label)); ?></h3>
                        </div>
                    </td>
                    <td colspan="5" class="right">
                        <form enctype="multipart/form-data" action="<?php print $_SERVER["PHP_SELF"]; ?>?id=<?php echo $rowid; ?>" method="POST" id="gpform-addfieldtype">
                            <input type="hidden" name="action" value="prepare_parcfield">
                            <input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">

                            <select name="gpnewfield_type" class="gp-slct-simple" data-placeholder="<?php echo $langs->trans('gp_newparcfieldType') ?>">
                                <option></option>
                                <?php foreach ($gestionparc_fields as $groupkey => $fields): ?>
                                    <optgroup label="<?php echo $langs->trans('gp_fieldtype_group_'.$groupkey); ?>">
                                        <?php foreach ($fields as $fieldkey => $fieldlabel): ?>
                                            <option value="<?php echo $fieldkey; ?>"><?php echo $fieldlabel; ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <!-- <input type="submit" name="" value="Ajouter"> -->
                            <button type="submit" class="dolpgs-btn btn-primary btn-sm" style=""><i class="fas fa-plus"></i> </button>
                        </form>
                    </td>
                </tr>

                <tr class="dolpgs-thead noborderside">
                    <th><?php echo $langs->trans('Field'); ?></th>
                    <th><?php echo $langs->trans('Type'); ?></th>
                    <th><?php echo $langs->trans('DefaultValue'); ?></th>
                    <th class="right"><?php echo $form->textwithpicto($langs->trans('Preview'),$langs->trans('gp_parc_col_fieldpreview_help')); ?></th>
                    <th class="right"><?php echo $langs->trans('Required'); ?></th>                    
                    <th class="right"><?php echo $langs->trans('gp_parcfield_on_onlyverif'); ?></th>
                    <th class="right"><?php echo $langs->trans('Position'); ?></th>
                    <th class="center"><?php echo $langs->trans('Statut'); ?></th>
                    <th width="120" class="center"></th>
                </tr>

                <?php if(!empty($gestionparc->fields)): foreach ($gestionparc->fields as $field): ?>
                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname"><?php echo $langs->trans($field->label); if($field->required): echo ' <span class="required">*</span>'; endif; ?></td>
                        <td><?php echo $langs->trans('gp_fieldtype_'.$field->type); ?></td>
                        <td><?php echo $field->default_value; ?></td>
                        <td class="right pgsz-optiontable-field"><?php echo $field->construct_field($gestionparc); ?></td>
                        <td class="right"><?php echo ($field->required)?$langs->trans('Yes'):$langs->trans('No'); ?></td>
                        <td class="right"><?php echo $field->only_verif?$langs->trans('Yes'):$langs->trans('No'); ?></td>
                        <td class="right"><?php echo $field->position; ?></td> 
                        <td class="center">
                            <?php if($field->enabled): echo '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?id='.$gestionparc->rowid.'&field_id='.$field->rowid.'&action=disable_field&token='.newToken().'">'.img_picto($langs->trans("Activated"), 'switch_on').'</a>';
                            else: echo '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?id='.$gestionparc->rowid.'&field_id='.$field->rowid.'&action=enable_field&token='.newToken().'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>'; endif; ?>
                        </td>
                        <td width="120" class="center">
                            <?php echo '<a class="reposition editfielda paddingrightonly" href="'.$_SERVER['PHP_SELF'].'?id='.$gestionparc->rowid.'&field_id='.$field->rowid.'&action=edit&token='.newToken().'">'.img_edit().'</a> &nbsp; '; ?>
                            <?php echo '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?id='.$gestionparc->rowid.'&field_id='.$field->rowid.'&action=delete&token='.newToken().'">'.img_delete().'</a>'; ?>
                        </td>
                    </tr>                
                <?php endforeach; endif; ?>           


            </tbody>
        </table>

        <?php // AJOUTER UN NOUVEAU CHAMP ?>
        <?php if($action == 'prepare_parcfield' && !$error || $action == 'add_parcfield' && $error): $field_params = GestionParcGetFieldParams($field_type,'newfield'); ?>
        <form enctype="multipart/form-data" action="<?php print $_SERVER["PHP_SELF"]; ?>?id=<?php echo $rowid; ?>" method="POST" style="margin-top: 42px;">
            <input type="hidden" name="action" value="add_parcfield">
            <input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
            <input type="hidden" name="newfield_type" value="<?php echo $field_type; ?>">
            
            <h3 class="dolpgs-table-title"><?php echo $langs->trans('gp_parc_addfieldtitle',$langs->transnoentities('gp_fieldtype_'.$field_type)); ?></h3>
            <table class="dolpgs-table">
                <tbody>

                    <tr class="dolpgs-thead noborderside">
                        <th><?php echo $langs->trans('Parameter'); ?></th>
                        <th><?php echo $langs->trans('Description'); ?></th>
                        <th class="right"><?php echo $langs->trans('Value'); ?></th>
                    </tr>

                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname"><?php echo $langs->trans('Label'); ?> <span class="required">*</span></td>
                        <td class="pgsz-optiontable-fielddesc"><?php echo $langs->trans('LabelOrTranslationKey'); ?></td>
                        <td class="right pgsz-optiontable-field"><input type="text" name="newfield_label" placeholder="<?php echo $langs->trans('Label'); ?>" value="<?php echo GETPOST('newfield_label'); ?>"></td>
                    </tr>
                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname"><?php echo $langs->trans('Position'); ?></td>
                        <td class="pgsz-optiontable-fielddesc"><?php echo $langs->trans('gp_field_position_desc'); ?></td>
                        <td class="right pgsz-optiontable-field"><input type="number" min="1" max="999" name="newfield_position" placeholder="100" value="<?php echo (GETPOST('newfield_position','int'))?GETPOST('newfield_position','int'):'100'; ?>"></td>
                    </tr>
                    <?php if($field_type != 'autonumber'): ?>
                        <tr class="dolpgs-tbody">
                            <td class="bold pgsz-optiontable-fieldname"><?php echo $langs->trans('Required'); ?></td>
                            <td class="pgsz-optiontable-fielddesc"><?php echo $langs->trans('gp_field_required_desc'); ?></td>
                            <td class="right pgsz-optiontable-field"><input type="checkbox" name="newfield_required" <?php if(GETPOST('newfield_required')): echo 'checked="checked"'; endif; ?>></td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($field_params as $fp): ?>
                    <tr class="dolpgs-tbody" valign="top">
                        <td class="bold pgsz-optiontable-fieldname"><?php echo $fp['label']; ?></td>
                        <td class="pgsz-optiontable-fielddesc"><?php echo $fp['description']; ?></td>
                        <td class="right pgsz-optiontable-field"><?php echo $fp['field']; ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname"><?php echo $langs->trans('gp_field_onlyverif'); ?></td>
                        <td class="pgsz-optiontable-fielddesc"><?php echo $langs->trans('gp_field_onlyverif_desc'); ?></td>
                        <td class="right pgsz-optiontable-field"><input type="checkbox" name="newfield_onlyverif" <?php if(GETPOST('newfield_onlyverif')): echo 'checked="checked"'; endif; ?>></td>
                    </tr>

                </tbody>
            </table>
            <div class="right">
                <input type="submit" name="" class="dolpgs-btn btn-primary btn-sm" value="<?php echo $langs->trans('Add'); ?>">
            </div>
        </form>
        <?php elseif($action == 'edit' && !$error || $action == 'edit_parcfield' && $error): $field_params = GestionParcGetFieldParams($field_to_update->type,'editfield',$field_to_update); ?>
        <form enctype="multipart/form-data" action="<?php print $_SERVER["PHP_SELF"]; ?>?id=<?php echo $rowid; ?>" method="POST" style="margin-top: 42px;">
            <input type="hidden" name="action" value="edit_parcfield">
            <input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
            <input type="hidden" name="field_id" value="<?php echo $field_to_update->rowid; ?>">

            <h3 class="dolpgs-table-title"><?php echo $langs->trans('gp_parc_editfieldtitle',$langs->transnoentities($field_to_update->label)); ?></h3>
            <table class="dolpgs-table">
                <tbody>
                    <tr class="dolpgs-thead noborderside">
                        <th><?php echo $langs->trans('Parameter'); ?></th>
                        <th><?php echo $langs->trans('Description'); ?></th>
                        <th class="right"><?php echo $langs->trans('Value'); ?></th>
                    </tr>

                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname"><?php echo $langs->trans('Label'); ?> <span class="required">*</span></td>
                        <td class="pgsz-optiontable-fielddesc"><?php echo $langs->trans('LabelOrTranslationKey'); ?></td>
                        <td class="right pgsz-optiontable-field"><input type="text" name="editfield_label" placeholder="<?php echo $langs->trans('Label'); ?>" value="<?php echo (GETPOSTISSET('editfield_label'))?GETPOST('editfield_label'):$field_to_update->label; ?>"></td>
                    </tr>
                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname"><?php echo $langs->trans('Position'); ?></td>
                        <td class="pgsz-optiontable-fielddesc"><?php echo $langs->trans('gp_field_position_desc'); ?></td>
                        <td class="right pgsz-optiontable-field"><input type="number" min="1" max="999" name="editfield_position" placeholder="100" value="<?php echo (GETPOST('editfield_position','int'))?GETPOST('editfield_position','int'):$field_to_update->position; ?>"></td>
                    </tr>
                    <?php if($field_to_update->type != 'autonumber'): ?>
                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname"><?php echo $langs->trans('Required'); ?></td>
                        <td class="pgsz-optiontable-fielddesc"><?php echo $langs->trans('gp_field_required_desc'); ?></td>
                        <td class="right pgsz-optiontable-field"><input type="checkbox" name="editfield_required" <?php if(GETPOST('editfield_required') || $field_to_update->required): echo 'checked="checked"'; endif; ?>></td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($field_params as $fp): ?>
                    <tr class="dolpgs-tbody" valign="top">
                        <td class="bold pgsz-optiontable-fieldname"><?php echo $fp['label']; ?></td>
                        <td class="pgsz-optiontable-fielddesc"><?php echo $fp['description']; ?></td>
                        <td class="right pgsz-optiontable-field"><?php echo $fp['field']; ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname"><?php echo $langs->trans('gp_field_onlyverif'); ?></td>
                        <td class="pgsz-optiontable-fielddesc"><?php echo $langs->trans('gp_field_onlyverif_desc'); ?></td>
                        <td class="right pgsz-optiontable-field"><input type="checkbox" name="editfield_onlyverif" <?php if(GETPOST('editfield_onlyverif') || $field_to_update->only_verif): echo 'checked="checked"'; endif; ?>></td>
                    </tr>
                    
                </tbody>
            </table>
            <div class="right">
                <input type="submit" name="" class="dolpgs-btn btn-primary btn-sm" value="<?php echo $langs->trans('Update'); ?>">
            </div>

            <?php //var_dump($field_params); ?>
            </form>
        <?php endif; ?>
    </div>
    
</div>

<?php dol_fiche_end(); llxFooter(); $db->close(); ?>

