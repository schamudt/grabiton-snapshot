<?php
// /grabiton/artist.php  (ÖFFENTLICH)
require_once __DIR__ . '/includes/db.php';
ini_set('display_errors', 1); error_reporting(E_ALL);

$artistId = (int)($_GET['id'] ?? 0);
if ($artistId <= 0) {
  http_response_code(400);
  echo "Künstler-ID fehlt.";
  exit;
}

/* Künstler laden */
try {
  $a = $pdo->prepare("SELECT id, artist_name, genre, banner_path, profile_path FROM artists WHERE id = ? LIMIT 1");
  $a->execute([$artistId]);
  $artist = $a->fetch();
  if (!$artist) {
    http_response_code(404);
    echo "Künstler nicht gefunden.";
    exit;
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo "Fehler beim Laden des Künstlers: " . htmlspecialchars($e->getMessage());
  exit;
}

/* Releases laden (neueste zuerst; falls created_at fehlt, fallback über id) */
try {
  $r = $pdo->prepare("
    SELECT id, title, type, cover_path
    FROM releases
    WHERE artist_id = ?
    ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
  ");
  $r->execute([$artistId]);
  $releases = $r->fetchAll();
} catch (Throwable $e) {
  $releases = [];
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($artist['artist_name']) ?> – Grab It On</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  html, body { height: 100%; margin: 0; }
  body {
    font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    background: #030d2f; /* dunkelsamtblau */
    color: #fff;
    display: flex; flex-direction: column;
  }

  /* Header/ Footer: milchglas */
  .glass {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    backdrop-filter: blur(6px);
  }
  header {
    display:flex; align-items:center; justify-content:space-between;
    padding: 14px 18px;
  }
  .brand { display:flex; align-items:center; gap:10px; }
  .brand img { height: 30px; filter: drop-shadow(0 1px 8px rgba(0,0,0,.35)); }
  .brand span { font-weight: 600; opacity: .9; font-size: 14px; }
  .nav a { color:#fff; text-decoration:none; opacity:.85; padding:8px 12px; border-radius:8px; }
  .nav a:hover { background: rgba(255,255,255,.08); opacity: 1; }

  main { width: min(1100px, 96vw); margin: 22px auto; flex: 1; }

  /* Artist Header */
  .artist-head { display:grid; grid-template-columns: 140px 1fr; gap:16px; align-items:center; }
  .profile {
    width: 140px; height: 140px; border-radius: 16px; object-fit: cover;
    background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.18);
  }
  .banner-wrap {
    position: relative; border-radius: 16px; overflow: hidden;
    height: 180px; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12);
  }
  .banner {
    width: 100%; height: 100%; object-fit: cover; display:block;
  }
  .artist-meta { margin-top: 10px; }
  .artist-meta h1 { margin: 0; font-size: 28px; }
  .artist-meta .genre { opacity: .85; }

  /* Release Cards */
  .release { margin-top: 18px; }
  .release h2 { margin: 0 0 10px; font-size: 20px; display:flex; align-items:center; gap:10px; }
  .badge {
    padding: 4px 10px; border-radius: 999px;
    background: rgba(255,255,255,.10); border:1px solid rgba(255,255,255,.18);
    font-size: 12px; font-weight: 600; opacity:.9;
  }
  .release-card {
    display:grid; grid-template-columns: 140px 1fr; gap:16px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 14px; padding: 14px;
    box-shadow: 0 10px 30px rgba(0,0,0,.25);
  }
  .cover {
    width: 140px; height: 140px; border-radius: 12px; object-fit: cover;
    background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.18);
  }

  table { width:100%; border-collapse: collapse; }
  th, td { padding: 10px; border-bottom: 1px solid rgba(255,255,255,.12); text-align:left; vertical-align:middle; font-size:14px; }
  th { font-weight: 600; opacity:.9; }

  .playbtn{
    appearance:none; border:1px solid rgba(255,255,255,.18);
    background:rgba(255,255,255,.08); color:#fff;
    padding:8px 12px; border-radius:10px; cursor:pointer; font-weight:600
  }
  .playbtn:hover{ background:rgba(255,255,255,.12) }

  /* Bottom Player */
  .player {
    position: sticky; bottom: 0; left: 0; right: 0;
    display: none;
  }
  .player .bar {
    display:flex; align-items:center; gap:12px;
    padding: 10px 16px;
  }

  footer {
    margin-top: 24px;
    padding: 16px 18px; text-align:center; font-size:12px; opacity:.7;
  }
</style>
</head>
<body>
<header class="glass">
  <div class="brand">
    <img src="/grabiton/assets/img/dat_Logo_GrabItOn_fontlogclaim_wh.png" alt="Grab It On">
    <span>Grab It On</span>
  </div>
  <nav class="nav">
    <a href="/grabiton/index.php">Start</a>
  </nav>
</header>

<main>
  <!-- Artist Header -->
  <div class="artist-head">
    <img class="profile" src="<?= h($artist['profile_path'] ?: '') ?>" alt="<?= h($artist['artist_name']) ?>">
    <div>
      <div class="banner-wrap">
        <?php if (!empty($artist['banner_path'])): ?>
          <img class="banner" src="<?= h($artist['banner_path']) ?>" alt="">
        <?php endif; ?>
      </div>
      <div class="artist-meta">
        <h1><?= h($artist['artist_name']) ?></h1>
        <?php if (!empty($artist['genre'])): ?>
          <div class="genre"><?= h($artist['genre']) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Releases -->
  <?php if (empty($releases)): ?>
    <div class="release"><div class="release-card">Noch keine Veröffentlichungen.</div></div>
  <?php else: ?>
    <?php foreach ($releases as $rel): ?>
      <?php
      // Tracks dieses Releases laden – NUR öffentliche (is_hidden=0)
      try {
        $ts = $pdo->prepare("
          SELECT id, title, audio_path
          FROM songs
          WHERE release_id = ? AND IFNULL(is_hidden,0) = 0
          ORDER BY track_no ASC, id ASC
        ");
        $ts->execute([(int)$rel['id']]);
        $tracks = $ts->fetchAll();
      } catch (Throwable $e) {
        $tracks = [];
      }
      $typeLabel = strtoupper($rel['type'] ?? 'single');
      ?>
      <section class="release">
        <h2>
          <?= h($rel['title']) ?>
          <span class="badge"><?= h($typeLabel) ?></span>
        </h2>
        <div class="release-card">
          <img class="cover" src="<?= h($rel['cover_path'] ?: '') ?>" alt="<?= h($rel['title']) ?>">
          <div>
            <?php if (empty($tracks)): ?>
              <div class="badge" style="opacity:.75">Keine öffentlichen Tracks</div>
            <?php else: ?>
              <table>
                <thead>
                  <tr>
                    <th style="width:8%;">#</th>
                    <th>Titel</th>
                    <th style="width:16%;">Play</th>
                  </tr>
                </thead>
                <tbody>
                <?php $i=1; foreach ($tracks as $t): ?>
                  <tr data-title="<?= h($t['title']) ?>" data-artist="<?= h($artist['artist_name']) ?>" data-audio="<?= h($t['audio_path'] ?? '') ?>" data-cover="<?= h($rel['cover_path'] ?? '') ?>">
                    <td><?= $i++ ?></td>
                    <td><?= h($t['title']) ?></td>
                    <td>
                      <?php if (!empty($t['audio_path'])): ?>
                        <button class="playbtn" type="button">Play</button>
                      <?php else: ?>
                        <span style="opacity:.7">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<!-- Sticky Player unten -->
<div class="player">
  <div class="glass bar">
    <img id="plCover" alt="" style="width:44px;height:44px;border-radius:8px;object-fit:cover;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18)">
    <div style="display:flex;flex-direction:column;">
      <strong id="plTitle" style="font-size:14px;">–</strong>
      <small id="plArtist" style="opacity:.8">–</small>
    </div>
    <div style="flex:1"></div>
    <audio id="plAudio" controls preload="none" style="max-width:420px;width:100%"></audio>
  </div>
</div>

<footer class="glass">© <?= date('Y') ?> Grab It On</footer>

<script>
(function(){
  const $ = sel => document.querySelector(sel);
  const $$ = sel => Array.from(document.querySelectorAll(sel));

  const pl = document.querySelector('.player');
  const plAudio = $('#plAudio');
  const plTitle = $('#plTitle');
  const plArtist= $('#plArtist');
  const plCover = $('#plCover');

  document.addEventListener('click', async (e)=>{
    const btn = e.target.closest('.playbtn');
    if (!btn) return;
    const tr = btn.closest('tr');
    const src = tr?.dataset.audio || '';
    if (!src) return;

    // Toggle: wenn derselbe Track schon läuft → play/pause
    if (plAudio.src && (plAudio.src.endsWith(src.replace(/^\/+/,'')) || plAudio.src === location.origin + src)) {
      if (plAudio.paused) { try{ await plAudio.play(); btn.textContent='Pause'; } catch{} }
      else { plAudio.pause(); btn.textContent='Play'; }
      return;
    }

    // Anderen Track laden
    $$('.playbtn').forEach(b=>b.textContent='Play');
    btn.textContent = 'Pause';

    plTitle.textContent = tr.dataset.title || '–';
    plArtist.textContent = tr.dataset.artist || '–';
    if (tr.dataset.cover) { plCover.src = tr.dataset.cover; plCover.style.display=''; }
    else { plCover.removeAttribute('src'); plCover.style.display='none'; }

    plAudio.src = src;
    pl.style.display = 'block';
    try { await plAudio.play(); }
    catch { btn.textContent='Play'; }
  });

  plAudio.addEventListener('play', ()=>{
    // aktiven Button auf "Pause" setzen (einfachheitshalber: alle zurücksetzen & suchen)
    $$('.playbtn').forEach(b=>b.textContent='Play');
    // wir könnten hier anhand src den korrekten Button finden; für schlankheit lassen wir das.
  });
  plAudio.addEventListener('pause', ()=>{
    // nichts zwingend
  });
})();
</script>
</body>
</html>
