<?php
// /grabiton/admin/_install_make_upload_dirs.php
// Legt benötigte Upload-Ordner an und schreibt Schutz-Dateien

$base = dirname(__DIR__) . '/uploads';
$dirs = [
    $base,
    $base . '/avatars',
    $base . '/banners',
    $base . '/audio',
    $base . '/covers',
];

$ok = true; $out = [];

foreach ($dirs as $d) {
    if (!is_dir($d)) {
        if (!mkdir($d, 0755, true)) {
            $ok = false;
            $out[] = "✗ Konnte Ordner nicht anlegen: $d";
        } else {
            $out[] = "✓ Ordner angelegt: $d";
        }
    } else {
        $out[] = "• Ordner vorhanden: $d";
    }

    // index.html (verhindert Verzeichnislisting)
    $indexFile = $d . '/index.html';
    if (!file_exists($indexFile)) {
        file_put_contents($indexFile, "<!doctype html><title></title>");
        $out[] = "  ↳ index.html geschrieben";
    }

    // .htaccess: Ausführung von Skripten verhindern (so gut es geht)
    $ht = $d . '/.htaccess';
    if (!file_exists($ht)) {
        $rules = <<<HT
# Verhindere das Ausführen von Scripten in Upload-Verzeichnissen
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8
<FilesMatch "\.(php|phtml|php\d+)$">
  Deny from all
</FilesMatch>
Options -ExecCGI
HT;
        file_put_contents($ht, $rules);
        $out[] = "  ↳ .htaccess geschrieben";
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $out), "\n";
echo $ok ? "\nFERTIG: Upload-Verzeichnisse bereit.\n" : "\nACHTUNG: Es gab Fehler (Rechte prüfen).\n";
