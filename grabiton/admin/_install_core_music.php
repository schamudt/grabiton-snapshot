<?php
// /grabiton/admin/_install_core_music.php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1); error_reporting(E_ALL);

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

/* artists muss existieren (du hast sie schon) */
if (!table_exists($pdo, 'artists')) {
  $pdo->exec("
    CREATE TABLE artists (
      id INT AUTO_INCREMENT PRIMARY KEY,
      artist_name VARCHAR(120) NOT NULL,
      genre VARCHAR(120) DEFAULT NULL,
      banner_path VARCHAR(255) DEFAULT NULL,
      avatar_path VARCHAR(255) DEFAULT NULL,
      artist_code VARCHAR(11) DEFAULT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY ux_artist_code (artist_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
  $out[] = "✓ Tabelle 'artists' erstellt (war nicht vorhanden).";
} else {
  $out[] = "• 'artists' vorhanden.";
}

/* releases */
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
  $out[] = "• 'releases' vorhanden.";
}

/* songs (FEHLTE BEI DIR) */
if (!table_exists($pdo, 'songs')) {
  $pdo->exec("
    CREATE TABLE songs (
      id INT AUTO_INCREMENT PRIMARY KEY,
      artist_id INT NOT NULL,
      title VARCHAR(200) NOT NULL,
      audio_path VARCHAR(255) DEFAULT NULL,
      cover_path VARCHAR(255) DEFAULT NULL,
      is_featured TINYINT(1) NOT NULL DEFAULT 0,
      release_id INT DEFAULT NULL,
      track_number INT DEFAULT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_songs_artist (artist_id),
      INDEX idx_songs_release (release_id),
      INDEX idx_songs_track (track_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
  $out[] = "✓ Tabelle 'songs' erstellt.";
} else {
  $out[] = "• 'songs' vorhanden.";
  if (!col_exists($pdo, 'songs', 'release_id')) { $pdo->exec("ALTER TABLE songs ADD COLUMN release_id INT NULL"); $out[]="✓ songs.release_id hinzugefügt."; }
  if (!col_exists($pdo, 'songs', 'track_number')) { $pdo->exec("ALTER TABLE songs ADD COLUMN track_number INT NULL"); $out[]="✓ songs.track_number hinzugefügt."; }
}

echo implode("\n", $out), "\n\nFERTIG.\n";
