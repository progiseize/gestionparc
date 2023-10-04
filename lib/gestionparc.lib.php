<?php

dol_include_once('./gestionparc/class/gestionparc.class.php');

//
function GestionParcAdminPrepareHead(){

    global $langs, $db, $conf;

    $langs->load("gestionparc@gestionparc");
    $head = '';

    $gestionparc = new GestionParc($db);
    $list_parctypes = $gestionparc->list_parcType();
    
    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/gestionparc/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("gp_options_tab_setup");
    $head[$h][2] = 'setup';
    $h++;

    $head[$h][0] = dol_buildpath("/gestionparc/admin/manager.php", 1);
    $head[$h][1] = $langs->trans("gp_options_tab_manager");
    $head[$h][2] = 'manager';
    $h++;

    if(!empty($list_parctypes)):
    
        foreach($list_parctypes as $rowid => $parc_type):

            $gestionparc->fetch_parcType($rowid);

            if(!empty($gestionparc->fields)): $nb_items = count($gestionparc->fields); else: $nb_items = 0; endif;

            $head[$h][0] = dol_buildpath("/gestionparc/admin/parc.php?id=".$rowid, 1);
            $head[$h][1] = '<i class="fas fa-caret-right"></i> &nbsp;'.$parc_type['label'].'<span class="badge marginleftonlyshort">'.$nb_items.'</span>';
            $head[$h][2] = 'parc'.$rowid;
            $h++;
        endforeach;

    endif;
    
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'gestionparc');

    return $head;
}

function GestionParcTabs($list_parctypes){

    /*var_dump($list_parctypes);
    var_dump($_SERVER);*/

    $i = 0;
    $tabs = array();
    foreach($list_parctypes as $parctype_key => $parctype_infos):

        $tabs[$i][0] = '#'.$parctype_infos['key']; //dol_buildpath("/gestionparc/admin/manager.php", 1);
        $tabs[$i][1] = $parctype_infos['label']; // $langs->trans("gp_options_tab_manager");
        $tabs[$i][2] = $parctype_infos['key']; // key
        $i++;

    endforeach;
    return $tabs;

}

//
function GestionParcGetFieldsType(){

    global $langs;

    $gp_fields = array();
    
    $gp_fields['customdata']['yearlist'] = $langs->trans('gp_fieldtype_yearlist');
    $gp_fields['customdata']['customlist'] = $langs->trans('gp_fieldtype_customlist');
    $gp_fields['customdata']['textfield'] = $langs->trans('gp_fieldtype_textfield');
    $gp_fields['customdata']['autonumber'] = $langs->trans('gp_fieldtype_autonumber');

    $gp_fields['doldata']['prodserv'] = $langs->trans('gp_fieldtype_prodserv');
    //$gp_fields['doldata']['dblist'] = $langs->trans('gp_fieldtype_dblist');

    return $gp_fields;
}

//
function GestionParcConstructOption($tab,$mode = 'kv',$varselected = '',$isarray = false){

    // kv -> key value
    // vv -> value value

    $listo = '';
    if(!empty($tab)):
        foreach($tab as $kparam => $vparam):

            if($mode == 'kv'): 
                if(!empty($varselected) && !$isarray && $varselected == $kparam): $listo .= '<option value="'.$kparam.'" selected="selected">'.$vparam.'</option>';
                elseif(!empty($varselected) && $isarray && in_array($kparam, $varselected)): $listo .= '<option value="'.$kparam.'" selected="selected">'.$vparam.'</option>';
                else: $listo .= '<option value="'.$kparam.'">'.$vparam.'</option>'; endif;
            elseif($mode == 'vv'): 
                if(!empty($varselected) && !$isarray && $varselected == $vparam): $listo .= '<option value="'.$vparam.'" selected="selected">'.$vparam.'</option>';
                elseif(!empty($varselected) && $isarray && in_array($vparam, $varselected)): $listo .= '<option value="'.$vparam.'" selected="selected">'.$vparam.'</option>';
                else: $listo .= '<option value="'.$vparam.'">'.$vparam.'</option>'; endif; 
            endif;

        endforeach;
    endif;

    return $listo;
}

