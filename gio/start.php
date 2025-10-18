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

// Song existiert?
$chk = $pdo->prepare('SELECT 1 FROM songs WHERE id = :id');
$chk->execute([':id' => $songId]);
if (!$chk->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['error' => 'song_not_found']);
    exit;
}

$ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
$uaHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

try {
    $stmt = $pdo->prepare('
        INSERT INTO plays (song_id, ip_hash, ua_hash, started_at)
        VALUES (:song_id, :ip_hash, :ua_hash, NOW())
    ');
    $stmt->execute([
        ':song_id' => $songId,
        ':ip_hash' => $ipHash,
        ':ua_hash' => $uaHash,
    ]);

    echo json_encode([
        'play_id' => $pdo->lastInsertId(),
        'status'  => 'ok'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
