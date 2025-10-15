<?php
// /grabiton/admin/song_edit.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';
ini_set('display_errors', 1); error_reporting(E_ALL);

$id        = (int)($_GET['id'] ?? 0);
$ctxArtist = (int)($_GET['artist'] ?? 0);
$from      = $_GET['from'] ?? '';
$from      = in_array($from, ['artist','list','overview'], true) ? $from : '';
$relId     = (int)($_GET['release'] ?? 0);

$errors = []; $messages = [];

/* Song + Release + Artist laden */
$st = $pdo->prepare("
  SELECT s.*, a.artist_name, r.title AS release_title, r.id AS rel_id
  FROM songs s
  JOIN artists a ON a.id = s.artist_id
  LEFT JOIN releases r ON r.id = s.release_id
  WHERE s.id = ?
");
$st->execute([$id]);
$song = $st->fetch();

if (!$song) { exit('Song nicht gefunden.'); }
if ($ctxArtist <= 0) $ctxArtist = (int)$song['artist_id'];
if ($relId <= 0)     $relId     = (int)$song['release_id'];

/* -------- AJAX: Sichtbarkeit (is_hidden) toggeln -------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_visibility') {
  header('Content-Type: application/json; charset=utf-8');
  try {
    $pdo->prepare("UPDATE songs SET is_hidden = IFNULL(1 - IFNULL(is_hidden,0), 1) WHERE id = ?")->execute([$id]);
    $stmt = $pdo->prepare("SELECT IFNULL(is_hidden,0) FROM songs WHERE id = ?");
    $stmt->execute([$id]);
    $newFlag = (int)$stmt->fetchColumn();
    echo json_encode(['ok'=>true, 'is_hidden'=>$newFlag]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()]);
  }
  exit;
}

/* -------- Hilfsfunktion: Datei speichern -------- */
function save_file_local(string $field, array $exts, array $mimes, int $max): ?string {
  if (empty($_FILES[$field]['name']) || $_FILES[$field]['error']===UPLOAD_ERR_NO_FILE) return null;
  $f=$_FILES[$field]; if($f['error']!==UPLOAD_ERR_OK) throw new RuntimeException("Upload-Fehler ($field)");
  if($f['size']>$max) throw new RuntimeException("Datei zu groß ($field).");
  $tmp=$f['tmp_name']; $ext=strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if(!in_array($ext,$exts,true)) throw new RuntimeException("Ungültige Endung ($field).");
  $mime=@mime_content_type($tmp); if(!$mime || !in_array($mime,$mimes,true)) throw new RuntimeException("Ungültiger Typ ($field).");
  $name=bin2hex(random_bytes(8)).'_'.time().'.'.$ext;
  $dir=dirname(__DIR__)."/uploads/audio"; if(!is_dir($dir)) mkdir($dir,0755,true);
  $dest="$dir/$name"; if(!move_uploaded_file($tmp,$dest)) throw new RuntimeException("Speichern fehlgeschlagen ($field).");
  return "/grabiton/uploads/audio/$name";
}

/* -------- POST: Titel/Audio speichern -------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_song') {
  $title = trim($_POST['title'] ?? '');
  if ($title === '') { $errors[] = 'Bitte einen Titel eingeben.'; }
  try {
    // Audio ersetzen?
    $newAudio = save_file_local('audio', ['mp3'], ['audio/mpeg'], 25*1024*1024);
    if ($newAudio) {
      if (!empty($song['audio_path'])) @unlink($_SERVER['DOCUMENT_ROOT'].$song['audio_path']);
      $song['audio_path'] = $newAudio;
    }

    $pdo->prepare("UPDATE songs SET title = ?, audio_path = ? WHERE id = ?")
        ->execute([$title, $song['audio_path'], $song['id']]);

    $messages[] = 'Song gespeichert.';
  } catch (Throwable $e) {
    $errors[] = $e->getMessage();
  }
}

/* -------- POST: Upload-Datum (nur Datumsteil) ändern -------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_date') {
  $newDate = trim($_POST['uploaded_date'] ?? '');
  try {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
      throw new RuntimeException('Ungültiges Datum (Format YYYY-MM-DD).');
    }
    $upd = $pdo->prepare("UPDATE songs SET uploaded_at = CONCAT(?, ' ', TIME(COALESCE(uploaded_at, '00:00:00'))) WHERE id = ?");
    $upd->execute([$newDate, $id]);

    // Für Anzeige neu laden
    $st->execute([$id]); $song = $st->fetch();
    $messages[] = 'Upload-Datum aktualisiert.';
  } catch (Throwable $e) {
    $errors[] = $e->getMessage();
  }
}

/* Zurück-Link */
$back = '/grabiton/admin/release_edit.php?' . http_build_query(array_filter([
  'id'     => $relId ?: null,
  'artist' => $ctxArtist ?: null,
  'from'   => $from ?: null,
]));
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Song bearbeiten – Grab It On</title>
<style>
  :root { color-scheme: dark; }
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#151102;color:#fff;margin:0}
  header,footer{padding:14px 18px;background:rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.12);backdrop-filter:blur(6px)}
  main{width:min(900px,96vw);margin:22px auto;padding:0 20px}
  .btn{display:inline-block;background:#2b5dff;color:#fff;border-radius:10px;padding:10px 16px;text-decoration:none;font-weight:600;border:0;cursor:pointer}
  .btn:hover{filter:brightness(1.05)}
  .btn-ghost{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18)}
  .btn-danger{background:#8f2a2f; color:#fff}
  .card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:16px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
  h1{margin:0 0 10px}
  label{display:block;margin:10px 0 6px;opacity:.9}
  .input,.file{width:100%;padding:12px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.08);color:#fff;outline:none;box-sizing:border-box}
  .row{display:grid;grid-template-columns:1fr;gap:16px}
  .msg{margin:10px 0;padding:10px 12px;border-radius:10px;background:rgba(255,0,0,.25);border:1px solid rgba(255,255,255,.4)}
  .ok{background:rgba(43,93,255,.2);border:1px solid rgba(43,93,255,.45)}
  .muted{opacity:.85}
  .inline{display:flex; gap:10px; align-items:center; flex-wrap:wrap}
  .date-display{padding:8px 12px; border-radius:10px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12)}
  .date-input{padding:10px 12px; border-radius:10px; border:1px solid rgba(255,255,255,.18); background:rgba(255,255,255,.10); color:#fff}
  .confirm-pane{margin-top:14px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.18); border-radius:12px; padding:14px}
  .confirm-actions{display:flex; gap:10px; margin-top:10px; flex-wrap:wrap}
  .hidden{display:none}
  /* Hinweis, wenn Song unsichtbar ist */
  .alert-hidden{
    background: rgba(255, 0, 0, .10);
    border: 1px solid rgba(255, 80, 80, .45);
    color: #ff9a9a;
    border-radius: 14px;
    padding: 12px 14px;
    margin-bottom: 12px;
  }
