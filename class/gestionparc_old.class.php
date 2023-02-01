<?php
/* Copyright (C) 2021  Progiseize */

require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

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
		$this->db->begin();

		if($user->rights->gestionparc->configurer):

			$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element;
			$sql.= " (label,description,tags,position,author,entity)";
			$sql.= " VALUES (";
			$sql.= " '".$this->db->escape($this->label)."'";
			$sql.= ", '".$this->db->escape($this->description)."'";
			if(empty($this->tags)) : $sql.= ", NULL"; else: $sql.= ", '".$this->tags."'"; endif;
			$sql.= ", '".$this->db->escape($this->position)."'";
			$sql.= ", '".$user->id."'";
			$sql.= ", '".$conf->entity."'";
			$sql.= ")";

			$result = $this->db->query($sql);

			if ($result): 
				$this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
				$this->author = $user->id;
				$this->entity = $conf->entity;
				$this->db->commit();
				return $this->rowid;
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
		$this->db->begin();

		if($user->rights->gestionparc->configurer):
			$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
			$sql .= " WHERE rowid = ".$parc_id;
			$result = $this->db->query($sql);
			return $result;
		else: return false;
		endif;
	}

	/*****************************************************************/
	// MODIFIER UN TYPE DE PARC
	/*****************************************************************/
	public function update_parcType($user){

		global $conf, $langs;
		$this->db->begin();

		if($user->rights->gestionparc->configurer):
			
			$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
			$sql .= " SET label = '".$this->db->escape($this->label)."'";
			$sql .= ",description  = '".$this->db->escape($this->description)."'";

			if(empty($this->tags)) : $sql.= ",tags = NULL"; else: $sql.= ",tags = '".$this->tags."'"; endif;

			$sql .= ",position  = '".$this->db->escape($this->position)."'";
			$sql .= ",author_maj  = '".$user->id."'";
			$sql .= " WHERE rowid = ".$this->rowid;

			$result = $this->db->query($sql);

			if ($result): return true;
			else: return false; $this->db->rollback();
			endif;

		else: return false;
		endif;
	}

	/*****************************************************************/
	// RECUPERER UN ELEMENT TYPE DE PARC
	/*****************************************************************/
	public function fetch_parcType($rowid){

		global $conf, $user, $langs;

		$cat = new Categorie($this->db);		

		$sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".$rowid;

		$result = $this->db->query($sql);
		$item = $this->db->fetch_object($result);

		if($result->num_rows == 0): return -1;
		else:
			$this->rowid = $item->rowid;
			$this->label = $item->label;
			$this->description = $item->description;
			//$this->tags = json_decode($item->tags);
			$this->position = intval($item->position);
			$this->date_creation = $item->date_creation;
			$this->date_modification = $item->tms;
			$this->author = $item->author;
			$this->author_maj = $item->author_maj;
			$this->entity = $item->entity;

			// ON CONSTRUIT LE TABLEAU DES TAGS
			if(empty($item->tags)): $this->tags = '';
			else:
				$tags = json_decode($item->tags);
				$tags_tab = array();
				foreach ($tags as $tag_id): $cat->fetch($tag_id); $tags_tab[$tag_id] = $cat->label; endforeach;
				$this->tags = $tags_tab;
			endif;

			// ON CONSTRUIT LE TABLEAU DES CHAMPS
			//$sql_fields = 

			$this->fields = $this->list_parcFields($this->rowid);



			/*$this->fields = json_decode($item->fields);
			if(!empty($this->fields)):
				usort($this->fields, fn($a, $b) => intval($a->position) <=> intval($b->position));
			endif;*/

			return $this->rowid;
		endif;
	}

	/*****************************************************************/
	// RECUPERER LA LISTE DES TYPES DE PARC
	/*****************************************************************/
	public function list_parcType(){

		global $conf, $user, $langs;
		$this->db->begin();

		$types = array();

		$sql = "SELECT rowid, label FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE entity = '".$conf->entity."'";
		$sql .= " ORDER BY position";
		$result = $this->db->query($sql);

		if($result):
			$nb_results = $this->db->num_rows($result);
			if($nb_results): $i = 0;
				while($i < $nb_results):
					$obj = $this->db->fetch_object($result);
					$types[$obj->rowid] = $obj->label;
					$i++;
				endwhile;
			endif;
		else: dol_print_error($this->db); 
		endif;

		return $types;
	}


	/* ------------------------------------------------------------------------------------------------------------------------------------- */

	
	

	/*****************************************************************/
	// RECUPERER LA LISTE DES CHAMPS D'UN PARC
	/*****************************************************************/
	public function list_parcFields($parc_id){

		global $conf, $user, $langs;
		$this->db->begin();

		$gpf = new GestionParcField($this->db);
		$fields = $gpf->list_parcFields();	

		return $fields;
	}















	// MAJICI
	

	/*public function add_parcTypeField($field,$user){

		global $conf, $langs;
		$this->db->begin();

		if($user->rights->gestionparc->configurer):

			if(empty($this->fields)): $this->fields = array(); endif;
			array_push($this->fields, (object) $field);

			usort($this->fields, fn($a, $b) => intval($a->position) <=> intval($b->position));

			$stringify_fields = json_encode($this->fields,JSON_UNESCAPED_UNICODE);

			$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
			$sql .= " SET fields = '".$stringify_fields."'";
			$sql .= ",author_maj  = '".$user->id."'";
			$sql .= " WHERE rowid = ".$this->rowid;

			$result = $this->db->query($sql);

			if ($result): return true;
			else: return false; $this->db->rollback();
			endif;

		else: return false; 
		endif;
	}*/

	// MAJICI
	/*****************************************************************/
	// SUPPRIMER UN CHAMP A UN TYPE DE PARC
	/*****************************************************************/
	/*public function delete_parcTypeField($field_key,$user){

		global $conf, $langs;
		$this->db->begin();

		if($user->rights->gestionparc->configurer):

			//var_dump($this);

			foreach($this->fields as $kfield => $field):
		        if($field->key == GETPOST('field_code','aZ09')): unset($this->fields[$kfield]); endif;
		    endforeach;

		    $stringify_fields = json_encode(array_values($this->fields),JSON_UNESCAPED_UNICODE);$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
			$sql .= " SET fields = '".$stringify_fields."'";
			$sql .= ",author_maj  = '".$user->id."'";
			$sql .= " WHERE rowid = ".$this->rowid;

			$result = $this->db->query($sql);

			if ($result): return true;
			else: return false; $this->db->rollback();
			endif;

		else: return false; 
		endif;
	}

	// MAJICI
	/*****************************************************************/
	// SUPPRIMER UN CHAMP A UN TYPE DE PARC
	/*****************************************************************/
	/*public function maj_parcTypeField($user){

		global $conf, $langs;
		$this->db->begin();

		if($user->rights->gestionparc->configurer):

			if(empty($this->fields)): $this->fields = array(); endif;

			usort($this->fields, fn($a, $b) => intval($a->position) <=> intval($b->position));

			$stringify_fields = json_encode($this->fields,JSON_UNESCAPED_UNICODE);

			$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
			$sql .= " SET fields = '".$stringify_fields."'";
			$sql .= ",author_maj  = '".$user->id."'";
			$sql .= " WHERE rowid = ".$this->rowid;

			$result = $this->db->query($sql);

			if ($result): return true;
			else: return false; $this->db->rollback();
			endif;

		else: return false; 
		endif;
	}*/




	/*****************************************************************/
	// CHAMPS PERSONNALISES
	/*****************************************************************/
	public function construct_field($field){

		$constructed_field = '';

		switch($field->type):

			// CHAMP PRODUIT - SERVICE
			case 'prodserv': echo 'prod'; break;

			// CHAMP YEAR
			case 'year': 

				// PARAMETRES
				if(empty($field->param)): $y = date('Y'); $ymin = 3; $ymax = 3;
				else:
					$field_param = explode(',', $field->param);
					if($field_param[0] == 'Y'): $y = date('Y'); else: $y = $field_param[0]; endif;
					if(isset($field_param[1])): $ymin = $field_param[1]; else: $ymin = 3; endif;
					if(isset($field_param[2])): $ymax = $field_param[2]; else: $ymax = 3; endif;
				endif;

				$ymin = intval($y) - intval($ymin);
				$ymax = intval($y) + intval($ymax);

				// VALEUR PAR DEFAUT
				$df = $field->default;

				
				/*echo $y + $ymax.'<br/>';
				echo $y - $ymin.'<br/>';*/

				$constructed_field .= '<select class="" name="">';
					
					while ($ymin <= $ymax):
						$constructed_field .= '<option value="">'.$ymin.'</option>';
						$ymin++;
					endwhile;
				$constructed_field .= '</select>';

			

			break;

		endswitch;

		return $constructed_field;

	}

	
}

class GestionParcField {

	public $table_element = 'gestionparc_fields';

	public $rowid;
	public $parc_id;
	public $label;
	public $field_key;
	public $type;
	public $params;
	public $default_value;
	public $statut;
	public $position;
	public $author;
	public $date_creation;
	public $date_modification;

	public $db;

	public function __construct($db){$this->db = $db;}

	



}