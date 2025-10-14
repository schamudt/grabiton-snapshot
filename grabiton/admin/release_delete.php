<?php
// /grabiton/admin/release_delete_safe.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$releaseId = (int)($_GET['id'] ?? 0);
$artistId  = isset($_GET['artist']) ? (int)$_GET['artist'] : 0;
$from      = $_GET['from'] ?? ''; // 'overview' | 'list' | 'artist' etc.

if ($releaseId <= 0) {
  header('Location: /grabiton/admin/releases_overview.php?err=' . urlencode('Ungültige Release-ID.'));
  exit;
}

/* Release + Artist */
$st = $pdo->prepare("SELECT r.*, a.artist_name FROM releases r JOIN artists a ON a.id = r.artist_id WHERE r.id = ?");
$st->execute([$releaseId]);
$release = $st->fetch();
if (!$release) {
  header('Location: /grabiton/admin/releases_overview.php?err=' . urlencode('Release nicht gefunden.'));
  exit;
}
if ($artistId <= 0) $artistId = (int)$release['artist_id'];

/* Tracks zählen */
$c = $pdo->prepare("SELECT COUNT(*) FROM songs WHERE release_id = ?");
$c->execute([$releaseId]);
$trackCount = (int)$c->fetchColumn();

/* Wohin zurück? */
$backOverview = '/grabiton/admin/releases_overview.php';
$backArtistReleases = '/grabiton/admin/releases.php?'.http_build_query(['artist'=>$artistId,'from'=>'list']);
$back = ($from === 'list' || $from === 'artist') ? $backArtistReleases : $backOverview;

/* Falls Songs vorhanden: Keine Löschung – nur Hinweis */
if ($trackCount > 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
  ?>
  <!doctype html>
  <html lang="de">
  <head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Release löschen – Hinweis</title>
  <style>
    :root { color-scheme: dark; }
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#0b1a3a;color:#fff;margin:0}
    header,footer{padding:14px 18px;background:rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.12);backdrop-filter:blur(6px)}
    main{width:min(800px,96vw);margin:22px auto;padding:0 20px}
    .card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:18px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
    .btn{display:inline-block;background:#2b5dff;color:#fff;border-radius:10px;padding:10px 16px;text-decoration:none;font-weight:600;border:0;cursor:pointer}
    .muted{opacity:.85}
  </style>
  </head>
  <body>
  <header>
    <a class="btn" href="<?= htmlspecialchars($back) ?>">← Zurück</a>
  </header>
  <main>
    <div class="card">
      <h1 style="margin:0 0 8px 0;">Löschen nicht möglich</h1>
      <p class="muted" style="margin:0 0 10px 0;">
        Künstler: <strong><?= htmlspecialchars($release['artist_name']) ?></strong><br>
        Release: <strong><?= htmlspecialchars($release['title']) ?></strong>
      </p>
      <p>Dieses Release hat noch Songs und kann hier nicht gelöscht werden.</p>
      <p class="muted">Entferne zunächst alle Songs aus diesem Release.</p>
      <p style="margin-top:12px;"><a class="btn" href="<?= htmlspecialchars($back) ?>">OK</a></p>
    </div>
  </main>
  <footer>© <?= date('Y') ?> Grab It On</footer>
  </body>
  </html>
  <?php
  exit;
}

/* 0 Songs -> Bestätigungsseite + tatsächliches Löschen auf POST 'yes' */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $confirm = $_POST['confirm'] ?? '';
  if ($confirm === 'no') {
    header('Location: '.$back); exit;
  }
  if ($confirm === 'yes') {
    try {
      $pdo->beginTransaction();
      // Cover löschen
      if (!empty($release['cover_path'])) @unlink($_SERVER['DOCUMENT_ROOT'].$release['cover_path']);
      // Release löschen
      $pdo->prepare("DELETE FROM releases WHERE id = ?")->execute([$releaseId]);
      $pdo->commit();
      header('Location: '.$back.'&msg='.urlencode('Release „'.$release['title'].'“ wurde gelöscht.'));
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      header('Location: '.$back.'&err='.urlencode('Löschen fehlgeschlagen: '.$e->getMessage()));
      exit;
    }
  }
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Release löschen – Bestätigung</title>
<style>
  :root { color-scheme: dark; }
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#0b1a3a;color:#fff;margin:0}
  header,footer{padding:14px 18px;background:rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.12);backdrop-filter:blur(6px)}
  main{width:min(800px,96vw);margin:22px auto;padding:0 20px}
  .card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:18px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
  .btn{display:inline-block;background:#2b5dff;color:#fff;border-radius:10px;padding:10px 16px;text-decoration:none;font-weight:600;border:0;cursor:pointer}
  .btn-danger{background:#8f2a2f; box-shadow:0 6px 18px rgba(143,42,47,.28); color:#fff}
  .btn-danger:hover{background:#7a2429}
  .btn-ghost{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18)}
  .btn-ghost:hover{background:rgba(255,255,255,.12)}
  .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
  .muted{opacity:.85}
</style>
</head>
<body>
<header>
  <a class="btn" href="<?= htmlspecialchars($back) ?>">← Zurück</a>
</header>
<main>
  <div class="card">
    <h1 style="margin:0 0 8px 0;">Release löschen</h1>
    <p class="muted" style="margin:0 0 10px 0;">
      Künstler: <strong><?= htmlspecialchars($release['artist_name']) ?></strong><br>
      Release: <strong><?= htmlspecialchars($release['title']) ?></strong>
    </p>
    <p>Für dieses Release sind keine Songs vorhanden.<br>Möchtest du es endgültig löschen?</p>
    <form method="post" class="actions">
      <button class="btn btn-danger" type="submit" name="confirm" value="yes">Ja, endgültig löschen</button>
      <button class="btn btn-ghost" type="submit" name="confirm" value="no">Nein, abbrechen</button>
    </form>
  </div>
</main>
<footer>© <?= date('Y') ?> Grab It On</footer>
</body>
</html>
