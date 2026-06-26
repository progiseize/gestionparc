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
$langs->loadLangs(array("gestionparc@gestionparc"));
$langs->load("gestionparc@gestionparc"); // Force reload

// Protection if external user
if ($user->socid > 0) : accessforbidden();
endif;
if (!$user->hasRight('gestionparc','parc','setup')) : accessforbidden();
endif;

// Provisionne l'extrafield societe "Référentiel de conformité" (upgrade-safe, idempotent)
$gpsetup = new GestionParcVerif($db);
$gpsetup->ensureSocieteExtrafields();

// Ajoute la colonne report_snapshot à la table des vérifs (instantané des données à la clôture)
$gpsetup->ensureVerifSnapshotColumn();

// Ajoute la colonne manual_position aux tables par-parc (tri par défaut sur la numérotation)
$gpparc = new GestionParc($db);
$gpparc->ensureManualPositionColumn();

/*******************************************************************
* FONCTIONS
********************************************************************/

/*******************************************************************
* ACTIONS
********************************************************************/
$action = GETPOST('action');

if ($action == 'set_options') :

    $error = 0;
    $db->begin();

    $gestionparc = new GestionParc($db);

    // ON VERIFIE LE TOKEN
    if(GETPOST('token') == $_SESSION['token']) :

        dolibarr_set_const($db, "MAIN_MODULE_GESTIONPARC_VERIFUSETIME", GETPOST('gp-verifusetime'), 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, "GESTIONPARC_ADVANCED_EXPORT_LINESPLIT", GETPOST('GESTIONPARC_ADVANCED_EXPORT_LINESPLIT','int'), 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, "GESTIONPARC_DEFAULT_VIEW", GETPOST('gp-default-view', 'alpha'), 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, "GESTIONPARC_VERIF_MODE", GETPOST('gp-verif-mode', 'alpha'), 'chaine', 0, '', $conf->entity);

        // Si l'option en cochée
        if(GETPOSTISSET('gp-use-verif')) :
            dolibarr_set_const($db, "MAIN_MODULE_GESTIONPARC_USEVERIF", true, 'chaine', 0, '', $conf->entity);
            $extras_fichinter = $extrafields->fetch_name_optionals_label('fichinter');

            if(!array_key_exists('gestionparc_isverif', $extras_fichinter)) :
                $extrafields->addExtraField('gestionparc_isverif', 'gp_extrafieldFichInter_isverif', 'int', '100', '', 'fichinter', 0, 0, 'null', '', 0, '', '0', '', '', '', 'gestionparc@gestionparc');
            endif;

            // Extrafields commercial / intervenant (création ou migration) + backfill
            $verifsetup = new GestionParcVerif($db);
            $verifsetup->ensureInterventionExtrafields();

            if(!$gestionparc->setVerifMode('add')) : $error++;
            endif;

            if(!$conf->ficheinter->enabled) :
                $res_act = activateModule('modFicheinter');
                setEventMessages($langs->trans('gp_modFicheInterEnabled'), null, 'mesgs');
            endif;
        else:
            dolibarr_set_const($db, "MAIN_MODULE_GESTIONPARC_USEVERIF", false, 'chaine', 0, '', $conf->entity);
            if(!$gestionparc->setVerifMode('remove')) : $error++;
            endif;

            $dir = DOL_DATA_ROOT.'/gestionparc';
            if (!is_dir($dir)) :
                if(!mkdir($dir, 0755)) : $error++; setEventMessages($langs->trans('gp_error_creafolder'), null, 'errors');
                endif;
            endif;

        endif;


        if(!$error) :$db->commit(); setEventMessages($langs->trans('gp_setup_saved'), null, 'mesgs');
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

llxHeader('', $langs->transnoentities('Setup').' :: '.$langs->transnoentities('Module300320Name'), '', '', '', '', $array_js, $array_css, '', 'gestionparc setup'); ?>

