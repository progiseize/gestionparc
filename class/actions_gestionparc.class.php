<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
dol_include_once('/gestionparc/class/gestionparc.class.php');

class ActionsGestionParc
{

    public $error;
    public $errors;
    public $results = array();
    public $resprints = '';

    /**
     * Execute action completeTabsHead
     *
     * @param  array        $parameters  Array of parameters
     * @param  CommonObject $object      The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param  string       $action      'add', 'update', 'view'
     * @param  Hookmanager  $hookmanager hookmanager
     * @return int                             <0 if KO,
     *                                          =0 if OK but we want to process standard actions too,
     *                                          >0 if OK and we want to replace standard actions.
     */
    public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $conf, $user,$db;

        // ON CHARGE LE FICHIER LANGUE
        $langs->load('gestionparc@gestionparc');

        // ON RECUPERE LE TYPE D'ELEMENT SUR LEQUEL ON EST
        $element = isset($parameters['object']->element) ? $parameters['object']->element : '';

        // SI ON EST SUR UN TIERS
        if ($element == 'societe' && $parameters['mode'] == 'add' && $parameters['filterorigmodule'] == 'external') {
            $nbItems = 0;
            $gestionparc = new GestionParc($db);
            $listParctypes = $gestionparc->list_parcType();

            foreach ($listParctypes as $parctypeID => $parctypeInfos) {
                $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."gestionparc__".$parctypeInfos['key']." WHERE socid = ".$object->id;
                $res = $db->query($sql);
                $nbItems += $res->num_rows;
            }

            $newtab = array();
            $newtab[0] = dol_buildpath('/gestionparc/tabs/gestionparc.php?socid='.$object->id, 1);
            $newtab[1] = $langs->trans('gp_clientparc').' <span class="badge marginleftonlyshort">'.$nbItems.'</span>';
            $newtab[2] = 'gestionparc';

            // On stocke le head modifié dans $this->results et on retourne 1
            $this->results = $parameters['head'];
            $this->results[] = $newtab;
            
            return 1;
        }
        return 0;
    }

    public function replaceThirdparty(&$parameters, &$object, &$action, $hookmanager)
    {

        global $langs, $conf, $user, $db;

        // ON CHARGE LE FICHIER LANGUE
        $langs->load('gestionparc@gestionparc');

        $contexts = explode(':', $parameters['context']);

        if (in_array('thirdpartycard', $contexts) && $action == 'confirm_merge') {

            $soc_origin = $parameters['soc_origin'];
            $soc_dest = $parameters['soc_dest'];
            $error = 0;

            $gestionparc = new GestionParc($db);
            $result_mergeparcs = $gestionparc->mergeParcs($soc_origin, $soc_dest);

            $verif = new GestionParcVerif($db);
            $result_mergeverifs = $verif->mergeVerifs($soc_origin, $soc_dest);

            if ($result_mergeparcs < 0) {
                $error++;
            }
            if ($result_mergeverifs < 0) {
                $error++;
            }

            if (!$error) {
                if ($result_mergeparcs > 0) {
                    setEventMessages($langs->trans('gp_mergeParcSuccess', $result_mergeparcs), null, 'mesgs');
                }
                if ($result_mergeverifs > 0) {
                    setEventMessages($langs->trans('gp_mergeVerifSuccess', $result_mergeverifs), null, 'mesgs');
                }
                return 1;
            } else {
                setEventMessages($langs->trans('gp_mergeError'), null, 'errors'); return -1;
            }
        }
    }



}

?>
