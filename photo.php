<?php
/* Copyright (C) 2026 Progiseize
 *
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Sert une photo de vérification. Les fichiers vivent hors du docroot
 * (DOL_DATA_ROOT), l'accès passe donc obligatoirement par ce script qui
 * vérifie les droits du module.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) : $res = @include '../main.inc.php'; endif;
if (!$res && file_exists("../../main.inc.php")) : $res = @include '../../main.inc.php'; endif;
if (!$res && file_exists("../../../main.inc.php")) : $res = @include '../../../main.inc.php'; endif;

dol_include_once('/gestionparc/class/gestionparcphoto.class.php');

if ($user->socid > 0 || !$user->hasRight('gestionparc', 'parc', 'read')) {
	accessforbidden();
}

$id    = GETPOSTINT('id');
$thumb = GETPOSTINT('thumb');

$gpphoto = new GestionParcPhoto($db);
$photo = $gpphoto->fetch($id);
if (!$photo) {
	header('HTTP/1.0 404 Not Found');
	exit;
}

$file = GestionParcPhoto::getAbsolutePath($photo->filepath);

// Le chemin doit rester sous le répertoire du module : garde-fou contre toute
// valeur de filepath fabriquée en base.
$base = realpath(DOL_DATA_ROOT.'/gestionparc');
$real = realpath($file);
if ($base === false || $real === false || strpos($real, $base.DIRECTORY_SEPARATOR) !== 0) {
	header('HTTP/1.0 404 Not Found');
	exit;
}

$info = @getimagesize($real);
$mime = ($info && !empty($info['mime'])) ? $info['mime'] : 'application/octet-stream';

// Vignette générée à la volée et mise en cache à côté du fichier d'origine
if ($thumb && $info !== false) {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';

	$thumb_dir = dirname($real).'/thumbs';
	if (!dol_is_dir($thumb_dir)) dol_mkdir($thumb_dir);
	$thumb_file = $thumb_dir.'/'.$photo->filename;

	if (!file_exists($thumb_file) || filemtime($thumb_file) < filemtime($real)) {
		$w = (int) $info[0];
		$h = (int) $info[1];
		$max = 320;
		if ($w >= $h) {
			$nw = min($w, $max);
			$nh = (int) round($h * $nw / $w);
		} else {
			$nh = min($h, $max);
			$nw = (int) round($w * $nh / $h);
		}
		@dol_imageResizeOrCrop($real, 0, $nw, $nh, 0, 0, $thumb_file, 80);
	}

	if (file_exists($thumb_file)) {
		$real = $thumb_file;
		$tinfo = @getimagesize($real);
		if ($tinfo && !empty($tinfo['mime'])) $mime = $tinfo['mime'];
	}
}

header('Content-Type: '.$mime);
header('Content-Length: '.filesize($real));
header('Content-Disposition: inline; filename="'.basename($photo->filename).'"');
header('Cache-Control: private, max-age=3600');
readfile($real);
exit;
