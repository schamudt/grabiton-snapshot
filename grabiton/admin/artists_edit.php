<?php
// /grabiton/admin/artist_edit.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';
ini_set('display_errors', 1); error_reporting(E_ALL);

$id = (int)($_GET['id'] ?? 0);
$messages=[]; $errors=[];

try {
  $stmt = $pdo->prepare("SELECT id, artist_name, genre, artist_code, avatar_path, banner_path FROM artists WHERE id=?");
  $stmt->execute([$id]);
  $artist = $stmt->fetch();
  if (!$artist) $errors[] = "Künstler nicht gefunden.";
} catch (Throwable $e) {
  $errors[] = "Fehler beim Laden: " . htmlspecialchars($e->getMessage());
  $artist = null;
}

function save_upload(string $field, string $subdir, array $ext, array $mime, int $max, bool $img=false): ?string{
  if (empty($_FILES[$field]['name']) || $_FILES[$field]['error']===UPLOAD_ERR_NO_FILE) return null;
  $f=$_FILES[$field]; if($f['error']!==UPLOAD_ERR_OK) throw new RuntimeException("Upload-Fehler ($field)");
  if($f['size']>$max) throw new RuntimeException("Datei zu groß ($field).");
  $tmp=$f['tmp_name']; $e=strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if(!in_array($e,$ext,true)) throw new RuntimeException("Ungültige Endung ($field).");
  $m=mime_content_type($tmp); if(!in_array($m,$mime,true)) throw new RuntimeException("Ungültiger Typ ($field).");
  if($img && @getimagesize($tmp)===false) throw new RuntimeException("Kein gültiges Bild ($field).");
  $name=bin2hex(random_bytes(8)).'_'.time().'.'.$e; $dir=dirname(__DIR__)."/uploads/$subdir"; if(!is_dir($dir)) mkdir($dir,0755,true);
  $dest="$dir/$name"; if(!move_uploaded_file($tmp,$dest)) throw new RuntimeException("Speichern fehlgeschlagen ($field).");
  return "/grabiton/uploads/$subdir/$name";
}

if ($_SERVER['REQUEST_METHOD']==='POST' && $artist) {
  $name=trim($_POST['artist_name']??''); $genre=trim($_POST['genre']??'');
  if ($name==='') $errors[]='Bitte einen Künstlernamen eingeben.';
  if (!$errors){
    try{
      $avatar=$artist['avatar_path']; $banner=$artist['banner_path'];
      if (!empty($_POST['delete_avatar']) && $avatar) { @unlink($_SERVER['DOCUMENT_ROOT'].$avatar); $avatar=null; $messages[]='Avatar entfernt.'; }
      if (!empty($_POST['delete_banner']) && $banner) { @unlink($_SERVER['DOCUMENT_ROOT'].$banner); $banner=null; $messages[]='Banner entfernt.'; }
      $newA=save_upload('avatar','avatars',['jpg','jpeg','png','webp'],['image/jpeg','image/png','image/webp'],5*1024*1024,true);
      if ($newA) { if($avatar) @unlink($_SERVER['DOCUMENT_ROOT'].$avatar); $avatar=$newA; $messages[]='Avatar hochgeladen.'; }
      $newB=save_upload('banner','banners',['jpg','jpeg','png','webp'],['image/jpeg','image/png','image/webp'],8*1024*1024,true);
      if ($newB) { if($banner) @unlink($_SERVER['DOCUMENT_ROOT'].$banner); $banner=$newB; $messages[]='Banner hochgeladen.'; }
      $pdo->prepare("UPDATE artists SET artist_name=?, genre=?, avatar_path=?, banner_path=? WHERE id=?")
          ->execute([$name,$genre!==''?$genre:null,$avatar,$banner,$artist['id']]);
      header('Location: /grabiton/admin/artist_edit.php?id='.$artist['id']); exit;
    } catch(Throwable $e){ $errors[]=$e->getMessage(); }
  }
}

