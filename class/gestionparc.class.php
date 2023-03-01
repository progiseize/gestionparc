<?php
/* Copyright (C) 2021  Progiseize */

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

// ON CHARGE LA LIBRAIRIE DU MODULE
dol_include_once('./gestionparc/lib/gestionparc.lib.php');

class GestionParc {

	/********************************************************
	******* TODO 
	* - Créer les bases des données automatiquement
	* - Ajouter une option catégorie tiers sur les types de parc
	*/
	
	public $table_element = 'gestionparc';
	public $table_element_fields = 'gestionparc_fields';

	public $rowid;
	public $label;
	public $old_label; // VERIF EN CAS DE MAJ
	public $parc_key;
	public $description;
	public $tags;
	public $position;
	public $date_creation;
	public $date_modification;
	public $author;
	public $author_maj;
	public $entity;
	public $fields;

	public $db;

	public function __construct($db){$this->db = $db;}

	/*****************************************************************/
	// AJOUTER UN TYPE DE PARC
	/*****************************************************************/
	public function add_parcType($user){

		global $conf, $langs;

		if($user->rights->gestionparc->configurer):

			$this->parc_key = $this->constructParcKey($this->label);

			$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element;
			$sql.= " (label,parc_key,description,tags,position,author,entity,enabled)";
			$sql.= " VALUES (";
			$sql.= " '".$this->db->escape($this->label)."'";
			$sql.= ", '".$this->parc_key."'";
			$sql.= ", '".$this->db->escape($this->description)."'";
			if(empty($this->tags)) : $sql.= ", NULL"; else: $sql.= ", '".$this->tags."'"; endif;
			$sql.= ", '".$this->db->escape($this->position)."'";
			$sql.= ", '".$user->id."'";
			$sql.= ", '".$conf->entity."'";
			$sql.= ", '0'";
			$sql.= ")";

			$result = $this->db->query($sql);

			if ($result): 
				$this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
				$this->author = $user->id;
				$this->entity = $conf->entity;

				// ON CREE LA TABLE
				$fields = array(
					'rowid'=> array('type'=>'int','value'=>'11','null'=>'NOT NULL','extra'=> 'auto_increment'),
        			'socid'=> array('type'=>'int','value'=>'11','null'=>'NOT NULL','extra'=> ''),
        			'author'=> array('type'=>'int','value'=>'11','null'=>'NOT NULL','extra'=> ''),
        			'author_maj'=> array('type'=>'int','value'=>'11','null'=>'NOT NULL','extra'=> 'DEFAULT 0'),
        			'date_creation' => array('type'=>'datetime','value'=>'','null'=>'NOT NULL','extra'=> 'DEFAULT CURRENT_TIMESTAMP'),
        			'tms' => array('type'=>'datetime','value'=>'','null'=>'NOT NULL','extra'=> 'DEFAULT CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'),
				);

				// ON VERIFIE SI LE MODE VERIF EST ACTIF POUR CREER LA COLONNE
				if($conf->global->MAIN_MODULE_GESTIONPARC_USEVERIF):
					$fields['verif'] = array('type'=>'BOOLEAN','null' => 'NOT NULL');
				endif;


				$check_creatable = $this->db->DDLCreateTable(MAIN_DB_PREFIX.$this->table_element.'__'.$this->parc_key, $fields, 'rowid', 'innoDB');

				if($check_creatable) : $this->db->commit(); return $this->rowid;
				else: $this->db->rollback(); return false; endif;

			else: $this->db->rollback(); return false; 
			endif;

		else: return false; 
		endif;
	}

	/*****************************************************************/
	// SUPPRIMER UN TYPE DE PARC
	/*****************************************************************/
	public function remove_parcType($parc_id,$user){

		global $conf, $langs;

		if($user->rights->gestionparc->configurer):

			$this->fetch_parcType($parc_id);

			$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
			$sql .= " WHERE rowid = ".$parc_id;
			$result = $this->db->query($sql);
			if ($result): 

				$check_droptable = $this->db->DDLDropTable(MAIN_DB_PREFIX.$this->table_element.'__'.$this->parc_key);
				if($check_droptable) : $this->db->commit(); return $this->rowid;
				else: $this->db->rollback(); return false; endif;

			else: $this->db->rollback(); endif;
			return $result;
		else: return false;
		endif;
	}

	/*****************************************************************/
	// MODIFIER UN TYPE DE PARC
	/*****************************************************************/
	public function update_parcType($user){

		global $conf, $langs;

		if($user->rights->gestionparc->configurer):

			$this->db->begin();

			//
			$update_key = false;

			$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
			$sql .= " SET label = '".$this->db->escape($this->label)."'";

			if($this->old_label && $this->label != $this->old_label):
				$update_key = true;
				$old_key = $this->parc_key;
				$this->parc_key = $this->constructParcKey($this->label);
				$sql .= ", parc_key = '".$this->parc_key."'";
			endif;

			$sql .= ",description  = '".$this->db->escape($this->description)."'";

			if(empty($this->tags)) : $sql.= ",tags = NULL"; else: $sql.= ",tags = '".$this->tags."'"; endif;

			$sql .= ",position  = '".$this->position."'";
			$sql .= ",author_maj  = '".$user->id."'";
			$sql .= " WHERE rowid = ".$this->rowid;

			$result = $this->db->query($sql);

			if ($result): 
				if($update_key):

					$altersql = "ALTER TABLE ".MAIN_DB_PREFIX.$this->table_element."__".$old_key." RENAME ".MAIN_DB_PREFIX.$this->table_element."__".$this->parc_key;
					$result = $this->db->query($altersql);
					if($result): $this->db->commit(); return true;
					else: $this->db->rollback(); return false;
					endif;

				else: $this->db->commit(); return true; endif;
			else: $this->db->rollback(); return false;
			endif;

		else: return false;
		endif;
	}

