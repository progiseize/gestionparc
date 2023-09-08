<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

// ON CHARGE LA LIBRAIRIE DU MODULE
dol_include_once('./gestionparc/class/gestionparc.class.php');

class ActionsGestionParc
{ 
	
	/**
	 * Execute action completeTabsHead
	 *
	 * @param   array           $parameters     Array of parameters
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         'add', 'update', 'view'
	 * @param   Hookmanager     $hookmanager    hookmanager
	 * @return  int                             <0 if KO,
	 *                                          =0 if OK but we want to process standard actions too,
	 *                                          >0 if OK and we want to replace standard actions.
	*/
	public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager){

		global $langs, $conf, $user,$db;

		// ON CHARGE LE FICHIER LANGUE
		$langs->load('gestionparc@gestionparc');

		// ON RECUPERE LE TYPE D'ELEMENT SUR LEQUEL ON EST
		$element = isset($parameters['object']->element)?$parameters['object']->element:'';

		// SI ON EST SUR UN TIERS
		if($element == 'societe' && $parameters['mode'] == 'add'):

			$nb_items = 0; 

			// ON CALCULE LE NBRE D'ITEMS			
			$socid = $parameters['object']->id;
			$gp = new GestionParc($db);
			$list_parctypes = $gp->list_parcType();

			foreach($list_parctypes as $parctype_id => $parctype_infos):

				$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."gestionparc__".$parctype_infos['key']." WHERE socid = ".$socid;
				$res = $db->query($sql);
				$nb_items += $res->num_rows;

			endforeach;

			// ON RECUPERE LA LISTE DES ONGLETS
			$tabs = $parameters['head'];
			foreach($tabs as $tab_key => $tab):

				// ON AJOUTE LE NBRE D'ITEMS AU BON ONGLET
				if($tab[2] == 'gestionparc'):  
					$parameters['head'][$tab_key][1] .= '<span class="badge marginleftonlyshort">'.$nb_items.'</span>';
				endif;

			endforeach;

		endif;

		$this->results = $parameters['head'];
		return 1;		
	}

	public function replaceThirdparty(&$parameters, &$object, &$action, $hookmanager){

		global $langs, $conf, $user, $db;

		// ON CHARGE LE FICHIER LANGUE
		$langs->load('gestionparc@gestionparc');

		$contexts = explode(':', $parameters['context']);

		if(in_array('thirdpartycard', $contexts) && $action == 'confirm_merge'):

			$soc_origin = $parameters['soc_origin'];
			$soc_dest = $parameters['soc_dest'];
			$error = 0;

			$gestionparc = new GestionParc($db);
			$result_mergeparcs = $gestionparc->mergeParcs($soc_origin,$soc_dest);

			$verif = new GestionParcVerif($db);
			$result_mergeverifs = $verif->mergeVerifs($soc_origin,$soc_dest);

			if($result_mergeparcs < 0): $error++; endif;
			if($result_mergeverifs < 0): $error++; endif;

			if(!$error):

				if($result_mergeparcs > 0): setEventMessages($langs->trans('gp_mergeParcSuccess',$result_mergeparcs), null, 'mesgs'); endif;
				if($result_mergeverifs > 0): setEventMessages($langs->trans('gp_mergeVerifSuccess',$result_mergeverifs), null, 'mesgs'); endif;
				return 1;

			else:
				setEventMessages($langs->trans('gp_mergeError'), null, 'errors'); return -1;
			endif;

		endif;

		
	}



}

?>