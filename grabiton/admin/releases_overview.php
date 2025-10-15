<?php
// /grabiton/admin/releases_overview.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';
ini_set('display_errors', 1); error_reporting(E_ALL);

$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';
$err = isset($_GET['err']) ? trim($_GET['err']) : '';

$errors=[];
$artists=[];
$byArtist=[];
try {
  // Künstler alphabetisch
  $a = $pdo->query("SELECT id, artist_name, artist_code FROM artists ORDER BY artist_name ASC");
  $artists = $a->fetchAll();

  // Releases je Künstler (nach Datum/Titel sortiert)
  $r = $pdo->query("
    SELECT r.id, r.title, r.release_type, r.release_date, r.artist_id
    FROM releases r
    ORDER BY r.release_date DESC, r.id DESC
  ");
  while ($row = $r->fetch()) {
    $byArtist[(int)$row['artist_id']][] = $row;
  }
} catch (Throwable $e) {
  $errors[] = "Fehler beim Laden: " . htmlspecialchars($e->getMessage());
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Releases Übersicht – Grab It On</title>
<style>
  :root { color-scheme: dark; }
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#0b1a3a;color:#fff;margin:0}
  header,footer{padding:14px 18px;background:rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.12);backdrop-filter:blur(6px)}
  main{width:min(1200px,96vw);margin:22px auto;padding:0 20px}
  .btn{display:inline-block;background:#2b5dff;color:#fff;border-radius:10px;padding:10px 16px;text-decoration:none;font-weight:600;border:0;cursor:pointer}
  .btn:hover{ filter:brightness(1.05) }
  .btn-danger{ background:#8f2a2f; color:#fff; box-shadow:0 6px 18px rgba(143,42,47,.28) }
  .btn-danger:hover{ background:#7a2429 }

  .card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:16px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
  .msg{margin:10px 0;padding:10px 12px;border-radius:10px;background:rgba(255,0,0,.25);border:1px solid rgba(255,0,0,.4)}
  .ok{background:rgba(43,93,255,.2);border:1px solid rgba(43,93,255,.45)}
  table{width:100%;border-collapse:collapse;margin-top:10px}
  th,td{padding:10px;border-bottom:1px solid rgba(255,255,255,.12);vertical-align:top;text-align:left}
  th{font-weight:600}
  .code{font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace}
  .muted{opacity:.8}

  /* Release-Row kompakter (~30% niedriger) */
  .rel-list{display:flex; flex-direction:column; gap:6px} /* vorher 8px */
  .rel-row{
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12);
    border-radius:10px; padding:7px 10px; /* vorher 10px 12px */
    font-size:13px; line-height:1.25;     /* kompaktere Typo */
  }
  .rel-meta{display:flex; gap:8px; align-items:center; flex-wrap:wrap}
  .tag{font-size:11px; padding:3px 7px; border-radius:999px; background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.18)}
  .actions{display:flex; gap:6px; flex-wrap:wrap}
  .btn.sm{padding:6px 10px; border-radius:8px; font-size:12px}
</style>
</head>
<body>
<header>
  <a class="btn" href="/grabiton/admin/dashboard.php">← Zurück</a>
</header>
<main>
  <h1>Releases Übersicht</h1>

  <?php if ($msg): ?><div class="msg ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="msg"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php foreach ($errors as $e): ?><div class="msg"><?= $e ?></div><?php endforeach; ?>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th style="width:30%;">Künstler</th>
          <th>Releases</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($artists)): ?>
          <tr><td colspan="2" class="muted">Keine Künstler vorhanden.</td></tr>
        <?php else: ?>
          <?php foreach ($artists as $a): ?>
            <tr>
              <td>
                <div style="font-weight:600;"><?= htmlspecialchars($a['artist_name']) ?></div>
                <?php if (!empty($a['artist_code'])): ?>
                  <div class="code" style="opacity:.85;"><?= htmlspecialchars($a['artist_code']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $rels = $byArtist[(int)$a['id']] ?? [];
                  if (empty($rels)) {
                    echo '<div class="muted">Keine Releases vorhanden.</div>';
                  } else {
                    echo '<div class="rel-list">';
                    foreach ($rels as $r) {
                      $edit = '/grabiton/admin/release_edit.php?id='.(int)$r['id'].'&artist='.(int)$a['id'].'&from=overview';
                      $del  = '/grabiton/admin/release_delete_safe.php?id='.(int)$r['id'].'&artist='.(int)$a['id'].'&from=overview';
                      $type = strtoupper(htmlspecialchars($r['release_type']));
                      $date = $r['release_date'] ? htmlspecialchars(date('d.m.Y', strtotime($r['release_date']))) : '—';
                      echo '<div class="rel-row">';
                        echo '<div class="rel-meta">';
                          echo '<div><strong>'.htmlspecialchars($r['title']).'</strong></div>';
                          echo '<span class="tag">'.$type.'</span>';
                          echo '<span class="muted">'.$date.'</span>';
                        echo '</div>';
                        echo '<div class="actions">';
                          echo '<a class="btn sm" href="'.htmlspecialchars($edit).'">Bearbeiten</a>';
                          echo '<a class="btn sm btn-danger" href="'.htmlspecialchars($del).'">Löschen</a>';
                        echo '</div>';
                      echo '</div>';
                    }
                    echo '</div>';
                  }
                ?>
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
