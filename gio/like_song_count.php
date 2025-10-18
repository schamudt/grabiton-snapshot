<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/../../../config/db.php';

// ids=1,2,3
$idsParam = $_GET['ids'] ?? '';
$ids = array_values(array_unique(array_filter(array_map(static function ($v) {
    $n = (int)trim($v);
    return $n > 0 ? $n : null;
}, explode(',', $idsParam)))));

$result = [];

if (count($ids) === 0) {
    echo json_encode($result);
    exit;
}

// Platzhalter bauen
$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    $stmt = $pdo->prepare("SELECT song_id, COUNT(*) AS c FROM likes_song WHERE song_id IN ($placeholders) GROUP BY song_id");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    // Erst alle angefragten IDs mit 0 vorbelegen
    foreach ($ids as $id) {
        $result[(string)$id] = 0;
    }
    // Vorhandene Counts überschreiben
    foreach ($rows as $r) {
        $result[(string)$r['song_id']] = (int)$r['c'];
    }

    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
