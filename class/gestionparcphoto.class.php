<?php
/* Copyright (C) 2026 Progiseize */

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';

/**
 * GestionParcPhoto — photos prises pendant une vérification.
 *
 * Une photo est rattachée à un élément de parc (ligne) ET à la vérification en
 * cours : une même ligne peut donc porter des photos différentes d'une année sur
 * l'autre, et un export historique retrouve les clichés de sa campagne.
 *
 * Stockage : DOL_DATA_ROOT/gestionparc/verif/<verif_id>/<parc_key>_<item_id>_<uniq>.jpg
 * Le dossier de la vérif est la source de vérité (il survit à la clôture) ; à la
 * clôture les fichiers sont recopiés dans les documents de l'intervention pour
 * être visibles nativement dans Dolibarr.
 */
class GestionParcPhoto
{
    public $table_element = 'gestionparc_photos';
    public $db;
    public $error = '';

    /** Cache de l'option "photo obligatoire" par organe (une requête par parc_key) */
    protected static $requiredCache = array();

    /** Côté le plus long conservé au stockage (px) */
    const MAX_SIDE = 1600;
    /** Qualité JPEG de recompression */
    const JPEG_QUALITY = 82;
    /** Taille max acceptée à l'upload (octets) */
    const MAX_UPLOAD_SIZE = 20971520; // 20 Mo

    public function __construct($db)
    {
        $this->db = $db;
    }

    /*****************************************************************/
    // SCHEMA (migration idempotente, comme les autres ensureXxx du module)
    /*****************************************************************/
    public function ensureSchema()
    {
        $table = MAIN_DB_PREFIX.$this->table_element;

        $res = $this->db->query("SHOW TABLES LIKE '".$this->db->escape($table)."'");
        if ($res && $this->db->num_rows($res) == 0) {
            $sql = "CREATE TABLE ".$table." (";
            $sql .= " rowid int NOT NULL AUTO_INCREMENT,";
            $sql .= " verif_id int NOT NULL DEFAULT 0,";
            $sql .= " socid int NOT NULL DEFAULT 0,";
            $sql .= " parc_id int NOT NULL DEFAULT 0,";
            $sql .= " parc_key varchar(32) NOT NULL,";
            $sql .= " item_id int NOT NULL DEFAULT 0,";
            $sql .= " filename varchar(255) NOT NULL,";
            $sql .= " filepath varchar(255) NOT NULL,";
            $sql .= " filesize int NOT NULL DEFAULT 0,";
            $sql .= " position int NOT NULL DEFAULT 0,";
            $sql .= " author int NOT NULL DEFAULT 0,";
            $sql .= " date_creation datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,";
            $sql .= " entity int NOT NULL DEFAULT 1,";
            $sql .= " PRIMARY KEY (rowid),";
            $sql .= " KEY idx_gpphotos_verif (verif_id),";
            $sql .= " KEY idx_gpphotos_item (parc_key, item_id)";
            $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8";
            $this->db->query($sql);
        }

        // Option "photo obligatoire" portée par l'organe
        $parc_table = MAIN_DB_PREFIX.'gestionparc';
        $rescol = $this->db->query("SHOW COLUMNS FROM ".$parc_table." LIKE 'photo_required'");
        if ($rescol && $this->db->num_rows($rescol) == 0) {
            $this->db->query("ALTER TABLE ".$parc_table." ADD photo_required BOOLEAN NOT NULL DEFAULT 0");
        }
    }

    /*****************************************************************/
    // CHEMINS
    /*****************************************************************/

    /** Répertoire de stockage d'une vérif (absolu) */
    public static function getVerifDir($verif_id)
    {
        return DOL_DATA_ROOT.'/gestionparc/verif/'.(int) $verif_id;
    }

    /** Chemin absolu d'une photo à partir de son filepath relatif */
    public static function getAbsolutePath($filepath)
    {
        return DOL_DATA_ROOT.'/'.ltrim((string) $filepath, '/');
    }

