<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Minimaler Einstiegspunkt. Lädt die Shell (Header/Sidebar/Player/Footer)
// und stellt <main id="gio-main"> bereit, den wir per JS befüllen.
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>gio – Neuaufbau</title>
  <link rel="stylesheet" href="/gio/assets/css/site.css">
  <link rel="icon" href="/gio/assets/img/giocon.png">
</head>
<body>
  <?php include __DIR__ . '/../shell/header.php'; ?>
  <div id="gio-shell">
    <?php include __DIR__ . '/../shell/sidebar.php'; ?>
    <main id="gio-main"></main>
    <?php include __DIR__ . '/../shell/player.php'; ?>
  </div>
  <?php include __DIR__ . '/../shell/footer.php'; ?>

 	<script src="/gio/assets/js/player.js"></script>
	<script src="/gio/assets/js/store.js" defer></script>
	<script src="/gio/assets/js/player.ui.js" defer></script>
	<script src="/gio/assets/js/app.js" defer></script>
	<script src="/gio/assets/js/search.js" defer></script>



</body>
</html>