	/*****************************************************************/
	// RECUPERER UN ELEMENT TYPE DE PARC
	/*****************************************************************/
	public function fetch_parcType($rowid,$return_obj = false){

		global $conf, $user, $langs;

		$cat = new Categorie($this->db);		

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".$rowid;

		$result = $this->db->query($sql);
		$item = $this->db->fetch_object($result);

		if($result->num_rows == 0): return -1;
		else:
			$this->rowid = $item->rowid;
			$this->label = $item->label;
			$this->parc_key = $item->parc_key;
			$this->description = $item->description;
			$this->position = intval($item->position);
			$this->date_creation = $item->date_creation;
			$this->date_modification = $item->tms;
			$this->author = $item->author;
			$this->author_maj = $item->author_maj;
			$this->entity = $item->entity;
			$this->enabled = $item->enabled;

			// ON CONSTRUIT LE TABLEAU DES TAGS
			if(empty($item->tags)): $this->tags = '';
			else:
				$tags = json_decode($item->tags);
				$tags_tab = array();
				foreach ($tags as $tag_id): $cat->fetch($tag_id); $tags_tab[$tag_id] = $cat->label; endforeach;
				$this->tags = $tags_tab;
			endif;

			// ON CONSTRUIT LE TABLEAU DES CHAMPS
			$this->fields = $this->list_parcFields($this->rowid);

			if(!$return_obj): return $this->rowid; else: return $this; endif;
		endif;
	}

	/*****************************************************************/
	// RECUPERER LA LISTE DES TYPES DE PARC
	/*****************************************************************/
	public function list_parcType(){

		global $conf, $user, $langs;

		$types = array();

		$sql = "SELECT rowid, label, parc_key FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE entity = '".$conf->entity."'";
		$sql .= " ORDER BY position";
		$result = $this->db->query($sql);

		if($result):
			$nb_results = $this->db->num_rows($result);
			if($nb_results): $i = 0;
				while($i < $nb_results):
					$obj = $this->db->fetch_object($result);
					$types[$obj->rowid] = array('label' => $obj->label,'key' => $obj->parc_key);
					$i++;
				endwhile;
			endif;
		else: dol_print_error($this->db); 
		endif;

		return $types;
	}

	/*****************************************************************/
	// RECUPERER LA LISTE DES CHAMPS D'UN PARC
	/*****************************************************************/
	public function list_parcFields($parc_id){

		global $conf, $user, $langs;
		
		$fields = array();

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element_fields." WHERE parc_id = ".$parc_id;
		$sql .= " ORDER BY position ASC";
		$result = $this->db->query($sql);

		if($result):
			$nb_results = $this->db->num_rows($result);
			if($nb_results): $i = 0;
				while($i < $nb_results):
					$obj = $this->db->fetch_object($result);					
					$gpf = new GestionParcField($this->db);
					$gpf->fetch_parcField($obj->rowid);
					array_push($fields,$gpf);
					$i++;
				endwhile;
				usort($fields, fn($a, $b) => intval($a->position) <=> intval($b->position));
			endif;
		endif;

		return $fields;
	}

	/*****************************************************************/
	// MODIFIER LE STATUT D'UN PARC
	/*****************************************************************/
	public function setParcStatus($parc_id, $status){

		global $user;

		if($user->rights->gestionparc->configurer):

			$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET enabled = ".$status." WHERE rowid = ".$parc_id;
			$result = $this->db->query($sql);

			if($result): $this->db->commit(); return true;
			else: $this->db->rollback(); return false;
			endif;
		
		else: return false;
		endif;

		
	}

	/*****************************************************************/
	// MODIFIER LE STATUT D'UN CHAMP
	/*****************************************************************/
	public function setFieldStatus($field_id, $status){

		$gpf = new GestionParcField($this->db);
		$gpf->rowid = $field_id;

		if($gpf->setStatus($status)): return true;
		else: return false; endif;
	}

	/*****************************************************************/
	// SUPPRIMER UN CHAMP
	/*****************************************************************/
	public function removeField($field_id,$user){

		if($user->rights->gestionparc->configurer):

			$gpf = new GestionParcField($this->db);
			$gpf->rowid = $field_id;

			if($gpf->remove_parcField($field_id,$user)): return true;
			else: return false; endif;

		else: return false;
		endif;
	}

