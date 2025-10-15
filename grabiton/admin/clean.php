<?php
// Admin UI: Direktlöschung per Song-ID (postet an song_delete.php)
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_login();

require_once __DIR__ . '/../includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['gio_csrf'])) { $_SESSION['gio_csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['gio_csrf'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$msg = (string)($_GET['msg'] ?? '');
$map = [
  'deleted'       => ['OK',   'Der Song wurde gelöscht.'],
  'has_release'   => ['Hinweis', 'Löschung blockiert: Song ist noch einem Release zugeordnet. Bitte erst die Zuordnung entfernen.'],
  'not_found'     => ['Fehler','Song wurde nicht gefunden.'],
  'invalid_id'    => ['Fehler','Ungültige ID.'],
  'csrf_fail'     => ['Fehler','Sicherheitsfehler (CSRF). Bitte Seite neu laden.'],
  'not_authorized'=> ['Fehler','Nicht autorisiert.'],
  'server_error'  => ['Fehler','Interner Fehler.'],
];
list($msgTitle, $msgText) = $map[$msg] ?? ['', ''];
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Direktlöschung (ID) – Grab It On Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{ --bg:#0b1a3a; --glass:rgba(255,255,255,.08); --border:rgba(255,255,255,.12); --txt:#fff; --blue:#2b5dff; --red:#8f2a2f; --redh:#7a2429; }
    *{box-sizing:border-box}
    body{ margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; background:var(--bg); color:var(--txt); }
    .wrap{ max-width:720px; margin:40px auto; padding:0 16px; }
    h1{ font-size:22px; margin:0 0 14px; }
    .card{ background:var(--glass); border:1px solid var(--border); border-radius:14px; padding:18px; backdrop-filter:blur(10px); box-shadow:0 10px 30px rgba(0,0,0,.25); }
    .desc{ opacity:.85; font-size:14px; line-height:1.5; margin:0 0 14px; }
    form{ display:grid; grid-template-columns: 1fr auto; gap:10px; align-items:center; }
    label{ font-size:13px; opacity:.9; }
    input[type="number"]{
      width:100%; padding:10px 12px; border-radius:10px; border:1px solid var(--border); background:rgba(255,255,255,.06); color:var(--txt); font-size:16px;
    }
    .btn{ appearance:none; border:0; border-radius:10px; padding:10px 16px; font-weight:700; cursor:pointer; color:#fff; background:var(--red); }
    .btn:hover{ background:var(--redh); }
    .links{ display:flex; gap:10px; margin-bottom:16px; }
    .linkbtn{ text-decoration:none; color:#fff; border:1px solid var(--border); background:rgba(255,255,255,.06); padding:8px 12px; border-radius:10px; font-weight:600; }
    .linkbtn:hover{ background:rgba(255,255,255,.12); }
    .alert{ margin:0 0 16px; padding:12px; border-radius:12px; border:1px solid var(--border); background:rgba(255,255,255,.07); }
    .alert b{ display:block; margin-bottom:4px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="links">
      <a class="linkbtn" href="/grabiton/admin/dashboard.php">← Dashboard</a>
      <a class="linkbtn" href="/grabiton/admin/songs_cleanup.php">Songs aufräumen</a>
    </div>

    <h1>Direktlöschung per Song-ID</h1>

    <?php if ($msgTitle): ?>
      <p class="alert"><b><?= h($msgTitle) ?></b><?= h($msgText) ?></p>
    <?php endif; ?>

    <section class="card">
      <p class="desc">
        Song gezielt per <b>ID</b> löschen. Aus Sicherheitsgründen werden nur Songs gelöscht, die <b>nicht</b> einem Release zugeordnet sind.
      </p>

      <form method="post" action="/grabiton/admin/song_delete.php"
            onsubmit="return confirm('Song endgültig löschen? Dies kann nicht rückgängig gemacht werden.');">
        <div>
          <label for="song_id">Song-ID</label>
          <input type="number" id="song_id" name="song_id" min="1" step="1" required>
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        </div>
        <div>
          <button class="btn" type="submit">Song löschen</button>
        </div>
      </form>
    </section>
  </div>
</body>
</html>
