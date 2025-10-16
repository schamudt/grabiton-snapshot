<?php
// /gio/api/likes.get.php
header('Content-Type: application/json; charset=utf-8');
$songId = isset($_GET['songId']) ? trim($_GET['songId']) : '';
if ($songId === '') { http_response_code(400); echo json_encode(['ok'=>false,'err'=>'songId']); exit; }

$base = dirname(__DIR__) . '/../data';
$file = $base . '/likes.json';

function client_ip() {
  $candidates = [
    'HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','HTTP_CLIENT_IP','REMOTE_ADDR'
  ];
  foreach ($candidates as $h) {
    if (!empty($_SERVER[$h])) {
      $v = $_SERVER[$h];
      if ($h === 'HTTP_X_FORWARDED_FOR') { $v = explode(',', $v)[0]; }
      return trim($v);
    }
  }
  return '0.0.0.0';
}

$count = 0; $liked = false;
if (is_file($file)) {
  $h = fopen($file, 'r');
  if ($h){
    flock($h, LOCK_SH);
    $raw = stream_get_contents($h);
    flock($h, LOCK_UN);
    fclose($h);
    $data = $raw ? json_decode($raw, true) : [];
    if (isset($data[$songId])) {
      $count = (int)($data[$songId]['count'] ?? 0);
      $ip = client_ip();
      $liked = !empty($data[$songId]['ips'][$ip]);
    }
  }
}

echo json_encode([
  'ok'=>true,
  'songId'=>$songId,
  'liked'=>$liked,
  'count'=>$count
]);
