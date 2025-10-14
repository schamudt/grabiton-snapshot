<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (!headers_sent()) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
}
require_once __DIR__ . '/../includes/db.php';

/**
 * WICHTIG: SALT individuell setzen!
 * Nur Hash (sha256) der IP wird gespeichert – keine Klartext-IP.
 */
const GIO_IP_SALT = 'CHANGE_ME_USE_A_LONG_RANDOM_SECRET';

function client_ip(): string {
  return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

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

  // Toggle: wenn bereits geliked -> löschen (unlike), sonst einfügen (like)
  $pdo->beginTransaction();

  $stmtChk = $pdo->prepare("SELECT id FROM likes WHERE song_id = ? AND ip_hash = ? LIMIT 1");
  $stmtChk->execute([$songId, $ipHash]);
  $row = $stmtChk->fetch(PDO::FETCH_ASSOC);

  $likedNow = false;
  if ($row) {
    // UNLIKE
    $stmtDel = $pdo->prepare("DELETE FROM likes WHERE id = ?");
    $stmtDel->execute([(int)$row['id']]);
    $likedNow = false;
  } else {
    // LIKE
    $stmtIns = $pdo->prepare("INSERT INTO likes (song_id, ip_hash) VALUES (?, ?)");
    $stmtIns->execute([$songId, $ipHash]);
    $likedNow = true;
  }

  // Count aktualisieren
  $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE song_id = ?");
  $stmtCnt->execute([$songId]);
  $count = (int)$stmtCnt->fetchColumn();

  $pdo->commit();

  echo json_encode(['ok'=>true, 'liked'=>$likedNow, 'count'=>$count]);
} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'server_error']);
}
