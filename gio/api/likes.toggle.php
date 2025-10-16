<?php
require __DIR__ . '/_bootstrap.php';
$song = trim($_POST['song_id'] ?? '');
if ($song === '') json_err('song_id missing');
if (isset($_SESSION['likes'][$song])) { unset($_SESSION['likes'][$song]); $liked=false; }
else { $_SESSION['likes'][$song] = true; $liked=true; }
json_ok(['song_id'=>$song,'liked'=>$liked,'count'=> ($liked?1:0)]); // Demo: count 0/1
