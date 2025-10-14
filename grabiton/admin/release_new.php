<?php
// /grabiton/admin/release_new.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';
ini_set('display_errors', 1); error_reporting(E_ALL);

$artistId = (int)($_GET['artist'] ?? 0);
if ($artistId <= 0) { exit('Kein Künstler-Kontext übergeben.'); }

try { $s=$pdo->prepare("SELECT id, artist_name FROM artists WHERE id=?"); $s->execute([$artistId]); $artist=$s->fetch(); }
catch(Throwable $e){ exit('Fehler beim Laden des Künstlers: '.htmlspecialchars($e->getMessage())); }
if(!$artist){ exit('Künstler nicht gefunden.'); }

$errors = [];

function save_cover_upload(string $field): ?string {
  if (empty($_FILES[$field]['name']) || $_FILES[$field]['error']===UPLOAD_ERR_NO_FILE) return null;
  $f=$_FILES[$field]; if($f['error']!==UPLOAD_ERR_OK) throw new RuntimeException("Upload-Fehler (Cover)");
  if($f['size']>8*1024*1024) throw new RuntimeException("Cover zu groß.");
  $tmp=$f['tmp_name']; $ext=strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if(!in_array($ext,['jpg','jpeg','png','webp'],true)) throw new RuntimeException("Ungültige Endung.");
  $mime=mime_content_type($tmp); if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)) throw new RuntimeException("Ungültiger Typ.");
  $name=bin2hex(random_bytes(8)).'_'.time().'.'.$ext; $dir=dirname(__DIR__)."/uploads/covers"; if(!is_dir($dir)) mkdir($dir,0755,true);
  $dest="$dir/$name"; if(!move_uploaded_file($tmp,$dest)) throw new RuntimeException("Speichern fehlgeschlagen.");
  return "/grabiton/uploads/covers/$name";
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  $title=trim($_POST['title']??''); $type=$_POST['release_type']??'single'; $date=trim($_POST['release_date']??'');
  if($title==='' || !in_array($type,['single','ep','album'],true)) { $errors[]="Bitte Titel und Typ korrekt angeben."; }
  else {
    try{
      $cover=save_cover_upload('cover');
      $pdo->prepare("INSERT INTO releases (artist_id,title,release_type,cover_path,release_date) VALUES (?,?,?,?,?)")
          ->execute([$artist['id'],$title,$type,$cover,$date!==''?$date:null]);
      $newId=(int)$pdo->lastInsertId();
      header('Location: /grabiton/admin/release_edit.php?id='.$newId.'&artist='.(int)$artist['id']); exit;
    }catch(Throwable $e){ $errors[]=$e->getMessage(); }
  }
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Neues Release – Grab It On</title>
<style>
  :root { color-scheme: dark; }
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#151102;color:#fff;margin:0}
  header,footer{padding:14px 18px;background:rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.12);backdrop-filter:blur(6px)}
  main{width:min(900px,96vw);margin:22px auto;padding:0 20px}
  .btn{display:inline-block;background:#2b5dff;color:#fff;border-radius:10px;padding:10px 16px;text-decoration:none;font-weight:600;border:0;cursor:pointer}
  .card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:16px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
  .field{margin-bottom:12px}
  label{display:block;margin:0 0 6px 0;opacity:.85}
  .input,.select,.file{width:100%;padding:12px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.08);color:#fff;outline:none;box-sizing:border-box}
  select.select{appearance:none;-webkit-appearance:none;-moz-appearance:none}
  select.select,select.select option{background:#151102;color:#fff}
  .muted{opacity:.8}
  .msg{margin:10px 0;padding:10px 12px;border-radius:10px;background:rgba(255,0,0,.25);border:1px solid rgba(255,0,0,.4)}
  .actions{margin-top:16px;display:flex;gap:8px}
</style>
</head>
<body>
<header>
  <a class="btn" href="/grabiton/admin/releases.php?artist=<?= (int)$artist['id'] ?>">← Zurück</a>
</header>
<main>
  <h1>Neues Release</h1>

  <?php foreach($errors as $e): ?><div class="msg"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

  <div class="card">
    <div class="field">
      <label>Künstler</label>
      <div class="input muted"><?= htmlspecialchars($artist['artist_name']) ?></div>
    </div>

    <form method="post" enctype="multipart/form-data" autocomplete="off">
      <div class="field">
        <label>Titel *</label>
        <input class="input" type="text" name="title" required>
      </div>

      <div class="field">
        <label>Typ *</label>
        <select class="select" name="release_type" required>
          <option value="single">Single</option>
          <option value="ep">EP</option>
          <option value="album">Album</option>
        </select>
      </div>

      <div class="field">
        <label>Release-Datum (optional)</label>
        <input class="input" type="date" name="release_date">
      </div>

      <div class="field">
        <label>Cover (JPG/PNG/WebP, max. 8 MB, optional)</label>
        <input class="file" type="file" name="cover" accept=".jpg,.jpeg,.png,.webp,image/*">
      </div>

      <div class="actions">
        <button class="btn" type="submit">Anlegen & weiter zu Tracks</button>
        <a class="btn" href="/grabiton/admin/releases.php?artist=<?= (int)$artist['id'] ?>">Abbrechen</a>
      </div>
    </form>
  </div>
</main>
<footer>© <?= date('Y') ?> Grab It On</footer>
</body>
</html>
