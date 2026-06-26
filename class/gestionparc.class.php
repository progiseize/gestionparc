<?php
/* Copyright (C) 2021  Progiseize */

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';


// ON CHARGE LA LIBRAIRIE DU MODULE
dol_include_once('./gestionparc/lib/gestionparc.lib.php');

class GestionParc
{
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
    public $error = '';
    public $enabled;
    public $numeroFieldCache = array();

    // todo: public $lines

    public $forbidden_words = array(
        'ACCESSIBLE','ADD','ALL','ALTER','ANALYZE','AND','AS','ASC','ASENSITIVE','AUTO_INCREMENT',
        'BDB','BEFORE','BERKELEYDB','BETWEEN','BIGINT','BINARY','BLOB','BOTH','BY',
        'CALL','CASCADE','CASE','CHANGE','CHAR','CHARACTER','CHECK','COLLATE','COLUMN','COLUMNS','CONDITION','CONNECTION','CONSTRAINT','CONTINUE','CONVERT','CREATE','CROSS','CURRENT_DATE','CURRENT_TIME','CURRENT_TIMESTAMP','CURRENT_USER','CURSOR',
        'DATABASE','DATABASES','DAY_HOUR','DAY_MICROSECOND','DAY_MINUTE','DAY_SECOND','DEC','DECIMAL','DECLARE','DEFAULT','DELAYED','DELETE','DESC','DESCRIBE','DETERMINISTIC','DISTINCT','DISTINCTROW','DIV','DOUBLE','DROP','DUAL',
        'EACH','ELSE','ELSEIF','ENCLOSED','ESCAPED','EXISTS','EXIT','EXPLAIN',
        'FALSE','FETCH','FIELDS','FLOAT','FLOAT4','FLOAT8','FOR','FORCE','FOREIGN','FOUND','FRAC_SECOND','FROM','FULLTEXT',
        'GENERAL','GRANT','GROUP',
        'HAVING','HIGH_PRIORITY','HOUR_MICROSECOND','HOUR_MINUTE','HOUR_SECOND',
        'IF','IGNORE','IGNORE_SERVER_IDS','IN','INDEX','INFILE','INNER','INNODB','INOUT','INSENSITIVE','INSERT','INT','INT1','INT2','INT3','INT4','INT8','INTEGER','INTERVAL','INTO','IO_THREAD','IS','ITERATE',
        'JOIN',
        'KEY','KEYS','KILL',
        'LEADING','LEAVE','LEFT','LIKE','LIMIT','LINEAR','LINES','LOAD','LOCALTIME','LOCALTIMESTAMP','LOCK','LONG','LONGBLOB','LONGTEXT','LOOP','LOW_PRIORITY',
        'MASTER_HEARTBEAT_PERIOD','MASTER_SERVER_ID','MASTER_SSL_VERIFY_SERVER_CERT','MATCH','MAXVALUE','MEDIUMBLOB','MEDIUMINT','MEDIUMTEXT','MIDDLEINT','MINUTE_MICROSECOND','MINUTE_SECOND','MOD','MODIFIES','MySQL',
        'NATURAL','NOT','NO_WRITE_TO_BINLOG','NULL','NUMERIC',
        'ON','OPTIMIZE','OPTION','OPTIONALLY','OR','ORDER','OUT','OUTER','OUTFILE',
        'PRECISION','PRIMARY','PRIVILEGES','PROCEDURE','PURGE',
        'RANGE','READ','READS','READ_WRITE','REAL','REFERENCES','REGEXP','RELEASE','RENAME','REPEAT','REPLACE','REQUIRE','RESIGNAL','RESTRICT','RETURN','REVOKE','RIGHT','RLIKE',
        'SCHEMA','SCHEMAS','SECOND_MICROSECOND','SELECT','SENSITIVE','SEPARATOR','SET','SHOW','SIGNAL','SLOW','SMALLINT','SOME','SONAME','SPATIAL','SPECIFIC','SQL','SQLEXCEPTION','SQLSTATE','SQLWARNING','SQL_BIG_RESULT','SQL_CALC_FOUND_ROWS','SQL_SMALL_RESULT','SQL_TSI_DAY','SQL_TSI_FRAC_SECOND','SQL_TSI_HOUR','SQL_TSI_MINUTE','SQL_TSI_MONTH','SQL_TSI_QUARTER','SQL_TSI_SECOND','SQL_TSI_WEEK','SQL_TSI_YEAR','SSL','STARTING','STRAIGHT_JOIN','STRIPED',
        'TABLE','TABLES','TERMINATED','THEN','TIMESTAMPADD','TIMESTAMPDIFF','TINYBLOB','TINYINT','TINYTEXT','TO','TRAILING','TRIGGER','TRUE','THE',
        'UNDO','UNION','UNIQUE','UNLOCK','UNSIGNED','UPDATE','USAGE','USE','USER_RESOURCES','USING','UTC_DATE','UTC_TIME','UTC_TIMESTAMP',
        'VALUES','VARBINARY','VARCHAR','VARCHARACTER','VARYING',
        'WHEN','WHERE','WHILE','WITH','WRITE',
        'XOR',
        'YEAR_MONTH',
        'ZEROFILL',
    );

    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /*****************************************************************/
    // AJOUTER UN TYPE DE PARC
    /*****************************************************************/
    public function add_parcType($user)
    {

        global $conf, $langs;

        if($user->hasRight('gestionparc','parc','setup')) :

            $this->parc_key = $this->constructParcKey($this->label);

            $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element;
            $sql.= " (label,parc_key,description,tags,position,author,entity,enabled)";
            $sql.= " VALUES (";
            $sql.= " '".$this->db->escape($this->label)."'";
            $sql.= ", '".$this->parc_key."'";
            $sql.= ", '".$this->db->escape($this->description)."'";
            if(empty($this->tags)) : $sql.= ", NULL"; else: $sql.= ", '".$this->tags."'";
            endif;
            $sql.= ", '".$this->db->escape($this->position)."'";
            $sql.= ", '".$user->id."'";
            $sql.= ", '".$conf->entity."'";
            $sql.= ", '0'";
            $sql.= ")";

            $result = $this->db->query($sql);

            if ($result) :
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
                 'position'=> array('type'=>'int','value'=>'11','null'=>'NOT NULL','extra'=> '', 'default' => 0),
                 'manual_position'=> array('type'=>'BOOLEAN','null'=>'NOT NULL','extra'=> 'DEFAULT 0'),
                );

                // ON VERIFIE SI LE MODE VERIF EST ACTIF POUR CREER LA COLONNE
                if($conf->global->MAIN_MODULE_GESTIONPARC_USEVERIF) :
                    $fields['verif'] = array('type'=>'BOOLEAN','null' => 'NOT NULL','extra'=> 'DEFAULT 0');
                endif;


                $check_creatable = $this->db->DDLCreateTable(MAIN_DB_PREFIX.$this->table_element.'__'.$this->parc_key, $fields, 'rowid', 'innoDB');

                if($check_creatable) : $this->db->commit(); return $this->rowid;
                else: $this->db->rollback(); return false;
                endif;

        else: $this->db->rollback(); return false;
        endif;

