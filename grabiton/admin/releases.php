<?php
// /grabiton/admin/releases.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';
ini_set('display_errors', 1); error_reporting(E_ALL);

$artistId = (int)($_GET['artist'] ?? 0);
$from     = $_GET['from'] ?? '';
$from     = in_array($from, ['artist','list'], true) ? $from : '';

$errors = [];
$artist = null;

if ($artistId > 0) {
  try {
    $s = $pdo->prepare("SELECT id, artist_name, artist_code FROM artists WHERE id = ?");
    $s->execute([$artistId]);
    $artist = $s->fetch();
    if (!$artist) { $errors[] = "Künstler mit ID $artistId nicht gefunden."; $artistId = 0; $from=''; }
  } catch (Throwable $e) {
    $errors[] = "Fehler beim Laden des Künstlers: " . htmlspecialchars($e->getMessage());
    $artistId = 0; $from='';
  }
}

try {
  if ($artistId > 0) {
    $stmt = $pdo->prepare("
      SELECT r.id, r.title, r.release_type, r.cover_path, r.release_date, a.artist_name, a.id AS artist_id
      FROM releases r JOIN artists a ON a.id = r.artist_id
      WHERE r.artist_id = ?
      ORDER BY r.created_at DESC, r.id DESC
    ");
    $stmt->execute([$artistId]);
  } else {
    $stmt = $pdo->query("
      SELECT r.id, r.title, r.release_type, r.cover_path, r.release_date, a.artist_name, a.id AS artist_id
      FROM releases r JOIN artists a ON a.id = r.artist_id
      ORDER BY r.created_at DESC, r.id DESC
    ");
  }
  $releases = $stmt->fetchAll();
} catch (Throwable $e) {
  $releases = [];
  $errors[] = "Fehler beim Laden der Releases: " . htmlspecialchars($e->getMessage());
}

$backLink = ($artistId > 0 && $from === 'artist')
  ? "/grabiton/admin/artist_edit.php?id=".(int)$artistId
  : "/grabiton/admin/artists.php";

function build_url(string $path, array $params): string {
  $qs = http_build_query(array_filter($params, fn($v)=>$v!==null && $v!==''));
  return $qs ? $path.'?'.$qs : $path;
}

$paramsCommon = [];
if ($artistId > 0) $paramsCommon['artist'] = $artistId;
if ($from) $paramsCommon['from'] = $from;

$newLink = build_url('/grabiton/admin/release_new.php', $paramsCommon);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Releases – Grab It On</title>
<style>
  :root { color-scheme: dark; }
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#151102;color:#fff;margin:0}
  header,footer{padding:14px 18px;background:rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.12);backdrop-filter:blur(6px)}
  main{width:min(1100px,96vw);margin:22px auto;padding:0 20px}
  .btn{display:inline-block;background:#2b5dff;color:#fff;border-radius:10px;padding:10px 16px;text-decoration:none;font-weight:600;border:0;cursor:pointer}
  .btn-danger{background:#8f2a2f; box-shadow:0 6px 18px rgba(143,42,47,.28); color:#fff}
  .btn-danger:hover{background:#7a2429}
  .titleline{display:flex;align-items:end;justify-content:space-between;gap:10px;flex-wrap:wrap}
  .meta{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .idbadge{display:inline-block;padding:6px 10px;border-radius:10px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
  .muted{opacity:.8}
  .card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:16px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{padding:10px;border-bottom:1px solid rgba(255,255,255,.12);text-align:left;vertical-align:middle}
  th{font-weight:600}
  .cover{width:48px;height:48px;border-radius:8px;object-fit:cover;background:rgba(255,255,255,.12)}
  .msg{margin:10px 0;padding:10px 12px;border-radius:10px;background:rgba(255,0,0,.25);border:1px solid rgba(255,0,0,.4)}
</style>
</head>
<body>
<header>
  <a class="btn" href="<?= htmlspecialchars($backLink) ?>">← Zurück</a>
  <a class="btn" href="<?= htmlspecialchars($newLink) ?>" style="margin-left:8px;">+ Neues Release</a>
</header>
<main>
  <div class="titleline">
    <h1>Releases</h1>
    <?php if ($artist): ?>
      <div class="meta">
        <span class="muted"><?= htmlspecialchars($artist['artist_name']) ?></span>
        <?php if (!empty($artist['artist_code'])): ?>
          <span class="idbadge"><?= htmlspecialchars($artist['artist_code']) ?></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php foreach ($errors as $e): ?><div class="msg"><?= $e ?></div><?php endforeach; ?>

  <div class="card" style="margin-top:12px;">
    <?php if (empty($releases)): ?>
      <p class="muted">Keine Releases vorhanden.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Cover</th>
            <th>Titel</th>
            <?php if ($artistId === 0): ?><th>Künstler</th><?php endif; ?>
            <th>Typ</th>
            <th>Release-Datum</th>
            <th>Aktionen</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($releases as $r):
          $edit = build_url('/grabiton/admin/release_edit.php', array_merge($paramsCommon, ['id'=>(int)$r['id']]));
          $del  = build_url('/grabiton/admin/release_delete.php', array_merge($paramsCommon, ['id'=>(int)$r['id']]));
        ?>
          <tr>
            <td><?php if ($r['cover_path']): ?><img class="cover" src="<?= htmlspecialchars($r['cover_path']) ?>" alt="Cover"><?php endif; ?></td>
            <td><?= htmlspecialchars($r['title']) ?></td>
            <?php if ($artistId === 0): ?><td class="muted"><?= htmlspecialchars($r['artist_name']) ?></td><?php endif; ?>
            <td><?= strtoupper(htmlspecialchars($r['release_type'])) ?></td>
            <td><?= $r['release_date'] ? htmlspecialchars(date('d.m.Y', strtotime($r['release_date']))) : '—' ?></td>
            <td>
              <a class="btn" href="<?= htmlspecialchars($edit) ?>">Bearbeiten</a>
              <!-- Delete in gleicher Farbe wie andere Löschen-Buttons -->
              <a class="btn btn-danger" href="<?= htmlspecialchars($del) ?>">Löschen</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</main>
<footer>© <?= date('Y') ?> Grab It On</footer>
</body>
</html>
