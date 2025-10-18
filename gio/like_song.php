<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/../../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['song_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'missing song_id']);
    exit;
}

$songId = (int)$input['song_id'];

// Song prüfen
$chk = $pdo->prepare('SELECT 1 FROM songs WHERE id = :id');
$chk->execute([':id' => $songId]);
if (!$chk->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['error' => 'song_not_found']);
    exit;
}

// IP-Hash
$ipRaw = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip = explode(',', $ipRaw)[0]; // erster Eintrag
$ipHash = hash('sha256', trim($ip));

try {
    $pdo->beginTransaction();

    // Gibt es bereits ein Like?
    $sel = $pdo->prepare('SELECT id FROM likes_song WHERE song_id = :sid AND ip_hash = :ih LIMIT 1');
    $sel->execute([':sid' => $songId, ':ih' => $ipHash]);
    $row = $sel->fetch();

    if ($row) {
        // Toggle → entfernen
        $del = $pdo->prepare('DELETE FROM likes_song WHERE id = :id');
        $del->execute([':id' => $row['id']]);
        $liked = false;
    } else {
        // Toggle → anlegen
        $ins = $pdo->prepare('INSERT IGNORE INTO likes_song (song_id, ip_hash, created_at) VALUES (:sid, :ih, NOW())');
        $ins->execute([':sid' => $songId, ':ih' => $ipHash]);
        $liked = true;
    }

    // Zähler aktual
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM likes_song WHERE song_id = :sid');
    $cnt->execute([':sid' => $songId]);
    $count = (int)$cnt->fetchColumn();

    $pdo->commit();

    echo json_encode([
        'status' => 'ok',
        'liked'  => $liked,
        'count'  => $count
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
