<?php
declare(strict_types=1);
require_once __DIR__ . '/../../_common.php';

require_post();

$body = json_body();
$play_id = $body['play_id'] ?? '';
$at = isset($body['at']) ? (int)$body['at'] : null; // 0,5,31

if (!$play_id || !in_array($at, [0,5,31], true)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>['code'=>'bad_request','msg'=>'play_id and at ∈ {0,5,31} required']], JSON_UNESCAPED_SLASHES);
  exit;
}

$play = load_play($play_id);
if (!$play) {
  http_response_code(404);
  echo json_encode(['ok'=>false,'error'=>['code'=>'not_found','msg'=>'play_id unknown']], JSON_UNESCAPED_SLASHES);
  exit;
}

$map = [0=>'mark0',5=>'mark5',31=>'mark31'];
$key = $map[$at];

if (!($play['marks'][$key] ?? false)) {
  $play['marks'][$key] = true;
  $play[$key.'_at'] = gmdate('c');
  save_play($play_id, $play);
}

echo json_encode(['ok'=>true,'data'=>['updated'=>true,'key'=>$key]], JSON_UNESCAPED_SLASHES);
