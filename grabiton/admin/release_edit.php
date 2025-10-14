<?php
// /grabiton/admin/release_edit.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';
ini_set('display_errors', 1); error_reporting(E_ALL);

$id        = (int)($_GET['id'] ?? 0);
$ctxArtist = (int)($_GET['artist'] ?? 0);
$from      = $_GET['from'] ?? '';
$from      = in_array($from, ['artist','list','overview'], true) ? $from : '';

$stmt = $pdo->prepare("SELECT r.*, a.artist_name FROM releases r JOIN artists a ON a.id = r.artist_id WHERE r.id = ?");
$stmt->execute([$id]);
$release = $stmt->fetch();
if (!$release) { exit('Release nicht gefunden.'); }
if ($ctxArtist <= 0) $ctxArtist = (int)$release['artist_id'];

$errors = [];

/* ---------- AJAX: Reihenfolge speichern ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'reorder') {
  header('Content-Type: application/json; charset=utf-8');
  $data = json_decode(file_get_contents('php://input'), true);
  if (!is_array($data['order'] ?? null)) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Ungültige Daten']); exit; }
  $order = array_values(array_filter(array_map('intval', $data['order'])));
  if (!$order) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Leere Reihenfolge']); exit; }
  try{
    $pdo->beginTransaction();
    $in = implode(',', array_fill(0, count($order), '?'));
    $check = $pdo->prepare("SELECT id FROM songs WHERE release_id = ? AND id IN ($in)");
    $check->execute(array_merge([$release['id']], $order));
    $valid = array_map('intval', $check->fetchAll(PDO::FETCH_COLUMN, 0));
    $clean = array_values(array_intersect($order, $valid));
    $pos=1; $upd=$pdo->prepare("UPDATE songs SET track_number=? WHERE id=? AND release_id=?");
    foreach($clean as $sid){ $upd->execute([$pos++, $sid, $release['id']]); }
    $pdo->commit(); echo json_encode(['ok'=>true]);
  }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
  exit;
}

/* ---------- Upload-Helper ---------- */
function save_file(string $field, string $subdir, array $exts, array $mimes, int $max, bool $img=false): ?string {
  if (empty($_FILES[$field]['name']) || $_FILES[$field]['error']===UPLOAD_ERR_NO_FILE) return null;
  $f=$_FILES[$field]; if($f['error']!==UPLOAD_ERR_OK) throw new RuntimeException("Upload-Fehler ($field)");
  if($f['size']>$max) throw new RuntimeException("Datei zu groß ($field).");
  $tmp=$f['tmp_name']; $ext=strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if(!in_array($ext,$exts,true)) throw new RuntimeException("Ungültige Endung ($field).");
  $mime=mime_content_type($tmp); if(!in_array($mime,$mimes,true)) throw new RuntimeException("Ungültiger Typ ($field).");
  if($img && @getimagesize($tmp)===false) throw new RuntimeException("Kein gültiges Bild ($field).");
  $name=bin2hex(random_bytes(8)).'_'.time().'.'.$ext;
  $dir=dirname(__DIR__)."/uploads/$subdir"; if(!is_dir($dir)) mkdir($dir,0755,true);
  $dest="$dir/$name"; if(!move_uploaded_file($tmp,$dest)) throw new RuntimeException("Speichern fehlgeschlagen ($field).");
  return "/grabiton/uploads/$subdir/$name";
}

