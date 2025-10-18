
<?php // Einstieg. Lädt Shell + optik.css + app.js ?>
<!doctype html>
<html lang="de">
<head>
     <link rel="icon" href="/gio/assets/img/giocon.png" type="image/png">
     <link rel="apple-touch-icon" href="/gio/assets/img/giocon.png">
   <meta name="theme-color" content="#000000">

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>gio</title>
  <link rel="stylesheet" href="/gio/css/optik.css">
</head>
<body>
  <div id="gio-app" class="app">
    <?php include __DIR__.'/shell/header.php'; ?>
    <div class="app-row">
      <?php include __DIR__.'/shell/sidebar.php'; ?>
      <main id="gio-main" class="app-main" role="main" aria-live="polite"></main>
    </div>
   </div>
  
   <?php include __DIR__.'/shell/player.php'; ?>
   <?php include __DIR__.'/shell/footer.php'; ?>
  <script type="module" src="/gio/js/app.js"></script>
</body>
</html>