<div class="dolpgs-main-wrapper">

    <h1 class="has-before"><?php echo $langs->transnoentities('gp_options_setup_pagetitle'); ?></h1>
    <?php $head = GestionParcAdminPrepareHead(); dol_fiche_head($head, 'setup', 'GestionParc', 1, 'fa-boxes_fas_#fb2a52'); ?>

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
                            <input type="checkbox" name="gp-use-verif" <?php if($conf->global->MAIN_MODULE_GESTIONPARC_USEVERIF) : ?>checked="checked"<?php
                           endif; ?> />
                        </td>
                    </tr>

                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname" valign="top"><?php echo $langs->trans('gp_setup_verif_mode'); ?></td>
                        <td class="pgsz-optiontable-fielddesc "><?php echo $langs->transnoentities('gp_setup_verif_mode_desc'); ?></td>
                        <td class="right pgsz-optiontable-field ">
                            <select name="gp-verif-mode" class="flat">
                                <option value="instant" <?php echo (getDolGlobalString('GESTIONPARC_VERIF_MODE') == 'instant' || !getDolGlobalString('GESTIONPARC_VERIF_MODE')) ? 'selected' : ''; ?>><?php echo $langs->trans('gp_setup_verif_mode_instant'); ?></option>
                                <option value="manual" <?php echo (getDolGlobalString('GESTIONPARC_VERIF_MODE') == 'manual') ? 'selected' : ''; ?>><?php echo $langs->trans('gp_setup_verif_mode_manual'); ?></option>
                            </select>
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
                            <?php echo ajax_constantonoff('MAIN_MODULE_GESTIONPARC_VERIFREDIRECT'); ?>
                        </td>
                    </tr>
                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname" valign="top"><?php echo $langs->trans('gp_setup_verif_details'); ?></td>
                        <td class="pgsz-optiontable-fielddesc "><?php echo $langs->transnoentities('gp_setup_verif_details_desc'); ?></td>
                        <td class="right pgsz-optiontable-field ">
                            <?php echo ajax_constantonoff('MAIN_MODULE_GESTIONPARC_VERIFDETAILS'); ?>
                        </td>
                    </tr>
                    <?php if(getDolGlobalInt('MAIN_MODULE_GESTIONPARC_USEVERIF')): ?>
                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname" valign="top"><?php echo $langs->trans('gp_setup_verif_allow_verifall'); ?></td>
                        <td class="pgsz-optiontable-fielddesc "><?php echo $langs->transnoentities('gp_setup_verif_allow_verifall_desc'); ?></td>
                        <td class="right pgsz-optiontable-field ">
                            <?php echo ajax_constantonoff('GESTIONPARC_VERIF_ALLOW_VERIFALL'); ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname" valign="top"><?php echo $langs->trans('gp_setup_default_view'); ?></td>
                        <td class="pgsz-optiontable-fielddesc "><?php echo $langs->transnoentities('gp_setup_default_view_desc'); ?></td>
                        <td class="right pgsz-optiontable-field ">
                            <select name="gp-default-view" class="flat">
                                <option value="cards" <?php echo (getDolGlobalString('GESTIONPARC_DEFAULT_VIEW') == 'cards' || !getDolGlobalString('GESTIONPARC_DEFAULT_VIEW')) ? 'selected' : ''; ?>><?php echo $langs->trans('GestionParcCardView'); ?></option>
                                <option value="list" <?php echo (getDolGlobalString('GESTIONPARC_DEFAULT_VIEW') == 'list') ? 'selected' : ''; ?>><?php echo $langs->trans('GestionParcListView'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname" valign="top"><?php echo $langs->trans('gp_setup_useAdvancedExport'); ?></td>
                        <td class="pgsz-optiontable-fielddesc "><?php echo $langs->transnoentities('gp_setup_useAdvancedExportDesc'); ?></td>
                        <td class="right pgsz-optiontable-field ">
                            <?php echo ajax_constantonoff('GESTIONPARC_ADVANCED_EXPORT'); ?>
                        </td>
                    </tr>
                    <?php if(getDolGlobalInt('GESTIONPARC_ADVANCED_EXPORT')): ?>
                    <tr class="dolpgs-tbody">
                        <td class="bold pgsz-optiontable-fieldname" valign="top"><?php echo $langs->trans('gp_setup_AdvancedExportLineSet'); ?></td>
                        <td class="pgsz-optiontable-fielddesc "><?php echo $langs->transnoentities('gp_setup_AdvancedExportLineSetDesc'); ?></td>
                        <td class="right pgsz-optiontable-field ">
                            <input type="number" min="0" step="1" name="GESTIONPARC_ADVANCED_EXPORT_LINESPLIT" value="<?php echo getDolGlobalInt('GESTIONPARC_ADVANCED_EXPORT_LINESPLIT'); ?>">
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="right">
                <input type="submit" class="dolpgs-btn btn-primary" name="" value="<?php print $langs->trans('Save'); ?>">
            </div>

        </form>
    </div>
</div>


<?php dol_fiche_end(); llxFooter(); $db->close(); ?>
