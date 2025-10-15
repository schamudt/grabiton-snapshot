<?php
// /grabiton/admin/artist_new.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';

$error = '';

function makeCode(): string {
  return 'gio' . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
  $name  = trim($_POST['artist_name'] ?? '');
  $genre = trim($_POST['genre'] ?? '');

  if($name === ''){
    $error = 'Bitte einen Künstlernamen eingeben.';
  }else{
    // Einzigartigen Code erzeugen
    $check = $pdo->prepare("SELECT id FROM artists WHERE artist_code = ? LIMIT 1");
    do {
      $code = makeCode();
      $check->execute([$code]);
      $exists = $check->fetch();
    } while ($exists);

    $ins = $pdo->prepare("INSERT INTO artists (artist_name, genre, artist_code) VALUES (?, ?, ?)");
    $ins->execute([$name, $genre !== '' ? $genre : null, $code]);

    header('Location: /grabiton/admin/artists.php');
    exit;
  }
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Neuer Künstler – Grab It On</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#0b1a3a;color:#fff;margin:0}
  header,footer{padding:14px 18px;background:rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.12);backdrop-filter:blur(6px)}
  main{width:min(800px,96vw);margin:22px auto;padding:0 20px}
  label{display:block;margin:14px 0 6px;opacity:.85}
  input{width:100%;padding:12px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.08);color:#fff}
  .btn{display:inline-block;background:#2b5dff;color:#fff;border-radius:10px;padding:10px 16px;text-decoration:none;font-weight:600;border:0;cursor:pointer;margin-top:16px}
  .error{color:#ff9aa2;margin-top:10px}
</style>
</head>
<body>
<header><a class="btn" href="/grabiton/admin/artists.php">← Zurück</a></header>
<main>
  <h1>Neuer Künstler</h1>
  <?php if($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <label>Künstlername *</label>
    <input type="text" name="artist_name" required>
    <label>Musikrichtung (optional)</label>
    <input type="text" name="genre" placeholder="z. B. Pop, Hip-Hop, Techno …">
    <button class="btn" type="submit">Speichern</button>
  </form>
</main>
<footer>© <?= date('Y') ?> Grab It On</footer>
</body>
</html>
