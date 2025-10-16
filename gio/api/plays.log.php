<?php
require __DIR__ . '/_bootstrap.php';
$song = trim($_POST['song_id'] ?? '');
if ($song === '') json_err('song_id missing');
$_SESSION['plays'][$song] = ($_SESSION['plays'][$song] ?? 0) + 1;
json_ok(['song_id'=>$song,'plays'=>$_SESSION['plays'][$song]]);