// Releases für die Liste laden
$releases=[];
if ($artist){
  try{
    $rels=$pdo->prepare("SELECT id,title,release_type,cover_path,release_date FROM releases WHERE artist_id=? ORDER BY created_at DESC,id DESC");
    $rels->execute([$artist['id']]); $releases=$rels->fetchAll();
  }catch(Throwable $e){ $errors[]="Releases konnten nicht geladen werden: ".htmlspecialchars($e->getMessage()); }
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Künstler bearbeiten – Grab It On</title>
<style>
  :root { color-scheme: dark; }
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#0b1a3a;color:#fff;margin:0}
  header,footer{padding:14px 18px;background:rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.12);backdrop-filter:blur(6px)}
  main{width:min(1100px,96vw);margin:22px auto;padding:0 20px}
  .btn{display:inline-block;background:#2b5dff;color:#fff;border-radius:10px;padding:10px 16px;text-decoration:none;font-weight:600;border:0;cursor:pointer}
  /* Releases-Buttons in #97871d */
  .btn-releases{ background:#97871d; color:#fff; box-shadow:0 6px 18px rgba(151,135,29,.28) }
  .btn-releases:hover{ background:#7f7218 }
  /* Löschen-Button wie überall */
  .btn-danger{ background:#8f2a2f; box-shadow:0 6px 18px rgba(143,42,47,.28); color:#fff }
  .btn-danger:hover{ background:#7a2429 }

  .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .card{position:relative;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:16px;box-shadow:0 10px 30px rgba(0,0,0,.25);overflow:hidden}
  h3{margin:0 0 10px 0}
  .field{margin-bottom:12px} label{display:block;margin:0 0 6px 0;opacity:.9}
  .input,.file,.select{width:100%;padding:12px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.08);color:#fff;outline:none;box-sizing:border-box;position:relative;z-index:1}

  /* Wichtig: Vorschau-Bilder auf max. 350 px begrenzen, Seitenverhältnis erhalten */
  img.preview{
    display:block;
    margin-top:8px;
    border-radius:12px;
    position:relative; z-index:1;
    max-width:350px;   /* breite Bilder werden max. 350px breit */
    max-height:350px;  /* hohe Bilder werden max. 350px hoch  */
    width:auto; height:auto; /* Seitenverhältnis beibehalten   */
    object-fit:contain;       /* innerhalb des Rahmens bleiben  */
  }

  table{width:100%;border-collapse:collapse;margin-top:10px} th,td{padding:10px;border-bottom:1px solid rgba(255,255,255,.12);text-align:left;vertical-align:middle}
  .cover{width:48px;height:48px;border-radius:8px;object-fit:cover;background:rgba(255,255,255,.12)}
  .muted{opacity:.8} .msg{margin:10px 0;padding:10px 12px;border-radius:10px;background:rgba(255,0,0,.25);border:1px solid rgba(255,0,0,.4)} .ok{background:rgba(43,93,255,.2);border:1px solid rgba(43,93,255,.45)}
  .row-actions{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap}
</style>
</head>
<body>
<header>
  <a class="btn" href="/grabiton/admin/artists.php">← Zurück</a>
</header>
<main>
  <h1>Künstler bearbeiten</h1>
  <?php foreach($messages as $m): ?><div class="msg ok"><?= htmlspecialchars($m) ?></div><?php endforeach; ?>
  <?php foreach($errors as $e): ?><div class="msg"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

  <?php if(!$artist): ?>
    <div class="card"><p>Bitte zurück zur <a class="btn" href="/grabiton/admin/artists.php">Künstlerliste</a>.</p></div>
  <?php else: ?>
  <form method="post" enctype="multipart/form-data" autocomplete="off">
    <div class="card">
      <div class="field"><label>Künstlername *</label><input class="input" type="text" name="artist_name" required value="<?= htmlspecialchars($artist['artist_name']) ?>"></div>
      <div class="field"><label>Musikrichtung (optional)</label><input class="input" type="text" name="genre" value="<?= htmlspecialchars($artist['genre'] ?? '') ?>"></div>
      <div class="field"><label>Künstler-ID</label><input class="input" type="text" value="<?= htmlspecialchars($artist['artist_code'] ?? '') ?>" readonly></div>
    </div>

    <div class="grid" style="margin-top:16px;">
      <div class="card">
        <h3>Profilbild (Avatar)</h3>
        <?php if ($artist['avatar_path']): ?>
          <img class="preview" src="<?= htmlspecialchars($artist['avatar_path']) ?>" alt="Avatar">
          <label style="display:flex;align-items:center;gap:8px;margin-top:8px;">
            <input type="checkbox" name="delete_avatar" value="1" style="width:auto;"> Avatar löschen
          </label>
        <?php else: ?>
          <p class="muted">Noch kein Avatar.</p>
        <?php endif; ?>
        <div class="field" style="margin-top:8px;">
          <label>Neuen Avatar hochladen (JPG/PNG/WebP, max. 5 MB)</label>
          <input class="file" type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp,image/*">
        </div>
      </div>

      <div class="card">
        <h3>Banner</h3>
        <?php if ($artist['banner_path']): ?>
          <img class="preview" src="<?= htmlspecialchars($artist['banner_path']) ?>" alt="Banner">
          <label style="display:flex;align-items:center;gap:8px;margin-top:8px;">
            <input type="checkbox" name="delete_banner" value="1" style="width:auto;"> Banner löschen
          </label>
        <?php else: ?>
          <p class="muted">Noch kein Banner.</p>
        <?php endif; ?>
        <div class="field" style="margin-top:8px;">
          <label>Neues Banner hochladen (JPG/PNG/WebP, max. 8 MB)</label>
          <input class="file" type="file" name="banner" accept=".jpg,.jpeg,.png,.webp,image/*">
        </div>
      </div>
    </div>

    <div class="row-actions">
      <button class="btn" type="submit">Künstler speichern</button>
      <a class="btn btn-releases" href="/grabiton/admin/release_new.php?artist=<?= (int)$artist['id'] ?>&from=artist">+ Release anlegen</a>
      <a class="btn btn-releases" href="/grabiton/admin/releases.php?artist=<?= (int)$artist['id'] ?>&from=artist">Releases</a>
      <a class="btn btn-danger" href="/grabiton/admin/artist_delete.php?id=<?= (int)$artist['id'] ?>&from=artist">Künstler löschen</a>
    </div>
  </form>

  <div class="card" style="margin-top:16px;">
    <h3>Releases</h3>
    <?php if (empty($releases)): ?>
      <p class="muted">Noch keine Releases.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Cover</th><th>Titel</th><th>Typ</th><th>Release-Datum</th><th>Aktionen</th></tr></thead>
        <tbody>
        <?php foreach ($releases as $r): ?>
          <tr>
            <td><?php if ($r['cover_path']): ?><img class="cover" src="<?= htmlspecialchars($r['cover_path']) ?>" alt="Cover"><?php endif; ?></td>
            <td><?= htmlspecialchars($r['title']) ?></td>
            <td><?= strtoupper(htmlspecialchars($r['release_type'])) ?></td>
            <td><?= $r['release_date'] ? htmlspecialchars(date('d.m.Y', strtotime($r['release_date']))) : '—' ?></td>
            <td>
              <a class="btn" href="/grabiton/admin/release_edit.php?id=<?= (int)$r['id'] ?>&artist=<?= (int)$artist['id'] ?>&from=artist">Bearbeiten</a>
              <a class="btn" href="/grabiton/admin/release_delete.php?id=<?= (int)$r['id'] ?>&artist=<?= (int)$artist['id'] ?>&from=artist">Löschen</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</main>
<footer>© <?= date('Y') ?> Grab It On</footer>
</body>
</html>
