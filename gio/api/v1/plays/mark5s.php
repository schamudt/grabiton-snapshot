<?php
declare(strict_types=1);
require_once __DIR__ . '/../../_common.php';

require_post();
ensure_store();

$body = json_body();
$play_id = $body['play_id'] ?? '';

if (!$play_id) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>['code'=>'bad_request','msg'=>'play_id required']], JSON_UNESCAPED_SLASHES);
  exit;
}

$play = load_play($play_id);
if (!$play) {
  http_response_code(404);
  echo json_encode(['ok'=>false,'error'=>['code'=>'not_found','msg'=>'play_id unknown']], JSON_UNESCAPED_SLASHES);
  exit;
}

if (!($play['marks']['mark5'] ?? false)) {
  $play['marks']['mark5'] = true;
  $play['mark5_at'] = gmdate('c');
  save_play($play_id, $play);
}

echo json_encode(['ok'=>true,'data'=>['updated'=>true]], JSON_UNESCAPED_SLASHES);
