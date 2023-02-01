<?php
/* Copyright (C) 2021      Progiseize <a.damhet@progiseize.fr> *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';


class box_gestionparc extends ModeleBoxes
{
    var $boxcode = "boxgestionparc";
    var $boximg = "object_projectpub";
    var $boxlabel= "gp_boxNb_title";
    var $depends = array("gestionparc");

    var $db;
    var $param;

    var $info_box_head = array();
    var $info_box_contents = array();


    /**
     *  Constructor
     *
     *  @param  DoliDB  $db         Database handler
     *  @param  string  $param      More parameters
     */
    function __construct($db,$param)
    {
        global $user;

        $this->db=$db;
    }

    /**
     *  Load data into info_box_contents array to show array later.
     *
     *  @param  int     $max        Maximum number of records to load
     *  @return void
     */
    function loadBox($max=5)
    {
        global $conf, $user, $langs, $db;

        $this->max=$max;

        dol_include_once('./gestionparc/class/gestionparc.class.php');

        $gestionparc = new GestionParc($db);
        $parc_types = $gestionparc->list_parcType();

        $this->info_box_head = array('text' => $langs->trans('gp_boxNb_title'));

        $i = 0;
        $this->info_box_contents[$i][0] = array('td' => 'class="bold"','text' => $langs->trans('gp_boxNb_parcType'));
        $this->info_box_contents[$i][1] = array('td' => 'class="bold right"','text' => $langs->trans('gp_boxNb_countParcs'));
        $this->info_box_contents[$i][2] = array('td' => 'class="bold right"','text' => $langs->trans('gp_boxNb_lastEntry'));
        $this->info_box_contents[$i][3] = array('td' => 'class="bold right"','text' => $langs->trans('gp_boxNb_countItems'));
        $i++;

        foreach($parc_types as $parc_key => $parc_infos):
            
            $nb_items = $gestionparc->count_parcItems($parc_infos['key']);
            $nb_socs = $gestionparc->count_parcSoc($parc_infos['key']);
            $last_entry = $gestionparc->get_lastParc($parc_infos['key']);

            $this->info_box_contents[$i][0] = array('text' => $parc_infos['label']);
            $this->info_box_contents[$i][1] = array('td' => 'class="right"','text' => $nb_socs);
            $this->info_box_contents[$i][2] = array('td' => 'class="right"','text' => $last_entry['name'],'url'=> $last_entry['url']);
            $this->info_box_contents[$i][3] = array('td' => 'class="right"','text' => $nb_items);

            $i++;

        endforeach;
    
        
    }

    /**
     *  Method to show box
     *
     *  @param  array   $head       Array with properties of box title
     *  @param  array   $contents   Array with properties of box lines
     *  @param  int     $nooutput   No print, only return string
     *  @return string
     */
    function showBox($head = null, $contents = null, $nooutput=0)
    {
        return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
    }

}