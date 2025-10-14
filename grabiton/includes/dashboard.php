<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
?>
<!doctype html>
<html lang="de">
<meta charset="utf-8">
<title>Admin Dashboard</title>
<body style="font-family:sans-serif; padding:2rem;">
  <h1>Hallo Admin 👋</h1>
  <p>Willkommen im Dashboard.</p>
  <p><a href="logout.php">Logout</a></p>
</body>
</html>
