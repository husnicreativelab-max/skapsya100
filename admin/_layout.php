<?php
// Kerangka tampilan admin (mobile-first, tombol besar).
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

function layout_awal(string $judul, string $tab = ''): void {
  $menu = [
    'dash' => ['index.php', '🏠', 'Beranda'],
    'kat'  => ['kategori.php', '🗂️', 'Kategori'],
    'soal' => ['soal.php', '❓', 'Soal'],
    'atur' => ['pengaturan.php', '⚙️', 'Atur'],
  ];
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> — Admin Skapsya100</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="admin">
<header class="topbar">
  <a class="merk" href="index.php">🎯 Skapsya<span>100</span></a>
  <div class="topbar-kanan">
    <span class="pengguna">👤 <?= e($_SESSION['admin_nama'] ?? 'Admin') ?></span>
    <a class="btn btn-kecil" href="password.php">🔑</a>
    <a class="btn btn-kecil btn-bahaya" href="logout.php">Keluar</a>
  </div>
</header>
<main class="bungkus">
  <h1 class="judul-halaman"><?= e($judul) ?></h1>
<?php }

function layout_akhir(string $tab = ''): void {
  $menu = [
    'dash' => ['index.php', '🏠', 'Beranda'],
    'kat'  => ['kategori.php', '🗂️', 'Kategori'],
    'soal' => ['soal.php', '❓', 'Soal'],
    'atur' => ['pengaturan.php', '⚙️', 'Atur'],
  ];
?>
</main>
<nav class="nav-bawah">
  <?php foreach ($menu as $k => [$url, $ikon, $label]): ?>
    <a href="<?= $url ?>" class="<?= $k === $tab ? 'aktif' : '' ?>"><?= $ikon ?><span><?= $label ?></span></a>
  <?php endforeach; ?>
</nav>
</body>
</html>
<?php }

// Pesan kilat (flash) sederhana.
function set_pesan(string $teks, string $jenis = 'ok'): void {
  mulai_sesi();
  $_SESSION['pesan'] = ['teks' => $teks, 'jenis' => $jenis];
}
function tampil_pesan(): void {
  mulai_sesi();
  if (!empty($_SESSION['pesan'])) {
    $p = $_SESSION['pesan'];
    unset($_SESSION['pesan']);
    echo '<div class="pesan ' . e($p['jenis']) . '">' . e($p['teks']) . '</div>';
  }
}
