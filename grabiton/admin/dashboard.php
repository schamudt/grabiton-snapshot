<?php
// /grabiton/admin/dashboard.php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$userEmail = '';
try {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['uid'] ?? 0]);
    $userEmail = $stmt->fetchColumn() ?: '';
} catch (Throwable $e) {
    $userEmail = '';
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard – Grab It On</title>
<style>
  :root { color-scheme: light dark; }
  html, body { height: 100%; margin: 0; }
  body {
    font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    background-color: #0b1a3a;
    color: #fff; display: flex; flex-direction: column; min-height: 100%;
  }
  .topbar {
    display:flex; align-items:center; justify-content:space-between;
    padding:14px 18px; background:rgba(255,255,255,0.06);
    border-bottom:1px solid rgba(255,255,255,0.12); backdrop-filter:blur(6px);
  }
  .brand{display:flex; align-items:center; gap:10px}
  .brand img{height:36px; width:auto; filter: drop-shadow(0 1px 8px rgba(0,0,0,.35))}
  .brand span{font-weight:600; opacity:.85; font-size:14px}
  .right{display:flex; align-items:center; gap:10px}
  .user{font-size:13px; opacity:.85; padding:6px 10px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12); border-radius:999px}
  .btn{appearance:none; border:0; border-radius:10px; padding:10px 16px; font-weight:600; cursor:pointer; color:#fff; background:#2b5dff; text-decoration:none; display:inline-block; box-shadow:0 6px 18px rgba(43,93,255,.35)}
  .btn:hover{ filter:brightness(1.05) }

  .btn-ghost{
    appearance:none; border:1px solid rgba(255,255,255,0.18);
    background:rgba(255,255,255,0.08);
    color:#fff; border-radius:10px; padding:8px 12px; font-weight:600; text-decoration:none; display:inline-block;
  }
  .btn-ghost:hover{ background:rgba(255,255,255,0.14); }

  .wrap{flex:1; display:flex; justify-content:center; padding:20px}
  .content{width:min(1100px, 96vw); margin-top:22px}
  .headline{display:flex; align-items:end; justify-content:space-between; gap:10px; margin:4px 0 16px}
  .headline h1{margin:0; font-size:22px}
  .headline small{opacity:.7}
  .cards{display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:16px}
  .card{
    background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12);
    border-radius:14px; padding:18px; box-shadow:0 10px 30px rgba(0,0,0,.25);
    transition: transform .1s ease, box-shadow .2s ease, background .2s ease;
    color:inherit;
  }
  a.card{text-decoration:none; display:block}
  .card:hover{ background:rgba(255,255,255,0.12); transform:translateY(-2px); box-shadow:0 12px 34px rgba(0,0,0,.3) }
  .card h3{margin:0 0 8px; font-size:18px}
  .card p{margin:0; opacity:.85; font-size:14px; line-height:1.45}
  .muted{opacity:.75; font-size:13px}
  .actions{display:flex; gap:10px; margin-top:14px; flex-wrap:wrap}
  footer{text-align:center; padding:18px; opacity:.6; font-size:12px}
</style>
</head>
<body>
<header class="topbar">
  <div class="brand">
    <img src="/grabiton/assets/img/dat_Logo_GrabItOn_fontlogclaim_wh.png" alt="Grab It On">
    <span>Admin</span>
  </div>
  <div class="right">
    <?php if ($userEmail): ?><div class="user"><?= htmlspecialchars($userEmail) ?></div><?php endif; ?>
    <a class="btn" href="/grabiton/admin/logout.php">Logout</a>
  </div>
</header>

<main class="wrap">
  <div class="content">
    <div class="headline">
      <h1>Dashboard</h1>
      <small class="muted"><?= date('d.m.Y H:i') ?> Uhr</small>
    </div>

    <div class="cards">
      <a href="/grabiton/admin/artists.php" class="card">
        <h3>Künstler*innen</h3>
        <p>Lege Künstler*innen an, bearbeite Profile und verwalte Releases</p>
      </a>

      <a href="/grabiton/admin/releases_overview.php" class="card">
        <h3>Veröffentlichungen</h3>
        <p>Liste der Künstler*innen mit jeweiligen Releases</p>
      </a>

      <a href="/grabiton/admin/track_library.php" class="card">
        <h3>Track Bibliothek</h3>
        <p>Track Index und Bewertungsbereich</p>
      </a>

      <!-- NEU: Songbereinigung mit zwei Aktionen -->
      <section class="card">
        <h3>Songbereinigung</h3>
        <p>Problem-Einträge finden & löschen (ohne Release, Dubletten) oder direkt per ID entfernen.</p>
        <div class="actions">
          <a class="btn" href="/grabiton/admin/songs_cleanup.php">Aufräumen öffnen</a>
          <a class="btn-ghost" href="/grabiton/admin/clean.php">Direktlöschung (ID)</a>
        </div>
      </section>

      <section class="card">
        <h3>Nächste Schritte</h3>
        <p>• Navigation anlegen<br>• Module hinzufügen<br>• Berechtigungen feiner einstellen</p>
      </section>
    </div>
  </div>
</main>

<footer>© <?= date('Y') ?> Grab It On</footer>
</body>
</html>
