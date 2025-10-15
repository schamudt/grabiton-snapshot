<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (!headers_sent()) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
}
require_once __DIR__ . '/../includes/db.php';

const GIO_IP_SALT = 'CHANGE_ME_USE_A_LONG_RANDOM_SECRET'; // gleich wie in like.php

function client_ip(): string {
  return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

try {
  $raw = file_get_contents('php://input');
  $ids = json_decode($raw, true);
  if (!is_array($ids) || empty($ids)) { echo json_encode(['ok'=>true, 'counts'=>[], 'liked'=>[]]); exit; }

  $songIds = array_values(array_unique(array_map('intval', $ids)));
  $songIds = array_filter($songIds, fn($v) => $v > 0);
  if (!$songIds) { echo json_encode(['ok'=>true, 'counts'=>[], 'liked'=>[]]); exit; }

  // Counts
  $in  = implode(',', array_fill(0, count($songIds), '?'));
  $sql = "SELECT song_id, COUNT(*) AS c FROM likes WHERE song_id IN ($in) GROUP BY song_id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($songIds);

  $counts = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $counts[(int)$row['song_id']] = (int)$row['c'];
  }

  // liked_by_me
  $ipHash = hash('sha256', GIO_IP_SALT . client_ip());
  $sql2   = "SELECT song_id FROM likes WHERE ip_hash = ? AND song_id IN ($in)";
  $stmt2  = $pdo->prepare($sql2);
  $stmt2->execute(array_merge([$ipHash], $songIds));
  $likedByMe = [];
  while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    $likedByMe[(int)$r['song_id']] = true;
  }

  echo json_encode(['ok'=>true, 'counts'=>$counts, 'liked'=>$likedByMe]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'server_error']);
}
