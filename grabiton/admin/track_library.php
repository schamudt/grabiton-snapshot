<?php
// /grabiton/admin/track_library.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';
ini_set('display_errors', 1); error_reporting(E_ALL);

/* --- AJAX: Flags setzen (Neu/Angesagt/Unsichtbar) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'toggle') {
  header('Content-Type: application/json; charset=utf-8');
  $input = json_decode(file_get_contents('php://input'), true);
  $songId = (int)($input['id'] ?? 0);
  $field  = $input['field'] ?? '';
  $value  = (int)!!($input['value'] ?? 0);

  if ($songId <= 0 || !in_array($field, ['is_new','is_featured','is_hidden'], true)) {
    http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Ungültige Parameter']); exit;
  }
  try {
    $stmt = $pdo->prepare("UPDATE songs SET $field = ? WHERE id = ?");
    $stmt->execute([$value, $songId]);
    echo json_encode(['ok'=>true]);
  } catch (Throwable $e) {
    http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
  }
  exit;
}

/* --- AJAX: Upload-Datum ändern (nur Datumsteil) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'update_date') {
  header('Content-Type: application/json; charset=utf-8');
  $input = json_decode(file_get_contents('php://input'), true);
  $songId = (int)($input['id'] ?? 0);
  $date   = trim($input['date'] ?? '');

  if ($songId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Ungültige Parameter']); exit;
  }
  try {
    $stmt = $pdo->prepare("UPDATE songs SET uploaded_at = CONCAT(?, ' ', TIME(COALESCE(uploaded_at, '00:00:00'))) WHERE id = ?");
    $stmt->execute([$date, $songId]);
    echo json_encode(['ok'=>true]);
  } catch (Throwable $e) {
    http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
  }
  exit;
}

/* --- Songs laden (+ Plays, Likes & Basiszähler) --- */
$errors = [];
try {
  $stmt = $pdo->query("
    SELECT
      s.id, s.title AS song_title, s.audio_path, s.is_featured,
      IFNULL(s.is_new, 0)    AS is_new,
      IFNULL(s.is_hidden, 0) AS is_hidden,
      COALESCE(s.uploaded_at, s.created_at) AS uploaded_at,
      a.id AS artist_id, a.artist_name,
      r.title AS release_title, r.cover_path,

      COALESCE(pc.base_count,0)                       AS base_count,
      COALESCE(p.cnt,0)                               AS play_events,
      (COALESCE(pc.base_count,0) + COALESCE(p.cnt,0)) AS play_total,

      COALESCE(l.cnt,0)                               AS like_total

    FROM songs s
    JOIN artists a ON a.id = s.artist_id
    LEFT JOIN releases r ON r.id = s.release_id
    LEFT JOIN (
      SELECT song_id, COUNT(*) AS cnt
      FROM plays
      GROUP BY song_id
    ) p ON p.song_id = s.id
    LEFT JOIN play_counters pc ON pc.song_id = s.id
    LEFT JOIN (
      SELECT song_id, COUNT(*) AS cnt
      FROM likes
      GROUP BY song_id
    ) l ON l.song_id = s.id
    ORDER BY a.artist_name ASC, s.title ASC, s.id ASC
  ");
  $rows = $stmt->fetchAll();
} catch (Throwable $e) {
  try {
    $stmt = $pdo->query("
      SELECT
        s.id, s.title AS song_title, s.audio_path, s.is_featured,
        IFNULL(s.is_new, 0)    AS is_new,
        IFNULL(s.is_hidden, 0) AS is_hidden,
        COALESCE(s.uploaded_at, s.created_at) AS uploaded_at,
        a.id AS artist_id, a.artist_name,
        r.title AS release_title, r.cover_path,
        0 AS base_count, 0 AS play_events, 0 AS play_total,
        0 AS like_total
      FROM songs s
      JOIN artists a ON a.id = s.artist_id
      LEFT JOIN releases r ON r.id = s.release_id
      ORDER BY a.artist_name ASC, s.title ASC, s.id ASC
    ");
    $rows = $stmt->fetchAll();
    $errors[] = 'Hinweis: Zähltabellen fehlen – Plays/Likes werden vorerst als 0 angezeigt.';
  } catch (Throwable $e2) {
    $rows = [];
    $errors[] = 'Songs konnten nicht geladen werden: ' . htmlspecialchars($e2->getMessage());
  }
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Track Bibliothek – Grab It On</title>
<style>
  :root { color-scheme: dark; }
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#0b1a3a;color:#fff;margin:0}
  header,footer{padding:14px 18px;background:rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.12);backdrop-filter:blur(6px)}
  main{width:min(1200px,96vw);margin:22px auto;padding:0 20px}

  .topline{display:flex; align-items:center; justify-content:space-between; gap:12px}
  .search{display:flex; align-items:center; gap:8px; margin-right:396px;}
  .search input{
    padding:10px 12px; border-radius:10px; border:1px solid rgba(255,255,255,.18);
    background:rgba(255,255,255,.10); color:#fff; width:260px; outline:none
  }

  .btn{display:inline-block;background:#2b5dff;color:#fff;border-radius:10px;padding:10px 16px;text-decoration:none;font-weight:600;border:0;cursor:pointer}
  .btn:hover{ filter:brightness(1.05) }
  .btn-ghost{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18)}
  .btn-danger{background:#8f2a2f; color:#fff}

  .layout{display:grid; grid-template-columns: 1fr 380px; gap:16px; align-items:start}
  .card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:16px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
  .panel-warning{background: rgba(255, 0, 0, .08); border: 1px solid rgba(255, 0, 0, .25)}
  .msg{margin:10px 0;padding:10px 12px;border-radius:10px;background:rgba(255,0,0,.25);border:1px solid rgba(255,0,0,.4)}
  .ok{background:rgba(43,93,255,.2);border:1px solid rgba(43,93,255,.45)}

  table{width:100%;border-collapse:collapse;margin-top:0}
  thead tr.sortrow th{ padding: 0 10px 4px 10px; border-bottom: none; }
  th,td{padding:10px;border-bottom:1px solid rgba(255,255,255,.12);text-align:left;vertical-align:middle; font-size:14px}
  th{font-weight:600}
  .muted{opacity:.8}

  .sortbtn{
    appearance:none; border:1px solid rgba(255,255,255,.18);
    background:rgba(255,255,255,.08); color:#fff;
    padding:6px 10px; border-radius:8px; cursor:pointer; font-weight:600; font-size:12px;
    display:inline-flex; align-items:center; gap:6px;
  }
  .sortbtn:hover{ background:rgba(255,255,255,.12) }
  .sortbtn .arrow{opacity:.85; line-height:1}

  .playbtn{appearance:none; border:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.08); color:#fff; padding:8px 12px; border-radius:10px; cursor:pointer; font-weight:600}
  .playbtn:hover{ background:rgba(255,255,255,.12) }
  .calbtn{appearance:none; border:1px solid rgba(255,255,255,.22); background:rgba(255,255,255,.10); color:#fff; padding:6px 10px; border-radius:8px; cursor:pointer; font-weight:600}
  .calbtn:hover{ background:rgba(255,255,255,.16) }

  .side{position:sticky; top:16px}
  .rowline{display:flex; gap:10px; align-items:center; flex-wrap:wrap}
  .date-text{opacity:.9}

  .player { position: sticky; bottom: 0; left:0; right:0;
    background: rgba(3,13,47,.9); backdrop-filter: blur(6px);
    border-top: 1px solid rgba(255,255,255,.12); padding: 10px 16px; display: none;
    margin-top:16px; border-radius:14px 14px 0 0
  }
  .player-inner{display:flex; align-items:center; gap:12px}
  .cover{width:48px; height:48px; border-radius:8px; object-fit:cover; background:rgba(255,255,255,.12)}
  .meta{display:flex; flex-direction:column}
  .meta small{opacity:.8}
  audio{width:100%; max-width:420px; display:block}
  .grow{flex:1}

  /* Treffer-Hervorhebung (Suche) */
  tr.hit { background: rgba(255,255,255,.16) !important; box-shadow: inset 0 0 0 1px rgba(255,255,255,.22); }
  tr.hit td { border-bottom-color: rgba(255,255,255,.22); }

  /* Unsichtbar-Markierung */
  tr.row-hidden { background: #2a1416 !important; color: #ff9a9a; }
  tr.row-hidden td { border-bottom-color: rgba(255,120,120,.25); }
  tr.row-hidden .date-text, tr.row-hidden .muted { color: #ff9a9a; opacity: .9; }
  tr.row-hidden .playbtn, tr.row-hidden .calbtn { border-color: rgba(255,120,120,.35); }

  .hidden{display:none}

  /* NEU: ID-Spalte */
  .idcell { white-space: nowrap; }
  .idtxt  { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; opacity:.95; }
  .copybtn{
    margin-left:8px; padding:4px 8px; font-size:12px; line-height:1;
    border-radius:8px; border:1px solid rgba(255,255,255,.18);
    background:rgba(255,255,255,.08); color:#fff; cursor:pointer; font-weight:600;
  }
  .copybtn:hover{ background:rgba(255,255,255,.14); }

  /* Mini-Flash, wenn Top-Sortierung angewandt wird */
  .flash { animation: flash .9s ease; }
  @keyframes flash {
    0%{ box-shadow: inset 0 0 0 0 rgba(255,255,255,0); }
    40%{ box-shadow: inset 0 0 0 2px rgba(43,93,255,.55); }
    100%{ box-shadow: inset 0 0 0 0 rgba(255,255,255,0); }
  }
</style>
</head>
<body>
<header>
  <a class="btn" href="/grabiton/admin/dashboard.php">← Zurück</a>
</header>

<main>
  <div class="topline">
    <h1 style="margin:0">Track Bibliothek</h1>
    <form class="search" id="searchForm" onsubmit="return false;">
      <input type="text" id="searchInput" placeholder="Suche: Künstler, Song, Datum, Monat …">
      <button class="btn" id="searchBtn" type="button">Suchen</button>
    </form>
  </div>

  <?php foreach($errors as $e): ?><div class="msg"><?= $e ?></div><?php endforeach; ?>

  <div class="layout">
    <!-- Linke Spalte: Liste -->
    <div class="card">
      <table id="tracksTable">
        <thead>
          <!-- Sortierzeile -->
          <tr class="sortrow">
            <!-- Künstler: nur A–Z -->
            <th><button class="sortbtn" id="sortArtist" type="button" title="Künstler alphabetisch sortieren"><span>A–Z</span></button></th>
            <!-- NEU: ID-Spalte Kopf ohne Sortierung -->
            <th><span class="muted">ID</span></th>
            <th><button class="sortbtn" id="sortSong"   type="button" title="Songs alphabetisch sortieren"><span>Songs</span><span class="arrow">A–Z</span></button></th>
            <th></th> <!-- Play -->
            <th><button class="sortbtn" id="sortDate"   type="button" title="Neueste Uploads zuerst"><span>Datum</span><span class="arrow">↓</span></button></th>
            <!-- NEU: Top-Buttons über Neu & Angesagt -->
            <th><button class="sortbtn" id="sortTopNew"  type="button" title="Neu-Häkchen nach oben, Rest A–Z">Top</button></th>
            <th><button class="sortbtn" id="sortTopFeat" type="button" title="Angesagt-Häkchen nach oben, Rest A–Z">Top</button></th>
            <th></th> <!-- Datum-Button -->
            <th></th> <!-- Unsichtbar -->
            <th><button class="sortbtn" id="sortPlays" type="button" title="Nach Plays absteigend sortieren"><span>Plays</span><span class="arrow">↓</span></button></th>
            <th><button class="sortbtn" id="sortLikes" type="button" title="Nach Likes absteigend sortieren"><span>Likes</span><span class="arrow">↓</span></button></th>
          </tr>
          <tr>
            <th style="width:20%;">Künstler</th>
            <!-- NEU: schmale ID-Spalte -->
            <th style="width:8%;">ID</th>
            <th style="width:26%;">Song</th>
            <th style="width:10%;">Play</th>
            <th style="width:12%;">Upload-Datum</th>
            <th style="width:6%;">Neu</th>
            <th style="width:6%;">Angesagt</th>
            <th style="width:6%;">Datum</th>
            <th style="width:4%;">Unsichtbar</th>
            <th style="width:8%;">Plays</th>
            <th style="width:8%;">Likes</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="11" class="muted">Keine Songs vorhanden.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <?php
                $dateDisp = $row['uploaded_at'] ? date('d.m.Y', strtotime($row['uploaded_at'])) : '—';
                $dateVal  = $row['uploaded_at'] ? date('Y-m-d', strtotime($row['uploaded_at'])) : '';
                $isHidden = !empty($row['is_hidden']);
                $playTotal = (int)($row['play_total'] ?? 0);
                $likeTotal = (int)($row['like_total'] ?? 0);

                $fullId  = (string)$row['id'];
                $shortId = (strlen($fullId) > 8) ? substr($fullId, 0, 8) . '…' : $fullId;
              ?>
              <tr
                class="<?= $isHidden ? 'row-hidden' : '' ?>"

                data-id="<?= (int)$row['id'] ?>"
                data-audio="<?= htmlspecialchars($row['audio_path'] ?? '') ?>"
                data-artist="<?= htmlspecialchars($row['artist_name']) ?>"
                data-title="<?= htmlspecialchars($row['song_title']) ?>"
                data-cover="<?= htmlspecialchars($row['cover_path'] ?? '') ?>"
                data-release="<?= htmlspecialchars($row['release_title'] ?? '') ?>"
                data-date="<?= htmlspecialchars($dateVal) ?>"
                data-date-disp="<?= htmlspecialchars($dateDisp) ?>"
                data-hidden="<?= $isHidden ? '1':'0' ?>"

                data-play-total="<?= $playTotal ?>"
                data-like-total="<?= $likeTotal ?>"
              >
                <td><?= htmlspecialchars($row['artist_name']) ?></td>

                <!-- NEU: Song-ID mit Trunkierung + Copy -->
                <td class="idcell">
                  <span class="idtxt" title="<?= htmlspecialchars($fullId) ?>"><?= htmlspecialchars($shortId) ?></span>
                  <button class="copybtn" type="button" data-copy="<?= htmlspecialchars($fullId) ?>">Copy</button>
                </td>

                <td><?= htmlspecialchars($row['song_title']) ?></td>

                <td>
                  <?php if (!empty($row['audio_path'])): ?>
                    <button class="playbtn" type="button">Play</button>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>

                <td><span class="date-text"><?= htmlspecialchars($dateDisp) ?></span></td>
                <td><input class="chk chk-new" type="checkbox" <?= !empty($row['is_new']) ? 'checked' : '' ?>></td>
                <td><input class="chk chk-feat" type="checkbox" <?= !empty($row['is_featured']) ? 'checked' : '' ?>></td>
                <td>
                  <button class="calbtn js-open-date" type="button" title="Upload-Datum ändern" aria-label="Upload-Datum ändern">📅</button>
                </td>
                <td>
                  <input class="chk chk-hide" type="checkbox" <?= $isHidden ? 'checked' : '' ?> title="Song unsichtbar schalten">
                </td>

                <!-- Plays-Zähler + Bearbeiten -->
                <td>
                  <span class="date-text" data-role="plays-total"><?= $playTotal ?></span>
                  <button class="calbtn" type="button" data-role="plays-edit" title="Plays manuell setzen">Bearbeiten</button>
                </td>

                <!-- Likes-Zähler (Anzeige) -->
                <td>
                  <span class="date-text" data-role="likes-total"><?= $likeTotal ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Rechte Spalte: Inline-Abfrage/Editor -->
    <aside class="side">
      <div class="card panel-warning hidden" id="editPanel">
        <h3 style="margin:0 0 8px;">Änderungen</h3>
        <div id="panelMeta" class="muted" style="margin-bottom:8px;">—</div>

        <!-- Schritt 1: Sicherheitsabfrage -->
        <div id="confirmPane">
          <div>Möchtest du das Upload-Datum verändern?</div>
          <div class="rowline" style="margin-top:10px;">
            <button class="btn btn-danger" id="confirmYes" type="button">Ja, Datum ändern</button>
            <button class="btn btn-ghost"  id="confirmNo"  type="button">Nein, abbrechen</button>
          </div>
        </div>

        <!-- Schritt 2: Datumsauswahl + Speichern -->
        <form id="dateForm" class="hidden" style="margin-top:12px;">
          <label for="panelDate" style="margin:0 0 6px;">Neues Datum auswählen</label>
          <div class="rowline">
            <input type="date" id="panelDate" required
                   style="padding:10px 12px; border-radius:10px; border:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.10); color:#fff">
            <button class="btn" type="submit">Datum speichern</button>
            <button class="btn btn-ghost" id="cancelDateEdit" type="button">Abbrechen</button>
          </div>
          <div class="muted" style="margin-top:6px;">Die Uhrzeit bleibt erhalten; es wird nur der Datumsteil geändert.</div>
        </form>
      </div>
    </aside>
  </div>

  <!-- Player unten -->
  <div class="player" id="playerBar">
    <div class="player-inner">
      <img id="plCover" class="cover" alt="">
      <div class="meta">
        <strong id="plTitle">–</strong>
        <small id="plArtist">–</small>
        <small id="plRelease" class="muted">–</small>
      </div>
      <div class="grow"></div>
      <audio id="plAudio" controls preload="none"></audio>
    </div>
  </div>
</main>

<footer>© <?= date('Y') ?> Grab It On</footer>

<script>
(function(){
  const $ = sel => document.querySelector(sel);
  const $$ = sel => Array.from(document.querySelectorAll(sel));

  const table    = $('#tracksTable');
  const tbody    = $('#tracksTable tbody');
  const player   = $('#playerBar');
  const plAudio  = $('#plAudio');
  const plCover  = $('#plCover');
  const plTitle  = $('#plTitle');
  const plArtist = $('#plArtist');
  const plRelease= $('#plRelease');

  // Suche
  const searchInput = $('#searchInput');
  const searchBtn   = $('#searchBtn');

  // Sortier-Buttons (im THEAD)
  const sortArtistBtn = $('#sortArtist');
  const sortSongBtn   = $('#sortSong');
  const sortDateBtn   = $('#sortDate');
  const sortPlaysBtn  = $('#sortPlays');
  const sortLikesBtn  = $('#sortLikes');
  const sortTopNewBtn  = $('#sortTopNew');
  const sortTopFeatBtn = $('#sortTopFeat');

  // Seitenspalte (Datum ändern)
  const panel     = $('#editPanel');
  const meta      = $('#panelMeta');
  const confirmUi = $('#confirmPane');
  const btnYes    = $('#confirmYes');
  const btnNo     = $('#confirmNo');
  const dateForm  = $('#dateForm');
  const panelDate = $('#panelDate');
  const btnCancel = $('#cancelDateEdit');

  let currentRow = null;

  function fmtDateForDisplay(yyyy_mm_dd){ if(!yyyy_mm_dd) return '—'; const [y,m,d]=yyyy_mm_dd.split('-'); return `${d}.${m}.${y}`; }

  /* ===== Play toggle ===== */
  table.addEventListener('click', async (e)=>{
    const btn = e.target.closest('.playbtn');
    if (!btn) return;
    const tr = btn.closest('tr'); if (!tr) return;
    const src = tr.dataset.audio; if (!src) return;

    if (plAudio.src && (plAudio.src.endsWith(src.replace(/^\/+/,'')) || plAudio.src === location.origin + src) && tr === e.currentTarget._currentRow) {
      if (plAudio.paused) { plAudio.play().catch(()=>{}); btn.textContent='Pause'; }
      else { plAudio.pause(); btn.textContent='Play'; }
      return;
    }

    e.currentTarget._currentRow = tr;
    $$('.playbtn').forEach(b=>b.textContent='Play');

    plAudio.src = src;
    plTitle.textContent  = tr.dataset.title || '–';
    plArtist.textContent = tr.dataset.artist || '–';
    plRelease.textContent= tr.dataset.release ? 'Release: ' + tr.dataset.release : '—';
    if (tr.dataset.cover) { plCover.src = tr.dataset.cover; plCover.style.display=''; }
    else { plCover.removeAttribute('src'); plCover.style.display='none'; }

    player.style.display = 'block';
    try { await plAudio.play(); btn.textContent='Pause'; }
    catch { btn.textContent='Play'; }
  });

  /* ===== Checkboxen (Neu/Angesagt/Unsichtbar) ===== */
  async function toggleFlag(tr, field, value){
    const res = await fetch(location.pathname + '?action=toggle', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ id: parseInt(tr.dataset.id), field, value: value ? 1 : 0 })
    });
    const data = await res.json(); if(!data.ok) throw new Error(data.msg||'Unbekannter Fehler');
  }

  table.addEventListener('change', async (e)=>{
    const chk = e.target; const tr=chk.closest('tr'); if(!tr) return;
    try{
      if (chk.classList.contains('chk-new'))  await toggleFlag(tr,'is_new',chk.checked);
      if (chk.classList.contains('chk-feat')) await toggleFlag(tr,'is_featured',chk.checked);
      if (chk.classList.contains('chk-hide')) {
        await toggleFlag(tr,'is_hidden',chk.checked);
        tr.dataset.hidden = chk.checked ? '1' : '0';
        tr.classList.toggle('row-hidden', chk.checked);
      }
    }catch(err){
      alert('Konnte nicht speichern: ' + err.message);
      chk.checked = !chk.checked;
    }
  });

  /* ===== Datum ändern (Panel) ===== */
  table.addEventListener('click', (e)=>{
    const btn = e.target.closest('.js-open-date');
    if (!btn) return;
    e.preventDefault(); e.stopPropagation();

    const tr = btn.closest('tr'); if(!tr) return;
    currentRow = tr;

    meta.textContent = `${tr.dataset.artist} – ${tr.dataset.title}`;
    panelDate.value = tr.dataset.date || '';

    confirmUi.classList.remove('hidden');
    dateForm.classList.add('hidden');
    panel.classList.remove('hidden');
  });

  btnYes.addEventListener('click', ()=>{
    confirmUi.classList.add('hidden');
    dateForm.classList.remove('hidden');
    panelDate.focus({preventScroll:true});
  });
  btnNo.addEventListener('click',  ()=>{ panel.classList.add('hidden'); });
  btnCancel.addEventListener('click', ()=>{ panel.classList.add('hidden'); });

  dateForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    if (!currentRow) return;
    const val = panelDate.value;
    if (!/^\d{4}-\d{2}-\d{2}$/.test(val)) { alert('Bitte ein gültiges Datum wählen (YYYY-MM-DD).'); return; }
    try{
      const res = await fetch(location.pathname + '?action=update_date', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id: parseInt(currentRow.dataset.id), date: val })
      });
      const data = await res.json(); if(!data.ok) throw new Error(data.msg||'Unbekannter Fehler');

      currentRow.dataset.date = val;
      const disp = currentRow.querySelector('.date-text');
      if (disp) disp.textContent = fmtDateForDisplay(val);
      panel.classList.add('hidden');
    }catch(err){
      alert('Konnte Datum nicht speichern: ' + err.message);
    }
  });

  /* ===== Suche ===== */
  function clearHits(){ $$('#tracksTable tbody tr').forEach(tr => tr.classList.remove('hit')); }
  const monthAliases = {
    'januar':'01','jan':'01','janu':'01','januer':'01','janar':'01',
    'februar':'02','feb':'02',
    'maerz':'03','marz':'03','mrz':'03','märz':'03','mae':'03',
    'april':'04','apr':'04','mai':'05','juni':'06','jun':'06','juli':'07','jul':'07',
    'august':'08','aug':'08','september':'09','sept':'09','sep':'09',
    'oktober':'10','okt':'10','oct':'10','november':'11','nov':'11','dezember':'12','dez':'12','dec':'12'
  };

  function runSearch(){
    const qRaw = searchInput.value || '';
    clearHits();
    if (!qRaw.trim()) return;

    const q = (qRaw || '').toLowerCase().trim()
      .replace(/\s+/g,' ')
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
      .replace(/ä/g,'ae').replace(/ö/g,'oe').replace(/ü/g,'ue').replace(/ß/g,'ss');

    const fullDate = /^(\d{1,2})\.(\d{1,2})\.(\d{4})$/;
    const dayMonth = /^(\d{1,2})\.(\d{1,2})\.?$/;
    const yearOnly = /^(\d{4})$/;

    let mode = 'text', dm=null, monthNum=null;
    if (fullDate.test(q))      { mode='fullDate'; dm=q.match(fullDate); }
    else if (dayMonth.test(q)) { mode='dayMonth'; dm=q.match(dayMonth); }
    else if (yearOnly.test(q)) { mode='year';     dm=q.match(yearOnly); }
    else if (monthAliases[q])  { mode='month';    monthNum = monthAliases[q]; }
    else {
      const first = q.split(' ')[0];
      if (monthAliases[first]) { mode='month'; monthNum = monthAliases[first]; }
    }

    const rows = $$('#tracksTable tbody tr');
    let first = null;

    rows.forEach(tr=>{
      const artist = (tr.dataset.artist || '').toLowerCase();
      const title  = (tr.dataset.title  || '').toLowerCase();
      const dateYmd= (tr.dataset.date || '');
      const dateDisp = (tr.dataset.dateDisp || '');

      let hit = false;
      if (mode === 'text') {
        hit = artist.includes(q) || title.includes(q);
      } else if (mode === 'fullDate') {
        const [ , dd, mm, yyyy ] = dm;
        const dd2 = dd.padStart(2,'0'), mm2 = mm.padStart(2,'0');
        hit = dateDisp === `${dd2}.${mm2}.${yyyy}`;
      } else if (mode === 'dayMonth') {
        const [ , dd, mm ] = dm;
        const d = dd.padStart(2,'0') + '.' + mm.padStart(2,'0') + '.';
        hit = dateDisp.startsWith(d);
      } else if (mode === 'year') {
        const yyyy = dm[1];
        hit = dateDisp.endsWith('.'+yyyy) || dateYmd.startsWith(yyyy+'-');
      } else if (mode === 'month') {
        const mm = (dateYmd.split('-')[1] || '').padStart(2,'0');
        hit = (mm === monthNum);
      }

      if (hit) {
        tr.classList.add('hit');
        if (!first) first = tr;
      }
    });

    if (first) first.scrollIntoView({behavior:'smooth', block:'center'});
  }

  searchBtn.addEventListener('click', runSearch);
  searchInput.addEventListener('keydown', (e)=>{
    if (e.key === 'Enter') { e.preventDefault(); runSearch(); }
  });

  /* ===== Sortierungen ===== */
  sortArtistBtn.addEventListener('click', ()=>{
    const rows = $$('#tracksTable tbody tr');
    rows.sort((a,b)=>{
      const aArtist = (a.dataset.artist||'').toLowerCase();
      const bArtist = (b.dataset.artist||'').toLowerCase();
      if (aArtist < bArtist) return -1;
      if (aArtist > bArtist) return 1;
      const aTitle = (a.dataset.title||'').toLowerCase();
      const bTitle = (b.dataset.title||'').toLowerCase();
      if (aTitle < bTitle) return -1;
      if (aTitle > bTitle) return 1;
      return (parseInt(a.dataset.id)||0) - (parseInt(b.dataset.id)||0);
    });
    rows.forEach(r => tbody.appendChild(r));
  });

  sortSongBtn.addEventListener('click', ()=>{
    const rows = $$('#tracksTable tbody tr');
    rows.sort((a,b)=>{
      const aTitleRaw = (a.dataset.title || '').trim();
      const bTitleRaw = (b.dataset.title || '').trim();
      const aAlpha = /^[a-z0-9]/i.test(aTitleRaw);
      const bAlpha = /^[a-z0-9]/i.test(bTitleRaw);
      if (aAlpha !== bAlpha) return aAlpha ? -1 : 1;
      const aTitle = aTitleRaw.toLowerCase();
      const bTitle = bTitleRaw.toLowerCase();
      if (aTitle < bTitle) return -1;
      if (aTitle > bTitle) return 1;
      return (parseInt(a.dataset.id)||0) - (parseInt(b.dataset.id)||0);
    });
    rows.forEach(r => tbody.appendChild(r));
  });

  sortDateBtn.addEventListener('click', ()=>{
    const rows = $$('#tracksTable tbody tr');
    rows.sort((a,b)=>{
      const ad = a.dataset.date || '';
      const bd = b.dataset.date || '';
      if (ad && bd) {
        if (ad > bd) return -1;
        if (ad < bd) return 1;
        return (parseInt(a.dataset.id)||0) - (parseInt(b.dataset.id)||0);
      }
      if (!ad && !bd) return 0;
      return ad ? -1 : 1;
    });
    rows.forEach(r => tbody.appendChild(r));
  });

  // Plays absteigend
  sortPlaysBtn.addEventListener('click', ()=>{
    const rows = $$('#tracksTable tbody tr');
    rows.sort((a,b)=>{
      const ap = parseInt(a.dataset.playTotal || (a.querySelector('[data-role="plays-total"]')?.textContent || '0'), 10) || 0;
      const bp = parseInt(b.dataset.playTotal || (b.querySelector('[data-role="plays-total"]')?.textContent || '0'), 10) || 0;
      if (bp !== ap) return bp - ap;
      const al = parseInt(a.dataset.likeTotal || '0', 10) || 0;
      const bl = parseInt(b.dataset.likeTotal || '0', 10) || 0;
      if (bl !== al) return bl - al;
      const aArtist = (a.dataset.artist||'').toLowerCase();
      const bArtist = (b.dataset.artist||'').toLowerCase();
      if (aArtist < bArtist) return -1;
      if (aArtist > bArtist) return 1;
      const aTitle = (a.dataset.title||'').toLowerCase();
      const bTitle = (b.dataset.title||'').toLowerCase();
      if (aTitle < bTitle) return -1;
      if (aTitle > bTitle) return 1;
      return (parseInt(a.dataset.id)||0) - (parseInt(b.dataset.id)||0);
    });
    rows.forEach(r => tbody.appendChild(r));
  });

  // Likes absteigend
  sortLikesBtn.addEventListener('click', ()=>{
    const rows = $$('#tracksTable tbody tr');
    rows.sort((a,b)=>{
      const al = parseInt(a.dataset.likeTotal || (a.querySelector('[data-role="likes-total"]')?.textContent || '0'), 10) || 0;
      const bl = parseInt(b.dataset.likeTotal || (b.querySelector('[data-role="likes-total"]')?.textContent || '0'), 10) || 0;
      if (bl !== al) return bl - al;
      const ap = parseInt(a.dataset.playTotal || '0', 10) || 0;
      const bp = parseInt(b.dataset.playTotal || '0', 10) || 0;
      if (bp !== ap) return bp - ap;
      const aArtist = (a.dataset.artist||'').toLowerCase();
      const bArtist = (b.dataset.artist||'').toLowerCase();
      if (aArtist < bArtist) return -1;
      if (aArtist > bArtist) return 1;
      const aTitle = (a.dataset.title||'').toLowerCase();
      const bTitle = (b.dataset.title||'').toLowerCase();
      if (aTitle < bTitle) return -1;
      if (aTitle > bTitle) return 1;
      return (parseInt(a.dataset.id)||0) - (parseInt(b.dataset.id)||0);
    });
    rows.forEach(r => tbody.appendChild(r));
  });

  /* ===== NEU: Copy-ID in Zwischenablage ===== */
  table.addEventListener('click', async (e)=>{
    const btn = e.target.closest('.copybtn');
    if (!btn) return;
    const val = btn.getAttribute('data-copy') || '';
    try{
      await navigator.clipboard.writeText(val);
      const old = btn.textContent;
      btn.textContent = 'Kopiert';
      setTimeout(()=>{ btn.textContent = old; }, 1200);
    }catch(err){
      // Fallback
      const ta = document.createElement('textarea');
      ta.value = val; document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); } catch(_) {}
      document.body.removeChild(ta);
      const old = btn.textContent;
      btn.textContent = 'Kopiert';
      setTimeout(()=>{ btn.textContent = old; }, 1200);
    }
  });

  /* ===== NEU: „Top“-Buttons – angehakte nach oben ===== */
  function moveCheckedToTop(checkboxSelector){
    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (!rows.length) return;

    const withCheck = [];
    const withoutCheck = [];

    // stabil: relative Reihenfolge beibehalten (Stable Partition)
    for (const tr of rows) {
      const chk = tr.querySelector(checkboxSelector);
      if (chk && chk.checked) withCheck.push(tr); else withoutCheck.push(tr);
    }

    // visueller Mini-Flash für die oberen Rows
    withCheck.forEach(tr => tr.classList.add('flash'));

    // neu anordnen
    [...withCheck, ...withoutCheck].forEach(tr => tbody.appendChild(tr));

    // Flash nach kurzer Zeit entfernen
    setTimeout(()=> withCheck.forEach(tr => tr.classList.remove('flash')), 900);
  }

  if (sortTopNewBtn) {
    sortTopNewBtn.addEventListener('click', ()=> moveCheckedToTop('.chk-new'));
  }
  if (sortTopFeatBtn) {
    sortTopFeatBtn.addEventListener('click', ()=> moveCheckedToTop('.chk-feat'));
  }

  /* ===== Plays bearbeiten (Gesamtsumme setzen) ===== */
  table.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-role="plays-edit"]');
    if (!btn) return;
    const tr = btn.closest('tr'); if (!tr) return;
    const songId = parseInt(tr.dataset.id || '0'); if (!songId) return;

    const totalEl = tr.querySelector('[data-role="plays-total"]');
    const current = totalEl ? parseInt(totalEl.textContent || tr.dataset.playTotal || '0') : 0;

    const input = prompt('Neue gewünschte Gesamtsumme der Plays:', String(current));
    if (input === null) return;
    const desired = parseInt(input);
    if (!Number.isFinite(desired) || desired < 0) { alert('Bitte eine nicht-negative Zahl eingeben.'); return; }

    try {
      const fd = new FormData();
      fd.append('song_id', String(songId));
      fd.append('desired_total', String(desired));
      const res  = await fetch('/grabiton/api/plays_set_base.php', { method:'POST', credentials:'same-origin', body: fd });
      const data = await res.json();
      if (!data || !data.ok) throw new Error('Serverfehler');
      if (totalEl) totalEl.textContent = String(data.total);
      tr.dataset.playTotal = String(data.total);
    } catch (err) {
      alert('Konnte Plays nicht speichern.');
    }
  });

})();
</script>
</body>
</html>
