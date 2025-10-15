<?php
// /grabiton/admin/songs_cleanup.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';
ini_set('display_errors', 1); error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

/** CSRF-Token */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

/** Mini-Helper: sicherer Redirect */
function go(string $url) {
  header('Location: ' . $url, true, 303);
  exit;
}

/** Pfad-Helper: nur relativ unterhalb des Projekts löschen */
function safe_unlink(?string $path): bool {
  if (!$path) return false;
  // Nur relative Pfade (beginnen mit "/grabiton/" oder ohne Slash)
  if (preg_match('#^https?://#i', $path)) return false;
  // Normalisieren
  $full = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/');
  // Doppelte Sicherheit: nur löschen, wenn die Datei wirklich im DocRoot liegt
  $doc = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR;
  $real = realpath($full);
  if ($real === false) return false;
  if (strpos($real, $doc) !== 0) return false;
  // Nur wenn Datei existiert und beschreibbar
  if (is_file($real) && is_writable($real)) {
    @unlink($real);
    return true;
  }
  return false;
}

/** Aktionen */
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/** POST: wirklich löschen */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'delete')) {
  $postId = (int)($_POST['id'] ?? 0);
  $token  = $_POST['csrf'] ?? '';
  if (!$postId || !$token || !hash_equals($CSRF, $token)) {
    go('/grabiton/admin/songs_cleanup.php?err=csrf');
  }

  try {
    $pdo->beginTransaction();

    // Songdaten holen (für Dateipfad-Anzeige/Löschung)
    $stmt = $pdo->prepare("
      SELECT s.id, s.title, s.audio_path, a.artist_name, r.title AS release_title
      FROM songs s
      JOIN artists a ON a.id = s.artist_id
      LEFT JOIN releases r ON r.id = s.release_id
      WHERE s.id = ?
      LIMIT 1
    ");
    $stmt->execute([$postId]);
    $song = $stmt->fetch();
    if (!$song) {
      $pdo->rollBack();
      go('/grabiton/admin/songs_cleanup.php?err=nf');
    }

    // Abhängigkeiten entfernen (falls keine ON DELETE CASCADE definiert sind)
    // Likes
    try {
      $pdo->prepare("DELETE FROM likes WHERE song_id = ?")->execute([$postId]);
    } catch (Throwable $e) {}
    // Plays events
    try {
      $pdo->prepare("DELETE FROM plays WHERE song_id = ?")->execute([$postId]);
    } catch (Throwable $e) {}
    // Play Counter (Basiszähler)
    try {
      $pdo->prepare("DELETE FROM play_counters WHERE song_id = ?")->execute([$postId]);
    } catch (Throwable $e) {}

    // Song löschen
    $pdo->prepare("DELETE FROM songs WHERE id = ?")->execute([$postId]);

    $pdo->commit();

    // Datei außerhalb der Transaktion löschen (kein DB-Rollback nötig)
    if (!empty($song['audio_path'])) {
      safe_unlink($song['audio_path']);
    }

    go('/grabiton/admin/songs_cleanup.php?ok=1');
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    go('/grabiton/admin/songs_cleanup.php?err=del');
  }
}

/** Ansicht: Bestätigung */
if ($action === 'confirm' && $id > 0) {
  $stmt = $pdo->prepare("
    SELECT s.id, s.title, s.audio_path, COALESCE(s.uploaded_at, s.created_at) AS up_at,
           a.artist_name, r.title AS release_title
    FROM songs s
    JOIN artists a ON a.id = s.artist_id
    LEFT JOIN releases r ON r.id = s.release_id
    WHERE s.id = ?
    LIMIT 1
  ");
  $stmt->execute([$id]);
  $song = $stmt->fetch();
  if (!$song) {
    go('/grabiton/admin/songs_cleanup.php?err=nf');
  }
  ?>
  <!doctype html>
  <html lang="de">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Song löschen – Grab It On</title>
    <style>
      :root { color-scheme: dark; }
      body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#0b1a3a;color:#fff;margin:0}
      header,footer{padding:14px 18px;background:rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.12);backdrop-filter:blur(6px)}
      main{width:min(900px,96vw);margin:24px auto;padding:0 20px}
      .card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:18px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
      .muted{opacity:.8}
      .row{display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap}
      .btn{display:inline-block;background:#2b5dff;color:#fff;border-radius:10px;padding:10px 16px;text-decoration:none;font-weight:600;border:0;cursor:pointer}
      .btn:hover{ filter:brightness(1.05) }
      .btn-ghost{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18)}
      .btn-danger{background:#8f2a2f}
      .grid{display:grid;grid-template-columns: 1fr 1fr; gap:10px}
      dl{margin:0}
      dt{opacity:.8; font-size:13px}
      dd{margin:0 0 8px}
    </style>
  </head>
  <body>
  <header>
    <a class="btn" href="/grabiton/admin/dashboard.php">← Zurück</a>
  </header>
  <main>
    <h1 style="margin:0 0 12px;">Song wirklich löschen?</h1>
    <div class="card">
      <div class="grid">
        <dl>
          <dt>Titel</dt><dd><?= htmlspecialchars($song['title']) ?></dd>
          <dt>Künstler*in</dt><dd><?= htmlspecialchars($song['artist_name']) ?></dd>
          <dt>Release</dt><dd><?= htmlspecialchars($song['release_title'] ?? '—') ?></dd>
        </dl>
        <dl>
          <dt>Upload/Erstellt</dt><dd><?= $song['up_at'] ? date('d.m.Y H:i', strtotime($song['up_at'])) : '—' ?></dd>
          <dt>Audio</dt><dd class="muted"><?= htmlspecialchars($song['audio_path'] ?: '—') ?></dd>
          <dt>Song-ID</dt><dd><?= (int)$song['id'] ?></dd>
        </dl>
      </div>

      <p class="muted" style="margin-top:6px">
        Hinweis: Zugehörige Zähler (Likes/Plays) werden entfernt; die Audiodatei wird – wenn möglich – vom Server gelöscht.
      </p>

      <form method="post" action="/grabiton/admin/songs_cleanup.php?action=delete" class="row">
        <input type="hidden" name="id"   value="<?= (int)$song['id'] ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
        <button class="btn btn-danger" type="submit">Ja, endgültig löschen</button>
        <a class="btn btn-ghost" href="/grabiton/admin/songs_cleanup.php">Abbrechen</a>
      </form>
    </div>
  </main>
  <footer>© <?= date('Y') ?> Grab It On</footer>
  </body>
  </html>
  <?php
  exit;
}

/** Standard-Ansicht: Liste + Löschlinks */
$stmt = $pdo->query("
  SELECT s.id, s.title, s.audio_path, COALESCE(s.uploaded_at, s.created_at) AS up_at,
         a.artist_name, r.title AS release_title
  FROM songs s
  JOIN artists a ON a.id = s.artist_id
  LEFT JOIN releases r ON r.id = s.release_id
  ORDER BY a.artist_name ASC, s.title ASC, s.id ASC
");
$songs = $stmt->fetchAll();
$ok  = isset($_GET['ok']);
$err = $_GET['err'] ?? '';
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Songs bereinigen – Grab It On</title>
<style>
  :root { color-scheme: dark; }
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#0b1a3a;color:#fff;margin:0}
  header,footer{padding:14px 18px;background:rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.12);backdrop-filter:blur(6px)}
  main{width:min(1100px,96vw);margin:24px auto;padding:0 20px}
  .card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:16px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px;border-bottom:1px solid rgba(255,255,255,.12);text-align:left;vertical-align:middle; font-size:14px}
  th{font-weight:600}
  .muted{opacity:.8}
  .btn{display:inline-block;background:#2b5dff;color:#fff;border-radius:10px;padding:8px 12px;text-decoration:none;font-weight:600;border:0;cursor:pointer}
  .btn:hover{ filter:brightness(1.05) }
  .btn-ghost{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18)}
  .btn-danger{background:#8f2a2f}
  .msg{margin:12px 0; padding:10px 12px; border-radius:10px}
  .ok{background:rgba(43,93,255,.2); border:1px solid rgba(43,93,255,.45)}
  .err{background:rgba(255,0,0,.2); border:1px solid rgba(255,0,0,.4)}
</style>
</head>
<body>
<header>
  <a class="btn" href="/grabiton/admin/dashboard.php">← Zurück</a>
</header>

<main>
  <h1 style="margin:0 0 10px;">Songs bereinigen</h1>

  <?php if ($ok): ?>
    <div class="msg ok">Song wurde gelöscht.</div>
  <?php elseif ($err === 'csrf'): ?>
    <div class="msg err">Sicherheitsfehler (CSRF). Bitte erneut versuchen.</div>
  <?php elseif ($err === 'nf'): ?>
    <div class="msg err">Song nicht gefunden.</div>
  <?php elseif ($err === 'del'): ?>
    <div class="msg err">Löschen fehlgeschlagen.</div>
  <?php endif; ?>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th style="width:22%">Künstler*in</th>
          <th style="width:26%">Titel</th>
          <th style="width:18%">Release</th>
          <th style="width:16%">Audio</th>
          <th style="width:10%">Datum</th>
          <th style="width:8%">Aktion</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($songs)): ?>
          <tr><td colspan="6" class="muted">Keine Songs gefunden.</td></tr>
        <?php else: ?>
          <?php foreach ($songs as $s): ?>
            <tr>
              <td><?= htmlspecialchars($s['artist_name']) ?></td>
              <td><?= htmlspecialchars($s['title']) ?></td>
              <td><?= htmlspecialchars($s['release_title'] ?? '—') ?></td>
              <td class="muted" title="<?= htmlspecialchars($s['audio_path'] ?: '') ?>">
                <?php
                  $p = $s['audio_path'] ?: '—';
                  echo htmlspecialchars(strlen($p) > 28 ? substr($p,0,28).'…' : $p);
                ?>
              </td>
              <td><?= $s['up_at'] ? date('d.m.Y', strtotime($s['up_at'])) : '—' ?></td>
              <td>
                <a class="btn btn-danger" href="/grabiton/admin/songs_cleanup.php?action=confirm&id=<?= (int)$s['id'] ?>">Löschen…</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<footer>© <?= date('Y') ?> Grab It On</footer>
</body>
</html>