/* ---------- Release speichern ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_release') {
  $title=trim($_POST['title']??''); $type=$_POST['release_type']??'single'; $date=trim($_POST['release_date']??''); $delCover=!empty($_POST['delete_cover']);
  if ($title==='' || !in_array($type,['single','ep','album'],true)) { $errors[]="Bitte Titel und Typ korrekt angeben."; }
  else {
    try{
      $cover = $release['cover_path'];
      if ($delCover && $cover) { @unlink($_SERVER['DOCUMENT_ROOT'].$cover); $cover=null; }
      $newCover = save_file('cover','covers',['jpg','jpeg','png','webp'],['image/jpeg','image/png','image/webp'],8*1024*1024,true);
      if ($newCover) { if($cover) @unlink($_SERVER['DOCUMENT_ROOT'].$cover); $cover=$newCover; }
      $pdo->prepare("UPDATE releases SET title=?, release_type=?, cover_path=?, release_date=? WHERE id=?")
          ->execute([$title,$type,$cover,$date!==''?$date:null,$release['id']]);

      $qs = http_build_query(['artist'=>$ctxArtist,'from'=>$from]);
      header('Location: /grabiton/admin/release_edit.php?id='.$release['id'].($qs?'&'.$qs:'')); exit;
    }catch(Throwable $e){ $errors[]=$e->getMessage(); }
  }
}

/* ---------- Track hinzufügen (hier setzen wir uploaded_at) ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_track') {
  $trackTitle=trim($_POST['track_title']??'');
  if ($trackTitle===''){ $errors[]="Bitte einen Track-Titel angeben."; }
  else {
    try{
      $audio=save_file('audio','audio',['mp3'],['audio/mpeg'],25*1024*1024,false); if(!$audio) throw new RuntimeException("Bitte eine MP3 hochladen.");
      $next=$pdo->prepare("SELECT COALESCE(MAX(track_number),0)+1 FROM songs WHERE release_id=?"); $next->execute([$release['id']]); $pos=(int)$next->fetchColumn();

      // WICHTIG: uploaded_at wird sofort auf NOW() gesetzt
      $stmt = $pdo->prepare("
        INSERT INTO songs (title, artist_id, release_id, track_number, audio_path, is_featured, is_new, uploaded_at)
        VALUES (?, ?, ?, ?, ?, 0, 0, NOW())
      ");
      $stmt->execute([$trackTitle, $release['artist_id'], $release['id'], $pos, $audio]);

      $qs = http_build_query(['artist'=>$ctxArtist,'from'=>$from]);
      header('Location: /grabiton/admin/release_edit.php?id='.$release['id'].($qs?'&'.$qs:'')); exit;
    }catch(Throwable $e){ $errors[]=$e->getMessage(); }
  }
}

/* ---------- Track löschen ---------- */
if (isset($_GET['delete_track'])) {
  $tid=(int)$_GET['delete_track'];
  $s=$pdo->prepare("SELECT audio_path FROM songs WHERE id=? AND release_id=?"); $s->execute([$tid,$release['id']]);
  if($row=$s->fetch()){ if(!empty($row['audio_path'])) @unlink($_SERVER['DOCUMENT_ROOT'].$row['audio_path']); $pdo->prepare("DELETE FROM songs WHERE id=?")->execute([$tid]); }
  $qs = http_build_query(['artist'=>$ctxArtist,'from'=>$from]);
  header('Location: /grabiton/admin/release_edit.php?id='.$release['id'].($qs?'&'.$qs:'')); exit;
}

/* ---------- Tracks laden ---------- */
$tq=$pdo->prepare("SELECT id,title,track_number,audio_path FROM songs WHERE release_id=? ORDER BY CASE WHEN track_number IS NULL THEN 1 ELSE 0 END, track_number ASC, id ASC");
$tq->execute([$release['id']]); $tracks=$tq->fetchAll();

