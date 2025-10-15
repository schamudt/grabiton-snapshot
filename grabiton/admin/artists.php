<?php
// /grabiton/admin/artists.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

/* Meldungen aus Redirect (optional) */
$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';
$err = isset($_GET['err']) ? trim($_GET['err']) : '';

$errors = [];
try {
  // Release-Anzahl pro Künstler mitladen
  $stmt = $pdo->query("
    SELECT
      a.id,
      a.artist_name,
      a.artist_code,
      a.genre,
      a.avatar_path,
      (SELECT COUNT(*) FROM releases r WHERE r.artist_id = a.id) AS release_count
    FROM artists a
    ORDER BY a.artist_name ASC
  ");
  $artists = $stmt->fetchAll();
} catch (Throwable $e) {
  $artists = [];
  $errors[] = 'Künstler konnten nicht geladen werden: ' . htmlspecialchars($e->getMessage());
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Künstler*innen – Grab It On</title>
<style>
  :root { color-scheme: dark; }
  body{
    font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
    background:#0b1a3a; /* Artists-Seite bleibt blau */
    color:#fff; margin:0;
  }
  header,footer{
    padding:14px 18px;
    background:rgba(255,255,255,.06);
    border-bottom:1px solid rgba(255,255,255,.12);
    backdrop-filter:blur(6px);
  }
  main{ width:min(1100px,96vw); margin:22px auto; padding:0 20px }

  .btn{
    display:inline-block; border:0; cursor:pointer; text-decoration:none;
    padding:10px 16px; border-radius:10px; font-weight:600; color:#fff;
    background:#2b5dff; /* Standard Blau */
  }
  .btn:hover{ filter:brightness(1.05) }

  /* Releases-Button: #97871d */
  .btn-releases{ background:#97871d; box-shadow:0 6px 18px rgba(151,135,29,.28) }
  .btn-releases:hover{ background:#7f7218 }

  /* Dunkler, entsättigter Löschen-Button */
  .btn-danger{ background:#8f2a2f; box-shadow:0 6px 18px rgba(143,42,47,.28) }
  .btn-danger:hover{ background:#7a2429 }

  .titleline{
    display:flex; align-items:center; justify-content:space-between;
    gap:10px; flex-wrap:wrap;
  }
  .searchwrap{ display:flex; align-items:center; gap:8px; margin-left:auto; }
  .search{
    width:260px; padding:10px 12px; border-radius:10px;
    border:1px solid rgba(255,255,255,.12);
    background:rgba(255,255,255,.08); color:#fff; outline:none; box-sizing:border-box;
  }

  .card{
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.12);
    border-radius:14px;
    padding:16px;
    box-shadow:0 10px 30px rgba(0,0,0,.25);
  }
  .msg{
    margin:10px 0; padding:10px 12px; border-radius:10px;
    background:rgba(255,0,0,.25); border:1px solid rgba(255,0,0,.4);
  }
  .ok{
    background:rgba(43,93,255,.2); border:1px solid rgba(43,93,255,.45);
  }

  table{ width:100%; border-collapse:collapse; margin-top:8px }
  th,td{ padding:10px; border-bottom:1px solid rgba(255,255,255,.12); text-align:left; vertical-align:middle }
  th{ font-weight:600 }
  .avatar{ width:42px; height:42px; border-radius:50%; object-fit:cover; background:rgba(255,255,255,.12) }
  .code{ font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace }

  /* Hervorhebung / Abblendung bei Suche */
  tr.highlight{ background:rgba(255,255,255,.14) }
  tr.dim{ opacity:.35 }

  .row-actions{ display:flex; gap:8px; flex-wrap:wrap; }
</style>
</head>
<body>
<header>
  <a class="btn" href="/grabiton/admin/dashboard.php">← Zurück</a>
  <a class="btn" href="/grabiton/admin/artist_new.php" style="margin-left:8px;">+ Neuer Künstler</a>
</header>

<main>
  <div class="titleline">
    <h1>Verwaltungsbereich</h1>
    <div class="searchwrap">
      <input id="artistSearch" class="search" type="search" placeholder="Suchen nach Name oder ID …" autocomplete="off">
    </div>
  </div>

  <?php if ($msg): ?><div class="msg ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="msg"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php foreach ($errors as $e): ?><div class="msg"><?= $e ?></div><?php endforeach; ?>

  <div class="card" style="margin-top:12px;">
    <?php if (empty($artists)): ?>
      <p>Keine Künstler vorhanden.</p>
    <?php else: ?>
      <table id="artistTable">
        <thead>
          <tr>
            <th>Avatar</th>
            <th>Name</th>
            <th>Releases</th>
            <th>ID</th>
            <th>Genre</th>
            <th>Aktionen</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($artists as $a): ?>
          <tr data-name="<?= htmlspecialchars(mb_strtolower($a['artist_name'])) ?>"
              data-code="<?= htmlspecialchars(mb_strtolower($a['artist_code'] ?? '')) ?>">
            <td>
              <?php if ($a['avatar_path']): ?>
                <img class="avatar" src="<?= htmlspecialchars($a['avatar_path']) ?>" alt="">
              <?php else: ?>
                <div class="avatar"></div>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($a['artist_name']) ?></td>
            <td><?= (int)($a['release_count'] ?? 0) ?></td>
            <td class="code"><?= htmlspecialchars($a['artist_code'] ?? '—') ?></td>
            <td><?= htmlspecialchars($a['genre'] ?? '—') ?></td>
            <td class="row-actions">
              <a class="btn" href="/grabiton/admin/artist_edit.php?id=<?= (int)$a['id'] ?>">Bearbeiten</a>
              <a class="btn btn-releases" href="/grabiton/admin/releases.php?artist=<?= (int)$a['id'] ?>&from=list">Releases</a>
              <a class="btn btn-danger"
                 href="/grabiton/admin/artist_delete.php?id=<?= (int)$a['id'] ?>&from=list">
                 Löschen
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</main>

<footer>© <?= date('Y') ?> Grab It On</footer>

<script>
// Live-Suche nach Name und ID: Treffer aufhellen, andere abdunkeln
(function(){
  const input = document.getElementById('artistSearch');
  const rows  = document.querySelectorAll('#artistTable tbody tr');

  function applyFilter(q){
    const query = (q || '').trim().toLowerCase();
    if (query === '') {
      rows.forEach(tr => tr.classList.remove('highlight','dim'));
      return;
    }
    rows.forEach(tr => {
      const name = tr.getAttribute('data-name') || '';
      const code = tr.getAttribute('data-code') || '';
      const match = name.includes(query) || code.includes(query);
      tr.classList.toggle('highlight', match);
      tr.classList.toggle('dim', !match);
    });
  }

  input.addEventListener('input', e => applyFilter(e.target.value));
  input.addEventListener('keydown', e => {
    if (e.key === 'Escape') { input.value = ''; applyFilter(''); }
  });
})();
</script>
</body>
</html>
