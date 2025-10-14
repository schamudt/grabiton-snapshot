<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
session_start();

function back($ok, $msg=''){
  $dest = '/grabiton/admin/songs_cleanup.php';
  if ($msg !== '') $dest .= '?msg=' . urlencode($msg);
  header("Location: $dest"); exit;
}

if (empty($_SESSION['gio_admin'])) back(false, 'not_authorized');

if (($_POST['csrf'] ?? '') !== ($_SESSION['gio_csrf'] ?? '')) back(false, 'csrf_fail');

$songId = isset($_POST['song_id']) ? (int)$_POST['song_id'] : 0;
if ($songId <= 0) back(false, 'invalid_id');

try {
  // Song laden + prüfen
  $stmt = $pdo->prepare("
    SELECT s.id, s.audio_path, s.release_id
    FROM songs s
    WHERE s.id = ?
    LIMIT 1
  ");
  $stmt->execute([$songId]);
  $song = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$song) back(false, 'not_found');

  // Löschen nur sicher erlauben, wenn KEIN Release verknüpft ist
  if (!empty($song['release_id'])) {
    back(false, 'has_release'); // Schutz
  }

  $pdo->beginTransaction();

  // Zähler-Tabellen bereinigen (falls vorhanden)
  try {
    $pdo->prepare("DELETE FROM likes WHERE song_id = ?")->execute([$songId]);
  } catch (Throwable $e) {} // ignorieren, wenn Table nicht existiert

  try {
    $pdo->prepare("DELETE FROM plays WHERE song_id = ?")->execute([$songId]);
  } catch (Throwable $e) {}

  // Song löschen
  $pdo->prepare("DELETE FROM songs WHERE id = ?")->execute([$songId]);

  $pdo->commit();

  // Datei löschen (best effort)
  $audioPath = (string)($song['audio_path'] ?? '');
  if ($audioPath !== '') {
    // Pfad relativ zum /grabiton/ Verzeichnis behandeln
    $base = realpath(__DIR__ . '/..');
    $full = realpath($base . '/' . ltrim($audioPath, '/'));
    if ($full && str_starts_with($full, $base) && is_file($full)) {
      @unlink($full);
    }
  }

  back(true, 'deleted');
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  back(false, 'server_error');
}
