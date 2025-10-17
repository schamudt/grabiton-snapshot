<?php
declare(strict_types=1);
require_once __DIR__ . '/../../_common.php';
header('Content-Type: application/json; charset=utf-8');

/*
GET /gio/api/v1/likes/song_count.php?ids=1,2,3
Antwort: { ok:true, data:{ "1":3,"2":0,"3":5 } }
*/

$ids = isset($_GET['ids']) ? trim($_GET['ids']) : '';
if ($ids === '') {
  echo json_encode(['ok'=>true,'data'=>[]]); exit;
}
$list = array_filter(array_map('intval', explode(',', $ids)));
if (!$list) {
  echo json_encode(['ok'=>true,'data'=>[]]); exit;
}

$base = rtrim(sys_get_temp_dir(),'/').'/gio_likes';
$out = [];
foreach ($list as $id){
  $dir = "$base/song_$id";
  $count = 0;
  if (is_dir($dir)){
    foreach (scandir($dir) as $f){
      if ($f!=='.' && $f!=='..' && str_ends_with($f,'.like')) $count++;
    }
  }
  $out[(string)$id] = $count;
}
echo json_encode(['ok'=>true,'data'=>$out], JSON_UNESCAPED_SLASHES);
