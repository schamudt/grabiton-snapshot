<?php
require __DIR__ . '/_bootstrap.php';
$song = trim($_POST['song_id'] ?? '');
if ($song === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'song_id missing']); exit; }
$_SESSION['starts'][$song] = ($_SESSION['starts'][$song] ?? 0) + 1;
echo json_encode(['ok'=>true,'data'=>['song_id'=>$song,'starts'=>$_SESSION['starts'][$song]]], JSON_UNESCAPED_UNICODE);
