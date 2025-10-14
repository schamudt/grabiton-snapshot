<?php
// admin/_install_create_artists.php
require_once __DIR__ . '/../includes/db.php';

$sql = "
CREATE TABLE IF NOT EXISTS artists (
  id INT AUTO_INCREMENT PRIMARY KEY,
  artist_name VARCHAR(120) NOT NULL,
  genre VARCHAR(120) DEFAULT NULL,
  banner_path VARCHAR(255) DEFAULT NULL,  -- kommt mit Uploads
  avatar_path VARCHAR(255) DEFAULT NULL,  -- kommt mit Uploads
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$pdo->exec($sql);
echo "Tabelle 'artists' angelegt (oder existierte bereits).";
