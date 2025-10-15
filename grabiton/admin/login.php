<?php
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    try {
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password_hash'])) {
            $_SESSION['uid'] = (int)$user['id'];
            header('Location: /grabiton/admin/dashboard.php');
            exit;
        } else {
            $error = 'E-Mail oder Passwort falsch';
        }
    } catch (Throwable $e) {
        $error = 'Technischer Fehler. Bitte später erneut versuchen.';
    }
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login – GrabItOn</title>
<style>
  :root { color-scheme: light dark; }
  html, body { height: 100%; }
  body {
    margin: 0;
    font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    background:
      radial-gradient(1000px 600px at 25% -10%, #1b2e6a 0%, transparent 60%),
      radial-gradient(900px 500px at 110% 120%, #0f2455 0%, transparent 60%),
      linear-gradient(160deg, #0b1a3a, #07122a 55%, #050e22);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
  }
  .box {
    width: 360px;
    max-width: 92vw;
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(6px);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 14px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.45);
    padding: 28px 24px 24px;
    text-align: center;
  }
  .logo {
    display: block;
    width: 100%;
    max-width: 320px;
    margin: 0 auto 16px auto;
    height: auto;
    filter: drop-shadow(0 2px 12px rgba(0,0,0,0.35));
  }

  input, button {
    width: 100%;
    box-sizing: border-box;
    padding: 12px 14px;
    border-radius: 10px;
    border: 0;
    font-size: 16px;
    outline: none;
  }
  input[type="email"], input[type="password"] {
    background: #ffffff;
    color: #111;
    margin: 8px 0;
  }
  input::placeholder { color: #666; }

  button {
    margin-top: 10px;
    background: #2b5dff;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    transition: transform .06s ease, box-shadow .2s ease, background .2s ease;
    box-shadow: 0 6px 20px rgba(43,93,255,0.35);
  }
  button:hover   { background: #1f49d6; }
  button:active  { transform: translateY(1px); }

  .err {
    min-height: 20px;
    margin-top: 10px;
    color: #ffb6b6;
    font-size: 14px;
  }

  .footer-note {
    margin-top: 14px;
    opacity: .65;
    font-size: 12px;
  }
</style>
</head>
<body>
  <div class="box">
    <!-- Logo (dein Originalname!) -->
    <img class="logo" src="/grabiton/assets/img/dat_Logo_GrabItOn_fontlogclaim_wh.png" alt="GrabItOn – your musik. your fame.">

    <form method="post" novalidate>
      <input name="email" type="email" placeholder="E-Mail" autocomplete="username" required>
      <input name="password" type="password" placeholder="Passwort" autocomplete="current-password" required>
      <button>Anmelden</button>
      <div class="err"><?= $error ? htmlspecialchars($error) : '' ?></div>
    </form>

    <div class="footer-note">© <?= date('Y') ?> GrabItOn</div>
  </div>
</body>
</html>
