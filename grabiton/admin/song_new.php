<?php
// /grabiton/admin/song_new.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';
ini_set('display_errors', 1); error_reporting(E_ALL);

$artistId  = (int)($_GET['artist']  ?? 0);
$releaseId = (int)($_GET['release'] ?? 0);
$from      = $_GET['from'] ?? '';
$from      = in_array($from, ['artist','list','overview'], true) ? $from : 'list';

$errors = []; $messages = [];

/* ===== Release + Artist prüfen ===== */
try {
  $st = $pdo->prepare("
    SELECT r.id, r.title, r.type, r.artist_id, a.artist_name
    FROM releases r
    JOIN artists a ON a.id = r.artist_id
    WHERE r.id = ?
    LIMIT 1
  ");
  $st->execute([$releaseId]);
  $release = $st->fetch();
  if (!$release) { exit('Release nicht gefunden.'); }
  if ($artistId <= 0) $artistId = (int)$release['artist_id'];
  if ((int)$release['artist_id'] !== $artistId) {
    exit('Release gehört nicht zu diesem Künstler.');
  }
} catch (Throwable $e) {
  exit('Fehler: ' . htmlspecialchars($e->getMessage()));
}

/* ===== Datei speichern (Audio) ===== */
function save_file_local_mp3(string $field, int $maxBytes = 25*1024*1024): string {
  if (empty($_FILES[$field]['name']) || $_FILES[$field]['error']===UPLOAD_ERR_NO_FILE) {
    throw new RuntimeException('Bitte eine MP3-Datei auswählen.');
  }
  $f = $_FILES[$field];
  if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload-Fehler (Audio).');
  if ($f['size'] > $maxBytes)       throw new RuntimeException('MP3 ist größer als erlaubt (25 MB).');

  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['mp3'], true)) throw new RuntimeException('Nur MP3-Dateien erlaubt.');
  $mime = @mime_content_type($f['tmp_name']);
  if ($mime !== 'audio/mpeg') {
    // Manche Server melden bei MP3 nicht sauber 'audio/mpeg' – wir prüfen notfalls nur die Endung.
    if ($ext !== 'mp3') throw new RuntimeException('Ungültiger Dateityp.');
  }

  $name = bin2hex(random_bytes(8)) . '_' . time() . '.mp3';
  $dir  = dirname(__DIR__) . '/uploads/audio';
  if (!is_dir($dir)) mkdir($dir, 0755, true);

  $dest = $dir . '/' . $name;
  if (!move_uploaded_file($f['tmp_name'], $dest)) {
    throw new RuntimeException('Speichern der Datei fehlgeschlagen.');
  }
  return '/grabiton/uploads/audio/' . $name;
}

/* ===== POST: Track anlegen ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title'] ?? '');

  if ($title === '') $errors[] = 'Bitte einen Titel eingeben.';

  if (empty($errors)) {
    try {
      $pdo->beginTransaction();

      // Nächste Track-Nummer (am Ende anhängen) – sperrt die Gruppe dieses Releases
      $mx = $pdo->prepare("SELECT COALESCE(MAX(track_no),0) AS mx FROM songs WHERE release_id = ? FOR UPDATE");
      $mx->execute([$releaseId]);
      $next = (int)$mx->fetchColumn() + 1;

      // Audio speichern (wirft ggf. Exception)
      $audioPath = save_file_local_mp3('audio');

      // Song einfügen
      $ins = $pdo->prepare("
        INSERT INTO songs (artist_id, release_id, title, audio_path, track_no, uploaded_at, created_at, is_hidden, is_new, is_featured)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 0, 0, 0)
      ");
      $ins->execute([$artistId, $releaseId, $title, $audioPath, $next]);

      $pdo->commit();

      // zurück zu Release bearbeiten
      $qs = http_build_query([
        'id'     => $releaseId,
        'artist' => $artistId,
        'from'   => $from,
        'msg'    => 'Track hinzugefügt.'
      ]);
      header('Location: /grabiton/admin/release_edit.php?' . $qs);
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = $e->getMessage();
    }
  }
}

/* Zurück-Link */
$back = '/grabiton/admin/release_edit.php?' . http_build_query([
  'id'     => $releaseId,
  'artist' => $artistId,
  'from'   => $from
]);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Track hinzufügen – Grab It On</title>
<style>
  :root { color-scheme: dark; }
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#151102;color:#fff;margin:0}
  header,footer{padding:14px 18px;background:rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.12);backdrop-filter:blur(6px)}
  main{width:min(900px,96vw);margin:22px auto;padding:0 20px}
  .btn{display:inline-block;background:#2b5dff;color:#fff;border-radius:10px;padding:10px 16px;text-decoration:none;font-weight:600;border:0;cursor:pointer}
  .btn:hover{filter:brightness(1.05)}
  .btn-ghost{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18)}
  .card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:16px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
  h1{margin:0 0 12px}
  label{display:block;margin:10px 0 6px;opacity:.9}
  .input,.file{width:100%;padding:12px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.08);color:#fff;outline:none;box-sizing:border-box}
  .rowline{display:flex; gap:10px; align-items:center; flex-wrap:wrap}
  .msg{margin:10px 0;padding:10px 12px;border-radius:10px;background:rgba(255,0,0,.25);border:1px solid rgba(255,255,255,.4)}
  .ok{background:rgba(43,93,255,.2);border:1px solid rgba(43,93,255,.45)}
  .muted{opacity:.85}
</style>
</head>
<body>
<header>
  <a class="btn" href="<?= htmlspecialchars($back) ?>">← Zurück</a>
</header>

<main>
  <h1>Track hinzufügen</h1>

  <?php foreach($errors as $e): ?><div class="msg"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  <?php foreach($messages as $m): ?><div class="msg ok"><?= htmlspecialchars($m) ?></div><?php endforeach; ?>

  <div class="card">
    <div class="muted" style="margin-bottom:10px;">
      <strong>Künstler:</strong> <?= htmlspecialchars($release['artist_name']) ?> &nbsp;•&nbsp;
      <strong>Release:</strong> „<?= htmlspecialchars($release['title']) ?>”
    </div>

    <form method="post" enctype="multipart/form-data" autocomplete="off">
      <label>Titel *</label>
      <input class="input" type="text" name="title" required placeholder="Track-Titel">

      <label>Audio (MP3, max. 25 MB) *</label>
      <input class="file" type="file" name="audio" accept=".mp3,audio/mpeg" required>

      <div class="rowline" style="margin-top:12px;">
        <button class="btn" type="submit">Speichern</button>
        <a class="btn btn-ghost" href="<?= htmlspecialchars($back) ?>">Abbrechen</a>
      </div>
    </form>
  </div>
</main>

<footer>© <?= date('Y') ?> Grab It On</footer>
</body>
</html>
