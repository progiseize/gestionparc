<?php
/* Copyright (C) 2026 Progiseize
 *
 * Backport des snapshots de vérification (report_snapshot) à partir des
 * rapports XLSX historiques générés par le module dans chaque intervention.
 *
 * Wrapper CLI : bootstrappe Dolibarr et appelle GestionParcVerif::backportSnapshots().
 * La même logique est exposée dans Configuration > GestionParc > Réparation.
 *
 * Lancer (dans le conteneur / sur le serveur Dolibarr) :
 *   php custom/gestionparc/scripts/backport_snapshots.php [--commit] [--force] [--limit=N]
 *
 *   (sans --commit : dry-run, n'écrit rien)
 */

if (substr(php_sapi_name(), 0, 3) !== 'cli') { echo "CLI only\n"; exit(1); }

$commit = false; $force = false; $limit = 0;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--commit') $commit = true;
    elseif ($a === '--force') $force = true;
    elseif (preg_match('/^--limit=(\d+)$/', $a, $m)) $limit = (int) $m[1];
}

// Bootstrap Dolibarr
if (!defined('NOSESSION')) define('NOSESSION', '1');
$res = 0;
$tmp = realpath(__DIR__);
$candidates = array(
    __DIR__.'/../../../master.inc.php',   // custom/gestionparc/scripts -> htdocs
    __DIR__.'/../../../../master.inc.php',
    __DIR__.'/../../../main.inc.php',
);
foreach ($candidates as $c) { if (file_exists($c)) { $res = @include $c; if ($res) break; } }
if (!$res) { fwrite(STDERR, "master.inc.php introuvable (lancez depuis l'arborescence Dolibarr)\n"); exit(1); }

dol_include_once('/gestionparc/class/gestionparc.class.php');

echo "=== Backport snapshots de vérification (XLSX) ".($commit ? "[COMMIT]" : "[DRY-RUN]")." ===\n";

$gpverif = new GestionParcVerif($db);
$stats = $gpverif->backportSnapshots($commit, $force, $limit);

echo "\n=== Résumé ===\n";
foreach ($stats as $k => $v) echo str_pad($k, 16).": $v\n";
if (!$commit) echo "\n(DRY-RUN : rien écrit. Relancer avec --commit pour appliquer.)\n";

$db->close();
exit(0);