</style>
</head>
<body>
<header>
  <a class="btn" href="<?= htmlspecialchars($back) ?>">← Zurück</a>
</header>
<main>
  <h1>Song bearbeiten</h1>

  <?php foreach($messages as $m): ?><div class="msg ok"><?= htmlspecialchars($m) ?></div><?php endforeach; ?>
  <?php foreach($errors as $e): ?><div class="msg"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

  <!-- Roter Hinweis, wenn der Song unsichtbar ist -->
  <div id="hiddenNotice" class="alert-hidden" style="<?= !empty($song['is_hidden']) ? '' : 'display:none;' ?>">
    Dieser Song ist <strong>nicht öffentlich</strong>. Er erscheint nicht auf der Startseite (z. B. „Angesagt“, „Neueste“)
    und auf der öffentlichen Künstlerseite <em>ohne</em> Play-Funktion.
  </div>

  <!-- Kleine Aktionskarte: Sichtbarkeit umschalten -->
  <div class="card" style="margin-bottom:16px;">
    <div class="inline">
      <div><strong><?= htmlspecialchars($song['artist_name']) ?></strong> – „<?= htmlspecialchars($song['title']) ?>”</div>
      <div class="muted">Upload: <?= $song['uploaded_at'] ? htmlspecialchars(date('d.m.Y', strtotime($song['uploaded_at']))) : '—' ?></div>
      <div style="flex:1"></div>
      <form id="toggleForm" method="post">
        <input type="hidden" name="action" value="toggle_visibility">
        <button id="toggleVisibility" class="btn btn-ghost" type="submit">
          <?= !empty($song['is_hidden']) ? 'Sichtbar machen' : 'Unsichtbar schalten' ?>
        </button>
      </form>
    </div>
  </div>

  <div class="card">
    <form method="post" enctype="multipart/form-data" autocomplete="off" id="songForm">
      <input type="hidden" name="action" value="save_song">

      <label>Künstler</label>
      <div class="input muted" style="user-select:none;"><?= htmlspecialchars($song['artist_name']) ?></div>

      <label>Release</label>
      <div class="input muted" style="user-select:none;"><?= htmlspecialchars($song['release_title'] ?: '—') ?></div>

      <label>Titel *</label>
      <input class="input" type="text" name="title" value="<?= htmlspecialchars($song['title']) ?>" required>

      <label>Audio ersetzen (MP3, max. 25 MB)</label>
      <input class="file" type="file" name="audio" accept=".mp3,audio/mpeg">
      <?php if (!empty($song['audio_path'])): ?>
        <div class="inline" style="margin-top:8px;">
          <audio controls src="<?= htmlspecialchars($song['audio_path']) ?>" style="height:28px; width:320px"></audio>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <!-- Upload-Datum block separat (unterhalb des „Song bearbeiten“-Kastens) -->
  <div class="card" style="margin-top:16px;">
    <h2 style="margin:0 0 10px;">Upload-Datum</h2>
    <div class="inline">
      <span class="muted">Aktuell:</span>
      <span class="date-display" id="currentDate">
        <?= $song['uploaded_at'] ? htmlspecialchars(date('d.m.Y', strtotime($song['uploaded_at']))) : '—' ?>
      </span>
      <button class="btn btn-ghost" id="btnChangeDate" type="button">Upload-Datum ändern</button>
    </div>

    <!-- 1) Sicherheitsabfrage (inline, kein Popup) -->
    <div id="confirmPane" class="confirm-pane hidden">
      <div>Möchtest du das Upload-Datum verändern?</div>
      <div class="confirm-actions">
        <button class="btn btn-danger" id="confirmYes" type="button">Ja, Datum ändern</button>
        <button class="btn btn-ghost"  id="confirmNo"  type="button">Nein, abbrechen</button>
      </div>
    </div>

    <!-- 2) Datumsauswahl + Speichern (zeigt sich erst nach „Ja“) -->
    <form method="post" id="dateForm" class="hidden" style="margin-top:12px;">
      <input type="hidden" name="action" value="update_date">
      <label for="uploaded_date" style="margin:0 0 6px;">Neues Datum auswählen</label>
      <div class="inline">
        <input class="date-input" type="date" name="uploaded_date" id="uploaded_date"
               value="<?= $song['uploaded_at'] ? htmlspecialchars(date('Y-m-d', strtotime($song['uploaded_at']))) : '' ?>"
               required>
        <button class="btn" type="submit">Datum speichern</button>
        <button class="btn btn-ghost" id="cancelDateEdit" type="button">Abbrechen</button>
      </div>
      <div class="muted" style="margin-top:6px;">Die Uhrzeit bleibt erhalten; es wird nur der Datumsteil geändert.</div>
    </form>
  </div>

  <!-- Aktionen (Speichern/Abbrechen) unten, beziehen sich auf Song-Titel/Audio -->
  <div class="card" style="margin-top:16px;">
    <div class="inline">
      <button class="btn" type="submit" form="songForm">Song speichern</button>
      <a class="btn btn-ghost" href="<?= htmlspecialchars($back) ?>">Abbrechen</a>
    </div>
  </div>