    /*****************************************************************/
    // AJOUT D'UNE PHOTO (depuis un $_FILES[...])
    // $file : array('name','tmp_name','size','error')
    // Retourne l'id inséré, ou 0 en cas d'échec ($this->error renseigné).
    /*****************************************************************/
    public function add($user, $verif_id, $socid, $parc_id, $parc_key, $item_id, $file)
    {
        global $conf, $langs;

        $this->ensureSchema();

        if (empty($verif_id) || empty($parc_key) || empty($item_id)) {
            $this->error = 'BadParameters';
            return 0;
        }
        if (!isset($file['tmp_name']) || !$this->isUploaded($file['tmp_name'])) {
            $this->error = 'NoFileUploaded';
            return 0;
        }
        if (!empty($file['error'])) {
            $this->error = 'UploadError'.$file['error'];
            return 0;
        }
        if ($file['size'] > self::MAX_UPLOAD_SIZE) {
            $this->error = 'FileTooBig';
            return 0;
        }

        // Type réel : on ne se fie pas à l'extension envoyée par le navigateur
        $info = @getimagesize($file['tmp_name']);
        if ($info === false || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP), true)) {
            $this->error = 'NotAnImage';
            return 0;
        }

        $dir = self::getVerifDir($verif_id);
        if (!dol_is_dir($dir)) dol_mkdir($dir);
        if (!dol_is_dir($dir) || !is_writable($dir)) {
            // Cas typique : DOL_DATA_ROOT/gestionparc non inscriptible par le serveur web
            dol_syslog('GestionParcPhoto::add cannot write into '.$dir, LOG_ERR);
            $this->error = 'CannotCreateDir';
            return 0;
        }

        $ext      = ($info[2] == IMAGETYPE_PNG) ? 'png' : (($info[2] == IMAGETYPE_GIF) ? 'gif' : (($info[2] == IMAGETYPE_WEBP) ? 'webp' : 'jpg'));
        $basename = dol_sanitizeFileName($parc_key.'_'.(int) $item_id.'_'.dol_print_date(dol_now(), '%Y%m%d%H%M%S').'_'.substr(md5(uniqid('gpphoto', true)), 0, 6).'.'.$ext);
        $dest     = $dir.'/'.$basename;

        if ($this->moveUploaded($file, $dest) <= 0) {
            dol_syslog('GestionParcPhoto::add move failed to '.$dest, LOG_ERR);
            $this->error = 'MoveFailed';
            return 0;
        }
        @chmod($dest, octdec(!empty($conf->global->MAIN_UMASK) ? $conf->global->MAIN_UMASK : '0664'));

        // Redresse l'orientation EXIF (photos smartphone) puis réduit le gabarit
        if ($info[2] == IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            @correctExifImageOrientation($dest, $dest, self::JPEG_QUALITY);
        }
        $this->shrink($dest);

        $filesize = (int) @filesize($dest);
        $filepath = 'gestionparc/verif/'.(int) $verif_id.'/'.$basename;

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " (verif_id, socid, parc_id, parc_key, item_id, filename, filepath, filesize, position, author, entity)";
        $sql .= " VALUES (";
        $sql .= (int) $verif_id;
        $sql .= ", ".(int) $socid;
        $sql .= ", ".(int) $parc_id;
        $sql .= ", '".$this->db->escape($parc_key)."'";
        $sql .= ", ".(int) $item_id;
        $sql .= ", '".$this->db->escape($basename)."'";
        $sql .= ", '".$this->db->escape($filepath)."'";
        $sql .= ", ".$filesize;
        $sql .= ", ".($this->countByItem($verif_id, $parc_key, $item_id) + 1);
        $sql .= ", ".(int) $user->id;
        $sql .= ", ".(int) $conf->entity;
        $sql .= ")";

        if (!$this->db->query($sql)) {
            dol_delete_file($dest);
            $this->error = $this->db->lasterror();
            return 0;
        }

        return (int) $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
    }

    /*****************************************************************/
    // PLOMBERIE D'UPLOAD (isolée pour rester testable hors requête HTTP)
    /*****************************************************************/
    protected function isUploaded($tmp_name)
    {
        return is_uploaded_file($tmp_name);
    }

    protected function moveUploaded($file, $dest)
    {
        return dol_move_uploaded_file($file['tmp_name'], $dest, 1, 0, (int) $file['error'], 1);
    }

    /*****************************************************************/
    // REDUCTION DU GABARIT (le côté le plus long tombe à MAX_SIDE)
    /*****************************************************************/
    protected function shrink($file)
    {
        $info = @getimagesize($file);
        if ($info === false) return;

        $w = (int) $info[0];
        $h = (int) $info[1];
        if ($w <= self::MAX_SIDE && $h <= self::MAX_SIDE) return;

        if ($w >= $h) {
            $newW = self::MAX_SIDE;
            $newH = (int) round($h * self::MAX_SIDE / $w);
        } else {
            $newH = self::MAX_SIDE;
            $newW = (int) round($w * self::MAX_SIDE / $h);
        }

        @dol_imageResizeOrCrop($file, 0, $newW, $newH, 0, 0, $file, self::JPEG_QUALITY);
    }

    /*****************************************************************/
    // SUPPRESSION
    /*****************************************************************/
    public function delete($rowid)
    {
        $photo = $this->fetch($rowid);
        if (!$photo) return false;

        $abs = self::getAbsolutePath($photo->filepath);
        if (file_exists($abs)) dol_delete_file($abs);

        // Vignette mise en cache par photo.php
        $thumb = dirname($abs).'/thumbs/'.$photo->filename;
        if (file_exists($thumb)) dol_delete_file($thumb);

        $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".(int) $rowid;
        return (bool) $this->db->query($sql);
    }

    /** Supprime toutes les photos d'une vérif (annulation) */
    public function deleteByVerif($verif_id)
    {
        foreach ($this->listByVerif($verif_id) as $photo) {
            $abs = self::getAbsolutePath($photo->filepath);
            if (file_exists($abs)) dol_delete_file($abs);
        }
        $dir = self::getVerifDir($verif_id);
        if (dol_is_dir($dir)) @dol_delete_dir_recursive($dir, 0, 1, 0, $tmpcount, 0);

        $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE verif_id = ".(int) $verif_id;
        return (bool) $this->db->query($sql);
    }

    /*****************************************************************/
    // LECTURES
    /*****************************************************************/
    public function fetch($rowid)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".(int) $rowid;
        $res = $this->db->query($sql);
        if (!$res || $this->db->num_rows($res) == 0) return null;
        return $this->db->fetch_object($res);
    }

    /** Photos d'un élément pour une vérif donnée */
    public function listByItem($verif_id, $parc_key, $item_id)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE verif_id = ".(int) $verif_id;
        $sql .= " AND parc_key = '".$this->db->escape($parc_key)."'";
        $sql .= " AND item_id = ".(int) $item_id;
        $sql .= " ORDER BY position ASC, rowid ASC";

        return $this->fetchAll($sql);
    }

    /** Toutes les photos d'une vérif */
    public function listByVerif($verif_id)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE verif_id = ".(int) $verif_id;
        $sql .= " ORDER BY parc_key ASC, item_id ASC, position ASC, rowid ASC";

        return $this->fetchAll($sql);
    }

    /** Photos d'une vérif indexées [parc_key][item_id] => array de photos */
    public function mapByVerif($verif_id)
    {
        $map = array();
        foreach ($this->listByVerif($verif_id) as $photo) {
            $map[$photo->parc_key][(int) $photo->item_id][] = $photo;
        }
        return $map;
    }

    public function countByItem($verif_id, $parc_key, $item_id)
    {
        $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE verif_id = ".(int) $verif_id;
        $sql .= " AND parc_key = '".$this->db->escape($parc_key)."'";
        $sql .= " AND item_id = ".(int) $item_id;
        $res = $this->db->query($sql);
        if (!$res) return 0;
        $obj = $this->db->fetch_object($res);
        return (int) $obj->nb;
    }

    protected function fetchAll($sql)
    {
        $rows = array();
        $res = $this->db->query($sql);
        if (!$res) return $rows;
        while ($obj = $this->db->fetch_object($res)) {
            $rows[] = $obj;
        }
        return $rows;
    }

    /*****************************************************************/
    // DESCRIPTION DE L'ELEMENT DE PARC PORTANT UNE PHOTO
    //
    // Sert à légender les clichés hors du contexte de la vérification
    // (onglet des interventions) : organe, numéro et produit associés.
    // Retourne array('organ', 'number', 'product', 'exists').
    /*****************************************************************/
    public function describeItem($parc_key, $item_id)
    {
        dol_include_once('/gestionparc/class/gestionparc.class.php');

        $out = array('organ' => $parc_key, 'number' => '', 'product' => '', 'exists' => false);

        $gestionparc = new GestionParc($this->db);
        if ($gestionparc->fetch_parcType(0, false, $parc_key) <= 0) return $out;
        $out['organ'] = $gestionparc->label;

        $table = MAIN_DB_PREFIX.'gestionparc__'.$this->db->escape($parc_key);
        $res = $this->db->query("SELECT * FROM ".$table." WHERE rowid = ".(int) $item_id);
        if (!$res || $this->db->num_rows($res) == 0) return $out; // élément retiré du parc depuis
        $line = $this->db->fetch_object($res);
        $out['exists'] = true;

        // Numéro affiché : champ de numérotation de l'organe
        $num_field = $gestionparc->getNumeroFieldKey($parc_key);
        if ($num_field !== '' && !empty($line->{$num_field})) {
            $out['number'] = $line->{$num_field};
        }

        // Produit associé : premier champ de type prodserv de l'organe
        foreach ((array) $gestionparc->fields as $field) {
            if (empty($field->enabled) || $field->type != 'prodserv') continue;
            $prod_id = (int) $line->{$field->field_key};
            if ($prod_id <= 0) continue;

            require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
            $product = new Product($this->db);
            if ($product->fetch($prod_id) > 0) $out['product'] = $product->label;
            break;
        }

        return $out;
    }

    /*****************************************************************/
    // COPIE DANS LES DOCUMENTS DE L'INTERVENTION (à la clôture)
    // Le dossier de la vérif reste la référence ; cette copie sert à
    // rendre les clichés visibles dans l'onglet Documents de Dolibarr.
    /*****************************************************************/
    public function copyToIntervention($verif_id, $upload_dir)
    {
        $photos = $this->listByVerif($verif_id);
        if (empty($photos)) return 0;

        $dest_dir = rtrim($upload_dir, '/').'/photos';
        if (!dol_is_dir($dest_dir)) dol_mkdir($dest_dir);
        if (!dol_is_dir($dest_dir)) return 0;

        $nb = 0;
        foreach ($photos as $photo) {
            $src = self::getAbsolutePath($photo->filepath);
            if (!file_exists($src)) continue;
            if (dol_copy($src, $dest_dir.'/'.$photo->filename, '0', 1) > 0) $nb++;
        }

        return $nb;
    }

    /*****************************************************************/
    // REMPLACEMENT DU CLICHE D'UNE PHOTO EXISTANTE
    //
    // La ligne (et donc son rattachement à l'élément de parc) est conservée,
    // seul le fichier change. Retourne true, ou false avec $this->error.
    /*****************************************************************/
    public function replaceFile($user, $rowid, $file)
    {
        $photo = $this->fetch($rowid);
        if (!$photo) {
            $this->error = 'UnknownPhoto';
            return false;
        }

        $old_path = $photo->filepath;
        $old_name = $photo->filename;

        // On réutilise le circuit d'ajout, puis on bascule la ligne d'origine
        // sur le nouveau fichier : le nouvel enregistrement n'est que temporaire.
        $newid = $this->add($user, $photo->verif_id, $photo->socid, $photo->parc_id, $photo->parc_key, $photo->item_id, $file);
        if ($newid <= 0) return false; // $this->error déjà renseigné

        $new = $this->fetch($newid);

        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " SET filename = '".$this->db->escape($new->filename)."'";
        $sql .= ", filepath = '".$this->db->escape($new->filepath)."'";
        $sql .= ", filesize = ".(int) $new->filesize;
        $sql .= " WHERE rowid = ".(int) $rowid;
        if (!$this->db->query($sql)) {
            $this->error = $this->db->lasterror();
            $this->delete($newid);
            return false;
        }

        // Suppression de la ligne temporaire sans toucher au fichier qu'elle a produit
        $this->db->query("DELETE FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".(int) $newid);

        // Puis retrait de l'ancien fichier et de sa vignette
        $old_abs = self::getAbsolutePath($old_path);
        if (file_exists($old_abs)) dol_delete_file($old_abs);
        $old_thumb = dirname($old_abs).'/thumbs/'.$old_name;
        if (file_exists($old_thumb)) dol_delete_file($old_thumb);

        return true;
    }

    /*****************************************************************/
    // COPIE D'UNE PHOTO DANS LES DOCUMENTS DE L'INTERVENTION
    // Garde le dossier documents de l'intervention aligné après une
    // modification ou une suppression faite depuis son onglet.
    /*****************************************************************/
    public function syncInterventionCopy($photo, $upload_dir, $previous_filename = '')
    {
        $dest_dir = rtrim($upload_dir, '/').'/photos';

        if ($previous_filename !== '' && file_exists($dest_dir.'/'.$previous_filename)) {
            dol_delete_file($dest_dir.'/'.$previous_filename);
        }
        if (!is_object($photo)) return;

        if (!dol_is_dir($dest_dir)) dol_mkdir($dest_dir);
        if (!dol_is_dir($dest_dir)) return;

        $src = self::getAbsolutePath($photo->filepath);
        if (file_exists($src)) dol_copy($src, $dest_dir.'/'.$photo->filename, '0', 1);
    }

    /*****************************************************************/
    // OPTION "PHOTO OBLIGATOIRE" D'UN ORGANE
    /*****************************************************************/
    public function isPhotoRequired($parc_key)
    {
        // Appelé pour chaque ligne de parc au rendu de l'onglet : on garde le
        // résultat en cache pour ne pas requêter à chaque élément.
        if (array_key_exists($parc_key, self::$requiredCache)) {
            return self::$requiredCache[$parc_key];
        }

        $required = false;
        $table = MAIN_DB_PREFIX.'gestionparc';
        $rescol = $this->db->query("SHOW COLUMNS FROM ".$table." LIKE 'photo_required'");
        if ($rescol && $this->db->num_rows($rescol) > 0) {
            $sql = "SELECT photo_required FROM ".$table." WHERE parc_key = '".$this->db->escape($parc_key)."'";
            $res = $this->db->query($sql);
            if ($res && $this->db->num_rows($res) > 0) {
                $obj = $this->db->fetch_object($res);
                $required = !empty($obj->photo_required);
            }
        }

        self::$requiredCache[$parc_key] = $required;
        return $required;
    }

    /** Active/désactive l'obligation de photo sur un organe */
    public function setPhotoRequired($parc_id, $yesorno)
    {
        $this->ensureSchema();
        self::$requiredCache = array();
        $sql = "UPDATE ".MAIN_DB_PREFIX."gestionparc SET photo_required = ".($yesorno ? 1 : 0);
        $sql .= " WHERE rowid = ".(int) $parc_id;
        return (bool) $this->db->query($sql);
    }

    /*****************************************************************/
    // URL DE TELECHARGEMENT D'UNE PHOTO (contrôle d'accès côté script)
    /*****************************************************************/
    public static function getViewUrl($rowid, $thumb = false)
    {
        return dol_buildpath('/gestionparc/photo.php', 1).'?id='.(int) $rowid.($thumb ? '&thumb=1' : '');
    }
}
