<?php
require __DIR__ . '/_bootstrap.php';
$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) $ids = [];
$resp = [];
foreach ($ids as $id) {
  $id = (string)$id;
  $resp[$id] = [
    'liked' => isset($_SESSION['likes'][$id]),
    'count' => isset($_SESSION['likes'][$id]) ? 1 : 0 // Demo
  ];
}
json_ok(['items'=>$resp]);
