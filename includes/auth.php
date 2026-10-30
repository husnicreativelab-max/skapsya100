<?php
// Otentikasi admin memakai sesi PHP.
require_once __DIR__ . '/db.php';

function mulai_sesi(): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
}

// Cek apakah admin sudah login.
function sudah_login(): bool {
  mulai_sesi();
  return !empty($_SESSION['admin_id']);
}

// ID admin yang login (atau null).
function admin_id(): ?int {
  mulai_sesi();
  return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
}

// Wajibkan login, kalau belum lempar ke halaman login admin.
function wajib_login(): void {
  if (!sudah_login()) {
    header('Location: ' . BASE_PATH . '/admin/login.php');
    exit;
  }
}

// Proses login: kembalikan true jika berhasil.
function proses_login(string $username, string $password): bool {
  mulai_sesi();
  $st = db()->prepare('SELECT id, password_hash FROM admins WHERE username = ? LIMIT 1');
  $st->execute([trim($username)]);
  $row = $st->fetch();
  if ($row && password_verify($password, $row['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$row['id'];
    $_SESSION['admin_nama'] = trim($username);
    return true;
  }
  return false;
}

// Keluar: hapus sesi.
function proses_logout(): void {
  mulai_sesi();
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
}
