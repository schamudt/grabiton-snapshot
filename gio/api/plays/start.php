<?php
declare(strict_types=1);
require_once __DIR__ . '/../../_common.php';

require_post();
ensure_store();

$body = json_body();
$song_id = $body['song_id'] ?? null;

if (!$song_id || !is_numeric($song_id)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>['code'=>'bad_request','msg'=>'song_id required']], JSON_UNESCAPED_SLASHES);
  exit;
}

$play_id = bin2hex(random_bytes(8)); // 16-stellig
$now = gmdate('c');

$data = [
  'play_id'   => $play_id,
  'song_id'   => (int)$song_id,
  'ip_hash'   => ip_hash(),
  'ua'        => $_SERVER['HTTP_USER_AGENT'] ?? '',
  'created_at'=> $now,
  'marks'     => ['mark0'=>true, 'mark5'=>false, 'mark31'=>false],
];

if (!save_play($play_id, $data)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>['code'=>'store_write_failed','msg'=>'cannot persist play']], JSON_UNESCAPED_SLASHES);
  exit;
}

echo json_encode(['ok'=>true,'data'=>['play_id'=>$play_id]], JSON_UNESCAPED_SLASHES);
