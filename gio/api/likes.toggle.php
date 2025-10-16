<?php
// /gio/api/likes.toggle.php
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'err'=>'method']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
$songId = isset($input['songId']) ? trim($input['songId']) : '';
if ($songId === '') { http_response_code(400); echo json_encode(['ok'=>false,'err'=>'songId']); exit; }

// Datapfad
$base = dirname(__DIR__) . '/../data';
@is_dir($base) || @mkdir($base, 0775, true);
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

function read_store($file){
  $data = [];
  $h = @fopen($file, 'c+');
  if ($h) {
    flock($h, LOCK_SH);
    $raw = stream_get_contents($h);
    $data = $raw ? json_decode($raw, true) : [];
    flock($h, LOCK_UN);
    fclose($h);
  }
  return is_array($data) ? $data : [];
}
function write_store($file, $data){
  $h = @fopen($file, 'c+');
  if (!$h) return false;
  flock($h, LOCK_EX);
  ftruncate($h, 0);
  rewind($h);
  fwrite($h, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
  fflush($h);
  flock($h, LOCK_UN);
  fclose($h);
  return true;
}

$ip = client_ip();
$store = read_store($file);
if (!isset($store[$songId])) $store[$songId] = ['count'=>0, 'ips'=>[]];

$entry =& $store[$songId];
$liked = false;

if (isset($entry['ips'][$ip]) && $entry['ips'][$ip] === true) {
  // UNLIKE
  unset($entry['ips'][$ip]);
  $entry['count'] = max(0, (int)$entry['count'] - 1);
  $liked = false;
} else {
  // LIKE
  $entry['ips'][$ip] = true;
  $entry['count'] = (int)$entry['count'] + 1;
  $liked = true;
}

write_store($file, $store);

echo json_encode([
  'ok' => true,
  'songId' => $songId,
  'liked' => $liked,
  'count' => (int)$entry['count']
]);