//
function GestionParcGetFieldParams($field_type,$mode,$editobj = ''){

    global $langs, $db, $conf;

    $form = new Form($db);

    $params = array();

    // TABLEAU DES SLCT
    $tab_sort = array('NO' => $langs->trans('None'),'ASC' => $langs->trans('gp_field_OrderByAsc'),'DESC' => $langs->trans('gp_field_OrderByDesc'));
    $tab_yesno = array('0' => $langs->trans('No'),'1' => $langs->trans('Yes'));

    switch ($field_type):

        case 'dblist':

        
            switch ($mode):
                case 'editfield':
                    $slct_table = GestionParcConstructOption($db->DDLListTables($conf->db->name),'vv',$editobj->params->dblist_table);
                    $tablist_keyval = (GETPOSTISSET($mode.'_param_dblist_keyval'))?GETPOST($mode.'_param_dblist_keyval'):$editobj->params->dblist_keyval;
                    $filter = (GETPOSTISSET($mode.'_param_dblist_filter'))?GETPOST($mode.'_param_dblist_filter'):$editobj->params->dblist_filter;
                    break;                
                case 'newfield':
                    $slct_table = GestionParcConstructOption($db->DDLListTables($conf->db->name),'vv');
                    $tablist_keyval = '';
                    $filter = '';
                    break;
            endswitch;

            $params = array(
                array(
                    'label' => $langs->trans('gp_field_dblist').' <span class="required">*</span>','description' => $langs->transnoentities('gp_field_dblist_desc'),
                    'field' => '<select class="gp-slct-simple" name="'.$mode.'_param_dblist_table" id="'.$mode.'_param_dblist_table" style="min-width:220px">'.$slct_table.'</select>'),
                array(
                    'label' => $langs->trans('gp_field_dblist_keyval').' <span class="required">*</span>','description' => $langs->transnoentities('gp_field_dblist_keyval_desc'),
                    'field' => '<input type="text" name="'.$mode.'_param_dblist_keyval" id="'.$mode.'_param_dblist_keyval" value="'.$tablist_keyval.'">'),
                array(
                    'label' => $langs->trans('gp_field_dblist_filter').'','description' => $langs->transnoentities('gp_field_dblist_filter_desc'),
                    'field' => '<input type="text" name="'.$mode.'_param_dblist_filter" id="'.$mode.'_param_dblist_filter" value="'.$filter.'">'),
                
            );


        break;

        // TYPE 'YEARLIST'
        case 'yearlist':

            // ON SUPPRIME LA VALEUR AUCUN TRI POUR CE TYPE DE CHAMP
            $y_tabsort = $tab_sort; unset($y_tabsort['NO']);            

            switch ($mode):
                case 'editfield':
                    $v_default_value = (GETPOSTISSET($mode.'_default_value'))?GETPOST($mode.'_default_value'):$editobj->default_value;
                    $v_yearstart = (GETPOSTISSET($mode.'_param_yearstart'))?GETPOST($mode.'_param_yearstart'):$editobj->params->yearstart;
                    $v_yearstop = (GETPOSTISSET($mode.'_param_yearstop'))?GETPOST($mode.'_param_yearstop'):$editobj->params->yearstop;
                    $slct_listsort = GestionParcConstructOption($y_tabsort,'kv',(GETPOSTISSET($mode.'_param_yearsort'))?GETPOST($mode.'_param_yearsort'):$editobj->params->yearsort);
                    $slct_yesno = GestionParcConstructOption($tab_yesno,'kv',(GETPOSTISSET($mode.'_param_yearcustom'))?GETPOST($mode.'_param_yearcustom'):$editobj->params->yearcustom);
                    break;
                
                case 'newfield':
                    $v_default_value = GETPOST($mode.'_default_value');
                    $v_yearstart = GETPOST($mode.'_param_yearstart');
                    $v_yearstop = GETPOST($mode.'_param_yearstop');
                    $slct_listsort = GestionParcConstructOption($y_tabsort,'kv',GETPOST($mode.'_param_yearsort'));
                    $slct_yesno = GestionParcConstructOption($tab_yesno,'kv',GETPOST($mode.'_param_yearcustom'));
                    break;
            endswitch;

            $params = array(
                array(
                    'label' => $langs->trans('gp_field_yearstart').' <span class="required">*</span>', 'description' => $form->textwithpicto($langs->transnoentities('gp_field_yearstart_desc'),$langs->transnoentities('gp_field_year_help')),
                    'field' => '<input type="text" name="'.$mode.'_param_yearstart" id="'.$mode.'_param_yearstart" placeholder="'.date('Y').'" value="'.$v_yearstart.'" >'),
                array(
                    'label' => $langs->trans('gp_field_yearstop').' <span class="required">*</span>', 'description' => $form->textwithpicto($langs->transnoentities('gp_field_yearstop_desc'),$langs->transnoentities('gp_field_year_help')),
                    'field' => '<input type="text" name="'.$mode.'_param_yearstop" id="'.$mode.'_param_yearstop" placeholder="'.date('Y').'" value="'.$v_yearstop.'" >'),
                array(
                    'label' => $langs->trans('DefaultValue'),'description' => $form->textwithpicto($langs->transnoentities('gp_field_default_desc'),$langs->transnoentities('gp_field_year_help')),
                    'field' => '<input type="text" name="'.$mode.'_default_value" id="'.$mode.'_default_value" value="'.$v_default_value.'">'),
                array(
                    'label' => $langs->trans('gp_field_sort').' <span class="required">*</span>', 'description' => $langs->trans('gp_field_sort_desc'),
                    'field' => '<select class="gp-slct-simple" name="'.$mode.'_param_yearsort" id="'.$mode.'_param_yearsort">'.$slct_listsort.'</select>'),
                array(
                    'label' => $langs->trans('gp_field_customvalue').' <span class="required">*</span>', 'description' => $langs->trans('gp_field_customvalue_desc'),
                    'field' => '<select class="gp-slct-simple" name="'.$mode.'_param_yearcustom" id="'.$mode.'_param_yearcustom">'.$slct_yesno.'</select>'),
            );
        break;

        // LISTE PERSONNALISEE
        case 'customlist':

            switch ($mode):
                case 'editfield':
                    $v_default_value = (GETPOSTISSET($mode.'_default_value'))?GETPOST($mode.'_default_value'):$editobj->default_value;
                    $listval = (GETPOSTISSET($mode.'_param_listvalues'))?GETPOST($mode.'_param_listvalues'):$editobj->params->listvalues;
                    $slct_customlist = GestionParcConstructOption($listval,'vv',$listval,true);
                    $slct_listsort = GestionParcConstructOption($tab_sort,'kv',(GETPOSTISSET($mode.'_param_listsort'))?GETPOST($mode.'_param_listsort'):$editobj->params->listsort);
                    $slct_yesno = GestionParcConstructOption($tab_yesno,'kv',(GETPOSTISSET($mode.'_param_listcustom'))?GETPOST($mode.'_param_listcustom'):$editobj->params->listcustom);
                    break;
                
                case 'newfield':
                    $v_default_value = GETPOST($mode.'_default_value');
                    $slct_customlist = GestionParcConstructOption(GETPOST($mode.'_param_listvalues'),'vv',GETPOST($mode.'_param_listvalues'),true);
                    $slct_listsort = GestionParcConstructOption($tab_sort,'kv',GETPOST($mode.'_param_listsort'));
                    $slct_yesno = GestionParcConstructOption($tab_yesno,'kv',GETPOST($mode.'_param_listcustom'));
                    break;
            endswitch;

            $params = array(
                array(
                    'label' => $langs->trans('gp_field_customlist'),'description' => $langs->transnoentities('gp_field_customlist_desc'),
                    'field' => '<select multiple="multiple" class="gp-slct-multi-tags" name="'.$mode.'_param_listvalues[]" id="'.$mode.'_param_listvalues" style="min-width:220px">'.$slct_customlist.'</select>'),
                array(
                    'label' => $langs->trans('DefaultValue'),'description' => $langs->transnoentities('gp_field_default_desc'),
                    'field' => '<input type="text" name="'.$mode.'_default_value" id="'.$mode.'_default_value" value="'.$v_default_value.'">'),
                array(
                    'label' => $langs->trans('gp_field_sort').' <span class="required">*</span>', 'description' => $langs->trans('gp_field_sort_desc'),
                    'field' => '<select class="gp-slct-simple" name="'.$mode.'_param_listsort" id="'.$mode.'_param_listsort">'.$slct_listsort.'</select>'),
                array(
                    'label' => $langs->trans('gp_field_customvalue').' <span class="required">*</span>', 'description' => $langs->trans('gp_field_customvalue_desc'),
                    'field' => '<select class="gp-slct-simple" name="'.$mode.'_param_listcustom" id="'.$mode.'_param_listcustom">'.$slct_yesno.'</select>'),
            );
        break;

        // LISTE DE PRODUITS / SERVICE par TAG
        case 'prodserv':

            $cats = $form->select_all_categories('product', $selected = '', 'parent', 64, 0, 1);

            switch ($mode):
                case 'editfield':
                    $v_default_value = (GETPOSTISSET($mode.'_default_value'))?GETPOST($mode.'_default_value'):$editobj->default_value;
                    $listcats = (GETPOSTISSET($mode.'_param_prodservtags'))?GETPOST($mode.'_param_prodservtags'):$editobj->params->prodservtags;
                    $slct_cats = GestionParcConstructOption($cats,'kv',$listcats,true);
                    $slct_yesno = GestionParcConstructOption($tab_yesno,'kv',(GETPOSTISSET($mode.'_param_prodservref'))?GETPOST($mode.'_param_prodservref'):$editobj->params->prodservref);
                    break;
                
                case 'newfield':
                    $v_default_value = GETPOST($mode.'_default_value');                   
                    $slct_cats = GestionParcConstructOption($cats,'kv',GETPOST($mode.'_param_prodservtags'),true);
                    $slct_yesno = GestionParcConstructOption($tab_yesno,'kv',GETPOST($mode.'_param_prodservref'));
                    break;
            endswitch;

            

            $params = array(
                array(
                    'label' => $langs->trans('gp_field_tagslist'),'description' => $langs->transnoentities('gp_field_tagslist_desc'),
                    'field' => '<select multiple="multiple" class="gp-slct-simple" name="'.$mode.'_param_prodservtags[]" id="'.$mode.'_param_prodservtags" style="min-width:220px">'.$slct_cats.'</select>'),
                array(
                    'label' => $langs->trans('DefaultValue'),'description' => $langs->transnoentities('gp_field_proddefault_desc'),
                    'field' => '<input type="text" name="'.$mode.'_default_value" id="'.$mode.'_default_value" value="'.$v_default_value.'">'),
                array(
                    'label' => $langs->trans('gp_field_showprodservref').' <span class="required">*</span>', 'description' => $langs->trans('gp_field_showprodservref_desc'),
                    'field' => '<select class="gp-slct-simple" name="'.$mode.'_param_prodservref" id="'.$mode.'_param_prodservref">'.$slct_yesno.'</select>'),
            );
        break;

        // CHAMP TEXTE
        case 'textfield':

            switch ($mode):
                case 'editfield':
                    $v_default_value = (GETPOSTISSET($mode.'_default_value'))?GETPOST($mode.'_default_value'):$editobj->default_value;                    
                    break;                
                case 'newfield':
                    $v_default_value = GETPOST($mode.'_default_value');                    
                    break;
            endswitch;

            $params = array(
                array(
                    'label' => $langs->trans('DefaultValue'),'description' => $langs->transnoentities('gp_field_default_desc'),
                    'field' => '<input type="text" name="'.$mode.'_default_value" id="'.$mode.'_default_value" value="'.$v_default_value.'">'),
            );
        break;

    endswitch;

    return $params;
}

