<?php
// Proteksi CSRF sederhana memakai token sesi.
require_once __DIR__ . '/auth.php';

// Ambil (atau buat) token CSRF untuk sesi ini.
function csrf_token(): string {
  mulai_sesi();
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}

// Validasi token dari form/JSON.
function csrf_valid(?string $token): bool {
  mulai_sesi();
  return !empty($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

// Cetak input hidden untuk form HTML.
function csrf_field(): string {
  return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
