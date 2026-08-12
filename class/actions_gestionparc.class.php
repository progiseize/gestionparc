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

        // SI ON EST SUR UNE INTERVENTION : onglet des photos de la vérification
        if ($element == 'fichinter' && $parameters['mode'] == 'add' && $parameters['filterorigmodule'] == 'external') {
            // Droit dédié, désactivé par défaut : seul l'admin le voit sans attribution
            if (!$user->admin && !$user->hasRight('gestionparc', 'photo', 'read')) {
                return 0;
            }

            dol_include_once('/gestionparc/class/gestionparcphoto.class.php');
            $gpverif = new GestionParcVerif($db);
            $verif_id = $gpverif->getVerifIdByFichinter($object->id);
            if (empty($verif_id)) return 0;

            $gpphoto = new GestionParcPhoto($db);
            $nbPhotos = count($gpphoto->listByVerif($verif_id));
            if (empty($nbPhotos)) return 0; // pas d'onglet vide sur les interventions sans cliché

            $newtab = array();
            $newtab[0] = dol_buildpath('/gestionparc/tabs/photos.php?id='.$object->id, 1);
            $newtab[1] = $langs->trans('gp_photo_label').' <span class="badge marginleftonlyshort">'.$nbPhotos.'</span>';
            $newtab[2] = 'gestionparc_photos';

            // L'onglet se place juste après "Contact intervention", pas en fin de barre.
            // Repli en dernière position si cet onglet est masqué (droits, config).
            $head = $parameters['head'];
            $position = count($head);
            foreach ($head as $i => $tab) {
                if (isset($tab[2]) && $tab[2] == 'contact') {
                    $position = $i + 1;
                    break;
                }
            }
            array_splice($head, $position, 0, array($newtab));

            $this->results = $head;

            return 1;
        }

        return 0;
    }

    /**
     * Hook on fichinter card: handle gp_export_excel / gp_export_pdf actions.
     * Generates the file and saves it to the fichinter document directory (no download).
     */
    public function doActions(&$parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $conf, $user, $db;

        $contexts = explode(':', $parameters['context']);
        if (!in_array('interventioncard', $contexts)) return 0;

        if (!isModEnabled('gestionparc') || !getDolGlobalInt('MAIN_MODULE_GESTIONPARC_USEVERIF')) return 0;

        if ($action !== 'gp_export_excel' && $action !== 'gp_export_pdf') return 0;

        $langs->load('gestionparc@gestionparc');

        if (GETPOST('token') != $_SESSION['token']) {
            setEventMessages($langs->trans('SecurityTokenHasExpiredSoActionHasBeenCanceledPleaseRetry'), null, 'warnings');
            return 0;
        }

        $fichinter_id = (int) $object->id;
        dol_include_once('gestionparc/class/gestionparc.class.php');
        $verification = new GestionParcVerif($db);

        if ($action === 'gp_export_excel') {
            $file_path = $verification->generateExcelExport($fichinter_id);
        } else {
            $file_path = $verification->generatePDFExport($fichinter_id);
        }

        if ($file_path && file_exists($file_path)) {
            setEventMessages($langs->trans('gp_export_success'), null, 'mesgs');
        } else {
            setEventMessages($langs->trans('gp_error'), null, 'errors');
        }

        header('Location: '.dol_buildpath('fichinter/card.php?id='.$fichinter_id, 1));
        exit;
    }

    /**
     * Hook on fichinter card: inject the Export Verif button + modal when a
     * closed GestionParc verification is linked to the fichinter.
     */
    public function addMoreActionsButtons(&$parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $conf, $user, $db;

        $contexts = explode(':', $parameters['context']);
        if (!in_array('interventioncard', $contexts)) return 0;

        if (!isModEnabled('gestionparc') || !getDolGlobalInt('MAIN_MODULE_GESTIONPARC_USEVERIF')) return 0;

        $langs->load('gestionparc@gestionparc');

        // Only show button if this fichinter has a closed GestionParc verification
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."gestionparc_verifs"
             . " WHERE fichinter_id = ".(int)$object->id." AND is_close = 1"
             . " ORDER BY rowid DESC LIMIT 1";
        $res = $db->query($sql);
        if (!$res || $res->num_rows == 0) return 0;

        $excel_url = dol_buildpath('fichinter/card.php', 1).'?id='.$object->id.'&action=gp_export_excel&token='.newToken();
        $pdf_url   = dol_buildpath('fichinter/card.php', 1).'?id='.$object->id.'&action=gp_export_pdf&token='.newToken();

        // --- CSS (not loaded on fichinter card by default) ---
        print '<link rel="stylesheet" href="'.dol_buildpath('/gestionparc/assets/css/gestionparc.css', 1).'">';

        // --- Export button ---
        print '<div class="inline-block divButAction">';
        print '<a class="butAction gp-open-export-modal" href="javascript:void(0)"'
            . ' data-excel-url="'.dol_escape_htmltag($excel_url).'"'
            . ' data-pdf-url="'.dol_escape_htmltag($pdf_url).'">';
        print '<i class="fa fa-file-export"></i> '.dol_escape_htmltag($langs->trans('Export'));
        print '</a>';
        print '</div>';

        // --- Modal ---
        print '<div id="gp-export-modal-overlay" class="gp-modal-overlay" style="display:none;">';
        print '  <div class="gp-modal-container">';
        print '    <div class="gp-modal-header">';
        print '      <h3>'.dol_escape_htmltag($langs->trans('gp_export_choice_title')).'</h3>';
        print '      <span class="gp-modal-close">&times;</span>';
        print '    </div>';
        print '    <div class="gp-modal-body">';
        print '      <p class="gp-modal-desc"><i class="fa fa-info-circle"></i> '.dol_escape_htmltag($langs->trans('gp_export_choice_desc')).'</p>';
        print '      <div class="gp-modal-cards">';
        print '        <a href="#" id="gp-fichinter-btn-excel" class="gp-modal-card excel">';
        print '          <i class="fas fa-file-excel"></i><span>'.dol_escape_htmltag($langs->trans('ExportExcel')).'</span>';
        print '        </a>';
        print '        <a href="#" id="gp-fichinter-btn-pdf" class="gp-modal-card pdf">';
        print '          <i class="fas fa-file-pdf"></i><span>'.dol_escape_htmltag($langs->trans('ExportPDF')).'</span>';
        print '        </a>';
        print '      </div>';
        print '    </div>';
        print '    <div class="gp-modal-footer">';
        print '      <button class="button gp-modal-close-btn">'.dol_escape_htmltag($langs->trans('Cancel')).'</button>';
        print '    </div>';
        print '  </div>';
        print '</div>';

        // --- JS ---
        print '<script>';
        print '(function(){';
        print '  var overlay = document.getElementById("gp-export-modal-overlay");';
        print '  document.querySelectorAll(".gp-open-export-modal").forEach(function(btn){';
        print '    btn.addEventListener("click", function(){';
        print '      document.getElementById("gp-fichinter-btn-excel").href = this.dataset.excelUrl;';
        print '      document.getElementById("gp-fichinter-btn-pdf").href = this.dataset.pdfUrl;';
        print '      overlay.style.display = "flex";';
        print '    });';
        print '  });';
        print '  document.querySelectorAll(".gp-modal-close,.gp-modal-close-btn").forEach(function(el){';
        print '    el.addEventListener("click", function(){ overlay.style.display = "none"; });';
        print '  });';
        print '  overlay.addEventListener("click", function(e){ if(e.target===overlay) overlay.style.display="none"; });';
        print '})();';
        print '</script>';

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