//
function GestionParcGetListProdServ($tab_cats,$showref = false){

    global $db;

    $tab_prodserv = array();

    $sql = "SELECT rowid, label, ref FROM ".MAIN_DB_PREFIX."product as a";    

    $nbcats = 0;
    if(!empty($tab_cats)):
        $sql .=" LEFT JOIN ".MAIN_DB_PREFIX."categorie_product as b ON a.rowid = b.fk_product";
        $sql .= " WHERE";    
        foreach($tab_cats as $cat_id): $nbcats++;
            if($nbcats > 1): $sql .= " OR"; endif;
            $sql .=" b.fk_categorie = '".$cat_id."'";
        endforeach;
    endif;
    
    $sql .=" ORDER BY label";
    $results_prodserv = $db->query($sql);

    if($results_prodserv): $count_prods = $db->num_rows($result_prods); $i = 0;
        while ($i < $count_prods): $prodserv = $db->fetch_object($result_prods);
            if($prodserv): 
                $labeltoshow = $prodserv->label;
                if($showref): $labeltoshow.= ' ('.$prodserv->ref.')'; endif;
                $tab_prodserv[$prodserv->rowid] = $labeltoshow; 
            endif;
            $i++;
        endwhile;
    endif;

    return $tab_prodserv;
}

//
function GestionParcCheckCookieForSocid($cookie_name,$socid){

    if(isset($_COOKIE[$cookie_name])):
        $cookie = json_decode($_COOKIE[$cookie_name]);
        $cookie = (array) $cookie;
        if(in_array($socid, $cookie)): return true; 
        else: return false; endif;
    endif;
}