/* Back-Link */
$back = ($from === 'overview')
  ? '/grabiton/admin/releases_overview.php'
  : '/grabiton/admin/releases.php?'.http_build_query(array_filter([
      'artist'=>$ctxArtist ?: null,
      'from'  =>$from ?: null,
    ]));
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Release bearbeiten – Grab It On</title>
<style>
  :root { color-scheme: dark; }
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#151102;color:#fff;margin:0}
  header,footer{padding:14px 18px;background:rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.12);backdrop-filter:blur(6px)}
  main{width:min(1100px,96vw);margin:22px auto;padding:0 20px}
  .btn{display:inline-block;background:#2b5dff;color:#fff;border-radius:10px;padding:10px 16px;text-decoration:none;font-weight:600;border:0;cursor:pointer}
  .btn.sm{padding:6px 10px;border-radius:8px;font-size:13px}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .card{position:relative;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:16px;box-shadow:0 10px 30px rgba(0,0,0,.25);overflow:hidden}
  h3{margin:0 0 10px 0}
  label{display:block;margin:10px 0 6px;opacity:.9}
  .input,.select,.file{width:100%;padding:12px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.08);color:#fff;outline:none;box-sizing:border-box;position:relative;z-index:1}
  select.select{appearance:none;-webkit-appearance:none;-moz-appearance:none}
  select.select,select.select option{background:#151102;color:#fff}
  img.cover{max-width:220px;border-radius:12px;display:block;position:relative;z-index:1}
  table{width:100%;border-collapse:collapse;margin-top:10px;font-size:13px;line-height:1.25}
  col.col-pos{width:46px}
  col.col-title{width:22%}
  col.col-audio{width:46%}
  col.col-actions{width:auto}
  th,td{padding:7px 10px;border-bottom:1px solid rgba(255,255,255,.12);vertical-align:middle}
  th{text-align:left;font-weight:600}
  td.actions{text-align:right;white-space:nowrap}
  audio{width:100%;max-width:100%;display:block}
  audio::-webkit-media-controls-panel{height:28px}
  audio{height:28px}
  .drag-row{cursor:grab; user-select:none; transition:background .12s ease, box-shadow .12s ease, transform .06s ease}
  .drag-row.dragging{opacity:1 !important; cursor:grabbing; background:rgba(255,255,255,.12); box-shadow:0 6px 16px rgba(0,0,0,.35); transform:scale(1.005)}
  .pos{width:42px;text-align:center;opacity:.85}
  .msg{margin:10px 0;padding:10px 12px;border-radius:10px;background:rgba(255,0,0,.25);border:1px solid rgba(255,0,0,.4)}
  .muted{opacity:.8}
</style>
</head>
<body>
<header>
  <a class="btn" href="<?= htmlspecialchars($back) ?>">← Zurück</a>
</header>
<main>
  <h1>Release bearbeiten</h1>
  <?php foreach($errors as $e): ?><div class="msg"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

  <div class="row">
    <div class="card">
      <h3>Details</h3>
      <form method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="action" value="save_release">

        <label>Künstler</label>
        <div class="input muted"><?= htmlspecialchars($release['artist_name']) ?></div>

        <label>Titel *</label>
        <input class="input" type="text" name="title" required value="<?= htmlspecialchars($release['title']) ?>">

        <label>Typ *</label>
        <select class="select" name="release_type" required>
          <option value="single" <?= $release['release_type']==='single'?'selected':'' ?>>Single</option>
          <option value="ep"     <?= $release['release_type']==='ep'?'selected':'' ?>>EP</option>
          <option value="album"  <?= $release['release_type']==='album'?'selected':'' ?>>Album</option>
        </select>

        <label>Release-Datum</label>
        <input class="input" type="date" name="release_date" value="<?= htmlspecialchars($release['release_date'] ?? '') ?>">

        <label>Cover (JPG/PNG/WebP, max. 8 MB)</label>
        <?php if (!empty($release['cover_path'])): ?>
          <img class="cover" src="<?= htmlspecialchars($release['cover_path']) ?>" alt="Cover" style="margin-bottom:8px">
          <label style="display:flex;align-items:center;gap:8px;margin:6px 0;">
            <input type="checkbox" name="delete_cover" value="1" style="width:auto;"> Cover löschen
          </label>
        <?php endif; ?>
        <input class="file" type="file" name="cover" accept=".jpg,.jpeg,.png,.webp,image/*">

        <div style="margin-top:12px;"><button class="btn" type="submit">Details speichern</button></div>
      </form>
    </div>

    <div class="card">
      <h3>Neuen Track hinzufügen</h3>
      <form method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="action" value="add_track">
        <label>Track-Titel *</label>
        <input class="input" type="text" name="track_title" required>
        <label>Audio (MP3, max. 25 MB) *</label>
        <input class="file" type="file" name="audio" accept=".mp3,audio/mpeg" required>
        <div style="margin-top:12px;"><button class="btn" type="submit">Track hinzufügen</button></div>
      </form>
    </div>
  </div>

  <div class="card" style="margin-top:16px;">
    <h3>Tracks (per Drag & Drop sortieren)</h3>
    <?php if (empty($tracks)): ?>
      <p class="muted">Noch keine Tracks.</p>
    <?php else: ?>
      <table id="trackTable">
        <colgroup>
          <col class="col-pos">
          <col class="col-title">
          <col class="col-audio">
          <col class="col-actions">
        </colgroup>
        <thead>
          <tr>
            <th class="pos">#</th>
            <th>Titel</th>
            <th>Audio</th>
            <th style="text-align:right;">Aktionen</th>
          </tr>
        </thead>
        <tbody>
        <?php $i=1; foreach($tracks as $t): ?>
          <tr class="drag-row" draggable="true" data-id="<?= (int)$t['id'] ?>">
            <td class="pos"><?= $i++ ?></td>
            <td><?= htmlspecialchars($t['title']) ?></td>
            <td>
              <?php if(!empty($t['audio_path'])): ?>
                <audio controls src="<?= htmlspecialchars($t['audio_path']) ?>" controlslist="nodownload noplaybackrate"></audio>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td class="actions">
              <a class="btn sm" href="/grabiton/admin/song_edit.php?id=<?= (int)$t['id'] ?>&artist=<?= (int)$ctxArtist ?>&from=<?= urlencode($from) ?>&release=<?= (int)$release['id'] ?>">Bearbeiten</a>
              <a class="btn sm" href="/grabiton/admin/release_edit.php?id=<?= (int)$release['id'] ?>&artist=<?= (int)$ctxArtist ?>&from=<?= urlencode($from) ?>&delete_track=<?= (int)$t['id'] ?>" onclick="return confirm('Diesen Track löschen?')">Löschen</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="muted" id="saveHint" style="display:none;margin-top:8px;">Reihenfolge wird gespeichert …</p>
    <?php endif; ?>
  </div>

</main>
<footer>© <?= date('Y') ?> Grab It On</footer>

<script>
(function(){
  const tbody=document.querySelector('#trackTable tbody'); if(!tbody) return;
  let dragEl=null;

  tbody.addEventListener('dragstart',e=>{
    const tr=e.target.closest('tr'); if(!tr) return;
    dragEl=tr;
    tr.classList.add('dragging');
    e.dataTransfer.effectAllowed='move';
  });

  tbody.addEventListener('dragover',e=>{
    e.preventDefault();
    const after=getAfter(tbody,e.clientY);
    if(after==null) tbody.appendChild(dragEl);
    else tbody.insertBefore(dragEl,after);
  });

  tbody.addEventListener('drop',e=>{
    e.preventDefault();
    if(!dragEl) return;
    dragEl.classList.remove('dragging');
    dragEl=null;
    renumber();
    save();
  });

  tbody.addEventListener('dragend',()=>{
    if(dragEl) dragEl.classList.remove('dragging');
    dragEl=null;
  });

  function getAfter(container,y){
    const els=[...container.querySelectorAll('tr:not(.dragging)')];
    return els.reduce((closest,child)=>{
      const box=child.getBoundingClientRect();
      const offset=y - box.top - box.height/2;
      if(offset<0 && offset>closest.offset) return {offset,element:child};
      else return closest;
    },{offset:-Infinity}).element;
  }
  function renumber(){
    [...tbody.querySelectorAll('tr')].forEach((tr,i)=>{
      const pos=tr.querySelector('.pos'); if(pos) pos.textContent=i+1;
    });
  }
  async function save(){
    const hint=document.getElementById('saveHint'); if(hint) hint.style.display='block';
    const ids=[...tbody.querySelectorAll('tr')].map(tr=>parseInt(tr.dataset.id));
    try{
      await fetch(location.pathname+'?id=<?= (int)$release['id'] ?>&artist=<?= (int)$ctxArtist ?>&from=<?= urlencode($from) ?>&action=reorder',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({order:ids})
      });
    }catch(e){ console.error(e); }
    finally{ if(hint) setTimeout(()=>hint.style.display='none',700); }
  }
})();
</script>
</body>
</html>