	/*****************************************************************/
	// CONSTRUIRE LA CLE D'UN CHAMP
	/*****************************************************************/
	public function constructParcKey($parclabel){

		$key = $parclabel; 
        $key = strip_tags($key);
        $key = strtolower(strtr(utf8_decode($key), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY'));
        $key = stripslashes($key);
        $key = preg_replace( '/[^a-z0-9_\-]/', '', $key );

        $u_key = $key;
        $i = 0;
        $check_unique = false;

        while (!$check_unique): $i++;
        	if($this->checkParcKey($key)): $check_unique = true;
        	else: $key = $u_key.'_'.$i; endif;
        endwhile;
        
        return $key;
	}

	/*****************************************************************/
	// VERIFIER LA CLE D'UN CHAMP
	/*****************************************************************/
	public function checkParcKey($key){

		// VERIFICATION DE LA CLE UNIQUE
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE parc_key = '".$key."'";
        $result = $this->db->query($sql);

        if($result->num_rows > 0): return false;
        else: return true;
        endif;
	}

	/*****************************************************************/
	// GET CONTENT
	/*****************************************************************/
	public function getSocParcContent($socid,$parc_key){

		global $user;

		$soc_parclines = array();
		
		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$parc_key;
		$sql .= " WHERE socid = '".$socid."'";
		$sql .= " ORDER BY rowid ASC";
		$result = $this->db->query($sql);

		if($result):
			$nb_results = $this->db->num_rows($result);
			if($nb_results): $i = 0;
				while($i < $nb_results):
					$obj = $this->db->fetch_object($result);
					$soc_parclines[$obj->rowid] = $obj;
					$i++;
				endwhile;
			endif;
		else: dol_print_error($this->db); 
		endif;

		return $soc_parclines;
	}

	public function getSocParcCount($socid,$parc_key,$isverif = false){

		$sql = "SELECT COUNT(*) as nb_items FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$parc_key;
		$sql .= " WHERE socid = '".$socid."'";
		if($isverif): $sql .= " AND verif = '1'"; endif;

		$result = $this->db->query($sql);
		$obj = $this->db->fetch_object($result);
		return $obj->nb_items;

		


	}

	/*****************************************************************/
	// COMPTER LES ELEMENTS D'UN PARC
	/*****************************************************************/
	public function count_parcItems($parc_key){

		$sql = "SELECT socid FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$parc_key;
		$result = $this->db->query($sql);
		$nb_items = 0;

		if($result):
			while($obj = $this->db->fetch_object($result)):

				$societe = new Societe($this->db);
				$societe->fetch($obj->socid);

				if($societe->client == 1 && $societe->status == 1): $nb_items++; endif;

			endwhile;
		endif;

		return $nb_items;	
	}

	/*****************************************************************/
	// COMPTER LES SOCIETES POSSEDANT UN PARC
	/*****************************************************************/
	public function count_parcSoc($parc_key){

		$sql = "SELECT DISTINCT socid FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$parc_key;
		$result = $this->db->query($sql);

		$nb_clients = 0;

		if($result):
			while($obj = $this->db->fetch_object($result)):

				$societe = new Societe($this->db);
				$societe->fetch($obj->socid);

				if($societe->client == 1 && $societe->status == 1): $nb_clients++; endif;

			endwhile;
		endif;

		return $nb_clients;
	}

	/*****************************************************************/
	// RECUPERER LE DERNIER PARC CREE
	/*****************************************************************/
	public function get_lastParc($parc_key){

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$parc_key;
		$sql .= " WHERE date_creation IN (SELECT max(date_creation) FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$parc_key.")";
		$result = $this->db->query($sql);

		$obj = $this->db->fetch_object($result);
		$societe = new Societe($this->db);
		$societe->fetch($obj->socid);

		$infos = array(
			'name' => $societe->name,
			'url' => dol_buildpath('gestionparc/tabs/gestionparc.php?socid='.$obj->socid,1)
		);

		return $infos;
	}

	/*****************************************************************/
	// LABEL D'UN PARC
	/*****************************************************************/
	public function get_parcLabel($parc_key){

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE parc_key = '".$parc_key."'";
		$result = $this->db->query($sql);

		$label = '';
		if($result):
			$obj = $this->db->fetch_object($result);
			$label = $obj->label;
		endif;

		return $label;
	}

	/*****************************************************************/
	// CREATION DU MODE VERIF
	/*****************************************************************/
	public function setVerifMode($action = 'add'){

		$error = 0;
		$list_parcs = $this->list_parcType();
		$this->db->begin();

		foreach($list_parcs as $parc_id => $parc):
			switch ($action):
				case 'add': if(!$this->db->DDLAddField(MAIN_DB_PREFIX.$this->table_element.'__'.$parc['key'],'verif',array('type'=>'BOOLEAN','null' => 'NOT NULL'))):$error++;endif;break;
				case 'remove': if(!$this->db->DDLDropField(MAIN_DB_PREFIX.$this->table_element.'__'.$parc['key'],'verif')):$error++;endif;break;
			endswitch;
		endforeach;

		if(!$error): $this->db->commit(); return true;
        else: $this->db->rollback(); return false;
        endif;
	}

	/*****************************************************************/
	// FUSIONNER PARCS CLIENTS
	/*****************************************************************/
	public function mergeParcs($origin_socid,$dest_socid){

		global $conf, $langs;
		$this->db->begin();

		$error = 0;
		$success = 0;
		$nb_modifs = 0;

		$list_parctypes = $this->list_parcType();
		//var_dump($list_parctypes);

		if($list_parctypes):

			foreach($list_parctypes as $parctype_id => $parctype_infos):

				$parc_fields = $this->list_parcFields($parctype_id);
				$autonumbers = array();

				// On récupère les identifiants des champs concernés
				$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$parctype_infos['key'];
				$sql.= " WHERE socid = '".$origin_socid."'";
				$resql = $this->db->query($sql);

				$list_ids = array();
				if($resql):
					while ($obj = $this->db->fetch_object($resql)):
						array_push($list_ids,$obj->rowid);
					endwhile;
				endif;
				//var_dump($list_ids);

				$gfield = new GestionParcField($this->db);
				foreach($parc_fields as $parcfield):
					if($parcfield->type == 'autonumber'): 
						array_push($autonumbers, $parcfield->field_key);
						//$gfield->getNextAutoNumber($dest_socid,$parctype_infos['key'],$parcfield->field_key);
					endif;
				endforeach;

				if($list_ids):
					foreach($list_ids as $line_id):

						$sql_up = "UPDATE ".MAIN_DB_PREFIX.$this->table_element.'__'.$parctype_infos['key'];
						$sql_up.= " SET socid = '".$dest_socid."'";
						if($autonumbers):
							foreach($autonumbers as $an):
								$sql_up.= ", ".$an." = '".$gfield->getNextAutoNumber($dest_socid,$parctype_infos['key'],$an)."'";
							endforeach;
						endif;
						$sql_up .= " WHERE rowid = '".$line_id."'";
						$resql_up = $this->db->query($sql_up);
						if($resql_up): $success++; else: $error++; endif;

					endforeach;
				endif;

			endforeach;
			
			if(!$error): $this->db->commit(); return $success;
			else: $this->db->rollback(); return -1; endif;

		else: return 0; endif;

	}

	/*****************************************************************/
	// RECUPERER LE CONTENU D'UN CHAMP DBLIST
	/*****************************************************************/
	public function getContentForDbList($value,$params){

		// On recupere le nom des colonnes
		$tmp = explode(':', $params->dblist_keyval);
		$fieldselect = $tmp[0]; // Le champ à afficher est le 1er du tableau

		// On commence à construire la requète
		$sql = "SELECT ".$fieldselect." FROM ".$params->dblist_table;

		// Condition
		$sql.= " WHERE ".$tmp[1]."='".$value."'"; // La clé primaire est le 2eme du tableau

		// On lance la requète
		$query_dblist = $this->db->query($sql);

		$obj = $this->db->fetch_row($query_dblist);

		return $obj[0];
	}

}

class GestionParcField {

