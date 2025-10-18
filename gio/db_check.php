<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
echo "DB CHECK\n";
try {
    $pdo = require __DIR__ . '/../config/db.php';
    echo "OK: connected\n";
    $stmt = $pdo->query("SHOW TABLES");
    print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