     else: return false;
     endif;
    }

    /*****************************************************************/
    // SUPPRIMER UN TYPE DE PARC
    /*****************************************************************/
    public function remove_parcType($parc_id,$user)
    {

        global $conf, $langs;

        if($user->hasRight('gestionparc','parc','setup')) :

            $this->fetch_parcType($parc_id);

            $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
            $sql .= " WHERE rowid = ".$parc_id;
            $result = $this->db->query($sql);
            if ($result) :

                $check_droptable = $this->db->DDLDropTable(MAIN_DB_PREFIX.$this->table_element.'__'.$this->parc_key);
                if($check_droptable) : $this->db->commit(); return $this->rowid;
                else: $this->db->rollback(); return false;
                endif;

         else: $this->db->rollback();
         endif;
         return $result;
     else: return false;
     endif;
    }

    /*****************************************************************/
    // MODIFIER UN TYPE DE PARC
    /*****************************************************************/
    public function update_parcType($user)
    {

        global $conf, $langs;

        if($user->hasRight('gestionparc','parc','setup')) :

            $this->db->begin();

            //
            $update_key = false;

            $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
            $sql .= " SET label = '".$this->db->escape($this->label)."'";

            if($this->old_label && $this->label != $this->old_label) :
                $update_key = true;
                $old_key = $this->parc_key;
                $this->parc_key = $this->constructParcKey($this->label);
                $sql .= ", parc_key = '".$this->parc_key."'";
            endif;

            $sql .= ",description  = '".$this->db->escape($this->description)."'";

            if(empty($this->tags)) : $sql.= ",tags = NULL"; else: $sql.= ",tags = '".$this->tags."'";
            endif;

            $sql .= ",position  = '".$this->position."'";
            $sql .= ",author_maj  = '".$user->id."'";
            $sql .= " WHERE rowid = ".$this->rowid;

            $result = $this->db->query($sql);

            if ($result) :
                if($update_key) :

                    $altersql = "ALTER TABLE ".MAIN_DB_PREFIX.$this->table_element."__".$old_key." RENAME ".MAIN_DB_PREFIX.$this->table_element."__".$this->parc_key;
                    $result = $this->db->query($altersql);
                    if($result) : $this->db->commit(); return true;
                    else: $this->db->rollback(); return false;
                    endif;

             else: $this->db->commit(); return true;
             endif;
         else: $this->db->rollback(); return false;
         endif;

     else: return false;
     endif;
    }

    /*****************************************************************/
    // RECUPERER UN ELEMENT TYPE DE PARC
    /*****************************************************************/
    public function fetch_parcType($rowid = 0, $return_obj = false, $parckey = '')
    {
        if ((int) $rowid <= 0 && empty($parckey)) {
            return -1;
        }

        global $conf, $user, $langs;

        $cat = new Categorie($this->db);

        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
        if ((int) $rowid <= 0 && !empty($parckey)) {
            $sql .= " WHERE parc_key = '".$this->db->escape($parckey)."'";
        } else {
            $sql .= " WHERE rowid = ".$rowid;
        }

        $result = $this->db->query($sql);
        $item = $this->db->fetch_object($result);

        if ($result->num_rows == 0) {
            return -1;
        }

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
        if(empty($item->tags)) {
            $this->tags = '';
        } else {
            $tags = json_decode($item->tags);
            $tags_tab = array();
            foreach ($tags as $tag_id) {
                $cat->fetch($tag_id);
                $tags_tab[$tag_id] = $cat->label;
            }
            $this->tags = $tags_tab;
        }

        // ON CONSTRUIT LE TABLEAU DES CHAMPS
        $this->fields = $this->list_parcFields($this->rowid);

        if(!$return_obj) {
            return $this->rowid;
        } else {
            return $this;
        }
    }

    /*****************************************************************/
    // RECUPERER LA LISTE DES TYPES DE PARC
    /*****************************************************************/
    public function list_parcType($enabled = 0,$isforsoc = 0,$soc_tags = array())
    {

        global $conf, $user, $langs;

        $types = array();

        $sql = "SELECT rowid, label, parc_key FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE entity = '".$conf->entity."'";

        if($isforsoc) :
            if(!empty($soc_tags)) :
                $sql .= " AND (tags IS NULL";
                foreach($soc_tags as $st):
                    $sql .= " OR JSON_CONTAINS(tags, '\"".$st."\"')";
                endforeach;

                $sql .= ")";
         else:
             $sql .= " AND tags IS NULL";
         endif;
        endif;

        if($enabled) : $sql .= " AND enabled = '1'";
        endif;
        $sql .= " ORDER BY position";
        $result = $this->db->query($sql);

        if($result) :
            $nb_results = $this->db->num_rows($result);
            if($nb_results) : $i = 0;
                while($i < $nb_results):
                    $obj = $this->db->fetch_object($result);

                    $types[$obj->rowid] = array(
                     'label' => $obj->label,
                     'key' => $obj->parc_key,
                    );
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
    public function list_parcFields($parc_id)
    {

        global $conf, $user, $langs;

        $fields = array();

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element_fields." WHERE parc_id = ".$parc_id;
        $sql .= " ORDER BY position ASC";
        $result = $this->db->query($sql);

        if($result) :
            $nb_results = $this->db->num_rows($result);
            if($nb_results) : $i = 0;
                while($i < $nb_results):
                    $obj = $this->db->fetch_object($result);
                    $gpf = new GestionParcField($this->db);
                    $gpf->fetch_parcField($obj->rowid);
                    array_push($fields, $gpf);
                    $i++;
                endwhile;
                //usort($fields, fn($a, $b) => intval($a->position) <=> intval($b->position)); // PHP 7.4+
                usort(
                    $fields, function ($a, $b) {
                        return intval($a->position) <=> intval($b->position);
                    }
                );
            endif;
        endif;

        return $fields;
    }

    /*****************************************************************/
    // MODIFIER LE STATUT D'UN PARC
    /*****************************************************************/
    public function setParcStatus($parc_id, $status)
    {
        global $user;

        if($user->hasRight('gestionparc','parc','setup')) :

            $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET enabled = ".$status." WHERE rowid = ".$parc_id;
            $result = $this->db->query($sql);

            if($result) : $this->db->commit(); return true;
         else: $this->db->rollback(); return false;
         endif;

     else: return false;
     endif;
    }

    /*****************************************************************/
    // MODIFIER LE STATUT D'UN CHAMP
    /*****************************************************************/
    public function setFieldStatus($field_id, $status)
    {

        $gpf = new GestionParcField($this->db);
        $gpf->rowid = $field_id;

        if($gpf->setStatus($status)) : return true;
     else: return false;
     endif;
    }

    /*****************************************************************/
    // MODIFIER LA VUE EN EXPORT
    /*****************************************************************/
    public function setFieldViewExport($field_id, $yesorno)
    {

        $gpf = new GestionParcField($this->db);
        $gpf->rowid = $field_id;

        if($gpf->setViewExport($yesorno)) : return true;
     else: return false;
     endif;
    }

    /*****************************************************************/
    // SUPPRIMER UN CHAMP
    /*****************************************************************/
    public function removeField($field_id,$user)
    {

        if($user->hasRight('gestionparc','parc','setup')) :

            $gpf = new GestionParcField($this->db);
            $gpf->rowid = $field_id;

            if($gpf->remove_parcField($field_id, $user)) : return true;
         else: return false;
         endif;

     else: return false;
     endif;
    }

    /*****************************************************************/
    // CONSTRUIRE LA CLE D'UN CHAMP
    /*****************************************************************/
    public function constructParcKey($parclabel)
    {

        $key = $parclabel;
        $key = strip_tags($key);
        $key = strtolower(strtr(utf8_decode($key), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY'));
        $key = stripslashes($key);
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);

        $u_key = $key;
        $i = 0;
        $check_unique = false;

        while (!$check_unique): $i++;
            if($this->checkParcKey($key)) : $check_unique = true;
            else: $key = $u_key.'_'.$i;
            endif;
        endwhile;

        return $key;
    }

    /*****************************************************************/
    // VERIFIER LA CLE D'UN CHAMP
    /*****************************************************************/
    public function checkParcKey($key)
    {

        // CHECK FORBIDDEN WORDS
        if(in_array(strtoupper($key), $this->forbidden_words)) : return false;
        endif;

        // VERIFICATION DE LA CLE UNIQUE
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE parc_key = '".$key."'";
        $result = $this->db->query($sql);

        if($result->num_rows > 0) : return false;
           else: return true;
           endif;
    }

    /*****************************************************************/
    // GET CONTENT
    /*****************************************************************/
    public function getSocParcContent(int $socid, $parc_key)
    {
        global $user;

        $soc_parclines = array();

        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$parc_key;
        $sql .= " WHERE socid = '".$socid."'";
        $sql .= " ORDER BY position ASC, rowid ASC";
        $result = $this->db->query($sql);

        if(!$result) {
            dol_print_error($this->db);
            return $soc_parclines;
        }

        // Récupère toutes les lignes
        $all = array();
        $nb_results = $this->db->num_rows($result);
        for ($i = 0; $i < $nb_results; $i++) {
            $all[] = $this->db->fetch_object($result);
        }

        // Affichage : les éléments NON déplacés manuellement suivent l'ordre de la
        // numérotation (champ autonumber). Les éléments déplacés manuellement
        // (manual_position = 1) gardent leur position et sont insérés à leur place.
        $numField = $this->getNumeroFieldKey($parc_key);

        if ($numField === '') {
            // Pas de champ de numérotation : on garde l'ordre par position (ancien comportement)
            foreach ($all as $obj) {
                $soc_parclines[$obj->rowid] = $obj;
            }
            return $soc_parclines;
        }

        foreach ($this->orderParcLines($all, $numField) as $obj) {
            $soc_parclines[$obj->rowid] = $obj;
        }

        return $soc_parclines;
    }

    /*****************************************************************/
    // ORDONNE UN TABLEAU D'ELEMENTS : les non-épinglés suivent la
    // numérotation ($numField), les épinglés gardent leur position.
    // $forceAutoRowid : traite cet élément comme non-épinglé (sert au
    // calcul de la position naturelle pour le désépinglage).
    /*****************************************************************/
    public function orderParcLines(array $all, $numField, $forceAutoRowid = 0)
    {
        if ($numField === '') return $all;

        $pinned = array();
        $auto   = array();
        foreach ($all as $obj) {
            $isPinned = !empty($obj->manual_position);
            if ($forceAutoRowid && (int) $obj->rowid === (int) $forceAutoRowid) {
                $isPinned = false;
            }
            if ($isPinned) $pinned[] = $obj; else $auto[] = $obj;
        }

        // Tri naturel des éléments auto par la valeur du champ de numérotation
        usort($auto, function ($a, $b) use ($numField) {
            $va = isset($a->{$numField}) ? (string) $a->{$numField} : '';
            $vb = isset($b->{$numField}) ? (string) $b->{$numField} : '';
            $cmp = strnatcasecmp($va, $vb);
            return $cmp !== 0 ? $cmp : ((int) $a->rowid <=> (int) $b->rowid);
        });

        // Tri des épinglés par leur position
        usort($pinned, function ($a, $b) {
            $cmp = ((int) $a->position) <=> ((int) $b->position);
            return $cmp !== 0 ? $cmp : ((int) $a->rowid <=> (int) $b->rowid);
        });

        // Fusion : on insère chaque épinglé à son emplacement (position 1-based)
        $merged = $auto;
        foreach ($pinned as $p) {
            $idx = ((int) $p->position) - 1;
            if ($idx < 0) $idx = 0;
            if ($idx > count($merged)) $idx = count($merged);
            array_splice($merged, $idx, 0, array($p));
        }

        return $merged;
    }

    /*****************************************************************/
    // INDIQUE SI UN ELEMENT, TRAITE COMME NON-EPINGLE, RETOMBERAIT A
    // L'INDEX $targetIndex (1-based). Sert à désépingler automatiquement
    // un organe redéposé à sa position naturelle (ordre de numérotation).
    /*****************************************************************/
    public function isAtNaturalPosition($parc_key, int $rowid, int $targetIndex)
    {
        $numField = $this->getNumeroFieldKey($parc_key);
        if ($numField === '') return false;

        $el = $this->fetchElement($parc_key, $rowid);
        if (!isset($el->rowid)) return false;

        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$this->db->escape($parc_key);
        $sql .= " WHERE socid = '".(int) $el->socid."'";
        $sql .= " ORDER BY position ASC, rowid ASC";
        $res = $this->db->query($sql);
        if (!$res) return false;

        $all = array();
        $nb = $this->db->num_rows($res);
        for ($i = 0; $i < $nb; $i++) {
            $all[] = $this->db->fetch_object($res);
        }

        $ordered = $this->orderParcLines($all, $numField, $rowid);
        $natural = 0;
        foreach ($ordered as $idx => $obj) {
            if ((int) $obj->rowid === (int) $rowid) { $natural = $idx + 1; break; }
        }

        return $natural === (int) $targetIndex;
    }

    /*****************************************************************/
    // RETOURNE LA CLE DU CHAMP DE NUMEROTATION (autonumber) D'UN PARC
    // Sert de tri par défaut. Chaîne vide si le parc n'en a pas.
    /*****************************************************************/
    public function getNumeroFieldKey($parc_key)
    {
        if (!isset($this->numeroFieldCache)) $this->numeroFieldCache = array();
        if (array_key_exists($parc_key, $this->numeroFieldCache)) {
            return $this->numeroFieldCache[$parc_key];
        }

        $field_key = '';
        $sql = "SELECT f.field_key FROM ".MAIN_DB_PREFIX.$this->table_element_fields." f";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX.$this->table_element." p ON f.parc_id = p.rowid";
        $sql .= " WHERE p.parc_key = '".$this->db->escape($parc_key)."' AND f.type = 'autonumber'";
        $sql .= " ORDER BY f.position ASC, f.rowid ASC";
        $res = $this->db->query($sql);
        if ($res && $this->db->num_rows($res) > 0) {
            $o = $this->db->fetch_object($res);
            $field_key = $o->field_key;
        }

        $this->numeroFieldCache[$parc_key] = $field_key;
        return $field_key;
    }

    /*****************************************************************/
    // MARQUE UN ELEMENT COMME DEPLACE MANUELLEMENT (épinglé) OU NON
    /*****************************************************************/
    public function setElementManual($parckey, int $rowid, int $val)
    {
        $sqlup = "UPDATE ".MAIN_DB_PREFIX.$this->table_element.'__'.$this->db->escape($parckey);
        $sqlup .= " SET manual_position = ".((int) $val ? 1 : 0);
        $sqlup .= " WHERE rowid = ".$rowid;
        return $this->db->query($sqlup) ? 1 : 0;
    }

    /*****************************************************************/
    // AJOUTE LA COLONNE manual_position AUX TABLES PAR-PARC (migration)
    // Idempotent : ne fait rien si la colonne existe déjà.
    /*****************************************************************/
    public function ensureManualPositionColumn()
    {
        $list_parcs = $this->list_parcType();
        foreach ($list_parcs as $parc_id => $parc) {
            $this->ensureManualPositionColumnForParc($parc['key']);
        }
    }

    /*****************************************************************/
    // AJOUTE LA COLONNE manual_position A UNE TABLE PAR-PARC (migration ciblée)
    // Idempotent. Sécurise les écritures avant que la migration globale tourne.
    /*****************************************************************/
    public function ensureManualPositionColumnForParc($parc_key)
    {
        if (empty($parc_key)) return;
        $table = MAIN_DB_PREFIX.$this->table_element.'__'.$this->db->escape($parc_key);
        $rescol = $this->db->query("SHOW COLUMNS FROM ".$table." LIKE 'manual_position'");
        if (!$rescol || $this->db->num_rows($rescol) > 0) return; // colonne déjà présente

        if (!$this->db->DDLAddField($table, 'manual_position', array('type'=>'BOOLEAN','null'=>'NOT NULL','extra'=>'DEFAULT 0'))) {
            return;
        }

        // Migration unique (à la création de la colonne) : on préserve les
        // arrangements manuels existants. Pour chaque tiers, si l'ordre enregistré
        // (position) diffère de l'ordre de création (rowid), le parc a été arrangé
        // manuellement -> on épingle tous ses éléments pour conserver l'ordre.
        // Sinon (ordre = ordre de création), il n'a jamais été déplacé -> on le
        // laisse suivre la numérotation (manual_position = 0 par défaut).
        $res = $this->db->query("SELECT rowid, socid FROM ".$table." ORDER BY socid ASC, position ASC, rowid ASC");
        if (!$res) return;

        $bySoc = array();
        while ($o = $this->db->fetch_object($res)) {
            $bySoc[(int) $o->socid][] = (int) $o->rowid;
        }
        foreach ($bySoc as $socid => $rowids) {
            $sorted = $rowids;
            sort($sorted, SORT_NUMERIC);
            if ($rowids !== $sorted) {
                $this->db->query("UPDATE ".$table." SET manual_position = 1 WHERE socid = ".(int) $socid);
            }
        }
    }

    public function getSocParcCount($socid, $parc_key, $isverif = false)
    {

        $sql = "SELECT COUNT(*) as nb_items FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$parc_key;
        $sql .= " WHERE socid = '".$socid."'";
        if($isverif) : $sql .= " AND verif = '1'";
        endif;

        $result = $this->db->query($sql);
        if(!$result): return -1; endif;

        $obj = $this->db->fetch_object($result);
        return $obj->nb_items;
    }

    public function setElementPosition($parckey, int $rowid, int $position)
    {
        $sqlup = "UPDATE ".MAIN_DB_PREFIX.$this->table_element.'__'.$this->db->escape($parckey);
        $sqlup .= " SET position = ".$position;
        $sqlup .= " WHERE rowid = ".$rowid;
        $resup = $this->db->query($sqlup);
        if(!$resup){
            return 0;
        }
        return 1;
    }

    public function fetchElement($parckey, int $rowid)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$this->db->escape($parckey);
        $sql .= " WHERE rowid = '".$rowid."'";
        $res = $this->db->query($sql);
        if (!$res) {
            return -1;
        }
        if ($res->num_rows > 0) {
            $obj = $this->db->fetch_object($res);
            return $obj;
        }
        return 0;
    }

    public function cloneElement($parckey, int $rowid, $after = 1)
    {
        global $user;

        $element = $this->fetchElement($parckey, $rowid);
        if (!isset($element->rowid)) {
            $this->error = 'ItemNotFound';
            return -1;
        }

        $parkID = $this->fetch_parcType(0, 0, $parckey);

        // Get Next position
        if ($after) {
            $position = (int) $element->position + 1;
        } else {
            $sqlpos = "SELECT MAX(position) as maxpos FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$this->db->escape($parckey);
            $sqlpos .= " WHERE socid = ".(int) $element->socid;
            $respos = $this->db->query($sqlpos);
            $objpos = $this->db->fetch_object($respos);
            $position = (int) $objpos->maxpos + 1;
        }

        $this->db->begin();

        // Un clone est considéré comme placé manuellement (épinglé) pour conserver son emplacement
        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element.'__'.$this->db->escape($parckey)." (socid, author, position, manual_position";
        foreach ($this->fields as $parcfield) {
            $sql .= ", ".$parcfield->field_key;
        }
        $sql .= ") VALUES (";
        $sql .= (int) $element->socid;
        $sql .= ", ".(int) $user->id;
        $sql .= ", ".(int) $position;
        $sql .= ", 1";
        foreach ($this->fields as $parcfield) {
            if ($parcfield->type === 'autonumber') {
                $new_val = $parcfield->getNextAutoNumber((int) $element->socid, $parckey, $parcfield->field_key);
                $sql .= ", '".$this->db->escape($new_val)."'";
            } else {
                $sql .= ", '".$this->db->escape($element->{$parcfield->field_key})."'";
            }
        }
        $sql .= ")";
        $res = $this->db->query($sql);
        if (!$res) {
            $this->db->rollback();
            $this->error = 'ErrorInsertNewItem';
            return -1;
        }

        $newElementID = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
        $this->db->commit();
        return $newElementID;
    }

    /*****************************************************************/
    // COMPTER LES ELEMENTS D'UN PARC
    /*****************************************************************/
    public function count_parcItems($parc_key)
    {
        // Compte le nombre total d'éléments dans le parc
        $sql = "SELECT COUNT(*) as nb_items
                FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$parc_key." p
                JOIN ".MAIN_DB_PREFIX."societe s ON p.socid = s.rowid
                WHERE s.client = 1 AND s.status = 1";

        $result = $this->db->query($sql);
        if (!$result) return 0;

        $obj = $this->db->fetch_object($result);
        return $obj->nb_items;
    }

    /*****************************************************************/
    // COMPTER LES SOCIETES POSSEDANT UN PARC
    /*****************************************************************/
    public function count_parcSoc($parc_key)
    {
        // Compte le nombre de sociétés distinctes possédant ce type de parc
        $sql = "SELECT COUNT(DISTINCT p.socid) as nb_clients
                FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$parc_key." p
                JOIN ".MAIN_DB_PREFIX."societe s ON p.socid = s.rowid
                WHERE s.client = 1 AND s.status = 1";

        $result = $this->db->query($sql);
        if (!$result) return 0;

        $obj = $this->db->fetch_object($result);
        return $obj->nb_clients;
    }

    /*****************************************************************/
    // RECUPERER LE DERNIER PARC CREE
    /*****************************************************************/
    public function get_lastParc($parc_key)
    {
        // Version corrigée avec le bon nom de colonne pour le nom de la société

        $sql = "SELECT p.rowid, p.socid, p.date_creation, s.nom
                FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$parc_key." p
                JOIN ".MAIN_DB_PREFIX."societe s ON p.socid = s.rowid
                WHERE s.client = 1 AND s.status = 1
                ORDER BY p.date_creation DESC
                LIMIT 1";

        $result = $this->db->query($sql);

        if ($result && $result->num_rows > 0) {
            $obj = $this->db->fetch_object($result);
            return array(
                'name' => $obj->nom, // Utilisation de nom au lieu de name
                'url' => dol_buildpath('gestionparc/tabs/gestionparc.php?socid='.$obj->socid, 1)
            );
        }

        // Si aucun résultat, on essaie sans les conditions sur la société
        $sql = "SELECT p.rowid, p.socid, p.date_creation, s.nom
                FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$parc_key." p
                JOIN ".MAIN_DB_PREFIX."societe s ON p.socid = s.rowid
                ORDER BY p.date_creation DESC
                LIMIT 1";

        $result = $this->db->query($sql);

        if ($result && $result->num_rows > 0) {
            $obj = $this->db->fetch_object($result);
            return array(
                'name' => $obj->nom, // Utilisation de nom au lieu de name
                'url' => dol_buildpath('gestionparc/tabs/gestionparc.php?socid='.$obj->socid, 1)
            );
        }

        return array('name' => '', 'url' => '');
    }

    /*****************************************************************/
    // LABEL D'UN PARC
    /*****************************************************************/
    public function get_parcLabel($parc_key)
    {

        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE parc_key = '".$parc_key."'";
        $result = $this->db->query($sql);

        $label = '';
        if($result) :
            $obj = $this->db->fetch_object($result);
            $label = $obj->label;
        endif;

        return $label;
    }

    /*****************************************************************/
    // CREATION DU MODE VERIF
    /*****************************************************************/
    public function setVerifMode($action = 'add')
    {

        $error = 0;
        $list_parcs = $this->list_parcType();
        $this->db->begin();

        foreach($list_parcs as $parc_id => $parc):
            switch ($action):
                case 'add':
                    if(!$this->db->DDLAddField(MAIN_DB_PREFIX.$this->table_element.'__'.$parc['key'], 'verif', array('type'=>'BOOLEAN','null' => 'NOT NULL','extra'=> 'DEFAULT 0', 'value' => ''))) :
                        $error++;
                    endif;
                    break;
                case 'remove':
                    if(!$this->db->DDLDropField(MAIN_DB_PREFIX.$this->table_element.'__'.$parc['key'], 'verif')) :
                        $error++;
                    endif;
                    break;
            endswitch;
        endforeach;

        if(!$error) : $this->db->commit(); return true;
        else: $this->db->rollback(); return false;
        endif;
    }

    /*****************************************************************/
    // FUSIONNER PARCS CLIENTS
    /*****************************************************************/
    public function mergeParcs($origin_socid,$dest_socid)
    {

        global $conf, $langs;
        $this->db->begin();

        $error = 0;
        $success = 0;
        $nb_modifs = 0;

        $list_parctypes = $this->list_parcType();
        //var_dump($list_parctypes);

        if($list_parctypes) :

            foreach($list_parctypes as $parctype_id => $parctype_infos):

                $parc_fields = $this->list_parcFields($parctype_id);
                $autonumbers = array();

                // On récupère les identifiants des champs concernés
                $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element.'__'.$parctype_infos['key'];
                $sql.= " WHERE socid = '".$origin_socid."'";
                $resql = $this->db->query($sql);

                $list_ids = array();
                if($resql) :
                    while ($obj = $this->db->fetch_object($resql)):
                        array_push($list_ids, $obj->rowid);
                    endwhile;
                endif;
                //var_dump($list_ids);

                $gfield = new GestionParcField($this->db);
                foreach($parc_fields as $parcfield):
                    if($parcfield->type == 'autonumber') :
                        array_push($autonumbers, $parcfield->field_key);
                        //$gfield->getNextAutoNumber($dest_socid,$parctype_infos['key'],$parcfield->field_key);
                    endif;
                endforeach;

                if($list_ids) :
                    foreach($list_ids as $line_id):

                        $sql_up = "UPDATE ".MAIN_DB_PREFIX.$this->table_element.'__'.$parctype_infos['key'];
                        $sql_up.= " SET socid = '".$dest_socid."'";
                        if($autonumbers) :
                            foreach($autonumbers as $an):
                                $sql_up.= ", ".$an." = '".$gfield->getNextAutoNumber($dest_socid, $parctype_infos['key'], $an)."'";
                            endforeach;
                        endif;
                        $sql_up .= " WHERE rowid = '".$line_id."'";
                        $resql_up = $this->db->query($sql_up);
                        if($resql_up) : $success++; else: $error++;
                        endif;

                    endforeach;
                endif;

            endforeach;

            if(!$error) : $this->db->commit(); return $success;
         else: $this->db->rollback(); return -1;
         endif;

     else: return 0;
     endif;

    }

    /*****************************************************************/
    // RECUPERER LE CONTENU D'UN CHAMP DBLIST
    /*****************************************************************/
    public function getContentForDbList($value,$params)
    {

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

class GestionParcField
{

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
    public $enabled;
    public $position;
    public $statut;
    public $author;
    public $author_maj;
    public $date_creation;
    public $date_modification;

    public $only_verif = 0;
    public $view_excel = 0;
    public $required_manual_verif = 0;
    public $force_default_on_verif = 0;

    public $forbidden_words = array(
    'ACCESSIBLE','ADD','ALL','ALTER','ANALYZE','AND','AS','ASC','ASENSITIVE','AUTO_INCREMENT',
    'BDB','BEFORE','BERKELEYDB','BETWEEN','BIGINT','BINARY','BLOB','BOTH','BY',
    'CALL','CASCADE','CASE','CHANGE','CHAR','CHARACTER','CHECK','COLLATE','COLUMN','COLUMNS','CONDITION','CONNECTION','CONSTRAINT','CONTINUE','CONVERT','CREATE','CROSS','CURRENT_DATE','CURRENT_TIME','CURRENT_TIMESTAMP','CURRENT_USER','CURSOR',
    'DATABASE','DATABASES','DAY_HOUR','DAY_MICROSECOND','DAY_MINUTE','DAY_SECOND','DEC','DECIMAL','DECLARE','DEFAULT','DELAYED','DELETE','DESC','DESCRIBE','DETERMINISTIC','DISTINCT','DISTINCTROW','DIV','DOUBLE','DROP','DUAL',
    'EACH','ELSE','ELSEIF','ENCLOSED','ESCAPED','EXISTS','EXIT','EXPLAIN',
    'FALSE','FETCH','FIELDS','FLOAT','FLOAT4','FLOAT8','FOR','FORCE','FOREIGN','FOUND','FRAC_SECOND','FROM','FULLTEXT',
    'GENERAL','GRANT','GROUP',
    'HAVING','HIGH_PRIORITY','HOUR_MICROSECOND','HOUR_MINUTE','HOUR_SECOND',
    'IF','IGNORE','IGNORE_SERVER_IDS','IN','INDEX','INFILE','INNER','INNODB','INOUT','INSENSITIVE','INSERT','INT','INT1','INT2','INT3','INT4','INT8','INTEGER','INTERVAL','INTO','IO_THREAD','IS','ITERATE',
    'JOIN',
    'KEY','KEYS','KILL',
    'LEADING','LEAVE','LEFT','LIKE','LIMIT','LINEAR','LINES','LOAD','LOCALTIME','LOCALTIMESTAMP','LOCK','LONG','LONGBLOB','LONGTEXT','LOOP','LOW_PRIORITY',
    'MASTER_HEARTBEAT_PERIOD','MASTER_SERVER_ID','MASTER_SSL_VERIFY_SERVER_CERT','MATCH','MAXVALUE','MEDIUMBLOB','MEDIUMINT','MEDIUMTEXT','MIDDLEINT','MINUTE_MICROSECOND','MINUTE_SECOND','MOD','MODIFIES','MySQL',
    'NATURAL','NOT','NO_WRITE_TO_BINLOG','NULL','NUMERIC',
    'ON','OPTIMIZE','OPTION','OPTIONALLY','OR','ORDER','OUT','OUTER','OUTFILE',
    'PRECISION','PRIMARY','PRIVILEGES','PROCEDURE','PURGE',
    'RANGE','READ','READS','READ_WRITE','REAL','REFERENCES','REGEXP','RELEASE','RENAME','REPEAT','REPLACE','REQUIRE','RESIGNAL','RESTRICT','RETURN','REVOKE','RIGHT','RLIKE',
    'SCHEMA','SCHEMAS','SECOND_MICROSECOND','SELECT','SENSITIVE','SEPARATOR','SET','SHOW','SIGNAL','SLOW','SMALLINT','SOME','SONAME','SPATIAL','SPECIFIC','SQL','SQLEXCEPTION','SQLSTATE','SQLWARNING','SQL_BIG_RESULT','SQL_CALC_FOUND_ROWS','SQL_SMALL_RESULT','SQL_TSI_DAY','SQL_TSI_FRAC_SECOND','SQL_TSI_HOUR','SQL_TSI_MINUTE','SQL_TSI_MONTH','SQL_TSI_QUARTER','SQL_TSI_SECOND','SQL_TSI_WEEK','SQL_TSI_YEAR','SSL','STARTING','STRAIGHT_JOIN','STRIPED',
    'TABLE','TABLES','TERMINATED','THEN','TIMESTAMPADD','TIMESTAMPDIFF','TINYBLOB','TINYINT','TINYTEXT','TO','TRAILING','TRIGGER','TRUE','THE',
    'UNDO','UNION','UNIQUE','UNLOCK','UNSIGNED','UPDATE','USAGE','USE','USER_RESOURCES','USING','UTC_DATE','UTC_TIME','UTC_TIMESTAMP',
    'VALUES','VARBINARY','VARCHAR','VARCHARACTER','VARYING',
    'WHEN','WHERE','WHILE','WITH','WRITE',
    'XOR',
    'YEAR_MONTH',
    'ZEROFILL',
    );

    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /*****************************************************************/
    // AJOUTER UN ELEMENT FIELD
    /*****************************************************************/
    public function add_parcField($user)
    {
        global $conf, $langs;

        if($user->hasRight('gestionparc','parc','setup')) :

            $this->field_key = $this->constructFieldKey($this->label);

            $this->statut = 1;
            $this->author = $user->id;

            $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element;
            $sql.= " (parc_id,label,field_key,type,params,required,default_value,enabled,position,author,only_verif,view_excel,required_manual_verif,force_default_on_verif)";
            $sql.= " VALUES (";
            $sql.= " ".$this->parc_id;
            $sql.= ", '".$this->db->escape($this->label)."'";
            $sql.= ", '".$this->field_key."'";
            $sql.= ", '".$this->db->escape($this->type)."'";
            $sql.= ", '".json_encode($this->params, JSON_UNESCAPED_UNICODE)."'";
            $sql.= ", '".$this->db->escape($this->required)."'";
            $sql.= ", '".$this->db->escape($this->default_value)."'";
            $sql.= ", ".$this->statut;
            $sql.= ", ".intval($this->position);
            $sql.= ", ".$this->author;
            $sql.= ", '".((int) $this->only_verif)."'";
            $sql.= ", '".((int) $this->view_excel)."'";
            $sql.= ", '".((int) $this->required_manual_verif)."'";
            $sql.= ", '".((int) $this->force_default_on_verif)."'";
            $sql.= ")";

            $result = $this->db->query($sql);

            if ($result) :
                $this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);

                // ON RECUPERE LA TABLE CORRESPONDANT AU PARC
                $gp = new GestionParc($this->db);
                $gp->fetch_parcType($this->parc_id);

                if($this->type == 'date'):
                    $options_sql = array('type'=>'DATE');
                else:
                    $options_sql = array('type'=>'TEXT');
                endif;

                $check_addfield = $this->db->DDLAddField(MAIN_DB_PREFIX.$this->parent_table_element."__".$gp->parc_key, $this->field_key, $options_sql);
                if($check_addfield) : $this->db->commit(); return $this->rowid;
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
    public function fetch_parcField($rowid)
    {
        global $conf, $user, $langs;

        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".$rowid;
        $result = $this->db->query($sql);

        $item = $this->db->fetch_object($result);

        if($result->num_rows == 0) : return -1;
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
         $this->only_verif = intval($item->only_verif);
         $this->view_excel = intval($item->view_excel);
         $this->required_manual_verif = intval($item->required_manual_verif);
         $this->force_default_on_verif = intval($item->force_default_on_verif);

         return $this->rowid;
     endif;
    }

    /*****************************************************************/
    // RECUPERER LES INFOS D'UN ELEMENT FIELD
    /*****************************************************************/
    public function getInfos_parcField($parc_id,$fieldkey)
    {
        global $conf, $user, $langs;

        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE parc_id = ".$parc_id." AND field_key = '".$fieldkey."'";
        $result = $this->db->query($sql);

        $item = $this->db->fetch_object($result);
        return $item;

    }

    /*****************************************************************/
    // MODIFIER UN ELEMENT FIELD
    /*****************************************************************/
    public function update_parcField($user)
    {
        global $conf, $langs;

        if($user->hasRight('gestionparc','parc','setup')) :

            $update_key = false;
            $this->author_maj = $user->id;

            $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
            $sql .= " SET label = '".$this->db->escape($this->label)."'";

            if($this->old_label && $this->label != $this->old_label) :
                $update_key = true;
                $old_key = $this->field_key;
                // ON RECONSTRUIT LA CLE DU CHAMP
                $this->field_key = $this->constructFieldKey($this->label);
                $sql .= ",field_key  = '".$this->field_key."'";
            endif;

            $sql .= ",params  = '".json_encode($this->params, JSON_UNESCAPED_UNICODE)."'";
            $sql .= ",required  = '".$this->db->escape($this->required)."'";
            $sql .= ",default_value  = '".$this->db->escape($this->default_value)."'";
            $sql .= ",position  = '".intval($this->position)."'";
            $sql .= ",author_maj  = '".$this->author_maj."'";
            $sql .= ",only_verif  = '".$this->only_verif."'";
            $sql .= ",view_excel  = '".$this->view_excel."'";
            $sql .= ",required_manual_verif  = '".$this->required_manual_verif."'";
            $sql .= ",force_default_on_verif  = '".$this->force_default_on_verif."'";
            $sql .= " WHERE rowid = ".$this->rowid;

            $result = $this->db->query($sql);

            if ($result) :
                if($update_key) :

                    $gp = new GestionParc($this->db);
                    $gp->fetch_parcType($this->parc_id);

                    $altersql = "ALTER TABLE ".MAIN_DB_PREFIX.$this->parent_table_element."__".$gp->parc_key." CHANGE ".$old_key." ".$this->field_key." TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL";
                    $check_alter = $this->db->query($altersql);
                    if($check_alter) : $this->db->commit(); return true;
                    else: $this->db->rollback(); return false;
                    endif;
             else: $this->db->commit(); return true;
             endif;
         else: $this->db->rollback(); return false;
         endif;

     else: return false;
     endif;
    }

    /*****************************************************************/
    // CONSTRUIRE LA CLE D'UN CHAMP
    /*****************************************************************/
    public function constructFieldKey($fieldlabel)
    {
        $key = $fieldlabel;
        $key = strip_tags($key);
        $key = strtolower(strtr(utf8_decode($key), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY'));
        $key = stripslashes($key);
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);

        $u_key = $key;
        $i = 0;
        $check_unique = false;

        while (!$check_unique): $i++;
            if($this->checkFieldKey($key, $this->parc_id)) : $check_unique = true;
            else: $key = $u_key.'_'.$i;
            endif;
        endwhile;

        return $key;
    }

    /*****************************************************************/
    // VERIFIER LA CLE D'UN CHAMP
    /*****************************************************************/
    public function checkFieldKey($key,$parc_id)
    {
        // CHECK FORBIDDEN WORDS
        if(in_array(strtoupper($key), $this->forbidden_words)) : return false;
        endif;

        // VERIFICATION DE LA CLE UNIQUE
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE field_key = '".$key."'";
        $sql .= " AND parc_id = ".$parc_id;
        $result = $this->db->query($sql);

        if($result->num_rows > 0) : return false;
           else: return true;
           endif;
    }

    /*****************************************************************/
    // SUPPRIMER UN CHAMP
    /*****************************************************************/
    public function remove_parcField($rowid,$user)
    {
        global $conf, $user, $langs;

        if($user->hasRight('gestionparc','parc','setup')) :

            $this->fetch_parcField($rowid);

            // ON RECUPERE LA TABLE CORRESPONDANT AU PARC
            $gp = new GestionParc($this->db);
            $gp->fetch_parcType($this->parc_id);

            $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
            $sql .= " WHERE rowid = ".$rowid;

            $result = $this->db->query($sql);
            if ($result) :

                $check_dropfield = $this->db->DDLDropField(MAIN_DB_PREFIX.$this->parent_table_element."__".$gp->parc_key, $this->field_key);
                if($check_dropfield) : $this->db->commit(); return true;
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
    public function setStatus($status)
    {
        global $conf, $user, $langs;

        if($user->hasRight('gestionparc','parc','setup')) :

            $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET enabled = ".$status." WHERE rowid = ".$this->rowid;

            $result = $this->db->query($sql);

            if($result) : $this->db->commit(); return true;
         else: $this->db->rollback(); return false;
         endif;

     else: return false;
     endif;
    }

    /*****************************************************************/
    // ACTIVER / DESACTIVER UN ELEMENT FIELD
    /*****************************************************************/
    public function setViewExport($yesorno)
    {
        global $conf, $user, $langs;

        if($user->hasRight('gestionparc','parc','setup')) :
            $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET view_excel = ".$yesorno." WHERE rowid = ".$this->rowid;
            $result = $this->db->query($sql);
            if($result) : $this->db->commit(); return true;
         else: $this->db->rollback(); return false;
         endif;

     else: return false;
     endif;
    }

    /*****************************************************************/
    // CONSTRUIRE LES CHAMPS
    /*****************************************************************/
    public function construct_field($gestionparc,$socid = '',$field_value = '',$additionnal_class = '')
    {
        $output_field = '';

        switch($this->type):

         // LISTE BDD
        case 'dblist':

            // On recupere le nom des colonnes
            $tmp = explode(':', $this->params->dblist_keyval);
            if($tmp[0] == $tmp[1]) : $fieldselect = $tmp[0];
            else: $fieldselect = $tmp[0].', '.$tmp[1];
            endif;

            // On commence à construire la requète
            $sql = "SELECT ".$fieldselect." FROM ".$this->params->dblist_table;

            // Condition
            if(isset($this->params->dblist_filter) && !empty($this->params->dblist_filter)) :
              $tmp_w = explode('=', $this->params->dblist_filter);
              $sql.= " WHERE ".$tmp_w[0]."='".$tmp_w[1]."'";
            endif;

            // On lance la requète
            $query_dblist = $this->db->query($sql);

            if(!$query_dblist) :
                $output_field .= 'Erreur paramètres';
            else:
                $nb_fields = $query_dblist->num_rows;
                if(!$nb_fields) : $output_field .= 'Aucun résultat';
                else:

                    // ON VERIFIE LES VARIABLES POST OU GET
                    if(GETPOSTISSET('gpfield_'.$this->field_key)) :
                        $postValue = GETPOST('gpfield_'.$this->field_key);
                        // Si la valeur POST est vide, on la traite comme null pour forcer l'option vide
                        $compare_value = ($postValue === '' || $postValue === null) ? null : $postValue;
                    else:
                        if($field_value !== '') : $compare_value = $field_value;
                        else: $compare_value = null;
                        endif;
                    endif;

                    $output_field .= '<div class="select-wrapper">';
                        $output_field .= '<select class="gp-slct-simple" name="gpfield_'.$this->field_key.'" id="gpfield_'.$this->field_key.'" style="width:100%">';
                        // Option vide si aucune valeur sélectionnée
                        if($compare_value === null || $compare_value === '') :
                            $output_field .= '<option value="" selected="selected"></option>';
                        endif;
                        while($obj = $this->db->fetch_object($query_dblist)):
                            $is_selected = ($obj->{$tmp[1]} == $compare_value )?'selected="selected"':'';
                            $output_field .= '<option value="'.$obj->{$tmp[1]}.'" '.$is_selected.'>'.$obj->{$tmp[0]}.'</option>';
                        endwhile;
                        $output_field .= '</select>';
                    $output_field .= '</div>';
                endif;
            endif;
        break;

        // NUMERO AUTOMATIQUE
        case 'autonumber':
            // SI ON EST DANS LA GESTION DES CHAMPS ON MET 1 COMME VALEUR
            if(empty($socid)) : $nb_val = 1;
            // SINON, ON VERIFIE SI LE CHAMP POSSEDE UNE VALEUR
            elseif(!empty($socid) && !empty($field_value)) : $nb_val = $field_value;
            // SINON ON CALCULE LE PROCHAIN NUMERO DISPO
            else :
                $nb_val = $this->getNextAutoNumber($socid, $gestionparc->parc_key, $this->field_key);
            endif;
            $output_field = '<input type="text" name="gpfield_'.$this->field_key.'" id="gpfield_'.$this->field_key.'" value="'.$nb_val.'" />';
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
            if(substr($param_yearstart, 0, 1) === 'Y') : $param_yearstart = $this->calculY($param_yearstart);
            else: $param_yearstart = intval($param_yearstart);
            endif;

            if(substr($param_yearstop, 0, 1) === 'Y') : $param_yearstop = $this->calculY($param_yearstop);
            else: $param_yearstop = intval($param_yearstop);
            endif;

            if(substr($param_yeardefault, 0, 1) === 'Y') : $param_yeardefault = $this->calculY($param_yeardefault);
            else: $param_yeardefault = intval($param_yeardefault);
            endif;

            // ON VERIFIE LES VARIABLES POST OU GET
            if(GETPOSTISSET('gpfield_'.$this->field_key)) :
                $postValue = GETPOST('gpfield_'.$this->field_key);
                $compare_value = ($postValue === '' || $postValue === null) ? null : $postValue;
            else:
                if($field_value !== '' && $field_value !== null) : $compare_value = $field_value;
                else: $compare_value = ($param_yeardefault ? $param_yeardefault : null); endif;
            endif;

            // ON DETERMINE LA VALEUR LA PLUS GRANDE ETLA PLUS PETITE
             $max_y = max($param_yearstart, $param_yearstop);
            $min_y = min($param_yearstart, $param_yearstop);

            // ON CREE UN TABLEAU AVEC TOUTES LES VALEURS ET ON LE TRIE SI BESOIN
            $years = array();
            while($min_y <= $max_y): array_push($years, $min_y); $min_y++; endwhile;
            if($param_yearsort == 'DESC') : rsort($years); endif;

            // ON DETERMINE LE TYPE DE SELECT
            if($this->params->yearcustom) : $slct_class = 'gp-slct-simple-tags';
            else: $slct_class = 'gp-slct-simple';
            endif;

            $output_field .= '<div class="select-wrapper">';
                $output_field .= '<select class="'.$slct_class.'" name="gpfield_'.$this->field_key.'" id="gpfield_'.$this->field_key.'" style="width:100%">';
                // Option vide si aucune valeur sélectionnée (nouvel élément)
                if($compare_value === null) :
                    $output_field .= '<option value="" selected="selected"></option>';
                endif;
                // Option N/C
                $nc_selected = ($compare_value === 'N/C') ? 'selected="selected"' : '';
                $output_field .= '<option value="N/C" '.$nc_selected.'>N/C</option>';
                foreach($years as $year):
                    $is_selected = ($year == $compare_value )?'selected="selected"':'';
                    $output_field .= '<option value="'.$year.'" '.$is_selected.'>'.$year.'</option>';
                endforeach;
                $output_field .= '</select>';
            $output_field .= '</div>';
        break;

        // DATE
        case 'date':

            $default_value = '';
            if($this->default_value == 'dd/mm/YYYY' || $this->default_value == 'YYYY_mm_dd'):
                $default_value = date('Y-m-d');
            elseif(!empty($this->default_value)):

                $pattern_fr = "/^\d{2}\/\d{2}\/\d{4}$/";
                $pattern_us = "/^\d{4}-\d{2}-\d{2}$/";

                if (preg_match($pattern_fr, $this->default_value)):
                    $arraydate = explode('/', $this->default_value);
                    $default_value = $arraydate[2].'-'.$arraydate[1].'-'.$arraydate[0];
                elseif (preg_match($pattern_us, $this->default_value)):
                    $default_value = $this->default_value;
                endif;
            endif;

            if(GETPOSTISSET('gpfield_'.$this->field_key)) : $compare_value = GETPOST('gpfield_'.$this->field_key);
            else: $compare_value = $field_value ? $field_value : $default_value;
            endif;

            $output_field = '<input type="date" name="gpfield_'.$this->field_key.'" id="gpfield_'.$this->field_key.'" value="'.$compare_value.'" />';

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
            if(GETPOSTISSET('gpfield_'.$this->field_key)) :
                $postValue = GETPOST('gpfield_'.$this->field_key);
                // Si la valeur POST est vide, on la traite comme null pour forcer l'option vide
                $compare_value = ($postValue === '' || $postValue === null) ? null : $postValue;
            else:
                if($field_value !== '') : $compare_value = $field_value;
                else: $compare_value = ($param_default ? $param_default : null);
                endif;
            endif;

            // ON DETERMINE LE TYPE DE SELECT
            if($this->params->listcustom) : $slct_class = 'gp-slct-simple-tags';
            else: $slct_class = 'gp-slct-simple';
            endif;

            $output_field .= '<div class="select-wrapper">';
                $output_field .= '<select class="'.$slct_class.'" name="gpfield_'.$this->field_key.'" id="gpfield_'.$this->field_key.'" style="width:100%">';
                // Option vide si aucune valeur sélectionnée
                if($compare_value === null || $compare_value === '') :
                    $output_field .= '<option value="" selected="selected"></option>';
                endif;
                // $this->default_value
                foreach($param_listvalues as $lv):
                    //var_dump($lv);
                    $is_selected = ($lv == $compare_value )?'selected="selected"':'';
                    //var_dump($is_selected);
                    $output_field .= '<option value="'.$lv.'" '.$is_selected.'>'.$lv.'</option>';
                endforeach;
                $output_field .= '</select>';
            $output_field .= '</div>';
        break;

        // PRODUITS / SERVICES
        case 'prodserv':

            $list_prodserv = GestionParcGetListProdServ($this->params->prodservtags, $this->params->prodservref);

            // ON VERIFIE LES VARIABLES POST OU GET
            if(GETPOSTISSET('gpfield_'.$this->field_key)) :
                $postValue = GETPOST('gpfield_'.$this->field_key);
                // Si la valeur POST est vide, on la traite comme null pour forcer l'option vide
                $compare_value = ($postValue === '' || $postValue === null) ? null : $postValue;
            else:
                if($field_value !== '') : $compare_value = $field_value;
                else: $compare_value = ($this->default_value ? $this->default_value : null);
                endif;
            endif;

            $output_field .= '<div class="select-wrapper">';
                $output_field .= '<select class="gp-slct-simple" name="gpfield_'.$this->field_key.'" id="gpfield_'.$this->field_key.'" style="width:100%">';
                // Option vide si aucune valeur sélectionnée
                if($compare_value === null || $compare_value === '') :
                    $output_field .= '<option value="" selected="selected"></option>';
                endif;
                foreach($list_prodserv as $kps => $ps):
                    $is_selected = ($kps == $compare_value )?'selected="selected"':'';
                    $output_field .= '<option value="'.$kps.'" '.$is_selected.'>'.$ps.'</option>';
                endforeach;
                $output_field .= '</select>';
            $output_field .= '</div>';
        break;

        // CHAMP TEXTE
        case 'textfield':
            if(GETPOSTISSET('gpfield_'.$this->field_key)) : $compare_value = GETPOST('gpfield_'.$this->field_key);
            else: $compare_value = $field_value ? $field_value : $this->default_value;
            endif;
            $output_field = '<input type="text" name="gpfield_'.$this->field_key.'" id="gpfield_'.$this->field_key.'" value="'.$compare_value.'" />';
        break;

        endswitch;

        return $output_field;
    }

    /*****************************************************************/
    // Prochain numéro auto
    /*****************************************************************/
    public function getNextAutoNumber(int $socid, $parc_key, $field_key)
    {
        $sql = "SELECT rowid, ".$field_key." FROM ".MAIN_DB_PREFIX.$this->parent_table_element."__".$this->db->escape($parc_key);
        $sql .= " WHERE socid = ".(int) $socid;
        $res = $this->db->query($sql);

        $nb_fields = $res->num_rows;
        $nb_used = array();
        $numero = 1;

        // SI ON A DES RESULTATS
        if ($nb_fields) {
            while ($obj = $this->db->fetch_object($res)) {
                array_push($nb_used, intval($obj->{$field_key}));
            }
            sort($nb_used, SORT_NUMERIC);
            $nb_last = max($nb_used);

            for ($i=1; $i < $nb_last + 1; $i++) {
                if (!in_array($i, $nb_used)) {
                    $numero = $i;
                    break;
                } else {
                    $numero = $nb_last + 1;
                }
            }
        }

        return $numero;
    }

    /*****************************************************************/
    // Check if defined value exist
    /*****************************************************************/
    public function checkAutoNumber(int $socid, $parc_key, $field_key, $fieldvalue)
    {
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."gestionparc__".$parc_key;
        $sql .= " WHERE ".$field_key." = '".$this->db->escape($fieldvalue)."'";
        $sql .= " AND socid=".$socid;
        $res = $this->db->query($sql);
        if ($res->num_rows > 0) {
            return false;
        }
        return true;
    }

    /*****************************************************************/
    // CALCULER ANNEE
    /*****************************************************************/
    private function calculY($y)
    {

        $nb_chars = strlen(trim($y));
        $actual_year = intval(date('Y'));

        // SI Y => ANNEE EN COURS
        if($nb_chars == 1) : $y = $actual_year;
            // SINON
     else:
         // ON VERIFIE LE TYPE DE CALCUL
         if(substr($y, 0, 2) === 'Y+') : $y_tmp = explode('+', $y); $y = $actual_year + intval($y_tmp[1]);
      elseif(substr($y, 0, 2) === 'Y-') : $y_tmp = explode('-', $y); $y = $actual_year - intval($y_tmp[1]);
      endif;
     endif;

     return $y;
    }
}

class GestionParcVerif
{

    public $table_element = 'gestionparc_verifs';
    public $parent_table_element = 'gestionparc';

    public $model_pdf = 'soleil';

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
    public $commercial;
    public $intervenant;
    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /*****************************************************************/
    // AJOUTE LA COLONNE report_snapshot A LA TABLE DES VERIFS (migration)
    // Stocke l'instantané des données d'organes à la clôture. Idempotent.
    /*****************************************************************/
    public function ensureVerifSnapshotColumn()
    {
        $table = MAIN_DB_PREFIX.$this->table_element;
        $rescol = $this->db->query("SHOW COLUMNS FROM ".$table." LIKE 'report_snapshot'");
        if ($rescol && $this->db->num_rows($rescol) == 0) {
            $this->db->query("ALTER TABLE ".$table." ADD report_snapshot MEDIUMTEXT NULL DEFAULT NULL");
        }
        // Sauvegarde des valeurs réinitialisées à l'ouverture (force_default_on_verif),
        // pour pouvoir les restaurer si la vérif est annulée.
        $rescol2 = $this->db->query("SHOW COLUMNS FROM ".$table." LIKE 'reset_backup'");
        if ($rescol2 && $this->db->num_rows($rescol2) == 0) {
            $this->db->query("ALTER TABLE ".$table." ADD reset_backup MEDIUMTEXT NULL DEFAULT NULL");
        }
    }

    /*****************************************************************/
    // ENREGISTRE L'INSTANTANE DES DONNEES D'UNE VERIF (à la clôture)
    /*****************************************************************/
    public function storeVerifSnapshot($verif_rowid, $report_data)
    {
        $this->ensureVerifSnapshotColumn();
        $json = json_encode($report_data);
        if ($json === false) return 0;
        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " SET report_snapshot = '".$this->db->escape($json)."'";
        $sql .= " WHERE rowid = ".(int) $verif_rowid;
        return $this->db->query($sql) ? 1 : 0;
    }

    /*****************************************************************/
    // RECUPERE L'INSTANTANE D'UNE VERIF A PARTIR DE L'INTERVENTION
    // Retourne le tableau report_data figé à la clôture, ou null.
    /*****************************************************************/
    public function getVerifSnapshotByFichinter($fichinter_id)
    {
        $table = MAIN_DB_PREFIX.$this->table_element;
        $rescol = $this->db->query("SHOW COLUMNS FROM ".$table." LIKE 'report_snapshot'");
        if (!$rescol || $this->db->num_rows($rescol) == 0) return null; // colonne absente (anciennes installs)

        $sql = "SELECT report_snapshot FROM ".$table;
        $sql .= " WHERE fichinter_id = ".(int) $fichinter_id." AND is_close = 1";
        $sql .= " ORDER BY rowid DESC LIMIT 1";
        $res = $this->db->query($sql);
        if (!$res || !$this->db->num_rows($res)) return null;

        $o = $this->db->fetch_object($res);
        if (empty($o->report_snapshot)) return null;

        $data = json_decode($o->report_snapshot, true);
        return is_array($data) ? $data : null;
    }

    /*****************************************************************/
    // BACKPORT DES SNAPSHOTS A PARTIR DES RAPPORTS XLSX HISTORIQUES
    // Reconstruit report_snapshot (en-tête + organes) pour les vérifs
    // clôturées qui n'en ont pas, à partir des fichiers rapport_*.xlsx
    // présents dans le répertoire documents de chaque intervention.
    //   $commit = false : dry-run (n'écrit rien) ; true : écrit en base
    //   $force  = true  : réécrit même si un snapshot existe déjà
    //   $maxItems       : limite (0 = tout)
    // Retourne un tableau de statistiques.
    /*****************************************************************/
    public function backportSnapshots($commit = false, $force = false, $maxItems = 0)
    {
        global $conf;

        @set_time_limit(0);
        $stats = array('total'=>0,'skipped_has'=>0,'no_xlsx'=>0,'parsed'=>0,'written'=>0,'suspect_mtime'=>0,'parse_empty'=>0);

        if ($commit) $this->ensureVerifSnapshotColumn();

        // La colonne doit exister pour lire/écrire
        $table = MAIN_DB_PREFIX.$this->table_element;
        $rescol = $this->db->query("SHOW COLUMNS FROM ".$table." LIKE 'report_snapshot'");
        $has_col = ($rescol && $this->db->num_rows($rescol) > 0);

        $base_dir = !empty($conf->ficheinter->dir_output) ? $conf->ficheinter->dir_output : (!empty($conf->fichinter->dir_output) ? $conf->fichinter->dir_output : DOL_DATA_ROOT.'/fichinter');

        $snapcol = $has_col ? "v.report_snapshot" : "NULL AS report_snapshot";
        $sql = "SELECT v.rowid, v.fichinter_id, f.ref, v.date_close, ".$snapcol;
        $sql .= " FROM ".$table." v";
        $sql .= " JOIN ".MAIN_DB_PREFIX."fichinter f ON f.rowid = v.fichinter_id";
        $sql .= " WHERE v.is_close = 1";
        $sql .= " ORDER BY v.date_close DESC";
        $res = $this->db->query($sql);
        if (!$res) return $stats;

        while ($row = $this->db->fetch_object($res)) {
            if ($maxItems && $stats['total'] >= $maxItems) break;
            $stats['total']++;

            if (!$force && $has_col && $row->report_snapshot !== null && $row->report_snapshot !== '') {
                $stats['skipped_has']++;
                continue;
            }

            $closeTs = !empty($row->date_close) ? strtotime($row->date_close) : 0;
            $closeYear = !empty($row->date_close) ? (int) substr($row->date_close, 0, 4) : 0;

            $dir = $base_dir.'/'.dol_sanitizeFileName($row->ref);
            list($file, $kind) = self::pickHistoryXlsx($dir, $row->ref, $closeYear);
            if (!$file) { $stats['no_xlsx']++; continue; }

            if ($kind === 'legacy' && $closeTs) {
                $mtime = @filemtime($file);
                if ($mtime && $mtime > $closeTs + 86400 * 7) $stats['suspect_mtime']++;
            }

            try {
                $report = self::parseXlsxReport($file);
            } catch (Exception $e) {
                $stats['parse_empty']++;
                continue;
            }
            if (empty($report['sections'])) { $stats['parse_empty']++; continue; }
            $stats['parsed']++;

            if ($commit && $has_col) {
                $json = json_encode($report, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                if ($json !== false) {
                    $u = $this->db->query("UPDATE ".$table." SET report_snapshot = '".$this->db->escape($json)."' WHERE rowid = ".(int) $row->rowid);
                    if ($u) $stats['written']++;
                }
            }
        }

        return $stats;
    }

    /*****************************************************************/
    // CHOISIT LE MEILLEUR XLSX HISTORIQUE POUR UNE INTERVENTION
    /*****************************************************************/
    public static function pickHistoryXlsx($dir, $ref, $closeYear)
    {
        $byref = $dir.'/rapport_'.$ref.'.xlsx';
        if (is_file($byref)) return array($byref, 'ref');
        if ($closeYear) {
            $byyear = $dir.'/rapport_verification_'.$closeYear.'.xlsx';
            if (is_file($byyear)) return array($byyear, 'year');
        }
        $legacy = $dir.'/rapport_verification.xlsx';
        if (is_file($legacy)) return array($legacy, 'legacy');
        return array('', '');
    }

    /*****************************************************************/
    // PARSE UN RAPPORT XLSX DU MODULE EN STRUCTURE report_data
    // { max_cols, header:{...}, sections:[{label,fields,lines,stats}] }
    /*****************************************************************/
    public static function parseXlsxReport($file)
    {
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) throw new Exception('zip open');

        // shared strings
        $ss = array();
        if (($s = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $x = @simplexml_load_string($s);
            if ($x) foreach ($x->si as $si) {
                if (isset($si->t) && count($si->r) == 0) $ss[] = (string) $si->t;
                else { $t = ''; foreach ($si->r as $r) $t .= (string) $r->t; $ss[] = $t; }
            }
        }

        // workbook : nom de feuille -> rId ; rels : rId -> fichier worksheet
        $wb = @simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $rels = @simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $relMap = array();
        if ($rels) foreach ($rels->Relationship as $r) $relMap[(string) $r['Id']] = (string) $r['Target'];
        $sheets = array();
        if ($wb) foreach ($wb->sheets->sheet as $sh) {
            $name = (string) $sh['name'];
            $rid = '';
            foreach ($sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships') as $k => $v) { if ($k == 'id') $rid = (string) $v; }
            $target = isset($relMap[$rid]) ? $relMap[$rid] : '';
            if ($target && strpos($target, '/') !== 0 && strpos($target, 'xl/') !== 0) $target = 'xl/'.$target;
            $sheets[] = array('name' => $name, 'path' => $target);
        }

        $report = array('max_cols' => 4, 'header' => array(), 'sections' => array());
        $headerCaptured = false;

        foreach ($sheets as $sheet) {
            if (self::snapNorm($sheet['name']) === self::snapNorm('RAPPORT COMPLET')) continue;
            $xml = $zip->getFromName($sheet['path']);
            if ($xml === false) continue;
            $grid = self::sheetGrid($xml, $ss);
            if (empty($grid)) continue;

            // ligne d'en-tête colonnes = 1re ligne où col A et col B sont remplies
            $headerRow = 0; $maxRow = max(array_keys($grid));
            for ($r = 1; $r <= $maxRow; $r++) {
                $a = isset($grid[$r][0]) ? trim((string) $grid[$r][0]) : '';
                $b = isset($grid[$r][1]) ? trim((string) $grid[$r][1]) : '';
                if ($a !== '' && $b !== '') { $headerRow = $r; break; }
            }
            if (!$headerRow) continue;

            // bloc en-tête (avant headerRow) : libellé col C, valeur col D ou E
            $h = array(); $parclabel = '';
            for ($r = 1; $r < $headerRow; $r++) {
                $label = isset($grid[$r][2]) ? self::snapNorm($grid[$r][2]) : '';
                if ($label === '') continue;
                $val = '';
                foreach (array(3, 4) as $cc) { if (isset($grid[$r][$cc]) && trim((string) $grid[$r][$cc]) !== '') { $val = trim((string) $grid[$r][$cc]); break; } }
                if ($val === '') continue;
                // Certains rapports anciens ont été générés sans la langue chargée :
                // le libellé est alors la clé brute (gp_advexp_user, gp_advexp_commercial, gp_advexp_parctype).
                if (strpos($label, 'technicien') !== false || strpos($label, 'gp_advexp_user') !== false) $h['technicien'] = $val;
                elseif (strpos($label, 'commercial') !== false) $h['commercial'] = $val;
                elseif ($label === 'client') $h['client'] = $val;
                elseif (strpos($label, 'adresse') !== false) $h['address'] = $val;
                elseif (strpos($label, 'tel') === 0 || strpos($label, 'telephone') !== false) $h['phone'] = $val;
                elseif (strpos($label, 'code client') !== false) $h['code_client'] = $val;
                elseif (strpos($label, "type d'organe") !== false || strpos($label, 'gp_advexp_parctype') !== false) $parclabel = $val;
            }
            if (!$headerCaptured && $h) { $report['header'] = $h; $headerCaptured = true; }

            // libellés de colonnes (headerRow), de A jusqu'à la 1re colonne vide
            $colLabels = array(); $c = 0;
            while (isset($grid[$headerRow][$c]) && trim((string) $grid[$headerRow][$c]) !== '') { $colLabels[] = trim((string) $grid[$headerRow][$c]); $c++; }
            $ncol = count($colLabels);
            if ($ncol < 1) continue;

            // lignes de données
            $lines = array(); $verified = null; $total_from_summary = null;
            for ($r = $headerRow + 1; $r <= $maxRow; $r++) {
                $rowAllText = '';
                for ($cc = 0; $cc < $ncol; $cc++) $rowAllText .= ' '.(isset($grid[$r][$cc]) ? (string) $grid[$r][$cc] : '');
                if (preg_match('/verifie/', self::snapNorm($rowAllText)) && preg_match('/(\d+)\s*\/\s*(\d+)/', $rowAllText, $mm)) {
                    $verified = (int) $mm[1]; $total_from_summary = (int) $mm[2]; break;
                }
                $vals = array(); $nonEmpty = false;
                for ($cc = 0; $cc < $ncol; $cc++) { $v = isset($grid[$r][$cc]) ? (string) $grid[$r][$cc] : ''; $vals[] = $v; if (trim($v) !== '') $nonEmpty = true; }
                if (!$nonEmpty) continue;
                // L'ancien format pagine dans la même feuille : il répète le bloc
                // d'en-tête + la ligne de colonnes avant chaque sous-tableau. On
                // ignore ces lignes pour ne garder que les vraies données.
                if (self::isHeaderNoise($vals, $colLabels)) continue;
                $lines[] = $vals;
            }
            if (empty($lines)) continue;

            $fields = array();
            foreach ($colLabels as $i => $lab) $fields['c'.$i] = array('label' => $lab, 'type' => '', 'pos' => $i, 'align' => '');

            $total = count($lines);
            $report['sections'][] = array(
                'label'  => $parclabel !== '' ? $parclabel : $sheet['name'],
                'fields' => $fields,
                'lines'  => $lines,
                'stats'  => array('total' => $total_from_summary !== null ? $total_from_summary : $total, 'verified' => $verified !== null ? $verified : $total),
            );
            if ($ncol > $report['max_cols']) $report['max_cols'] = $ncol;
        }

        $zip->close();
        return $report;
    }

    /** Lit une feuille XLSX (XML) en grille [rowNum][colIdx0] = valeur résolue. */
    protected static function sheetGrid($xml, $ss)
    {
        $x = @simplexml_load_string($xml);
        $grid = array();
        if (!$x || !isset($x->sheetData)) return $grid;
        foreach ($x->sheetData->row as $row) {
            $rn = (int) $row['r'];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $ci = 0;
                if (preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) { $cc = $m[1]; for ($k = 0; $k < strlen($cc); $k++) $ci = $ci * 26 + (ord($cc[$k]) - 64); $ci--; }
                $t = (string) $c['t'];
                if ($t === 'inlineStr') { $v = isset($c->is->t) ? (string) $c->is->t : ''; }
                else { $v = isset($c->v) ? (string) $c->v : ''; if ($t === 's') $v = isset($ss[(int) $v]) ? $ss[(int) $v] : ''; }
                $grid[$rn][$ci] = $v;
            }
        }
        return $grid;
    }

    /** Normalise une chaîne (minuscule, sans accents) pour comparaison de libellés. */
    protected static function snapNorm($s)
    {
        $s = mb_strtolower(trim((string) $s));
        return strtr($s, array('é'=>'e','è'=>'e','ê'=>'e','à'=>'a','â'=>'a','î'=>'i','ï'=>'i','ô'=>'o','û'=>'u','ç'=>'c','’'=>"'"));
    }

    /**
     * Détecte une ligne "parasite" issue d'un bloc d'en-tête répété dans la feuille
     * (ancien format paginé) : ligne d'en-tête de colonnes répétée, ligne de titre,
     * ou ligne d'info (colonne B vide + colonne C = libellé d'en-tête connu).
     */
    protected static function isHeaderNoise($row, $colLabels)
    {
        // Ligne d'en-tête de colonnes répétée
        if ($row === $colLabels) return true;

        $a = isset($row[0]) ? self::snapNorm($row[0]) : '';
        $b = isset($row[1]) ? trim((string) $row[1]) : '';
        $c = isset($row[2]) ? self::snapNorm($row[2]) : '';

        // Ligne de titre du rapport
        if (strpos($a, 'rapport de verif') !== false || strpos($a, 'gp_advexp_title') !== false) return true;

        // Ligne d'info d'en-tête : colonne Produit (B) vide + colonne C = libellé connu
        if ($b === '' && $c !== '') {
            foreach (array('date de passage', 'technicien', 'commercial', 'client', 'adresse', 'tel', 'code client', "type d'organe", 'gp_advexp') as $kw) {
                if (strpos($c, $kw) !== false) return true;
            }
        }
        return false;
    }

    /*****************************************************************/
    // RECUPERER UNE LIGNE DE VERIF
    /*****************************************************************/
    public function fetch($rowid)
    {

        global $conf, $user, $langs;

        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".$rowid;
        $result = $this->db->query($sql);

        $item = $this->db->fetch_object($result);

        if($result->num_rows == 0) : return -1;
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
         $this->files_list = !empty($item->files_list) ? json_decode($item->files_list) : array();
         $this->commercial = isset($item->commercial) ? intval($item->commercial) : 0;
         $this->intervenant = isset($item->intervenant) ? intval($item->intervenant) : 0;

         return $this->rowid;
     endif;
    }

    /*****************************************************************/
    // OUVERTURE DU MODE VERIF SUR UN PARC CLIENT
    // Todo : verifier si deja verif non fermee
    /*****************************************************************/
    public function openVerif($socid)
    {

        global $conf, $langs,$user;

        $sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql_check.= " WHERE socid = '".$this->db->escape($socid)."'";
        $sql_check.= " AND is_close = '0'";
        $result_check = $this->db->query($sql_check);

        if(!$result_check) : return false;
        endif;
        if($result_check->num_rows > 0) : return false;
        endif;

        //
        $gestionparc = new GestionParc($this->db);
        $list_parctypes = $gestionparc->list_parcType();

        $nb_lines = 0;
        $reset_backup = array(); // valeurs d'origine des champs réinitialisés (pour restauration si annulation)

        foreach($list_parctypes as $parctype_key => $parctype_infos):
            $parc_lines = $gestionparc->getSocParcContent($socid, $parctype_infos['key']);
            $nb_lines = $nb_lines + count($parc_lines);

            foreach($parc_lines as $parcline):
                $this->setLineCheck($socid, $parctype_infos['key'], $parcline->rowid, 0);

                // Reset fields with force_default_on_verif to their default value
                // (y compris une valeur par défaut vide : le champ est alors vidé,
                //  ex. "Observations" remis à zéro à chaque nouvelle vérification)
                $fields = $gestionparc->list_parcFields($parctype_key);
                foreach($fields as $field):
                    if($field->force_default_on_verif):
                        // Sauvegarde de la valeur d'origine avant écrasement (restauration si annulation)
                        $old_value = isset($parcline->{$field->field_key}) ? $parcline->{$field->field_key} : '';
                        if ((string) $old_value !== (string) $field->default_value) {
                            $reset_backup[$parctype_infos['key']][$parcline->rowid][$field->field_key] = $old_value;
                        }
                        $sql_reset = "UPDATE ".MAIN_DB_PREFIX.$this->parent_table_element."__".$parctype_infos['key'];
                        $sql_reset .= " SET `".$field->field_key."` = '".$this->db->escape($field->default_value)."'";
                        $sql_reset .= " WHERE rowid = '".$parcline->rowid."' AND socid = '".$socid."'";
                        $res_reset = $this->db->query($sql_reset);
                    endif;
                endforeach;
            endforeach;

        endforeach;

        // Commercial / intervenant repris de la précédente intervention (sinon valeurs par défaut)
        list($def_commercial, $def_intervenant) = $this->getDefaultVerifUsers($socid);

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element;
        $sql.= " (socid,author,nb_total,commercial,intervenant) VALUES ('".$socid."', '".$user->id."','".$nb_lines."','".(int) $def_commercial."','".(int) $def_intervenant."')";
        $result = $this->db->query($sql);

        if($result) :
            $this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
            $this->socid = $socid;
            $this->author = $user->id;
            $this->commercial = (int) $def_commercial;
            $this->intervenant = (int) $def_intervenant;

            // Mémorise les valeurs d'origine réinitialisées pour pouvoir les restaurer si la vérif est annulée
            if (!empty($reset_backup)) {
                $this->ensureVerifSnapshotColumn();
                $json = json_encode($reset_backup, JSON_UNESCAPED_UNICODE);
                if ($json !== false) {
                    $this->db->query("UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET reset_backup = '".$this->db->escape($json)."' WHERE rowid = ".(int) $this->rowid);
                }
            }

            return $this->rowid;
     else: return false;
     endif;
    }

    /*****************************************************************/
    // VALEURS PAR DEFAUT COMMERCIAL / INTERVENANT POUR UNE NOUVELLE VERIF
    // 1. Reprise depuis la précédente intervention clôturée (commercial ET
    //    intervenant) - cohérent avec ce qu'affiche l'onglet avant la verif,
    //    pour ne pas écraser le choix existant.
    // 2. Repli si vide : commercial = commercial du tiers, intervenant =
    //    utilisateur courant.
    /*****************************************************************/
    public function getDefaultVerifUsers($socid)
    {
        global $user;

        include_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
        include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

        $commercial = 0;
        $intervenant = 0;

        // 1. Reprise depuis la précédente intervention clôturée (commercial + intervenant)
        $last_fichinter_id = $this->getLastVerif($socid);
        if ($last_fichinter_id) {
            $prev = new Fichinter($this->db);
            if ($prev->fetch($last_fichinter_id) > 0) {
                $prev->fetch_optionals();
                $commercial  = !empty($prev->array_options['options_gestionparc_commercial'])  ? (int) $prev->array_options['options_gestionparc_commercial']  : 0;
                $intervenant = !empty($prev->array_options['options_gestionparc_intervenant']) ? (int) $prev->array_options['options_gestionparc_intervenant'] : 0;
            }
        }

        // 2a. Repli commercial : commercial actuel du tiers
        if (empty($commercial)) {
            $soc = new Societe($this->db);
            if ($soc->fetch($socid) > 0) {
                $reps = $soc->getSalesRepresentatives($user);
                if (!empty($reps)) {
                    $commercial = (int) $reps[0]['id'];
                }
            }
        }

        // 2b. Repli intervenant : utilisateur courant
        if (empty($intervenant)) {
            $intervenant = (int) $user->id;
        }

        return array($commercial, $intervenant);
    }

    /*****************************************************************/
    // METTRE A JOUR COMMERCIAL / INTERVENANT D'UNE VERIF EN COURS
    /*****************************************************************/
    public function updateVerifUsers($rowid, $commercial, $intervenant)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
        $sql.= " SET commercial = '".(int) $commercial."', intervenant = '".(int) $intervenant."'";
        $sql.= " WHERE rowid = '".(int) $rowid."'";

        if ($this->db->query($sql)) {
            $this->commercial = (int) $commercial;
            $this->intervenant = (int) $intervenant;
            return 1;
        }
        return -1;
    }

    /*****************************************************************/
    // METTRE A JOUR UN SEUL CHAMP (commercial | intervenant) D'UNE VERIF
    /*****************************************************************/
    public function updateVerifUserField($rowid, $field, $value)
    {
        if (!in_array($field, array('commercial', 'intervenant'))) {
            return -1;
        }

        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
        $sql.= " SET ".$field." = '".(int) $value."'";
        $sql.= " WHERE rowid = '".(int) $rowid."'";

        if ($this->db->query($sql)) {
            $this->{$field} = (int) $value;
            return 1;
        }
        return -1;
    }

    /*****************************************************************/
    // RECOPIE COMMERCIAL / INTERVENANT DE LA CAMPAGNE VERS LES EXTRAFIELDS
    // DE LA FICHE D'INTERVENTION (appelé à la clôture). Repli intervenant
    // sur le valideur courant si la campagne n'en porte pas.
    /*****************************************************************/
    public function writeInterventionUsers($intervention, $verif_rowid)
    {
        global $user;

        $commercial = 0; $intervenant = 0;
        $resu = $this->db->query("SELECT commercial, intervenant FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".(int) $verif_rowid);
        if ($resu && ($o = $this->db->fetch_object($resu))) {
            $commercial  = (int) $o->commercial;
            $intervenant = (int) $o->intervenant;
        }
        if (empty($intervenant)) {
            $intervenant = (int) $user->id;
        }

        $intervention->array_options['options_gestionparc_commercial']  = $commercial  ? $commercial  : '';
        $intervention->array_options['options_gestionparc_intervenant'] = $intervenant ? $intervenant : '';
        $intervention->updateExtraField('gestionparc_commercial');
        $intervention->updateExtraField('gestionparc_intervenant');
    }

    /*****************************************************************/
    // RECOPIE LES CHAMPS PERSONNALISES DU TIERS VERS LES EXTRAFIELDS
    // DE LA FICHE D'INTERVENTION (snapshot à la clôture/validation).
    /*****************************************************************/
    public function writeInterventionCustomSocFields($intervention, $socid)
    {
        require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

        $soc = new Societe($this->db);
        if ($soc->fetch($socid) <= 0) return -1;
        $soc->fetch_optionals();

        foreach (array_keys(self::getCustomSocFields()) as $code) {
            $opt_key = 'options_'.$code;
            $value = isset($soc->array_options[$opt_key]) ? $soc->array_options[$opt_key] : '';
            $intervention->array_options[$opt_key] = $value;
            $intervention->updateExtraField($code);
        }
        return 1;
    }

    /*****************************************************************/
    // BACKFILL DES INTERVENTIONS DE VERIF EXISTANTES
    // - intervenant : = valideur (fk_user_valid)
    // - commercial  : = commercial actuel du tiers (1er de societe_commerciaux)
    // Idempotent : ne renseigne que les valeurs vides.
    /*****************************************************************/
    public function backfillInterventionUsers()
    {
        // Intervenant <- valideur de l'intervention
        $sql = "UPDATE ".MAIN_DB_PREFIX."fichinter_extrafields as ef";
        $sql.= " INNER JOIN ".MAIN_DB_PREFIX."fichinter as f ON f.rowid = ef.fk_object";
        $sql.= " SET ef.gestionparc_intervenant = f.fk_user_valid";
        $sql.= " WHERE (ef.gestionparc_intervenant IS NULL OR ef.gestionparc_intervenant = '' OR ef.gestionparc_intervenant = '0')";
        $sql.= " AND ef.gestionparc_isverif IS NOT NULL AND ef.gestionparc_isverif > 0";
        $sql.= " AND f.fk_user_valid > 0";
        $this->db->query($sql);

        // Commercial <- commercial actuel du tiers (boucle PHP : UPDATE mono-table par rowid, fiable)
        $sql = "SELECT ef.rowid as ef_rowid, f.fk_soc";
        $sql.= " FROM ".MAIN_DB_PREFIX."fichinter_extrafields as ef";
        $sql.= " INNER JOIN ".MAIN_DB_PREFIX."fichinter as f ON f.rowid = ef.fk_object";
        $sql.= " WHERE (ef.gestionparc_commercial IS NULL OR ef.gestionparc_commercial = '' OR ef.gestionparc_commercial = '0')";
        $sql.= " AND ef.gestionparc_isverif IS NOT NULL AND ef.gestionparc_isverif > 0";

        $resql = $this->db->query($sql);
        if ($resql) {
            $cache_com = array();
            while ($obj = $this->db->fetch_object($resql)) {
                $soc = (int) $obj->fk_soc;
                if (!isset($cache_com[$soc])) {
                    $cache_com[$soc] = 0;
                    $r = $this->db->query("SELECT MIN(fk_user) as fk_user FROM ".MAIN_DB_PREFIX."societe_commerciaux WHERE fk_soc = ".$soc);
                    if ($r && ($o = $this->db->fetch_object($r)) && $o->fk_user > 0) {
                        $cache_com[$soc] = (int) $o->fk_user;
                    }
                }
                if ($cache_com[$soc] > 0) {
                    $this->db->query("UPDATE ".MAIN_DB_PREFIX."fichinter_extrafields SET gestionparc_commercial = '".$cache_com[$soc]."' WHERE rowid = ".(int) $obj->ef_rowid);
                }
            }
        }

        return 1;
    }

    /*****************************************************************/
    // CREE / MIGRE LES EXTRAFIELDS FICHINTER (commercial, intervenant)
    // + LANCE LE BACKFILL. Type 'link' vers user. Idempotent.
    /*****************************************************************/
    public function ensureInterventionExtrafields()
    {
        require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

        $extrafields = new ExtraFields($this->db);
        $extras_fichinter = $extrafields->fetch_name_optionals_label('fichinter');

        $param_user = array('options' => array('User:user/class/user.class.php:0' => null));
        $defs = array(
            'gestionparc_commercial'  => array('gp_extrafieldFichInter_commercial', '101'),
            'gestionparc_intervenant' => array('gp_extrafieldFichInter_intervenant', '102'),
        );

        // entity laissé vide : create_label/update_label utilisent alors $conf->entity en interne
        // (évite tout risque d'insérer une valeur non numérique dans la colonne entity).
        foreach ($defs as $code => $def) {
            $curtype = isset($extrafields->attributes['fichinter']['type'][$code]) ? $extrafields->attributes['fichinter']['type'][$code] : '';
            if (!array_key_exists($code, $extras_fichinter)) {
                $extrafields->addExtraField($code, $def[0], 'link', $def[1], '', 'fichinter', 0, 0, '', $param_user, 1, '', '1', '', '', '', 'gestionparc@gestionparc');
            } elseif ($curtype !== 'link') {
                // Migration ancienne définition (sellist) -> link
                $extrafields->update($code, $def[0], 'link', 0, 'fichinter', 0, 0, $def[1], $param_user, 1, '', '1', '', '', '', '', 'gestionparc@gestionparc');
            }
        }

        // Champs personnalisés : snapshot sur la fiche d'intervention (visible/éditable sur la card)
        foreach (self::getCustomSocFields() as $code => $def) {
            if (!array_key_exists($code, $extras_fichinter)) {
                $param = array('options' => $def['options']);
                $extrafields->addExtraField($code, $def['label'], 'checkbox', $def['pos_inter'], '', 'fichinter', 0, 0, '', $param, 1, '', '1', '', '', '', 'gestionparc@gestionparc');
            }
        }

        // Renseigne l'intervenant (= valideur) des interventions de vérification existantes
        $this->backfillInterventionUsers();
    }

    /*****************************************************************/
    // CONFIG DES CHAMPS PERSONNALISES TIERS (cases à cocher).
    // Chaque champ : extrafield 'checkbox' sur le tiers (saisi dans
    // l'onglet GestionParc) + snapshot sur l'intervention à la clôture,
    // affiché en bas du header des exports. Pour en ajouter un, il suffit
    // d'ajouter une entrée ici.
    //   label    : clé de langue du libellé du champ
    //   pos_soc  : position de l'extrafield societe
    //   pos_inter: position de l'extrafield fichinter
    //   required : si true, au moins une case doit être cochée pour clôturer
    //   options  : cle => libellé des cases à cocher
    /*****************************************************************/
    public static function getCustomSocFields()
    {
        return array(
            'gestionparc_referentiel' => array(
                'label'    => 'gp_referentiel_conformite',
                'pos_soc'  => 100,
                'pos_inter'=> 103,
                'required' => true,
                'options'  => array(
                    'apsadr4'       => 'APSAD R4',
                    'codedutravail' => 'Code du Travail',
                ),
            ),
            'gestionparc_typedeplan' => array(
                'label'    => 'gp_typedeplan',
                'pos_soc'  => 101,
                'pos_inter'=> 104,
                'required' => true,
                'options'  => array(
                    'plansecurite'     => 'Plan de sécurité',
                    'planintervention' => "Plan d'intervention",
                    'planevacuation'   => "Plan d'évacuation",
                    'sans'             => 'Sans',
                ),
            ),
        );
    }

    /*****************************************************************/
    // CREE LES EXTRAFIELDS SOCIETE (champs personnalisés cases à cocher).
    // Affichés/édités uniquement depuis l'onglet GestionParc (list = 0).
    // Idempotent.
    /*****************************************************************/
    public function ensureSocieteExtrafields()
    {
        require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

        $extrafields = new ExtraFields($this->db);
        $extras_societe = $extrafields->fetch_name_optionals_label('societe');

        // entity laissé vide : create_label utilise alors $conf->entity en interne.
        foreach (self::getCustomSocFields() as $code => $def) {
            if (!array_key_exists($code, $extras_societe)) {
                $param = array('options' => $def['options']);
                $extrafields->addExtraField($code, $def['label'], 'checkbox', $def['pos_soc'], '', 'societe', 0, 0, '', $param, 1, '', '0', '', '', '', 'gestionparc@gestionparc');
            }
        }
    }

    /*****************************************************************/
    // RESOLUTION D'UN CHAMP PERSONNALISE EN TEXTE (pour exports)
    // Lit en priorité le snapshot de l'intervention ; repli sur le tiers
    // (anciennes interventions). Retourne les libelles separes par ", ".
    /*****************************************************************/
    public static function resolveCustomSocField($db, $fieldkey, $intervention, $customer = null)
    {
        $fields = self::getCustomSocFields();
        if (!isset($fields[$fieldkey])) return '';
        $opt_key = 'options_'.$fieldkey;

        $raw = '';

        // 1. Snapshot porté par l'intervention
        if (is_object($intervention)) {
            if (empty($intervention->array_options)) $intervention->fetch_optionals();
            if (!empty($intervention->array_options[$opt_key])) {
                $raw = $intervention->array_options[$opt_key];
            }
        }

        // 2. Repli sur le tiers si l'intervention ne porte pas la valeur
        if ($raw === '' && is_object($customer)) {
            if (empty($customer->array_options)) $customer->fetch_optionals();
            $raw = isset($customer->array_options[$opt_key]) ? $customer->array_options[$opt_key] : '';
        }

        if ($raw === '' || $raw === null) return '';

        $options = $fields[$fieldkey]['options'];
        $labels = array();
        foreach (explode(',', $raw) as $key) {
            $key = trim($key);
            if ($key === '') continue;
            $labels[] = isset($options[$key]) ? $options[$key] : $key;
        }

        return implode(', ', $labels);
    }

    /*****************************************************************/
    // RESOLUTION DES NOMS TECHNICIEN / COMMERCIAL POUR LES EXPORTS
    // Lit les extrafields de l'intervention ; repli sur l'ancien
    // comportement (utilisateur courant / commerciaux du tiers) si vide.
    /*****************************************************************/
    public static function resolveExportUsers($db, $intervention, $customer)
    {
        global $user;

        require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

        if (empty($intervention->array_options)) {
            $intervention->fetch_optionals();
        }

        $intervenant_id = !empty($intervention->array_options['options_gestionparc_intervenant']) ? (int) $intervention->array_options['options_gestionparc_intervenant'] : 0;
        $commercial_id  = !empty($intervention->array_options['options_gestionparc_commercial'])  ? (int) $intervention->array_options['options_gestionparc_commercial']  : 0;

        $intervenant_name = '';
        $commercial_name  = '';

        if ($intervenant_id > 0) {
            $u = new User($db);
            if ($u->fetch($intervenant_id) > 0) $intervenant_name = trim($u->firstname.' '.$u->lastname);
        }
        if ($commercial_id > 0) {
            $u = new User($db);
            if ($u->fetch($commercial_id) > 0) $commercial_name = trim($u->firstname.' '.$u->lastname);
        }

        // Repli ancien comportement
        if (empty($intervenant_name)) {
            $intervenant_name = trim($user->firstname.' '.$user->lastname);
            if (empty($intervenant_name)) $intervenant_name = $user->login;
        }
        if (empty($commercial_name) && is_object($customer) && method_exists($customer, 'getSalesRepresentatives')) {
            $parts = array();
            foreach ($customer->getSalesRepresentatives($user) as $sr) {
                $parts[] = trim($sr['firstname'].' '.$sr['lastname']);
            }
            $commercial_name = implode(' / ', $parts);
        }

        return array('intervenant' => $intervenant_name, 'commercial' => $commercial_name);
    }

    /*****************************************************************/
    // MODIFIER LE STATUT DE VERIF D'UNE LIGNE
    /*****************************************************************/
    public function setLineCheck($socid,$parc_key,$item_id,$val,$maj_verifid = false)
    {

        global $conf, $langs;

        $this->db->begin();

        $sql_update = "UPDATE ".MAIN_DB_PREFIX.$this->parent_table_element."__".$parc_key;
        $sql_update .= " SET verif = '".$val."'";
        $sql_update .= " WHERE rowid = '".$item_id."' AND socid = '".$socid."'";

        $resUpdate = $this->db->query($sql_update);
        if($resUpdate) :

            if($maj_verifid) :
                // Incrémente si on contrôle (val=1), décrémente si on revient en arrière (val=0)
                $delta = $val ? 1 : -1;
                $sql_bis = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
                $sql_bis.= " SET nb_verified = GREATEST(0, nb_verified + (".$delta."))";
                $sql_bis.= " WHERE rowid = '".$maj_verifid."'";
                $res_bis = $this->db->query($sql_bis);

                $this->nb_verified = max(0, (int) $this->nb_verified + $delta);

            endif;


            $this->db->commit(); return true;
           else: $this->db->rollback(); return false;
           endif;
    }

    /*****************************************************************/
    // MODIFIER LE STATUT VERIF DE TOUTES LES LIGNES D'UN PARC
    /*****************************************************************/
    public function setParcCheck($socid,$parc_key,$val,$maj_verifid = false)
    {

        global $conf, $langs;

        $this->db->begin();

        $sql_update = "UPDATE ".MAIN_DB_PREFIX.$this->parent_table_element."__".$parc_key;
        $sql_update .= " SET verif = '".$val."'";
        $sql_update .= " WHERE socid = '".$socid."'";
        $resUpdate = $this->db->query($sql_update);

        if(!$resUpdate): $this->db->rollback(); return false; endif;

        if($maj_verifid):

            $nb_verifrows = $this->db->affected_rows($resUpdate);

            $sql_bis = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
            $sql_bis.= " SET nb_verified = nb_verified + ".$nb_verifrows;
            $sql_bis.= " WHERE rowid = '".$maj_verifid."'";
            $res_bis = $this->db->query($sql_bis);

            $this->nb_verified += $nb_verifrows;
        endif;
        $this->db->commit();
        return true;
    }

    /*****************************************************************/
    // DETERMINER SI UN PARC CLIENT EST EN MODE VERIF
    /*****************************************************************/
    public function isVerif($socid)
    {

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE socid='".$socid."' AND is_close = 0";
        $result = $this->db->query($sql);

        if($result) :
            if($result->num_rows == 0) : return false;
         elseif($result->num_rows > 1) : return -1;
         else:
             $obj = $this->db->fetch_object($result);
             $this->fetch($obj->rowid);
             return $obj->rowid;
         endif;

     else: return false;
     endif;
    }

    public function cancelVerif($verif_id)
    {
        $this->ensureVerifSnapshotColumn();

        // Restaure les valeurs d'origine réinitialisées à l'ouverture (force_default_on_verif)
        $resq = $this->db->query("SELECT socid, reset_backup FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".(int) $verif_id);
        if ($resq && $this->db->num_rows($resq)) {
            $vrow = $this->db->fetch_object($resq);
            if (!empty($vrow->reset_backup)) {
                $backup = json_decode($vrow->reset_backup, true);
                if (is_array($backup)) {
                    $socid = (int) $vrow->socid;
                    foreach ($backup as $parc_key => $lines) {
                        if (!preg_match('/^[a-zA-Z0-9_]+$/', $parc_key)) continue;
                        foreach ($lines as $line_rowid => $cfields) {
                            foreach ($cfields as $field_key => $old_value) {
                                if (!preg_match('/^[a-zA-Z0-9_]+$/', $field_key)) continue;
                                $sql_r = "UPDATE ".MAIN_DB_PREFIX.$this->parent_table_element."__".$this->db->escape($parc_key);
                                $sql_r .= " SET `".$field_key."` = '".$this->db->escape($old_value)."'";
                                $sql_r .= " WHERE rowid = ".(int) $line_rowid." AND socid = ".$socid;
                                $this->db->query($sql_r);
                            }
                        }
                    }
                }
            }
        }

        $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".(int) $verif_id;
        $result = $this->db->query($sql);

        if($result) : $this->db->commit(); return true;
     else: $this->db->rollback(); return false;
     endif;
    }

    public function closeVerif($rowid,$socid,$description,$duree)
    {

        global $conf, $langs, $user;

        include_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
        include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

        // Version Dolibarr
        $dolibarr_version = explode('.', DOL_VERSION);

        if(intval($dolibarr_version[0]) <= 17) :
            include_once DOL_DOCUMENT_ROOT.'/core/modules/export/export_csv.modules.php';
            $export_class_name = 'ExportCsv';
        else:
            include_once DOL_DOCUMENT_ROOT.'/core/modules/export/export_csvutf8.modules.php';
            $export_class_name = 'ExportCsvUtf8';
        endif;

        $error = 0;

        $this->db->begin();

        $intervention = new Fichinter($this->db);
        $intervention->socid = $socid;
        $intervention->description = 'Vérification Parc Client '.date('d/m/Y');
        if(!empty($description)) : $intervention->note_public = $description;
        endif;
        $intervention->create($user);

        $gestionparc = new GestionParc($this->db);
        $list_parctypes = $gestionparc->list_parcType();

        // CONTENU
        $lineverif_desc = ''; $i = 0;

        /*var_dump($intervention);
        var_dump($gestionparc);*/

        foreach($list_parctypes as $parctype_id => $parctype_infos): $i++;

            $list_parcFields = $gestionparc->list_parcFields($parctype_id);
            $parc_lines = $gestionparc->getSocParcContent($socid, $parctype_infos['key']);

            $nb_parclines = count($parc_lines);

            if($nb_parclines > 0) :

                $csv_parc = new $export_class_name($this->db);
                $csv_parc->separator = ';';

                // ON DONNE UN NOM AU FICHIER
                $upload_dir = $conf->ficheinter->dir_output.'/'.dol_sanitizeFileName($intervention->ref);
                $result_creadir = dol_mkdir($upload_dir);
                $file_title = 'gestionparc-'.$parctype_infos['key'].'-SOC'.$socid.'-'.date('dmY').'.'.$csv_parc->extension;
                $dir_file = $upload_dir.'/'.$file_title;

                // ON OUVRE LE FICHIER
                $csv_parc->open_file($dir_file, $langs);

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

                    if($enabled[$key_field]) :

                        $column_name = $key_field;
                        if($types[$key_field] == 'prodserv') : $column_name .='-ID';
                        endif;

                        array_push($csv_labels, $column_name);
                        array_push($csv_labels_type, 'Text');

                        if($types[$key_field] == 'prodserv') :
                            array_push($csv_labels, $key_field.'-label');
                            array_push($csv_labels_type, 'Text');
                        endif;

                    endif;
                endforeach;

                $csv_parc->write_title($csv_labels, $csv_labels, $langs, $csv_labels_type);

                $lineverif_desc .= '<br/>';
                $lineverif_desc .= '<b><u>'.$parctype_infos['label'].'</u></b><br/>';

                $full_description = '';

                // POUR CHAQUE ELEMENT ON AJOUTE + 1 SI VERIF
                foreach($parc_lines as $parcline):

                    $csv_line = array();
                    $csv_line_type = array();

                    $full_description .= '- ';

                    if($parcline->verif) :
                        $verified_lines++;
                        array_push($csv_line, 'oui');
                        array_push($csv_line_type, 'Text');
                    else:
                        array_push($csv_line, 'non');
                        array_push($csv_line_type, 'Text');
                    endif;

                    //var_dump($pos,$labels,$types,$enabled);
                    foreach($pos as $key_field => $key_pos):
                        if($enabled[$key_field]) :
                            if(!empty($parcline->{$key_field})) : array_push($csv_line, $parcline->{$key_field});
                            else: array_push($csv_line, ' ');
                            endif;
                            array_push($csv_line_type, 'Text');

                            $full_description .= '<b>'.$labels[$key_field].':</b> '.$parcline->{$key_field}.' ';

                            if($types[$key_field] == 'prodserv') :

                                $p = new Product($this->db);
                                $p->fetch($parcline->{$key_field});

                                array_push($csv_line, $p->label);
                                array_push($csv_line_type, 'Text');

                                $full_description .= '('.$p->label.') ';
                            endif;

                            $full_description .= '<b>/</b> ';

                        endif;
                    endforeach;

                    if($parcline->verif) : $full_description .= '<b>Vérifié: </b>Oui';
                    else: $full_description .= '<b>Vérifié: </b>Non';
                    endif;
                    $full_description .= '<br/>';

                    $csv_parc->write_title($csv_line, $csv_line, $langs, $csv_line_type);
                endforeach;

                $csv_parc->write_footer($langs);
                $csv_parc->close_file();

                $lineverif_desc .= '<span style="font-size:0.85em"><b>Eléments vérifiés:</b> '.$verified_lines.'/'.$nb_parclines.'</span><br/>';
                if(getDolGlobalInt('MAIN_MODULE_GESTIONPARC_VERIFDETAILS')):
                    $lineverif_desc .= '<span style="font-size:0.85em">'.$full_description.'</span><br/>';
                endif;

            endif;
        endforeach;

        // ON AJOUTE LA LIGNE
        $now = dol_now();
        $intervention->addline($user, $intervention->id, $lineverif_desc, $now, $duree);

        // ON VALIDE L'INTERVENTION
        $intervention->setValid($user);

        // ON REPREND L'ENSEMBLE DES INFOS
        $intervention->fetch($intervention->id);

        // Extrafield Fichinter
        $intervention->array_options['options_gestionparc_isverif'] = $rowid;
        $intervention->updateExtraField('gestionparc_isverif');

        // Extrafield Fichinter : commercial / intervenant (repris de la campagne)
        $this->writeInterventionUsers($intervention, $rowid);

        // Extrafields Fichinter : champs personnalisés (snapshot du tiers)
        $this->writeInterventionCustomSocFields($intervention, $socid);

        // Snapshot des données d'organes en BDD : fige l'état au moment de la
        // clôture pour que les exports ultérieurs reflètent la vérif d'origine.
        require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
        $snap_customer = new Societe($this->db);
        $snap_customer->fetch($socid);
        $this->storeVerifSnapshot($rowid, $this->getReportData($gestionparc->list_parcType(1), $snap_customer));

        // ON GENERE LE DOCUMENT
        $intervention->generateDocument($this->model_pdf, $langs);

        // ON CLOS LE MODE VERIF
        $sql_close = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
        $sql_close .= " SET date_close = '".date('Y-m-d H:i:s')."'";
        $sql_close .= ", commentaires = '".$this->db->escape($description)."'";
        $sql_close .= ", fichinter_id = '".$intervention->id."'";
        $sql_close .= ", is_close = '1'";
        $sql_close .= " WHERE rowid = '".$rowid."' AND socid = '".$socid."'";

        $result_close = $this->db->query($sql_close);
        if($result_close && !$error) : $this->db->commit(); return $intervention->id;
        else: $this->db->rollback(); return false;
        endif;
    }

    public function advancedCloseVerif($socid, $description, $duree){

        global $conf, $langs, $user, $mysoc;

        include_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
        include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
        dol_include_once('gestionparc/class/gestionparcexport.class.php');
        dol_include_once('gestionparc/class/gestionparcpdf.class.php');

        $this->db->begin();

        $langs->load('companies');
        $langs->load('bills');
        $langs->load('gestionparc@gestionparc');

        $customer = new Societe($this->db);
        $customer->fetch($socid);

        $intervention = new Fichinter($this->db);
        $intervention->socid = $socid;
        $intervention->description = 'Vérification Parc Client '.date('d/m/Y');
        if (!empty($description)) { $intervention->note_public = $description; }
        $intervention->create($user);
        $lineverif_desc = '';

        $gestionparc    = new GestionParc($this->db);
        $list_parctypes = $gestionparc->list_parcType(1);

        $now = dol_now();
        $intervention->addline($user, $intervention->id, $lineverif_desc, $now, $duree);

        // ON REPREND L'ENSEMBLE DES INFOS
        $intervention->fetch($intervention->id);

        // Extrafield Fichinter
        $intervention->array_options['options_gestionparc_isverif'] = $this->rowid;
        $intervention->updateExtraField('gestionparc_isverif');

        // Extrafield Fichinter : commercial / intervenant (repris de la campagne)
        $this->writeInterventionUsers($intervention, $this->rowid);

        // Extrafields Fichinter : champs personnalisés (snapshot du tiers)
        $this->writeInterventionCustomSocFields($intervention, $socid);

        // ON VALIDE L'INTERVENTION (la ref provisoire devient la ref définitive ici)
        $blop = $intervention->setValid($user);

        // Recharger l'objet pour avoir la ref définitive (ex: FI2024-0001 au lieu de PROV23)
        $intervention->fetch($intervention->id);

        // ── Générer les rapports GestionParc avec la ref définitive ──
        $base_dir = !empty($conf->ficheinter->dir_output) ? $conf->ficheinter->dir_output : (!empty($conf->fichinter->dir_output) ? $conf->fichinter->dir_output : DOL_DATA_ROOT.'/fichinter');
        $upload_dir = $base_dir.'/'.dol_sanitizeFileName($intervention->ref);
        dol_mkdir($upload_dir);

        $sheetfile = new GestionParcExport($this->db);
        $excel_filename = 'rapport_'.dol_sanitizeFileName($intervention->ref).'.'.$sheetfile->extension;
        $excel_file     = $upload_dir.'/'.$excel_filename;
        if (file_exists($excel_file)) dol_delete_file($excel_file);
        $this->_renderExcelReport($intervention, $list_parctypes, $customer, $excel_file);

        $pdf_filename = 'rapport_'.dol_sanitizeFileName($intervention->ref).'.pdf';
        $pdf_file     = $upload_dir.'/'.$pdf_filename;
        if (file_exists($pdf_file)) dol_delete_file($pdf_file);
        $report_data = $this->getReportData($list_parctypes, $customer);
        $this->_renderPDFReport($intervention, $report_data, $customer, $pdf_file);

        // Snapshot des données d'organes en BDD : fige l'état au moment de la
        // clôture pour que les exports ultérieurs reflètent la vérif d'origine.
        $this->storeVerifSnapshot($this->rowid, $report_data);

        // ON GENERE LE DOCUMENT (PDF standard Dolibarr de la ficheinter)
        $intervention->generateDocument($this->model_pdf, $langs);

        // ON CLOS LE MODE VERIF
        $sql_close = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
        $sql_close .= " SET date_close = '".date('Y-m-d H:i:s')."'";
        $sql_close .= ", commentaires = '".$this->db->escape($description)."'";
        $sql_close .= ", fichinter_id = '".$intervention->id."'";
        $sql_close .= ", is_close = '1'";
        $sql_close .= " WHERE rowid = '".$this->rowid."' AND socid = '".$socid."'";

        $result_close = $this->db->query($sql_close);
        if($result_close) : $this->db->commit(); return $intervention->id;
        else: $this->db->rollback(); return false;
        endif;

    }

    /**
     * Standalone method to generate an Excel export for an existing intervention.
     *
     * @param int $fichinter_id
     * @return string|bool Absolute path to the generated file or false on error.
     */
    public function generateExcelExport($fichinter_id)
    {
        global $db, $conf, $langs, $user;

        include_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
        include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
        dol_include_once('gestionparc/class/gestionparcexport.class.php');
        dol_include_once('gestionparc/class/gestionparc.class.php');

        $intervention = new Fichinter($db);
        if ($intervention->fetch($fichinter_id) <= 0) return false;

        $customer = new Societe($db);
        $customer->fetch($intervention->socid);

        $gestionparc = new GestionParc($db);
        $list_parctypes = $gestionparc->list_parcType(1);

        $sheetfile = new GestionParcExport($db);
        
        // Prepare output dir in the intervention's document directory
        $base_dir = !empty($conf->ficheinter->dir_output) ? $conf->ficheinter->dir_output : (!empty($conf->fichinter->dir_output) ? $conf->fichinter->dir_output : DOL_DATA_ROOT.'/fichinter');
        $upload_dir = $base_dir.'/'.dol_sanitizeFileName($intervention->ref);
        dol_mkdir($upload_dir);

        $filename = 'rapport_'.dol_sanitizeFileName($intervention->ref).'.'.$sheetfile->extension;
        $dir_file = $upload_dir.'/'.$filename;


        // Force regeneration: delete existing new-style file AND legacy-style file in the CORRECT folder
        if (file_exists($dir_file)) dol_delete_file($dir_file);
        $legacy_file = $upload_dir.'/rapport_verification.'.$sheetfile->extension;
        if (file_exists($legacy_file)) dol_delete_file($legacy_file);

        // Données figées à la clôture si disponibles (export historique fidèle),
        // sinon données live (anciennes vérifs sans snapshot).
        $report_data = $this->getVerifSnapshotByFichinter($fichinter_id);

        // Render
        if ($this->_renderExcelReport($intervention, $list_parctypes, $customer, $dir_file, $report_data)) {
            return $dir_file;
        }

        return false;
    }

    /**
     * Standalone method to generate a PDF export for an existing intervention.
     *
     * @param int $fichinter_id
     * @return string|bool Absolute path to the generated file or false on error.
     */
    public function generatePDFExport($fichinter_id)
    {
        global $db, $conf, $langs, $user;

        include_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
        include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
        dol_include_once('gestionparc/class/gestionparcpdf.class.php');
        dol_include_once('gestionparc/class/gestionparc.class.php');

        $intervention = new Fichinter($db);
        if ($intervention->fetch($fichinter_id) <= 0) return false;

        $customer = new Societe($db);
        $customer->fetch($intervention->socid);

        $gestionparc = new GestionParc($db);
        $list_parctypes = $gestionparc->list_parcType(1);

        // Prepare output dir in the intervention's document directory
        $base_dir = !empty($conf->ficheinter->dir_output) ? $conf->ficheinter->dir_output : (!empty($conf->fichinter->dir_output) ? $conf->fichinter->dir_output : DOL_DATA_ROOT.'/fichinter');
        $upload_dir = $base_dir.'/'.dol_sanitizeFileName($intervention->ref);
        dol_mkdir($upload_dir);

        $filename = 'rapport_'.dol_sanitizeFileName($intervention->ref).'.pdf';
        $dir_file = $upload_dir.'/'.$filename;

        // Force regeneration
        if (file_exists($dir_file)) dol_delete_file($dir_file);


        // Données figées à la clôture si disponibles (export historique fidèle),
        // sinon données live (anciennes vérifs sans snapshot).
        $report_data = $this->getVerifSnapshotByFichinter($fichinter_id);
        if ($report_data === null) {
            $report_data = $this->getReportData($list_parctypes, $customer);
        }

        // Render
        if ($this->_renderPDFReport($intervention, $report_data, $customer, $dir_file)) {
            return $dir_file;
        }

        return false;
    }

    /**
     * Shared helper to gather all data needed for verification reports (Excel & PDF).
     *
     * @param array $list_parctypes
     * @param Societe $customer
     * @return array Structured data for rendering
     */
    /**
     * Core rendering logic for the Advanced PDF Report.
     *
     * @param Fichinter $intervention
     * @param array     $report_data
     * @param Societe   $customer
     * @param string    $dir_file
     * @return bool
     */
    protected function _renderPDFReport($intervention, $report_data, $customer, $dir_file)
    {
        global $db, $langs, $conf;

        dol_include_once('gestionparc/class/gestionparcpdf.class.php');
        
        $pdf = new GestionParcPDF($this->db);
        $pdf->generate($intervention, $report_data, $customer, $dir_file);

        return true;
    }

    public function getReportData($list_parctypes, $customer)
    {
        global $db, $langs, $conf;
        include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
        
        $gestionparc = new GestionParc($this->db);
        $data = array(
            'max_cols' => 4,
            'sections' => array()
        );

        // 1. Determine max columns
        foreach ($list_parctypes as $parctype_id => $parctype_infos) {
            $nb = 0;
            foreach ($gestionparc->list_parcFields($parctype_id) as $parcfield) {
                if ($parcfield->enabled && $parcfield->view_excel) $nb++;
            }
            if ($nb > $data['max_cols']) $data['max_cols'] = $nb;
        }

        // 2. Gather sections data
        foreach ($list_parctypes as $parctype_id => $parctype_infos) {
            $section = array(
                'label' => $parctype_infos['label'],
                'fields' => array(),
                'lines' => array(),
                'stats' => array('total' => 0, 'verified' => 0)
            );

            // Fields
            foreach ($gestionparc->list_parcFields($parctype_id) as $parcfield) {
                if (!$parcfield->enabled || !$parcfield->view_excel) continue;
                $section['fields'][$parcfield->field_key] = array(
                    'label' => $parcfield->label,
                    'type'  => $parcfield->type,
                    'pos'   => (int) $parcfield->position,
                    'align' => getDolGlobalString('GESTIONPARC_EXCEL_ALIGN_'.strtoupper($parcfield->field_key)),
                );
            }
            if (empty($section['fields'])) continue;
            uasort($section['fields'], function($a, $b) { return $a['pos'] - $b['pos']; });

            // Lines
            $parc_lines = $gestionparc->getSocParcContent($customer->id, $parctype_infos['key']);
            $section['stats']['total'] = count($parc_lines);
            if ($section['stats']['total'] <= 0) continue;

            foreach ($parc_lines as $parcline) {
                if ($parcline->verif) $section['stats']['verified']++;
                
                $row_values = array();
                foreach ($section['fields'] as $fkey => $fdata) {
                    if ($fdata['type'] == 'prodserv') {
                        $p = new Product($this->db);
                        $p->fetch($parcline->{$fkey});
                        $row_values[] = $p->label;
                    } else {
                        $row_values[] = $parcline->{$fkey};
                    }
                }
                $section['lines'][] = $row_values;
            }

            $data['sections'][] = $section;
        }

        return $data;
    }


    /**
     * Core rendering logic for the Advanced Excel Report.
     *
     * @param Fichinter $intervention
     * @param array     $list_parctypes
     * @param Societe   $customer
     * @param string    $dir_file
     * @param array|null $report_data  Données figées (snapshot) ; si null, lecture live
     * @return bool
     */
    protected function _renderExcelReport($intervention, $list_parctypes, $customer, $dir_file, $report_data = null)
    {
        global $db, $langs, $conf;

        $langs->loadLangs(array('companies', 'bills'));

        dol_include_once('gestionparc/class/gestionparcexport.class.php');
        $sheetfile = new GestionParcExport($this->db);

        if (!is_array($report_data)) {
            $report_data = $this->getReportData($list_parctypes, $customer);
        }
        $max_cols_global = $report_data['max_cols'];

        
        $sheetfile->open_file($dir_file, $langs);
        $sheetfile->write_header($langs);

        // ── 2. Create "RAPPORT COMPLET" Sheet ──
        $tab = 1;
        $full_sheet = $sheetfile->workbook->getActiveSheet();
        $sheetfile->setupSheet($full_sheet, $langs->transnoentities('gp_advexp_fullreport'), $max_cols_global);
        $inter_date = !empty($intervention->dateo) ? $intervention->dateo : (!empty($intervention->datec) ? $intervention->datec : null);
        $export_users = self::resolveExportUsers($this->db, $intervention, $customer);
        $extra_header_rows = array();
        foreach (self::getCustomSocFields() as $code => $def) {
            $val = self::resolveCustomSocField($this->db, $code, $intervention, $customer);
            if ($val !== '') $extra_header_rows[] = array('label' => $langs->transnoentities($def['label']), 'value' => $val);
        }
        // En-tête figé (backport) : technicien/commercial/champs client de l'époque, sinon valeurs live
        $snapHeader = (isset($report_data['header']) && is_array($report_data['header'])) ? $report_data['header'] : array();
        $hdr_interv = !empty($snapHeader['technicien']) ? $snapHeader['technicien'] : $export_users['intervenant'];
        $hdr_comm   = !empty($snapHeader['commercial']) ? $snapHeader['commercial'] : $export_users['commercial'];
        $full_row = $sheetfile->customHeader($full_sheet, 1, $max_cols_global, $customer, '', $inter_date, $hdr_interv, $hdr_comm, $extra_header_rows, $snapHeader);

        foreach ($report_data['sections'] as $section) {
            // Render Individual Worksheets
            $col_labels = array();
            $align_map  = array();
            $c_idx = 0;
            foreach ($section['fields'] as $fdata) {
                $col_labels[] = $fdata['label'];
                if (!empty($fdata['align'])) $align_map[$c_idx] = $fdata['align'];
                $c_idx++;
            }

            $nb_data_cols = count($col_labels);
            $tab++;
            $sheetfile->workbook->createSheet();
            $sheetfile->workbook->setActiveSheetIndex($tab - 1);
            $sheet = $sheetfile->workbook->getActiveSheet();
            $sheetfile->setupSheet($sheet, $section['label'], $nb_data_cols);

            $row = $sheetfile->customHeader($sheet, 1, $nb_data_cols, $customer, $section['label'], $inter_date, $hdr_interv, $hdr_comm, $extra_header_rows, $snapHeader);
            $row = $sheetfile->writeColumnHeaders($sheet, $row, $col_labels);

            // Feuille consolidée : chaque organe occupe la largeur totale (max_cols_global) ;
            // les organes plus courts fusionnent leur dernière cellule pour la même largeur.
            $full_row = $sheetfile->writeSectionTitle($full_sheet, $full_row, $max_cols_global, $section['label']);
            $full_row = $sheetfile->writeColumnHeaders($full_sheet, $full_row, $col_labels, $max_cols_global);

            foreach ($section['lines'] as $l_idx => $values) {
                $isOdd = ($l_idx % 2 === 1);
                $row      = $sheetfile->writeDataRow($sheet, $row, $values, $isOdd, $align_map);
                $full_row = $sheetfile->writeDataRow($full_sheet, $full_row, $values, $isOdd, $align_map, $max_cols_global);
            }

            $row      = $sheetfile->writeSummaryRow($sheet, $row, $section['stats']['verified'], $section['stats']['total'], $nb_data_cols);
            $full_row = $sheetfile->writeSummaryRow($full_sheet, $full_row, $section['stats']['verified'], $section['stats']['total'], $max_cols_global);
            $full_row += 2;
        }


        $sheetfile->write_footer($langs);
        $sheetfile->close_file();

        return true;
    }

    public function getLastVerif($socid)
    {

        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE socid = ".$socid." AND is_close = 1 ORDER BY rowid DESC LIMIT 1";
        $result = $this->db->query($sql);

        $item = $this->db->fetch_object($result);

        if($result->num_rows == 0) : return 0;
     else: return $item->fichinter_id;
     endif;
    }

    /*****************************************************************/
    // MODIFIER LE TIERS D'UNE VERIF - EN CAS DE FUSION DE TIERS
    /*****************************************************************/
    public function mergeVerifs($origin_socid,$dest_socid)
    {

        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
        $sql.= " SET socid = '".$dest_socid."'";
        $sql.= " WHERE socid = '".$origin_socid."'";
        $result = $this->db->query($sql);

        $nb = $this->db->db->affected_rows;

        if($result) : $this->db->commit(); return $nb;
     else: $this->db->rollback(); return -1;
     endif;

    }
}