	public $table_element = 'gestionparc_fields';
	public $parent_table_element = 'gestionparc';

	public $rowid;
	public $parc_id;
	public $label;
	public $old_label; // VERIF EN CAS DE MAJ
	public $field_key;
	public $type;
	public $params;
	public $required;
	public $default_value;
	public $position;
	public $statut;	
	public $author;
	public $author_maj;
	public $date_creation;
	public $date_modification;

	public $db;

	public function __construct($db){$this->db = $db;}

	/*****************************************************************/
	// AJOUTER UN ELEMENT FIELD
	/*****************************************************************/
	public function add_parcField($user){

		global $conf, $langs;

		if($user->rights->gestionparc->configurer):

			$this->field_key = $this->constructFieldKey($this->label);

			$this->statut = 0;
			$this->author = $user->id;

			//var_dump($this);

			$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element;
			$sql.= " (parc_id,label,field_key,type,params,required,default_value,enabled,position,author)";
			$sql.= " VALUES (";
			$sql.= " ".$this->parc_id;
			$sql.= ", '".$this->db->escape($this->label)."'";
			$sql.= ", '".$this->field_key."'";
			$sql.= ", '".$this->db->escape($this->type)."'";
			$sql.= ", '".json_encode($this->params,JSON_UNESCAPED_UNICODE)."'";
			$sql.= ", '".$this->db->escape($this->required)."'";
			$sql.= ", '".$this->db->escape($this->default_value)."'";
			$sql.= ", ".$this->statut;
			$sql.= ", ".intval($this->position);
			$sql.= ", ".$this->author;
			$sql.= ")";

			$result = $this->db->query($sql);

			if ($result): 
				$this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);

				// ON RECUPERE LA TABLE CORRESPONDANT AU PARC
				$gp = new GestionParc($this->db);
				$gp->fetch_parcType($this->parc_id);

				$check_addfield = $this->db->DDLAddField(MAIN_DB_PREFIX.$this->parent_table_element."__".$gp->parc_key,$this->field_key,array('type'=>'TEXT'));
				if($check_addfield): $this->db->commit(); return $this->rowid;
				else: $this->db->rollback(); return false;
				endif;
				
				
			else: $this->db->rollback(); return false; 
			endif;

		else: $this->db->rollback(); return false; 
		endif;
	}

	/*****************************************************************/
	// RECUPERER UN ELEMENT FIELD
	/*****************************************************************/
	public function fetch_parcField($rowid){

		global $conf, $user, $langs;		

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".$rowid;
		$result = $this->db->query($sql);

		$item = $this->db->fetch_object($result);

		if($result->num_rows == 0): return -1;
		else:
			$this->rowid = $item->rowid;
			$this->parc_id = $item->parc_id;
			$this->label = $item->label;
			$this->field_key = $item->field_key;
			$this->type = $item->type;
			$this->params = json_decode($item->params);
			$this->required = $item->required;
			$this->default_value = $item->default_value;
			$this->enabled = $item->enabled;
			$this->position = intval($item->position);
			$this->date_creation = $item->date_creation;
			$this->date_modification = $item->tms;
			$this->author = $item->author;

			return $this->rowid;
		endif;
	}

	/*****************************************************************/
	// RECUPERER LES INFOS D'UN ELEMENT FIELD
	/*****************************************************************/
	public function getInfos_parcField($parc_id,$fieldkey){

		global $conf, $user, $langs;		

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE parc_id = ".$parc_id." AND field_key = '".$fieldkey."'";
		$result = $this->db->query($sql);

		$item = $this->db->fetch_object($result);
		return $item;

	}

	/*****************************************************************/
	// MODIFIER UN ELEMENT FIELD
	/*****************************************************************/
	public function update_parcField($user){

		global $conf, $langs;

		if($user->rights->gestionparc->configurer):

			$update_key = false;
			$this->author_maj = $user->id;
			
			$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
			$sql .= " SET label = '".$this->db->escape($this->label)."'";

			if($this->old_label && $this->label != $this->old_label):
				$update_key = true;
				$old_key = $this->field_key;
				// ON RECONSTRUIT LA CLE DU CHAMP
				$this->field_key = $this->constructFieldKey($this->label);
				$sql .= ",field_key  = '".$this->field_key."'";
			endif;			

			$sql .= ",params  = '".json_encode($this->params,JSON_UNESCAPED_UNICODE)."'";
			$sql .= ",required  = '".$this->db->escape($this->required)."'";
			$sql .= ",default_value  = '".$this->db->escape($this->default_value)."'";
			$sql .= ",position  = '".intval($this->position)."'";
			$sql .= ",author_maj  = '".$this->author_maj."'";
			$sql .= " WHERE rowid = ".$this->rowid;

			$result = $this->db->query($sql);

			if ($result): 
				if($update_key):

					$gp = new GestionParc($this->db);
					$gp->fetch_parcType($this->parc_id);

					$altersql = "ALTER TABLE ".MAIN_DB_PREFIX.$this->parent_table_element."__".$gp->parc_key." CHANGE ".$old_key." ".$this->field_key." TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL";
					$check_alter = $this->db->query($altersql);
					if($check_alter): $this->db->commit(); return true;
					else: $this->db->rollback(); return false;
					endif;
				else: $this->db->commit(); return true; endif;
			else: $this->db->rollback(); return false;
			endif;

		else: return false;
		endif;
	}

