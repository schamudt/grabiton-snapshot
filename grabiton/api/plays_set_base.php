<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (!headers_sent()) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
}
require_once __DIR__ . '/../includes/db.php';

/*
  Materialisiertes „Basis“-Zählwerk:
  - Admin gibt gewünschte Gesamtsumme N vor.
  - Wir setzen base_count = max(0, N - COUNT(plays)).
  - Ab jetzt wird total = base_count + COUNT(plays) angezeigt – neue Plays zählen weiter.
*/

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false, 'error'=>'method_not_allowed']); exit;
  }
  $songId = isset($_POST['song_id']) ? (int)$_POST['song_id'] : 0;
  $desired = isset($_POST['desired_total']) ? (int)$_POST['desired_total'] : -1;

  if ($songId <= 0 || $desired < 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'bad_request']); exit;
  }

  // sicherstellen, dass play_counters existiert
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS play_counters (
      song_id    INT PRIMARY KEY,
      base_count INT NOT NULL DEFAULT 0,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      CONSTRAINT fk_pc_song FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  // aktuelles Log zählen
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM plays WHERE song_id = ?");
  $stmt->execute([$songId]);
  $logged = (int)$stmt->fetchColumn();

  $base = max(0, $desired - $logged);

  // upsert
  $stmt2 = $pdo->prepare("
    INSERT INTO play_counters (song_id, base_count)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE base_count = VALUES(base_count)
  ");
  $stmt2->execute([$songId, $base]);

  echo json_encode([
    'ok'     => true,
    'total'  => $base + $logged,
    'base'   => $base,
    'logged' => $logged,
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'server_error']);
}
