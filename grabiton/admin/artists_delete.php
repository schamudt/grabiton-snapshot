<?php
// /grabiton/admin/artist_delete.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$artistId = (int)($_GET['id'] ?? 0);
$from     = $_GET['from'] ?? '';
$from     = in_array($from, ['artist','list'], true) ? $from : '';

if ($artistId <= 0) {
  header('Location: /grabiton/admin/artists.php?err=' . urlencode('Ungültige Künstler-ID.'));
  exit;
}

/* Künstler laden */
$st = $pdo->prepare("SELECT id, artist_name, avatar_path, banner_path FROM artists WHERE id = ?");
$st->execute([$artistId]);
$artist = $st->fetch();
if (!$artist) {
  header('Location: /grabiton/admin/artists.php?err=' . urlencode('Künstler nicht gefunden.'));
  exit;
}

/* Releases zählen */
$countReleases = (int)$pdo->query("SELECT COUNT(*) FROM releases WHERE artist_id = ".(int)$artistId)->fetchColumn();

/* Wenn Releases vorhanden: zurück zur Liste mit Hinweis "Release vorhanden" */
if ($countReleases > 0) {
  header('Location: /grabiton/admin/artists.php?err=' . urlencode('Release vorhanden'));
  exit;
}

/* AB HIER: 0 Releases -> Bestätigungsseite (Ja/Nein) statt Direktlöschung */

$backUrl = '/grabiton/admin/artists.php';

/* POST: Entscheidung */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $confirm = $_POST['confirm'] ?? '';
  if ($confirm === 'no') {
    header('Location: ' . $backUrl); exit;
  }
  if ($confirm === 'yes') {
    // Löschung durchführen
    try {
      $pdo->beginTransaction();

      // Avatar/Banner löschen (falls vorhanden)
      if (!empty($artist['avatar_path'])) @unlink($_SERVER['DOCUMENT_ROOT'].$artist['avatar_path']);
      if (!empty($artist['banner_path'])) @unlink($_SERVER['DOCUMENT_ROOT'].$artist['banner_path']);

      // Künstler löschen
      $pdo->prepare("DELETE FROM artists WHERE id = ?")->execute([$artistId]);

      $pdo->commit();
      header('Location: '.$backUrl.'?msg='.urlencode('Künstler „'.$artist['artist_name'].'“ wurde gelöscht.'));
      exit;

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      header('Location: '.$backUrl.'?err='.urlencode('Löschen fehlgeschlagen: '.$e->getMessage()));
      exit;
    }
  }
}

/* GET: Bestätigungsseite anzeigen (0 Releases) */
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Künstler löschen – Grab It On</title>
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
  <a class="btn" href="<?= htmlspecialchars($backUrl) ?>">← Zurück</a>
</header>
<main>
  <div class="card">
    <h1 style="margin:0 0 8px 0;">Künstler löschen</h1>
    <p class="muted" style="margin:0 0 10px 0;">
      Künstler: <strong><?= htmlspecialchars($artist['artist_name']) ?></strong>
    </p>

    <p style="margin:10px 0 12px 0;">
      Für diesen Künstler sind <strong>keine Releases</strong> vorhanden.
      <br>Möchtest du den Künstler endgültig löschen?
    </p>

    <form method="post" class="actions">
      <button class="btn btn-danger" type="submit" name="confirm" value="yes">Ja, endgültig löschen</button>
      <button class="btn btn-ghost" type="submit" name="confirm" value="no">Nein, abbrechen</button>
    </form>
  </div>
</main>
<footer>© <?= date('Y') ?> Grab It On</footer>
</body>
</html>