	/*****************************************************************/
	// CONSTRUIRE LA CLE D'UN CHAMP
	/*****************************************************************/
	public function constructFieldKey($fieldlabel){

		$key = $fieldlabel; 
        $key = strip_tags($key);
        $key = strtolower(strtr(utf8_decode($key), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY'));
        $key = stripslashes($key);
        $key = preg_replace( '/[^a-z0-9_\-]/', '', $key );

        $u_key = $key;
        $i = 0;
        $check_unique = false;

        while (!$check_unique): $i++;
        	if($this->checkFieldKey($key,$this->parc_id)): $check_unique = true;
        	else: $key = $u_key.'_'.$i; endif;
        endwhile;
        
        return $key;
	}

	/*****************************************************************/
	// VERIFIER LA CLE D'UN CHAMP
	/*****************************************************************/
	public function checkFieldKey($key,$parc_id){

		// VERIFICATION DE LA CLE UNIQUE
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE field_key = '".$key."'";
        $sql .= " AND parc_id = ".$parc_id;
        $result = $this->db->query($sql);

        if($result->num_rows > 0): return false;
        else: return true;
        endif;
	}

	/*****************************************************************/
	// SUPPRIMER UN CHAMP
	/*****************************************************************/
	public function remove_parcField($rowid,$user){

		global $conf, $user, $langs;

		if($user->rights->gestionparc->configurer):

			$this->fetch_parcField($rowid);

			// ON RECUPERE LA TABLE CORRESPONDANT AU PARC
			$gp = new GestionParc($this->db);
			$gp->fetch_parcType($this->parc_id);

			$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
			$sql .= " WHERE rowid = ".$rowid;

			$result = $this->db->query($sql);
			if ($result): 

				$check_dropfield = $this->db->DDLDropField(MAIN_DB_PREFIX.$this->parent_table_element."__".$gp->parc_key,$this->field_key);
				if($check_dropfield): $this->db->commit(); return true;
				else: $this->db->rollback(); return false;
				endif;
			else: $this->db->rollback(); return false;
			endif;

		else: return false;
		endif;
	}

	/*****************************************************************/
	// ACTIVER / DESACTIVER UN ELEMENT FIELD
	/*****************************************************************/
	public function setStatus($status){

		global $conf, $user, $langs;

		if($user->rights->gestionparc->configurer):

			$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET enabled = ".$status." WHERE rowid = ".$this->rowid;

			$result = $this->db->query($sql);

			if($result): $this->db->commit(); return true;
			else: $this->db->rollback(); return false;
			endif;
		
		else: return false;
		endif;
	}

	/*****************************************************************/
	// CONSTRUIRE LES CHAMPS
	/*****************************************************************/
	public function construct_field($gestionparc,$socid = '',$field_value = '',$additionnal_class = ''){

		$output_field = '';

		switch($this->type):

			// LISTE BDD
			case 'dblist':

				// On recupere le nom des colonnes
				$tmp = explode(':', $this->params->dblist_keyval);
				if($tmp[0] == $tmp[1]): $fieldselect = $tmp[0];
				else: $fieldselect = $tmp[0].', '.$tmp[1];
				endif;

				// On commence à construire la requète
				$sql = "SELECT ".$fieldselect." FROM ".$this->params->dblist_table;

				// Condition
				if(isset($this->params->dblist_filter) && !empty($this->params->dblist_filter)):
					$tmp_w = explode('=', $this->params->dblist_filter);
					$sql.= " WHERE ".$tmp_w[0]."='".$tmp_w[1]."'";
				endif;

				// On lance la requète
				$query_dblist = $this->db->query($sql);

				if(!$query_dblist):
					$output_field .= 'Erreur paramètres';
				else:

					$nb_fields = $query_dblist->num_rows;

					if(!$nb_fields): $output_field .= 'Aucun résultat';
					else:

						// ON VERIFIE LES VARIABLES POST OU GET
						if(GETPOSTISSET('gpfield_'.$this->field_key)): $compare_value = GETPOST('gpfield_'.$this->field_key);
						else: 
							if(!empty($field_value)): $compare_value = $field_value; endif;
						endif;

						$output_field .= '<select class="gp-slct-simple" name="gpfield_'.$this->field_key.'" id="gpfield_'.$this->field_key.'" style="width:100%">';
						while($obj = $this->db->fetch_object($query_dblist)):
							$is_selected = ($obj->{$tmp[1]} == $compare_value )?'selected="selected"':'';
							$output_field .= '<option value="'.$obj->{$tmp[1]}.'" '.$is_selected.'>'.$obj->{$tmp[0]}.'</option>';
						endwhile;
						$output_field .= '</select>';					
					
					endif;
				endif;

			break;

			// NUMERO AUTOMATIQUE
			case 'autonumber':
				// SI ON EST DANS LA GESTION DES CHAMPS ON MET 1 COMME VALEUR
				if(empty($socid)): $nb_val = 1; 
				// SINON, ON VERIFIE SI LE CHAMP POSSEDE UNE VALEUR
				elseif(!empty($socid) && !empty($field_value)): $nb_val = $field_value;
				// SINON ON CALCULE LE PROCHAIN NUMERO DISPO
				else : 
					$nb_val = $this->getNextAutoNumber($socid,$gestionparc->parc_key,$this->field_key);
				endif;

				$output_field = '<input type="number" min="1" step="1" name="gpfield_'.$this->field_key.'" id="gpfield_'.$this->field_key.'" value="'.$nb_val.'" />';
			break;

			// LISTE ANNEE
			case 'yearlist':

				// PARAMS
				$param_yearstart = $this->params->yearstart;
				$param_yearstop = $this->params->yearstop;
				$param_yearsort = $this->params->yearsort;
				$param_yearcustom = $this->params->yearcustom;
				$param_yeardefault = $this->default_value;

				// SI ON DOIT CALCULER L'ANNEE				
				if(substr($param_yearstart, 0, 1) === 'Y'): $param_yearstart = $this->calculY($param_yearstart);
				else: $param_yearstart = intval($param_yearstart);
				endif;

				if(substr($param_yearstop, 0, 1) === 'Y'): $param_yearstop = $this->calculY($param_yearstop);
				else: $param_yearstop = intval($param_yearstop);
				endif;

				if(substr($param_yeardefault, 0, 1) === 'Y'): $param_yeardefault = $this->calculY($param_yeardefault);
				else: $param_yeardefault = intval($param_yeardefault);
				endif;

				// ON VERIFIE LES VARIABLES POST OU GET
				if(GETPOSTISSET('gpfield_'.$this->field_key)): $compare_value = GETPOST('gpfield_'.$this->field_key);
				else: 
					if(!empty($field_value)): $compare_value = $field_value;
					else: $compare_value = $param_yeardefault;
					endif;
				endif;

				// ON DETERMINE LA VALEUR LA PLUS GRANDE ETLA PLUS PETITE
				$max_y = max($param_yearstart,$param_yearstop);
				$min_y = min($param_yearstart,$param_yearstop);

				// ON CREE UN TABLEAU AVEC TOUTES LES VALEURS ET ON LE TRIE SI BESOIN
				$years = array();
				while($min_y <= $max_y): array_push($years, $min_y); $min_y++; endwhile;
				if($param_yearsort == 'DESC'): rsort($years); endif;

				// ON DETERMINE LE TYPE DE SELECT
				if($this->params->yearcustom): $slct_class = 'gp-slct-simple-tags'; else: $slct_class = 'gp-slct-simple'; endif;

				$output_field .= '<select class="'.$slct_class.'" name="gpfield_'.$this->field_key.'" id="gpfield_'.$this->parc_id.'_'.$this->field_key.'" style="width:100%">';
				// $this->default_value
				foreach($years as $year):
					$is_selected = ($year == $compare_value )?'selected="selected"':'';
					$output_field .= '<option value="'.$year.'" '.$is_selected.'>'.$year.'</option>';
				endforeach;
				$output_field .= '</select>';
			break;

			// LISTE CUSTOM
			case 'customlist':

				// PARAMS
				$param_listsort = $this->params->listsort;
				$param_default = $this->default_value;
				$param_listvalues = $this->params->listvalues;

				switch($param_listsort):
					case 'ASC': sort($param_listvalues); break;
					case 'DESC': rsort($param_listvalues); break;
				endswitch;

				// ON VERIFIE LES VARIABLES POST OU GET
				if(GETPOSTISSET('gpfield_'.$this->field_key)): $compare_value = GETPOST('gpfield_'.$this->field_key);
				else: 
					//var_dump($field_value);
					if(!empty($field_value)): $compare_value = $field_value;
					else: $compare_value = $param_default;
					endif;
					//var_dump($compare_value);
				endif;

				// ON DETERMINE LE TYPE DE SELECT
				if($this->params->listcustom): $slct_class = 'gp-slct-simple-tags'; else: $slct_class = 'gp-slct-simple'; endif;

				$output_field .= '<select class="'.$slct_class.'" name="gpfield_'.$this->field_key.'" id="gpfield_'.$this->parc_id.'_'.$this->field_key.'" style="width:100%">';
				// $this->default_value
				foreach($param_listvalues as $lv):
					//var_dump($lv);
					$is_selected = ($lv == $compare_value )?'selected="selected"':'';
					//var_dump($is_selected);
					$output_field .= '<option value="'.$lv.'" '.$is_selected.'>'.$lv.'</option>';
				endforeach;
				$output_field .= '</select>';
			break;

			// PRODUITS / SERVICES
			case 'prodserv':

				$list_prodserv = GestionParcGetListProdServ($this->params->prodservtags,$this->params->prodservref);

				// ON VERIFIE LES VARIABLES POST OU GET
				if(GETPOSTISSET('gpfield_'.$this->field_key)): $compare_value = GETPOST('gpfield_'.$this->field_key);
				else: 
					if(!empty($field_value)): $compare_value = $field_value;
					else: $compare_value = $this->default_value; endif;
				endif;

				$output_field .= '<select class="gp-slct-simple" name="gpfield_'.$this->field_key.'" id="gpfield_'.$this->parc_id.'_'.$this->field_key.'" style="width:100%">';
				foreach($list_prodserv as $kps => $ps):
					$is_selected = ($kps == $compare_value )?'selected="selected"':'';
					$output_field .= '<option value="'.$kps.'" '.$is_selected.'>'.$ps.'</option>';
				endforeach;
				$output_field .= '</select>';
			break;
			
			// CHAMP TEXTE
			case 'textfield':
				if(GETPOSTISSET('gpfield_'.$this->field_key)): $compare_value = GETPOST('gpfield_'.$this->field_key);
				else: $compare_value = $field_value ? $field_value : $this->default_value;
				endif;
				$output_field = '<input type="text" name="gpfield_'.$this->field_key.'" id="gpfield_'.$this->field_key.'" value="'.$compare_value.'" />';
			break;

		endswitch;

		return $output_field;
	}

	public function getNextAutoNumber($socid,$parc_key,$field_key){

		$sql = "SELECT rowid, ".$field_key." FROM ".MAIN_DB_PREFIX.$this->parent_table_element."__".$parc_key;
		$sql .= " WHERE socid = ".$socid;
		$res = $this->db->query($sql);

		$nb_fields = $res->num_rows;
		$nb_used = array();

		// SI ON A DES RESULTATS
		if($nb_fields): 

			while($obj = $this->db->fetch_object($res)):
				array_push($nb_used, intval($obj->{$field_key}));
			endwhile;
			sort($nb_used,SORT_NUMERIC);
			$nb_last = max($nb_used);

			for ($i=1; $i < $nb_last + 1; $i++):
				if(!in_array($i, $nb_used)):$nb_val = $i;break;
				else: $nb_val = $nb_last + 1; endif;
			endfor;
		else: $nb_val = 1;
		endif;

		return $nb_val;
	}

	/*****************************************************************/
	// CALCULER ANNEE
	/*****************************************************************/
	private function calculY($y){

		$nb_chars = strlen(trim($y));
		$actual_year = intval(date('Y'));

		// SI Y => ANNEE EN COURS
		if($nb_chars == 1): $y = $actual_year;
		// SINON
		else:
			// ON VERIFIE LE TYPE DE CALCUL
			if(substr($y, 0, 2) === 'Y+'): $y_tmp = explode('+', $y); $y = $actual_year + intval($y_tmp[1]);
			elseif(substr($y, 0, 2) === 'Y-'): $y_tmp = explode('-', $y); $y = $actual_year - intval($y_tmp[1]);
			endif;
		endif;

		return $y;
	}

}

class GestionParcVerif {

	public $table_element = 'gestionparc_verifs';
	public $parent_table_element = 'gestionparc';	

	public $model_pdf = 'GestionParc';

	public $rowid;
	public $socid;
	public $author;
	public $date_creation;
	public $date_close;
	public $nb_verified;
	public $nb_total;
	public $commentaires;
	public $fichinter_id;
	public $is_close;
	public $files_list;
	public $db;

	public function __construct($db){$this->db = $db;}

	/*****************************************************************/
	// RECUPERER UNE LIGNE DE VERIF
	/*****************************************************************/
	public function fetch($rowid){

		global $conf, $user, $langs;		

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".$rowid;
		$result = $this->db->query($sql);

		$item = $this->db->fetch_object($result);

		if($result->num_rows == 0): return -1;
		else:

			$this->rowid = $item->rowid;
			$this->socid = $item->socid;
			$this->author = $item->author;
			$this->date_creation = $item->date_creation;
			$this->date_close = $item->date_close;
			$this->nb_verified = intval($item->nb_verified);
			$this->nb_total = intval($item->nb_total);
			$this->commentaires = $item->commentaires;
			$this->fichinter_id = $item->fichinter_id;
			$this->is_close = $item->is_close;
			$this->files_list = json_decode($item->files_list);

			return $this->rowid;
		endif;
	}

	/*****************************************************************/
	// OUVERTURE DU MODE VERIF SUR UN PARC CLIENT
	// Todo : verifier si deja verif non fermee
	/*****************************************************************/
	public function openVerif($socid){

		global $conf, $langs,$user;

		//
		$gestionparc = new GestionParc($this->db);
		$list_parctypes = $gestionparc->list_parcType();

		$nb_lines = 0;

		foreach($list_parctypes as $parctype_key => $parctype_infos):
            $parc_lines = $gestionparc->getSocParcContent($socid,$parctype_infos['key']);
            $nb_lines = $nb_lines + count($parc_lines);

            foreach($parc_lines as $parcline):
            	$this->setLineCheck($socid,$parctype_infos['key'],$parcline->rowid,0);
            endforeach;

        endforeach;

		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element;
		$sql.= " (socid,author,nb_total) VALUES ('".$socid."', '".$user->id."','".$nb_lines."')";
		$result = $this->db->query($sql);

		if($result):
			$this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
			$this->socid = $socid;
			$this->author = $user->id;
			return $this->rowid;
		else: return false;
		endif;
	}

	/*****************************************************************/
	// MODIFIER LE STATUT DE VERIF D'UNE LIGNE
	/*****************************************************************/
	public function setLineCheck($socid,$parc_key,$item_id,$val,$maj_verifid = false){

		global $conf, $langs;

		$this->db->begin();

		$sql_update = "UPDATE ".MAIN_DB_PREFIX.$this->parent_table_element."__".$parc_key;
        $sql_update .= " SET verif = '".$val."'";
        $sql_update .= " WHERE rowid = '".$item_id."' AND socid = '".$socid."'";

        $resUpdate = $this->db->query($sql_update);
        if($resUpdate): 

        	if($maj_verifid):
	        	$sql_bis = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
	        	$sql_bis.= " SET nb_verified = nb_verified + 1";
	        	$sql_bis.= " WHERE rowid = '".$maj_verifid."'";
	        	$res_bis = $this->db->query($sql_bis);

	        	$this->nb_verified++;

	        endif;


        	$this->db->commit(); return true;
        else: $this->db->rollback(); return false;
        endif;
	}

	/*****************************************************************/
	// DETERMINER SI UN PARC CLIENT EST EN MODE VERIF
	/*****************************************************************/
	public function isVerif($socid){

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE socid='".$socid."' AND is_close = 0";
		$result = $this->db->query($sql);

		if($result):
			if($result->num_rows == 0): return false;
			elseif($result->num_rows > 1): return -1;
			else:
				$obj = $this->db->fetch_object($result);
				$this->fetch($obj->rowid);
				return $obj->rowid;
			endif;
			
		else: return false;
		endif;
	}

	public function cancelVerif($verif_id){

		$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".$verif_id;
		$result = $this->db->query($sql);

		if($result): $this->db->commit(); return true;			
		else: $this->db->rollback(); return false;
		endif;
	}

	public function closeVerif($rowid,$socid,$description,$duree){

		require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
        require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
        require_once DOL_DOCUMENT_ROOT.'/core/modules/export/export_csv.modules.php';

        global $conf, $langs, $user;

        $error = 0;

        $this->db->begin();        

        $intervention = new Fichinter($this->db);
        $intervention->socid = $socid;
        $intervention->description = 'Vérification Parc Client '.date('d/m/Y');
        if(!empty($description)): $intervention->note_public = $description; endif;
        $intervention->create($user);

        $gestionparc = new GestionParc($this->db);
		$list_parctypes = $gestionparc->list_parcType();

		$verif_files = array();

		$docs_list = array();

		// CONTENU 
        $lineverif_desc = ''; $i = 0;

        foreach($list_parctypes as $parctype_id => $parctype_infos): $i++;

        	$list_parcFields = $gestionparc->list_parcFields($parctype_id);
            $parc_lines = $gestionparc->getSocParcContent($socid,$parctype_infos['key']);

            $nb_parclines = count($parc_lines);

            if($nb_parclines > 0):

            	$csv_parc = new ExportCsv($this->db);
            	$csv_parc->separator = ';';

            	// ON DONNE UN NOM AU FICHIER
				$upload_dir = $conf->ficheinter->dir_output.'/'.dol_sanitizeFileName($intervention->ref);
				$result_creadir = dol_mkdir($upload_dir);
	        	$file_title = 'gestionparc-'.$parctype_infos['key'].'-SOC'.$socid.'-'.date('dmY').'.'.$csv_parc->extension;
	        	$dir_file = $upload_dir.'/'.$file_title;
	        	array_push($verif_files, $file_title);

	        	// ON OUVRE LE FICHIER
	        	$csv_parc->open_file($dir_file,$langs);

	        	// ON ECRIT LE HEADER DU FICHIER
	        	$csv_parc->write_header($langs);

            	$verified_lines = 0;

            	$pos = array();
	            $labels = array();
	            $types = array();
	            $enabled = array();
	            foreach($list_parcFields as $parcfield):
	                $pos[$parcfield->field_key] = $parcfield->position;
	                $labels[$parcfield->field_key] = $parcfield->label;
	                $types[$parcfield->field_key] = $parcfield->type;
	                $enabled[$parcfield->field_key] = $parcfield->enabled;
	            endforeach;
	            asort($pos);

	            // CSV LABELS
	            $csv_labels = array('verif');
	            $csv_labels_type = array('Text');
	            foreach($pos as $key_field => $key_pos):
	            	
	            	if($enabled[$key_field]): 

	            		$column_name = $key_field;
	            		if($types[$key_field] == 'prodserv'): $column_name .='-ID'; endif;

	            		array_push($csv_labels,$column_name);
	            		array_push($csv_labels_type,'Text');

	            		if($types[$key_field] == 'prodserv'):
	            			array_push($csv_labels,$key_field.'-label');
	            			array_push($csv_labels_type,'Text');
	            		endif;

	            	endif;
	            endforeach;
	            $csv_parc->write_title($csv_labels,$csv_labels,$langs,$csv_labels_type);

            	$lineverif_desc .= '<br/>';
	            $lineverif_desc .= '<b><u>'.$parctype_infos['label'].'</u></b><br/>';

	            // POUR CHAQUE ELEMENT ON AJOUTE + 1 SI VERIF
	            foreach($parc_lines as $parcline): 

	            	$csv_line = array();
	            	$csv_line_type = array();

	            	if($parcline->verif):
	            		$verified_lines++; array_push($csv_line,'oui'); array_push($csv_line_type,'Text');
	            		else: array_push($csv_line,'non'); array_push($csv_line_type,'Text');
	            	endif;

	            	//var_dump($pos,$labels,$types,$enabled);
	            	foreach($pos as $key_field => $key_pos):
	            		if($enabled[$key_field]): 
	            			if(!empty($parcline->{$key_field})): array_push($csv_line,$parcline->{$key_field});
	            			else: array_push($csv_line,''); endif;
	            			array_push($csv_line_type,'Text');

	            			if($types[$key_field] == 'prodserv'):

	            				$p = new Product($this->db);
	            				$p->fetch($parcline->{$key_field});

		            			array_push($csv_line,$p->label);
		            			array_push($csv_line_type,'Text');
		            		endif;

	            		endif;
	            	endforeach;

	            	$csv_parc->write_title($csv_line,$csv_line,$langs,$csv_line_type);

	            	
	            endforeach;

	            $csv_parc->write_footer($langs);
   				$csv_parc->close_file();

            	$lineverif_desc .= '<span style="font-size:0.85em"><b>Eléments vérifiés:</b> '.$verified_lines.'/'.$nb_parclines.'<br/></span>';

            	//
            	$parc_infos = array(
            		'parc_key' => $parctype_infos['key'],
            		'parc_label' => $parctype_infos['label'],
            		'parc_fields' => $list_parcFields,
            		'parc_lines' => $parc_lines,
            	);
            	$docs_list[$parctype_id] = $parc_infos;


            endif;
        endforeach;

        //var_dump($verif_files,json_encode($verif_files));

        // ON AJOUTE LA LIGNE
        $now = dol_now();
        $intervention->addline($user,$intervention->id,$lineverif_desc,$now,$duree);        

        // ON VALIDE L'INTERVENTION
        $intervention->setValid($user);

        // ON REPREND L'ENSEMBLE DES INFOS
        $intervention->fetch($intervention->id);

        // Extrafield Fichinter
        $intervention->array_options['options_gestionparc_isverif'] = $rowid;
        $intervention->updateExtraField('gestionparc_isverif');

        // ON GENERE LES DOCUMENT 
        /*if(!empty($docs_list)):
        	foreach($docs_list as $parc_id => $parc_infos):
        		$intervention->generateDocument($this->model_pdf,$langs,0,0,0,$parc_infos); // New Doc futur dev
        	endforeach;
        endif;*/

        // ON CLOS LE MODE VERIF
        $sql_close = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
        $sql_close .= " SET date_close = '".date('Y-m-d H:i:s')."'";
        $sql_close .= ", commentaires = '".$this->db->escape($description)."'";
        $sql_close .= ", fichinter_id = '".$intervention->id."'";
        $sql_close .= ", is_close = '1'";
        //$sql_close .= ", files_list = '".json_encode($verif_files,JSON_UNESCAPED_UNICODE)."'";
        $sql_close .= " WHERE rowid = '".$rowid."' AND socid = '".$socid."'";

        $result_close = $this->db->query($sql_close);
        if($result_close && !$error): $this->db->commit(); return $intervention->id;
        else: $this->db->rollback(); return false;
        endif;
	}

	public function getLastVerif($socid){

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE socid = ".$socid." AND is_close = 1 ORDER BY rowid DESC LIMIT 1";
		$result = $this->db->query($sql);

		$item = $this->db->fetch_object($result);

		if($result->num_rows == 0): return 0;
		else: return $item->fichinter_id;
		endif;
	}

	/*****************************************************************/
	// MODIFIER LE TIERS D'UNE VERIF - EN CAS DE FUSION DE TIERS
	/*****************************************************************/
	public function mergeVerifs($origin_socid,$dest_socid){

		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
		$sql.= " SET socid = '".$dest_socid."'";
		$sql.= " WHERE socid = '".$origin_socid."'";
		$result = $this->db->query($sql);

		$nb = $this->db->db->affected_rows;


		if($result): $this->db->commit(); return $nb;
		else: $this->db->rollback(); return -1;
		endif;

	}

}