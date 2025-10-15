<?php
// /grabiton/admin/_install_releases.php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

// Optional: Fehler zeigen, um schneller zu debuggen (später wieder auskommentieren)
ini_set('display_errors', 1);
error_reporting(E_ALL);

function table_exists(PDO $pdo, string $table): bool {
    $s = $pdo->prepare("SHOW TABLES LIKE ?");
    $s->execute([$table]);
    return (bool)$s->fetch();
}
function col_exists(PDO $pdo, string $table, string $col): bool {
    $s = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $s->execute([$col]);
    return (bool)$s->fetch();
}

$out = [];

/* 1) releases */
try {
    if (!table_exists($pdo, 'releases')) {
        $pdo->exec("
            CREATE TABLE releases (
              id INT AUTO_INCREMENT PRIMARY KEY,
              artist_id INT NOT NULL,
              title VARCHAR(200) NOT NULL,
              release_type ENUM('single','ep','album') NOT NULL,
              cover_path VARCHAR(255) DEFAULT NULL,
              release_date DATE DEFAULT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_releases_artist (artist_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $out[] = "✓ Tabelle 'releases' erstellt.";
    } else {
        $out[] = "• Tabelle 'releases' vorhanden.";
    }
} catch (Throwable $e) {
    $out[] = "✗ releases: " . $e->getMessage();
}

/* 2) songs-Spalten */
try {
    if (!col_exists($pdo, 'songs', 'release_id')) {
        $pdo->exec("ALTER TABLE songs ADD COLUMN release_id INT NULL");
        $out[] = "✓ songs.release_id hinzugefügt.";
    } else { $out[] = "• songs.release_id vorhanden."; }

    if (!col_exists($pdo, 'songs', 'track_number')) {
        $pdo->exec("ALTER TABLE songs ADD COLUMN track_number INT NULL");
        $out[] = "✓ songs.track_number hinzugefügt.";
    } else { $out[] = "• songs.track_number vorhanden."; }

    try { $pdo->exec("CREATE INDEX idx_songs_release_id ON songs(release_id)"); $out[]="✓ Index songs.release_id"; }
    catch (Throwable $e) { $out[]="• Index songs.release_id evtl. vorhanden."; }

    try { $pdo->exec("CREATE INDEX idx_songs_track_number ON songs(track_number)"); $out[]="✓ Index songs.track_number"; }
    catch (Throwable $e) { $out[]="• Index songs.track_number evtl. vorhanden."; }

} catch (Throwable $e) {
    $out[] = "✗ songs alter: " . $e->getMessage();
}

/* 3) Upload-Ordner check (nur Hinweis) */
$base = dirname(__DIR__) . '/uploads';
$dirs = [$base, "$base/audio", "$base/covers"];
foreach ($dirs as $d) {
    if (!is_dir($d)) $out[] = "⚠ Hinweis: Upload-Ordner fehlt: $d (bitte _install_make_upload_dirs.php ausführen)";
}

echo implode("\n", $out), "\n\nFERTIG.\n";
