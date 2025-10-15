<?php
// /grabiton/index.php – Fixe Shell + simpelstes Server-Routing per Query (?code=gio########)

declare(strict_types=1);

$fragment = __DIR__ . '/fragments/home.php';

// Wenn ?code=gio######## vorhanden ist → Artist-Seite laden
$code = $_GET['code'] ?? '';
if (is_string($code) && preg_match('/^gio\d{8}$/', $code)) {
  // artist.php erwartet $_GET['code'] – ist schon gesetzt
  $fragment = __DIR__ . '/fragments/artist.php';
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>GrabItOn – Musik entdecken</title>
  <meta name="description" content="GrabItOn – Musikplattform. Entdecke Artists, Releases und Empfehlungen.">
  <link rel="icon" type="image/svg+xml" href="/grabiton/assets/img/dat_Logo_GrabItOn_log_wh.svg">
  <link rel="stylesheet" href="/grabiton/assets/css/site.css">
</head>
<body>

  <!-- FIXE SHELL (Header/Sidebar/Footer/Player) -->
  <div class="gio-shell">
    <?php include __DIR__ . '/shell/header.php'; ?>
    <?php include __DIR__ . '/shell/sidebar.php'; ?>
    <?php include __DIR__ . '/shell/footer.php'; ?>
    <?php include __DIR__ . '/shell/player.php'; ?>
  </div>

  <!-- HAUPTFENSTER -->
  <div id="gio-scrolllayer">
    <main id="gio-main" class="gio-main">
      <?php include $fragment; ?>
    </main>
  </div>

  <!-- Dein JS kann bleiben – wird hier nicht benötigt -->
  <script src="/grabiton/assets/js/site.js" defer></script>
</body>
</html>
