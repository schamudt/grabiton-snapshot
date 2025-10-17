<?php
header('Content-Type: application/json; charset=utf-8');
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if (mb_strlen($q) < 2) {
  echo json_encode(['ok'=>true,'data'=>['songs'=>[],'artists'=>[],'releases'=>[]]], JSON_UNESCAPED_SLASHES);
  exit;
}
echo json_encode([
  'ok'=>true,
  'data'=>[
    'songs'=>[
      ['id'=>101,'title'=>"{$q} Song",'artist_id'=>11,'cover_url'=>'/gio/assets/img/placeholder.jpg','duration_sec'=>197],
    ],
    'artists'=>[
      ['id'=>11,'name'=>"{$q} Artist",'avatar_url'=>'/gio/assets/img/placeholder.jpg','genre'=>'indie'],
    ],
    'releases'=>[
      ['id'=>201,'title'=>"{$q} Album",'type'=>'album','cover_url'=>'/gio/assets/img/placeholder.jpg','released_at'=>'2024-01-01','artist_id'=>11],
    ],
  ]
], JSON_UNESCAPED_SLASHES);
