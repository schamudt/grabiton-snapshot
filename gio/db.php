<?php
declare(strict_types=1);

/**
 * /gio/config/db.php
 * Gibt eine verbundene PDO-Instanz zurück oder beendet mit 500.
 * IONOS: nutze die MySQL-Zugangsdaten aus dem Control Panel.
 */
$DB_HOST = 'db5018828100.hosting-data.io';
$DB_NAME = 'dbs14868420';
$DB_USER = 'dbu1170075';
$DB_PASS = '9bx6jmjzQ6xCABmeX7mxi_?121';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "DB_CONNECT_ERROR";
  exit;
}

return $pdo;