</main>
<footer>© <?= date('Y') ?> Grab It On</footer>

<script>
(function(){
  // Upload-Datum – Flow wie gehabt
  const btnChange   = document.getElementById('btnChangeDate');
  const pane        = document.getElementById('confirmPane');
  const yes         = document.getElementById('confirmYes');
  const no          = document.getElementById('confirmNo');
  const dateForm    = document.getElementById('dateForm');
  const cancelDate  = document.getElementById('cancelDateEdit');

  btnChange.addEventListener('click', ()=>{
    pane.classList.remove('hidden');
    dateForm.classList.add('hidden');
  });
  yes.addEventListener('click', ()=>{
    pane.classList.add('hidden');
    dateForm.classList.remove('hidden');
  });
  no.addEventListener('click', ()=>{
    pane.classList.add('hidden');
    dateForm.classList.add('hidden');
  });
  cancelDate.addEventListener('click', ()=>{
    dateForm.classList.add('hidden');
  });

  // Sichtbarkeit toggeln (Unsichtbar / Sichtbar)
  const toggleForm = document.getElementById('toggleForm');
  const toggleBtn  = document.getElementById('toggleVisibility');
  const notice     = document.getElementById('hiddenNotice');

  toggleForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    try{
      const formData = new FormData(toggleForm);
      const res = await fetch(location.pathname + location.search, {
        method: 'POST',
        body: formData
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.msg || 'Fehler');

      const hidden = !!data.is_hidden;
      // Hinweis zeigen/verstecken
      notice.style.display = hidden ? '' : 'none';
      // Button-Text anpassen
      toggleBtn.textContent = hidden ? 'Sichtbar machen' : 'Unsichtbar schalten';
    }catch(err){
      alert('Konnte Sichtbarkeit nicht ändern: ' + err.message);
    }
  });
})();
</script>
</body>
</html>
