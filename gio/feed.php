<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/../../../config/db.php'; // /gio/config/db.php

$limit = 20; // hard cap

try {
  // Trending
  $sqlTrending = "
    SELECT
      s.`id`,
      s.`title`,
      s.`duration_sec`,
      s.`cover_url`,
      s.`release_id`,
      s.`artist_id`,
      a.`name` AS `artist_name`
    FROM `songs` s
    JOIN `artists` a ON a.`id` = s.`artist_id`
    WHERE s.`is_trending` = 1
    ORDER BY s.`uploaded_at` DESC, s.`id` DESC
    LIMIT {$limit}";
  $st = $pdo->query($sqlTrending);
  $trending = $st->fetchAll() ?: [];

  // Newest
  $sqlNewest = "
    SELECT
      s.`id`,
      s.`title`,
      s.`duration_sec`,
      s.`cover_url`,
      s.`release_id`,
      s.`artist_id`,
      a.`name` AS `artist_name`
    FROM `songs` s
    JOIN `artists` a ON a.`id` = s.`artist_id`
    WHERE s.`is_new` = 1
    ORDER BY s.`uploaded_at` DESC, s.`id` DESC
    LIMIT {$limit}";
  $st = $pdo->query($sqlNewest);
  $newest = $st->fetchAll() ?: [];

  echo json_encode([
    'trending' => $trending,
    'newest'   => $newest,
    'ts'       => gmdate('c')
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'db_error']);
}
