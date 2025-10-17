<?php
declare(strict_types=1);
require_once __DIR__ . '/../../_common.php';
header('Content-Type: application/json; charset=utf-8');

/*
POST /gio/api/v1/likes/like_song.php
Body: { "song_id": 123 }
Antwort: { ok:true, data:{ liked:true|false, count:int } }
Funktion: IP-basiertes Toggle. Ein Klick liked, nochmaliges Klick entliked.
*/

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>['code'=>'method_not_allowed','msg'=>'POST required']]);
  exit;
}

$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true) ?: [];
$song_id = isset($body['song_id']) ? (int)$body['song_id'] : 0;

if ($song_id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>['code'=>'bad_request','msg'=>'song_id required']]);
  exit;
}

function like_store_dir(): string {
  $d = rtrim(sys_get_temp_dir(),'/').'/gio_likes';
  if (!is_dir($d)) @mkdir($d,0777,true);
  if (is_dir($d) && is_writable($d)) return $d;

  $fb = __DIR__ . '/../../_likes';
  if (!is_dir($fb)) @mkdir($fb,0777,true);
  if (!is_dir($fb) || !is_writable($fb)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>['code'=>'storage_unavailable','msg'=>'no writable like store']]);
    exit;
  }
  return $fb;
}

$sdir = like_store_dir() . '/song_' . $song_id;
if (!is_dir($sdir)) @mkdir($sdir,0777,true);

$who = ip_hash(); // aus _common.php
$file = $sdir . '/' . $who . '.like';

// Toggle
if (is_file($file)) {
  @unlink($file);
  $liked = false;
} else {
  file_put_contents($file, '1');
  $liked = true;
}

// Zählen
$files = @scandir($sdir) ?: [];
$count = 0;
foreach ($files as $f) {
  if ($f !== '.' && $f !== '..' && str_ends_with($f,'.like')) $count++;
}

echo json_encode(['ok'=>true,'data'=>['liked'=>$liked,'count'=>$count]], JSON_UNESCAPED_SLASHES);
