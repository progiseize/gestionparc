<?php
/* 
 * Copyright (C) 2018 Anthony Damhet - Doliexperts <anthony.damhet@outlook.fr>
 */

$res=0;
if (! $res && file_exists("../main.inc.php")): $res=@include '../main.inc.php'; endif;
if (! $res && file_exists("../../main.inc.php")): $res=@include '../../main.inc.php'; endif;
if (! $res && file_exists("../../../main.inc.php")): $res=@include '../../../main.inc.php'; endif;

// Protection if external user
if ($user->societe_id > 0): accessforbidden(); endif;


/************************************************
*  FICHIERS NECESSAIRES 
************************************************/
require_once(DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php');
require_once(DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php');
require_once DOL_DOCUMENT_ROOT.'/core/modules/export/export_csv.modules.php';
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
$socid  = GETPOST('socid', 'int');

$societe = new Societe($db);
$societe->fetch($socid);
$object = $societe;
$form = new Form($db);

$soc_cats = $societe->getCategoriesCommon('customer');

$ficheinter = new Fichinter($db);
$gestionparc = new GestionParc($db);
$list_parctypes = $gestionparc->list_parcType();

$parctype = (GETPOSTISSET('parctype'))?GETPOST('parctype'):(reset($list_parctypes))['key'];

$verification = new GestionParcVerif($db);
$last_intervention = $verification->getLastVerif($socid);

// On vérifie si on est en mode verif
$is_mode_verif = false;
$id_mode_verif = $verification->isVerif($socid);
if($id_mode_verif && $id_mode_verif > 0): $is_mode_verif = true;
elseif($id_mode_verif && $id_mode_verif < 0):
    $error++; setEventMessages($langs->trans('gp_verif_error_twice'), null, 'warnings');
endif;

/*******************************************************************
* ACTIONS
********************************************************************/

switch($action):

    case 'initclose_verif':

        if(GETPOST('token') != $_SESSION['token']): $error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings'); endif;
        if(empty(GETPOST('socid'))): $error++; setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings'); endif;

        if(GETPOSTISSET('cancel_verif') && GETPOSTISSET('verif_id') && !$error): 
            if($verification->cancelVerif(GETPOST('verif_id','int'))):
                setEventMessages($langs->trans('gp_verif_success_oncancel'), null, 'mesgs'); $action=''; $is_mode_verif = false;
            endif;
        endif;
    break;

    case 'close_verif':

        require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
        require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

        $verification->nb_verified;
        $verification->nb_total;

        $nb_total_verifs = GETPOST('nb_verif','int');
        $verifs_ok = GETPOST('nbverif_ok','int');
        $verifs_error = GETPOST('nbverif_error','int');

        if(GETPOST('token') != $_SESSION['token']): $error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings'); endif;
        if(empty(GETPOST('socid'))): $error++; setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings'); endif;

        if($verification->nb_verified != $verification->nb_total):
            if(empty(GETPOST('intercom'))): 
                $error++; $action = 'initclose_verif';
                setEventMessages($langs->trans('gp_verif_error_needIntercom'), null, 'errors');
            endif;
        endif;

        $t2s = convertTime2Seconds(GETPOST('durationhour'),GETPOST('durationmin'));
        if($t2s <= 0):
            $error++; $action = 'initclose_verif';
            setEventMessages($langs->trans('gp_verif_error_needDuration'), null, 'errors');
        endif;

        if(!$error):
            if($id_intervention = $verification->closeVerif($verification->rowid,$socid,GETPOST('intercom','restricthtml'),$t2s)):

                $ficheinter->fetch($id_intervention); $last_intervention = $id_intervention;
                $is_mode_verif = false;
                setEventMessages($langs->trans('gp_verif_success_onclose',$ficheinter->ref), null, 'mesgs');
                if($conf->global->MAIN_MODULE_GESTIONPARC_VERIFREDIRECT): 
                    header('Location: '.dol_buildpath('fichinter/card.php?id='.$id_intervention,1));
                endif;
            else: setEventMessages($langs->trans('gp_verif_error_onclose'), null, 'errors');
            endif;
        endif;
    break;

    // Activer le mode verifiation
    case 'mode_verif':

        //$_SESSION['verif_'.$socid] = 'active';
        if(GETPOST('token') != $_SESSION['token']): $error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings'); endif;
        if(empty(GETPOST('socid'))): $error++; setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings'); endif;

        if(!$error): 
            $verif_id = $verification->openVerif($socid);
            $id_mode_verif = $verif_id;
            $is_mode_verif = true;
            $verification->fetch($verif_id);
        endif;
    break;

    // VERIFICATION LIGNE
    case 'set_line_verify':

        if(GETPOST('token') != $_SESSION['token']): $error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings'); endif;
        if(empty(GETPOST('socid'))): $error++; setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings'); endif;
        if(empty(GETPOST('itemid'))): $error++; setEventMessages($langs->trans('gp_error_needItemId'), null, 'warnings'); endif;
        if(empty(GETPOST('parcid'))): $error++; setEventMessages($langs->trans('gp_error_needTypeId'), null, 'warnings'); endif;

        if(!$error):
            $gestionparc->fetch_parcType(GETPOST('parcid'));
            if($verification->setLineCheck($socid,$gestionparc->parc_key,GETPOST('itemid'),1,$verification->rowid)): setEventMessages($langs->trans('gp_verifline_success'), null, 'mesgs');
            else: $error++; setEventMessages($langs->trans('gp_error'), null, 'warnings'); endif;
        endif;
    break;

    // COOKIE
    case 'set_cookie_parc':

        // SI LE COOKIE EXISTE
        if(isset($_COOKIE['gestionparc_empty_views'])):

            $cookie_val = json_decode($_COOKIE['gestionparc_empty_views']);
            $cookie_val = (array) $cookie_val;

            if(GETPOSTISSET('view_empty_parc') && !in_array($socid, $cookie_val) ): 
                array_push($cookie_val, $socid); 
                $cookie_val = json_encode($cookie_val);
                setcookie('gestionparc_empty_views',$cookie_val,time()+(60*60*24*30));

            else: 
                if (($key = array_search($socid, $cookie_val)) !== false): unset($cookie_val[$key]); endif;
                $cookie_val = json_encode($cookie_val);
                setcookie('gestionparc_empty_views',$cookie_val,time()+(60*60*24*30));
            endif;

            header('Location:'.$_SERVER["PHP_SELF"].'?socid='.$socid);

        // SI LE COOKIE N'EXISTE PAS ON LE CREE
        else:

            $cookie_val = array('0',$socid);
            $cookie_val = json_encode($cookie_val);
            setcookie('gestionparc_empty_views',$cookie_val,time()+(60*60*24*30));
            header('Location:'.$_SERVER["PHP_SELF"].'?socid='.$socid);

        endif;
    break;

    // AJOUTER
    case 'add':

        //var_dump($_POST); // ON VERIFIE LES CHAMPS
        if(GETPOST('token') != $_SESSION['token']): $error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings'); endif;
        if(empty(GETPOST('socid'))): $error++; setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings'); endif;
        if(empty(GETPOST('parcid'))): $error++; setEventMessages($langs->trans('gp_error_needTypeId'), null, 'warnings'); endif;

        if(!$error):

            $gestionparc->fetch_parcType(GETPOST('parcid'));

            // ON VERIFIE LES CHAMPS DYNAMIQUES
            foreach($gestionparc->fields as $parcfield):

                if($parcfield->enabled):

                    // ON VERIFIE SI LES CHAMPS OBLIGATOIRES SONT REMPLIS
                    if($parcfield->required && empty(GETPOST('gpfield_'.$parcfield->field_key))):
                        $error++; setEventMessages($langs->trans('ErrorFieldRequired',$parcfield->label), null, 'warnings');
                    endif;

                    // ON VERIFIE SI AUTONUMBER -> non attribué
                    if($parcfield->type == 'autonumber' && !$error):

                        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."gestionparc__".$gestionparc->parc_key;
                        $sql .= " WHERE ".$parcfield->field_key." = ".GETPOST('gpfield_'.$parcfield->field_key);
                        $sql .= " AND socid=".GETPOST('socid');
                        $res = $db->query($sql);
                        if($res->num_rows > 0): $error++; setEventMessages($langs->trans('gp_error_autonumber_exist'), null, 'warnings'); endif;

                    endif;

                endif;

            endforeach;

            // SI PAS D'ERREUR ON CONTINUE
            if(!$error):

                $db->begin();
                $sql_insert = "INSERT INTO ".MAIN_DB_PREFIX."gestionparc__".$gestionparc->parc_key." (socid, author";
                foreach($gestionparc->fields as $parcfield): if($parcfield->enabled):
                    $sql_insert .= ", ".$parcfield->field_key;
                endif; endforeach;
                $sql_insert .= ") VALUES (".GETPOST('socid').", ".$user->id;
                foreach($gestionparc->fields as $parcfield): if($parcfield->enabled):
                    $sql_insert .= ", '".$db->escape(GETPOST('gpfield_'.$parcfield->field_key))."'";
                endif; endforeach;
                $sql_insert .= ")";

                $result_insert = $db->query($sql_insert);

                if($result):                    
                    $db->commit();
                    setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
                    foreach($gestionparc->fields as $parcfield): unset($_POST['gpfield_'.$parcfield->field_key]); endforeach;
                else:
                    $error++; setEventMessages($langs->trans('gp_error'), null, 'warnings');
                    $db->rollback();
                endif;
                

            endif;

        endif;
    break;

    // DUPLICATA
    case 'duplicate':

        // ON VERIFIE LES CHAMPS
        if(GETPOST('token') != $_SESSION['token']): $error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings'); endif;
        if(empty(GETPOST('socid'))): $error++; setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings'); endif;
        if(empty(GETPOST('parcid'))): $error++; setEventMessages($langs->trans('gp_error_needTypeId'), null, 'warnings'); endif;
        if(empty(GETPOST('itemid'))): $error++; setEventMessages($langs->trans('gp_error_needItemId'), null, 'warnings'); endif;

        if(!$error):

            $gestionparc->fetch_parcType(GETPOST('parcid'));

            $db->begin();
            $sql_dup = "INSERT INTO ".MAIN_DB_PREFIX."gestionparc__".$gestionparc->parc_key." (socid, author";
            foreach($gestionparc->fields as $parcfield): 
                $sql_dup .= ", ".$parcfield->field_key;
            endforeach;
            $sql_dup .= ")";
            $sql_dup .= " SELECT '".GETPOST('socid')."', '".$user->id."' ";
            foreach($gestionparc->fields as $parcfield): 

                if($parcfield->type == 'autonumber'):
                    $nxt_autonum = $parcfield->getNextAutoNumber(GETPOST('socid'),$gestionparc->parc_key,$parcfield->field_key);
                    $sql_dup .= ", '".$nxt_autonum."'";
                else:
                    $sql_dup .= ", ".$parcfield->field_key;
                endif;

            endforeach;
            $sql_dup .= " FROM ".MAIN_DB_PREFIX."gestionparc__".$gestionparc->parc_key;
            $sql_dup .= " WHERE rowid = ".GETPOST('itemid');

            $result = $db->query($sql_dup);

            if($result):
                $db->commit(); setEventMessages($langs->trans('gp_duplicate_success'), null, 'mesgs');
            else:
                $error++; setEventMessages($langs->trans('gp_error'), null, 'warnings');
                $db->rollback();
            endif;
        endif;
    break;

    // SUPPRESSION
    case 'confirm_delete':

        $error = 0;

        if(GETPOST('token') != $_SESSION['token']): $error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings'); endif;
        if(empty(GETPOST('socid'))): $error++; setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings'); endif;
        if(empty(GETPOST('itemid'))): $error++; setEventMessages($langs->trans('gp_error_needItemId'), null, 'warnings'); endif;
        if(empty(GETPOST('parcid'))): $error++; setEventMessages($langs->trans('gp_error_needTypeId'), null, 'warnings'); endif;
        if(GETPOST('confirm') != 'yes'): $error++; setEventMessages($langs->trans('gp_error_needActionConfirm'), null, 'warnings'); endif;

        if(!$error):

            $gestionparc->fetch_parcType(GETPOST('parcid'));

            $db->begin();

            $sql = "DELETE FROM ".MAIN_DB_PREFIX."gestionparc__".$gestionparc->parc_key;
            $sql .= " WHERE rowid=".GETPOST('itemid')." AND socid=".GETPOST('socid');

            $result = $db->query($sql);
            if(!$result):
                $error++; setEventMessages($langs->trans('gp_error'), null, 'warnings');
                $db->rollback();
            else:
                setEventMessages($langs->trans('gp_field_delete_success'), null, 'mesgs');
                $db->commit();
            endif;

        endif;
    break;

    // EDITION
    case 'edit':

        $editItem_id = 0; 
        $error = 0;

        // ON VERIFIE LES CHAMPS
        if(GETPOST('token') != $_SESSION['token']): $error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings'); $action = ''; endif;
        if(empty(GETPOST('socid'))): $error++; setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings'); endif;
        if(empty(GETPOST('itemid'))): $error++; setEventMessages($langs->trans('gp_error_needItemId'), null, 'warnings'); endif;
        if(empty(GETPOST('parcid'))): $error++; setEventMessages($langs->trans('gp_error_needTypeId'), null, 'warnings'); endif;

        if(!$error): $editItem_id = GETPOST('itemid'); endif;
    break;

    // EDITION
    case 'edit_item':

        $error = 0;

        if(GETPOST('token') != $_SESSION['token']): $error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings'); endif;
        if(empty(GETPOST('socid'))): $error++; setEventMessages($langs->trans('gp_error_needSocId'), null, 'warnings'); endif;
        if(empty(GETPOST('itemid'))): $error++; setEventMessages($langs->trans('gp_error_needItemId'), null, 'warnings'); endif;
        if(empty(GETPOST('parcid'))): $error++; setEventMessages($langs->trans('gp_error_needTypeId'), null, 'warnings'); endif;

        if(!$error):
            $gestionparc->fetch_parcType(GETPOST('parcid'));
            //var_dump($gestionparc);

            // ON VERIFIE LES CHAMPS DYNAMIQUES
            foreach($gestionparc->fields as $parcfield):

                if($parcfield->enabled):

                    // ON VERIFIE SI LES CHAMPS OBLIGATOIRES SONT REMPLIS
                    if($parcfield->required && empty(GETPOST('gpfield_'.$parcfield->field_key))):
                        $error++; setEventMessages($langs->trans('ErrorFieldRequired',$parcfield->label), null, 'warnings');
                    endif;
                endif;

            endforeach;
        endif;

        // SI IL Y A DES ERREURS, ON RESTE EN MODE EDITION
        if($error): $action = 'edit'; $editItem_id = GETPOST('itemid');
        else:

            $db->begin();

            $sql_update = "UPDATE ".MAIN_DB_PREFIX."gestionparc__".$gestionparc->parc_key;
            $sql_update .= " SET author_maj = '".$user->id."'";
            foreach($gestionparc->fields as $parcfield): if($parcfield->enabled):
                $sql_update .= ", ".$parcfield->field_key." = '".$db->escape(GETPOST('gpfield_'.$parcfield->field_key))."'";
            endif; endforeach;
            $sql_update .= " WHERE rowid = '".GETPOST('itemid')."' AND socid = '".$socid."'";

            $result = $db->query($sql_update);
            if($result):
                $db->commit(); $action = '';
                setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
                foreach($gestionparc->fields as $parcfield): unset($_POST['gpfield_'.$parcfield->field_key]); endforeach;
            else:
                $error++; setEventMessages($langs->trans('gp_error'), null, 'warnings');
                $db->rollback();
            endif;

        endif;
    break;

endswitch;


/*******************************************************************
* PREPARATION DES DONNEES
********************************************************************/

$tabs = array(); $nb_tabs = 0; $abc = '';
foreach($list_parctypes as $parctype_key => $parctype_infos): 

    $gestionparc->fetch_parcType($parctype_key);
    $show_parc = true;

    if($parctype == $parctype_infos['key']): $keyparc = $parctype_key; endif;

    // ON VERIFIE SI ON PEUT AFFICHER LE PARC EST ACTIF
    if(!$gestionparc->enabled): $show_parc = false; endif;

    // ON VERIFIE SI ON PEUT AFFICHER LE PARC SUR CE TYPE DE TIERS
    if(!empty($gestionparc->tags)):
        foreach($gestionparc->tags as $tagid => $taglabel):
            if(!in_array($tagid,$soc_cats)): $show_parc = false; endif;
        endforeach;
    endif;

    // ON VERIFIE S'IL CONTIENT DES CHAMPS
    if(empty($gestionparc->fields)): $show_parc = false; endif;

    // SI ON PEUT AFFICHER
    if($show_parc):

        // ON COMPTE LES LIGNES
        $nb_lines = $gestionparc->getSocParcCount($societe->id,$gestionparc->parc_key);

        // SI ON EST EN MODE VERIF
        if($is_mode_verif):
            $nb_verifs = $gestionparc->getSocParcCount($societe->id,$gestionparc->parc_key,true);
            $label_count = $nb_verifs.' / '.$nb_lines;
        else: $label_count = $nb_lines;
        endif;

        // ON AJOUTE LE LIEN
        $tabs[$nb_tabs][0] = $_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype_infos['key']; //dol_buildpath("/gestionparc/admin/manager.php", 1);
        $tabs[$nb_tabs][1] = $parctype_infos['label'].' <span class="badge marginleftonlyshort">'.$label_count.'</span>'; // $langs->trans("gp_options_tab_manager");
        $tabs[$nb_tabs][2] = $parctype_infos['key']; // key
        $nb_tabs++;

    endif;

endforeach;

if($keyparc):
    $parc = $gestionparc->fetch_parcType($keyparc,true);
    $parc_lines = $gestionparc->getSocParcContent($societe->id,$parc->parc_key);
endif;





/***************************************************
* VIEW
****************************************************/

llxHeader('',$societe->name.' - '.$langs->trans('gp_clientparc'),'','','','',array("/gestionparc/assets/js/gestionparc.js"),array("/gestionparc/assets/css/gestionparc.css"));

// ACTIONS NECESSITANT LE HEADER
if ($action == 'delete'):
    $error = 0;
    if(GETPOST('token') != $_SESSION['token']): $error++; setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings'); endif;
    if(!$error): echo $form->formconfirm($_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parctype.'&itemid='.GETPOST('itemid').'&parcid='.GETPOST('parcid'),$langs->trans('gp_confirmDeleteTitle'),$langs->trans('gp_confirmDelete'),'confirm_delete','','',1,0,500,0); endif;
endif;

// AFFICHAGE DES ONGLETS THIRDPARTY
$head = societe_prepare_head($societe, $user);
echo dol_get_fiche_head($head, 'gestionparc', $langs->trans("ThirdParty"),1,'company');
?>

<div id="pgsz-option" class="tabBar pgsz-theme-<?php echo $conf->theme; ?>">

    <?php dol_banner_tab($societe, 'socid', '', ($user->societe_id?0:1), 'rowid', 'nom'); ?>

    <?php // AFFICHAGE CODES CLIENT & FOURNISSEUR ?>
    <div class="fichecenter">
        <div class="underbanner clearboth"></div>
        <table class="border centpercent tableforfield">
            <?php if ($societe->client && !empty($societe->code_client)): ?>
                <tr>
                    <td class="titlefield"><?php echo $langs->trans('CustomerCode'); ?></td>
                    <td><?php echo $societe->code_client; ?>
                    <?php $tmpcheck = $societe->check_codeclient(); if ($tmpcheck != 0 && $tmpcheck != -5): ?>
                        <font class="error">(<?php echo $langs->trans("WrongCustomerCode")?>)</font>
                    <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php if ($societe->fournisseur && !empty($societe->code_fournisseur)): ?>
                <tr>
                    <td class="titlefield"><?php echo $langs->trans('SupplierCode'); ?></td>
                    <td><?php echo $societe->code_fournisseur; ?>
                    <?php $tmpcheck = $societe->check_codefournisseur(); if ($tmpcheck != 0 && $tmpcheck != -5): ?>
                        <font class="error"><?php echo $langs->trans("WrongSupplierCode"); ?>)</font>
                    <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            
            <?php if($last_intervention): $ficheinter->fetch($last_intervention); ?>
                <tr>
                    <td><?php echo $langs->trans('gp_client_lastverif'); ?></td>
                    <td><a href="<?php echo dol_buildpath('fichinter/card.php?id='.$ficheinter->id,1); ?>"><?php echo $ficheinter->ref; ?></a></td>
                </tr>
            <?php endif; ?>
            <?php if($conf->global->MAIN_MODULE_GESTIONPARC_USEVERIF): ?>
            <tr>
                <td valign="top"><?php echo $langs->trans('gp_client_verifmode'); ?></td>
                <td>
                    <form enctype="multipart/form-data" action="<?php print $_SERVER["PHP_SELF"]; ?>?socid=<?php echo $societe->id; ?>&parctype=<?php echo $parctype; ?>" method="POST">
                        
                        <input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
                        <?php if($is_mode_verif): ?>

                            <input type="hidden" name="verif_id" value="<?php echo $id_mode_verif; ?>">

                            <?php if($action == 'initclose_verif'): ?>

                                <?php if(!$conf->global->MAIN_MODULE_GESTIONPARC_VERIFUSETIME): ?>
                                    <div style="font-weight: bold;text-decoration: underline;margin-bottom: 3px"><?php echo $langs->trans('InterDuration'); ?><span class="required">*</span></div>
                                    <div style="margin-bottom: 8px;"><?php echo $form->select_duration('duration',(!GETPOST('durationhour', 'int') && !GETPOST('durationmin', 'int')) ? 3600 : (60 * 60 * GETPOST('durationhour', 'int') + 60 * GETPOST('durationmin', 'int')),0,'select'); ?></div>
                                <?php else: ?>
                                    <input type="hidden" name="durationhour" value="<?php echo $conf->global->MAIN_MODULE_GESTIONPARC_VERIFUSETIME; ?>">
                                    <input type="hidden" name="durationmin" value="0">
                                <?php endif; ?>
                                        
                                    
                                <div style="font-weight: bold;text-decoration: underline;margin-bottom: 3px">
                                    <?php echo $langs->trans('gp_verifcom'); ?>
                                    <?php if($verification->nb_verified != $verification->nb_total): ?>
                                        <span class="required">*</span>
                                    <?php endif; ?>
                                </div>
                                <div><textarea name="intercom" class="minwidth400 minheight" style="min-height: 96px;"><?php echo GETPOST('intercom') ?></textarea></div>
                                <input type="hidden" name="action" value="close_verif">
                                <input type="submit" name="" value="<?php echo $langs->trans('gp_verif_close'); ?>" class="pgsz-button-submit">
                            <?php else: ?>
                                <?php if($verification->nb_verified == $verification->nb_total): $class_sub = "pgsz-button-submit"; else: $class_sub = "pgsz-button-submit not-full"; endif; ?>
                                <input type="hidden" name="action" value="initclose_verif">
                                <input type="submit" name="close" value="<?php echo $langs->trans('gp_verif_close'); ?>" class="<?php echo $class_sub; ?>">
                                <input type="submit" name="cancel_verif" value="<?php echo $langs->trans('gp_verif_cancel'); ?>" class="pgsz-button-cancel">
                            <?php endif; ?>
                        <?php else: ?>
                            <input type="hidden" name="action" value="mode_verif">
                            <input type="submit" name="" value="<?php echo $langs->trans('gp_verif_open'); ?>" class="pgsz-button-submit">
                        <?php endif; ?>

                    </form>
                </td>
            </tr>
            <?php endif; ?>

        </table>
        <div class="clearboth"></div>
    </div>

    <div id="pgsz-option"><br/>

        <?php echo dol_fiche_head($tabs,$parctype); // ON AFFICHE LES TABS ?>

        <?php if(!empty($parc->description)): ?>
            <div class="justify opacitymedium" style="margin-bottom: 16px;"><span class="fas fa-info-circle  em088 opacityhigh" style=" vertical-align: middle;" title="Information"></span> <?php echo $parc->description; ?></div>
        <?php endif; ?>


        <form enctype="multipart/form-data" action="<?php print $_SERVER["PHP_SELF"]; ?>?socid=<?php echo $societe->id; ?>&parctype=<?php echo $parctype; ?>" method="POST" id="" class="<?php echo $parc_class; ?>">
                    
            <input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
            <input type="hidden" name="parcid" value="<?php echo $parc->rowid; ?>">

            <table class="noborder centpercent pgsz-option-table gestionparc-table" style="border-top:none;" id="gestionparc-table-<?php echo $gestionparc->rowid; ?>">
                <tbody>

                    <?php // NOM DES COLONNES ?>
                    <tr class="liste_titre pgsz-optiontable-coltitle">
                        <?php if($is_mode_verif): ?>
                            <th><?php echo $langs->trans('gp_verif_label'); ?></th>
                        <?php endif; ?>
                        <?php foreach($parc->fields as $parcfield_key => $parcfield): if($parcfield->enabled): ?>
                            <th><?php echo $parcfield->label; if($parcfield->required): echo ' <span class="required">*</span>'; endif; ?></th>
                        <?php endif; endforeach; ?>
                        <th class="right">
                            <?php if($action != 'edit'): ?>
                                <button class="pgsz-button-submit gestionparc-add" onclick="event.preventDefault();"><i class="fas fa-plus"></i></button>
                            <?php endif; ?>
                        </th>
                    </tr>

                    <?php // AJOUTER LIGNES ?>
                            <?php if($action != 'edit'): ?>
                    <tr class="oddeven pgsz-optiontable-tr gestionparc-newline" <?php if($action == "add" && $error && GETPOST('parcid') == $parc->rowid): echo 'style="display: table-row;"'; endif; ?>>
                        <?php if($is_mode_verif): ?><td></td><?php endif; ?>
                        <?php foreach($parc->fields as $parcfield_key => $parcfield): if($parcfield->enabled): ?>
                        <td><?php echo $parcfield->construct_field($parc,$societe->id); ?></td>
                        <?php endif; endforeach; ?>
                        <td class="right">
                            <input type="hidden" name="action" value="add">
                            <input type="submit" value="<?php echo $langs->trans('Add') ?>" class="pgsz-button-submit">
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach($parc_lines as $lineid => $linecontent): ?>
                    <tr class="pgsz-optiontable-tr gestionparc-line <?php if($is_mode_verif && $linecontent->verif): echo 'parcline-ok'; endif; ?>">

                        <?php if($is_mode_verif): ?>
                            <td>
                                <?php if($action != "edit" || $action == "edit" && $editItem_id != $linecontent->rowid):
                                    if($linecontent->verif): echo img_picto($langs->trans("Activated"), 'switch_on');
                                    else: echo '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?socid='.$societe->id.'&parctype='.$parctype.'&itemid='.$linecontent->rowid.'&action=set_line_verify&parcid='.$parc->rowid.'&token='.newToken().'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
                                    endif; 
                                endif; ?>
                            </td>                                     
                        <?php endif; ?>

                        <?php foreach($parc->fields as $parcfield_key => $parcfield): if($parcfield->enabled): ?>
                            <td class="pgsz-optiontable-fielddesc"><?php 

                            // SI ON EST EN MODE EDITION
                            if($action == 'edit' && $editItem_id == $linecontent->rowid):

                                echo '<span class="gp-infos-label">'.$parcfield->label.' : </span>';

                                if($parcfield->type == 'autonumber'): 
                                    echo $linecontent->{$parcfield->field_key};
                                    echo '<input type="hidden" name="gpfield_'.$parcfield->field_key.'" id="gpfield_'.$parcfield->field_key.'" value="'.$linecontent->{$parcfield->field_key}.'">';
                                else: echo $parcfield->construct_field($parc,$societe->id,$linecontent->{$parcfield->field_key});
                                endif;

                            // MODE AFFICHAGE
                            else:

                                echo '<span class="gp-infos-label">'.$parcfield->label.' : </span>';

                                // SI ON DOIT RETROUVER UN PRODUIT
                                if($parcfield->type == 'prodserv'):
                                     
                                    if(!empty($linecontent->{$parcfield->field_key})):
                                        $prodserv = new Product($db);
                                        $check_prodserv = $prodserv->fetch($linecontent->{$parcfield->field_key});
                                        if($check_prodserv): echo '<a href="'.dol_buildpath('product/card.php?id='.$linecontent->{$parcfield->field_key},1).'" >'.$prodserv->label.'</a>';
                                        else: echo $langs->trans('gp_product_unknown');
                                        endif;
                                    endif;

                                // SI DBLIST PERSO
                                elseif($parcfield->type == 'dblist'):
                                    if(!empty($linecontent->{$parcfield->field_key})):
                                        $l_content = $parc->getContentForDbList($linecontent->{$parcfield->field_key},$parcfield->params);
                                        if($l_content): echo $l_content; endif;
                                    endif;
                                     
                                // ON AFFICHE LA VALEUR DU CHAMP
                                else: echo $linecontent->{$parcfield->field_key};
                                endif; 

                            endif;


                            ?>                                        
                            </td>
                        <?php endif; endforeach; ?>

                        <td width="120" class="right">
                            <?php if($action != "edit"): ?>
                                <div>
                                <?php echo '<a class="reposition gp-duplicate" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&action=duplicate&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&token='.newToken().'"><i class="fas fa-clone"></i></a> &nbsp; '; ?>
                                <?php echo '<a class="reposition editfielda paddingrightonly" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&action=edit&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&token='.newToken().'">'.img_edit().'</a> &nbsp; '; ?>
                                <?php echo '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&parctype='.$parc->parc_key.'&action=delete&itemid='.$linecontent->rowid.'&parcid='.$parc->rowid.'&token='.newToken().'">'.img_delete().'</a>'; ?>
                            </div>
                            <?php elseif($action == "edit" && GETPOST('itemid') == $linecontent->rowid): ?>
                                <input type="hidden" name="action" value="edit_item">
                                <input type="hidden" name="itemid" value="<?php echo $linecontent->rowid; ?>">
                                <input type="submit" class="button buttongen button-save" value="<?php echo $langs->trans('Save'); ?>">                                
                                <input type="button" class="button buttongen button-save" value="<?php echo $langs->trans('Cancel'); ?>" onClick="window.location='<?php echo $_SERVER['PHP_SELF']; ?>?socid=<?php echo $socid; ?>&parctype=<?php echo $parc->parc_key; ?>'">
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>





                </tbody>
            </table>

        </form>

        

        <?php /*// POUR CHAQUE TYPE DE PARC ?>
        <?php foreach($list_parctypes as $parctype_key => $parctype_infos): 

            $gestionparc->fetch_parcType($parctype_key); 

            // ON VERIFIE SI ON PEUT AFFICHER LE PARC SUR CE TYPE DE TIERS
            $show_parc = true;
            if(!empty($gestionparc->tags)):
                foreach($gestionparc->tags as $tagid => $taglabel):
                    if(!in_array($tagid,$soc_cats)): $show_parc = false; endif; 
                endforeach;
            endif;

            // ON VERIFIE S'IL CONTIENT DES CHAMPS
            if(empty($gestionparc->fields)): $show_parc = false; endif;

            // ON CALCULE LA TAILLE DU TABLEAU
            $table_size = 1;
            foreach($gestionparc->fields as $f): if($f->enabled):$table_size++;endif; endforeach;

            if($is_mode_verif):$table_size++;endif;

            // ON RECUPERE LES ENTREES
            $lines = $gestionparc->getSocParcContent($societe->id,$gestionparc->parc_key);
            //if(empty($lines)): $show_parc = false; endif;

            // CLASS PARC
            $parc_class = '';
            if(empty($lines)): 
                $parc_class = 'gp-parc-empty';
                if($isCookieParc && !$is_mode_verif): $parc_class .= ' gp-parc-visible'; endif;
            endif;

            // SI ON PEUT AFFICHER
            if($show_parc): ?>
                 <form enctype="multipart/form-data" action="<?php print $_SERVER["PHP_SELF"]; ?>?socid=<?php echo $societe->id; ?>" method="POST" id="" class="<?php echo $parc_class; ?>">
                    
                    <input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>">
                    <input type="hidden" name="parcid" value="<?php echo $parctype_key; ?>">

                    <table class="noborder centpercent pgsz-option-table gestionparc-table" style="border-top:none;" id="gestionparc-table-<?php echo $gestionparc->rowid; ?>">
                        <tbody>

                            <?php // TITRE ?>
                            <tr class="titre" style="background:#fff;">
                                <td class="nobordernopadding valignmiddle col-title" style="" colspan="<?php echo $table_size - 1; ?>">
                                    <div class="titre inline-block" style="padding:16px 0"><?php echo $langs->trans($gestionparc->label).' <span class="opacitymedium colorblack paddingleft" style="font-size:0.8em;">('.count($lines).')</span>'; ?></div>
                                </td>
                                <td class="right gp-eye-icon">
                                    <i class="fas fa-eye-slash"></i>
                                </td>
                            </tr>

                            <?php // NOM DES COLONNES ?>
                            <tr class="liste_titre pgsz-optiontable-coltitle">
                                <?php if($is_mode_verif): ?>
                                    <th>Vérification</th>
                                <?php endif; ?>
                                <?php foreach($gestionparc->fields as $parcfield_key => $parcfield): if($parcfield->enabled): ?>
                                    <th><?php echo $parcfield->label; if($parcfield->required): echo ' <span class="required">*</span>'; endif; ?></th>
                                <?php endif; endforeach; ?>
                                <th class="right">
                                    <?php if($action != 'edit'): ?>
                                        <button class="pgsz-button-submit gestionparc-add" onclick="event.preventDefault();"><i class="fas fa-plus"></i></button>
                                    <?php endif; ?>
                                </th>
                            </tr>

                            <?php // AJOUTER LIGNES ?>
                            <?php if($action != 'edit'): ?>
                            <tr class="oddeven pgsz-optiontable-tr gestionparc-newline" <?php if($action == "add" && $error && GETPOST('parcid') == $gestionparc->rowid): echo 'style="display: table-row;"'; endif; ?>>
                                <?php if($is_mode_verif): ?><td></td><?php endif; ?>
                                <?php foreach($gestionparc->fields as $parcfield_key => $parcfield): if($parcfield->enabled): ?>
                                <td><?php echo $parcfield->construct_field($gestionparc,$societe->id); ?></td>
                                <?php endif; endforeach; ?>
                                <td class="right">
                                    <input type="hidden" name="action" value="add">
                                    <input type="submit" value="<?php echo $langs->trans('Add') ?>" class="pgsz-button-submit">
                                </td>
                            </tr>
                            <?php endif; ?>

                            <?php // CONTENU LIGNES ?>
                            <?php foreach($lines as $lineid => $linecontent): ?>
                                <tr class="pgsz-optiontable-tr gestionparc-line <?php if($is_mode_verif && $linecontent->verif): echo 'parcline-ok'; endif; ?>">

                                    <?php if($is_mode_verif): ?>
                                        <td>
                                            <?php if($action != "edit" || $action == "edit" && $editItem_id != $linecontent->rowid):
                                                if($linecontent->verif): echo img_picto($langs->trans("Activated"), 'switch_on');
                                                else: echo '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?socid='.$societe->id.'&itemid='.$linecontent->rowid.'&action=set_line_verify&parcid='.$parctype_key.'&token='.newToken().'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
                                                endif; 
                                            endif; ?>
                                        </td>                                     
                                    <?php endif; ?>

                                    <?php foreach($gestionparc->fields as $parcfield_key => $parcfield): if($parcfield->enabled): ?>
                                        <td class="pgsz-optiontable-fielddesc"><?php 

                                        // SI ON EST EN MODE EDITION
                                        if($action == 'edit' && $editItem_id == $linecontent->rowid):

                                            echo '<span class="gp-infos-label">'.$parcfield->label.' : </span>';

                                            if($parcfield->type == 'autonumber'): 
                                                echo $linecontent->{$parcfield->field_key};
                                                echo '<input type="hidden" name="gpfield_'.$parcfield->field_key.'" id="gpfield_'.$parcfield->field_key.'" value="'.$linecontent->{$parcfield->field_key}.'">';
                                            else: echo $parcfield->construct_field($gestionparc,$societe->id,$linecontent->{$parcfield->field_key});
                                            endif;

                                        // MODE AFFICHAGE
                                        else:

                                            echo '<span class="gp-infos-label">'.$parcfield->label.' : </span>';

                                            // SI ON DOIT RETROUVER UN PRODUIT
                                            if($parcfield->type == 'prodserv'):
                                                 
                                                if(!empty($linecontent->{$parcfield->field_key})):
                                                    $prodserv = new Product($db);
                                                    $check_prodserv = $prodserv->fetch($linecontent->{$parcfield->field_key});
                                                    if($check_prodserv): echo '<a href="'.dol_buildpath('product/card.php?id='.$linecontent->{$parcfield->field_key},1).'" >'.$prodserv->label.'</a>';
                                                    else: echo $langs->trans('gp_product_unknown');
                                                    endif;
                                                endif;

                                            // SI DBLIST PERSO
                                            elseif($parcfield->type == 'dblist'):
                                                if(!empty($linecontent->{$parcfield->field_key})):
                                                    $l_content = $gestionparc->getContentForDbList($linecontent->{$parcfield->field_key},$parcfield->params);
                                                    if($l_content): echo $l_content; endif;
                                                endif;
                                                 
                                            // ON AFFICHE LA VALEUR DU CHAMP
                                            else: echo $linecontent->{$parcfield->field_key};
                                            endif; 

                                        endif;


                                        ?>                                        
                                        </td>
                                    <?php endif; endforeach; ?>
                                    <td width="120" class="right">
                                        <?php if($action != "edit"): ?>
                                            <div>
                                            <?php echo '<a class="reposition gp-duplicate" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&action=duplicate&itemid='.$linecontent->rowid.'&parcid='.$parctype_key.'&token='.newToken().'"><i class="fas fa-clone"></i></a> &nbsp; '; ?>
                                            <?php echo '<a class="reposition editfielda paddingrightonly" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&action=edit&itemid='.$linecontent->rowid.'&parcid='.$parctype_key.'&token='.newToken().'">'.img_edit().'</a> &nbsp; '; ?>
                                            <?php echo '<a class="reposition" href="'.$_SERVER['PHP_SELF'].'?socid='.$socid.'&action=delete&itemid='.$linecontent->rowid.'&parcid='.$parctype_key.'&token='.newToken().'">'.img_delete().'</a>'; ?>
                                        </div>
                                        <?php elseif($action == "edit" && GETPOST('itemid') == $linecontent->rowid): ?>
                                            <input type="hidden" name="action" value="edit_item">
                                            <input type="hidden" name="itemid" value="<?php echo $linecontent->rowid; ?>">
                                            <input type="submit" class="button buttongen button-save" value="<?php echo $langs->trans('Save'); ?>">                                
                                            <input type="button" class="button buttongen button-save" value="<?php echo $langs->trans('Cancel'); ?>" onClick="window.location='<?php echo $_SERVER['PHP_SELF']; ?>?socid=<?php echo $socid; ?>'">
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                 </form>
            <?php endif; ?>
        <?php endforeach;*/ ?>     
        

    </div>

</div>

<?php llxFooter(); $db->close(); ?>