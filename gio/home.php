<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok'=>true,
  'data'=>[
    'new'=>[
      ['id'=>1,'title'=>'Demo Song','artist_id'=>1,'cover_url'=>'/gio/assets/img/placeholder.jpg','duration_sec'=>180],
    ],
    'trending'=>[
      ['id'=>2,'title'=>'Hot Track','artist_id'=>2,'cover_url'=>'/gio/assets/img/placeholder.jpg','duration_sec'=>200],
    ],
  ]
], JSON_UNESCAPED_SLASHES);
