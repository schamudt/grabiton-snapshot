<?php
if (session_status() === PHP_SESSION_NONE) session_start();
function require_login(): void {
  if (empty($_SESSION['uid'])) {
    header('Location: /grabiton/admin/login.php');
    exit;
  }
}
