<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
session_start();
if (!isset($_SESSION['likes'])) $_SESSION['likes'] = [];       // song_id => true
if (!isset($_SESSION['plays'])) $_SESSION['plays'] = [];       // song_id => count
if (!isset($_SESSION['starts'])) $_SESSION['starts'] = [];     // song_id => count
function json_ok($data = []) { echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE); exit; }
function json_err($msg, $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
