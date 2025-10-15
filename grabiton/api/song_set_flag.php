<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (!headers_sent()) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
}
require_once __DIR__ . '/../../includes/db.php';

/*
  Setzt ein einzelnes Flag in songs:
  Erlaubte Felder: is_hidden, is_featured, is_new
  Input (POST): song_id, field, value (0|1)
*/
try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'method_not_allowed']); exit;
  }
  $songId = isset($_POST['song_id']) ? (int)$_POST['song_id'] : 0;
  $field  = isset($_POST['field'])   ? (string)$_POST['field']     : '';
  $value  = isset($_POST['value'])   ? (int)$_POST['value']        : 0;

  if ($songId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_song']); exit; }

  $allowed = ['is_hidden','is_featured','is_new'];
  if (!in_array($field, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'bad_field']); exit;
  }
  $value = $value ? 1 : 0;

  $sql = "UPDATE songs SET `$field` = :v WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':v'=>$value, ':id'=>$songId]);

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error']);
}
