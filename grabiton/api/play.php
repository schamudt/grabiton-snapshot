<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (!headers_sent()) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
}
require_once __DIR__ . '/../includes/db.php';

/**
 * Optional: gleiche SALT-Konstante wie bei Likes verwenden.
 * WICHTIG: Wirklich auf einen langen, zufälligen String setzen!
 */
const GIO_IP_SALT = 'CHANGE_ME_USE_A_LONG_RANDOM_SECRET';

function client_ip(): string {
  return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/*
-- Tabelle (falls noch nicht vorhanden):
CREATE TABLE IF NOT EXISTS plays (
  id INT AUTO_INCREMENT PRIMARY KEY,
  song_id INT NOT NULL,
  ip_hash CHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_song (song_id),
  INDEX idx_song_time (song_id, created_at),
  INDEX idx_song_ip_time (song_id, ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false, 'error'=>'method_not_allowed']); exit;
  }

  $songId = isset($_POST['song_id']) ? (int)$_POST['song_id'] : 0;
  if ($songId <= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'invalid_song_id']); exit;
  }

  $ipHash = hash('sha256', GIO_IP_SALT . client_ip());

  // --- Dedup: höchstens 1 Play pro 10 Minuten pro (song_id, ip_hash) ---
  $stmtChk = $pdo->prepare("
    SELECT 1
    FROM plays
    WHERE song_id = ? AND ip_hash = ?
      AND created_at >= (NOW() - INTERVAL 10 MINUTE)
    LIMIT 1
  ");
  $stmtChk->execute([$songId, $ipHash]);
  $tooSoon = (bool)$stmtChk->fetchColumn();

  if (!$tooSoon) {
    $stmtIns = $pdo->prepare("INSERT INTO plays (song_id, ip_hash) VALUES (?, ?)");
    $stmtIns->execute([$songId, $ipHash]);
  }

  // aktuelle Anzahl zurückgeben
  $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM plays WHERE song_id = ?");
  $stmtCnt->execute([$songId]);
  $count = (int)$stmtCnt->fetchColumn();

  echo json_encode([
    'ok'      => true,
    'count'   => $count,
    'dedup'   => $tooSoon,     // true = Play wurde innerhalb der 10-min-Periode NICHT erneut gezählt
    'window'  => 600           // Sekunden (10 min) – nur als Info
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'server_error']);
}
