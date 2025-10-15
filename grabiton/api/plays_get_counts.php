<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (!headers_sent()) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
}
require_once __DIR__ . '/../includes/db.php';

/*
  Request: POST JSON body: [song_id, song_id, ...]
  Response: { ok:true, totals: { <id>: <total>, ... } }
*/
try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false, 'error'=>'method_not_allowed']); exit;
  }
  $raw = file_get_contents('php://input');
  $ids = json_decode($raw, true);
  if (!is_array($ids)) $ids = [];

  $ids = array_values(array_unique(array_map('intval', $ids)));
  $ids = array_filter($ids, fn($x) => $x > 0);

  if (!$ids) { echo json_encode(['ok'=>true, 'totals'=>[]]); exit; }

  // Plays (Log)
  $in = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $pdo->prepare("SELECT song_id, COUNT(*) AS cnt FROM plays WHERE song_id IN ($in) GROUP BY song_id");
  $stmt->execute($ids);
  $log = [];
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $log[(int)$r['song_id']] = (int)$r['cnt']; }

  // Bases
  $stmt2 = $pdo->prepare("SELECT song_id, base_count FROM play_counters WHERE song_id IN ($in)");
  $stmt2->execute($ids);
  $base = [];
  while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) { $base[(int)$r['song_id']] = (int)$r['base_count']; }

  $out = [];
  foreach ($ids as $id) {
    $out[$id] = (int)($log[$id] ?? 0) + (int)($base[$id] ?? 0);
  }

  echo json_encode(['ok'=>true, 'totals'=>$out]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'server_error']);
}
