<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/../../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['play_id']) || !isset($input['at'])) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_parameters']);
    exit;
}

$playId = (int)$input['play_id'];
$at     = (int)$input['at'];

$col = match ($at) {
    0  => 'mark0_at',
    5  => 'mark5_at',
    31 => 'mark31_at',
    default => null
};
if ($col === null) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_marker']);
    exit;
}

// Play existiert?
$chk = $pdo->prepare('SELECT 1 FROM plays WHERE id = :id');
$chk->execute([':id' => $playId]);
if (!$chk->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['error' => 'play_not_found']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE plays SET {$col} = NOW() WHERE id = :id");
    $stmt->execute([':id' => $playId]);
    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
