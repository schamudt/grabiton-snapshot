<?php
declare(strict_types=1);

/**
 * /gio/migrate.php
 * Führt *.sql in /migrations aus, idempotent. Zeigt Klartext-Status.
 * ?p=ping → nur DB-Test.
 */

header('Content-Type: text/plain; charset=utf-8');
$baseDir = __DIR__;
$migDir  = $baseDir . '/migrations';

try {
  /** @var PDO $pdo */
  $pdo = require __DIR__ . '/config/db.php';
} catch (Throwable $e) {
  http_response_code(500);
  echo "ERROR: cannot include DB.\n";
  exit;
}

if (isset($_GET['p']) && $_GET['p'] === 'ping') {
  echo "OK: DB connected\n";
  exit;
}

if (!is_dir($migDir)) {
  http_response_code(500);
  echo "ERROR: migrations/ not found\n";
  exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(100) PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$appliedStmt = $pdo->query("SELECT version FROM schema_migrations");
$applied = array_column($appliedStmt->fetchAll(), 'version');
sort($applied);

$files = glob($migDir . '/*.sql');
natsort($files);

if (empty($files)) {
  echo "NO MIGRATIONS FOUND\n";
  exit;
}

foreach ($files as $file) {
  $version = basename($file, '.sql');
  if (in_array($version, $applied, true)) {
    echo "[SKIP] {$version}\n";
    continue;
  }

  $sql = file_get_contents($file);
  if ($sql === false) {
    echo "[FAIL] {$version} cannot read\n";
    http_response_code(500);
    exit;
  }

  try {
    $pdo->beginTransaction();

    // Simple Split am Semikolon, ignoriert ; in Strings (hier unkritisch).
    $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');
    foreach ($statements as $stmt) {
      $pdo->exec($stmt);
    }

    // Sicherstellen, dass version eingetragen ist (falls nicht im Skript)
    $pdo->prepare("INSERT IGNORE INTO schema_migrations (version) VALUES (?)")->execute([$version]);

    $pdo->commit();
    echo "[APPLIED] {$version}\n";
  } catch (Throwable $e) {
    $pdo->rollBack();
    echo "[ERROR] {$version}: " . $e->getMessage() . "\n";
    http_response_code(500);
    exit;
  }
}

echo "DONE\n";
