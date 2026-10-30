<?php
// Konfigurasi utama. SESUAIKAN bagian database setelah upload ke cPanel.

// --- Database MySQL (isi sesuai database di cPanel) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'ganti_nama_db');
define('DB_USER', 'ganti_user_db');
define('DB_PASS', 'ganti_password_db');

// --- Dasar URL (otomatis, biasanya tidak perlu diubah) ---
// Contoh hasil: https://sekolah.sch.id/kuis
$__skema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$__host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
$__jalan = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
// Naik ke akar aplikasi jika script ada di subfolder (api/admin/display/remote)
$__jalan = preg_replace('#/(api|admin|display|remote)$#', '', $__jalan);
define('BASE_URL', $__skema . '://' . $__host . $__jalan);

// --- Nama aplikasi ---
define('APP_NAMA', 'Skapsya100');

// --- Batas polling (detik) — pengingat untuk JS ---
define('POLL_DETIK', 1);
