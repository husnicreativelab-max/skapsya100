<?php
// Remote guru (HP): wajib login admin + pairing kode display.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

mulai_sesi();
if (!sudah_login()) {
  $jalur = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/remote/')), '/');
  header('Location: ' . BASE_URL . '/admin/login.php?lanjut=' . urlencode($jalur . '/'));
  exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Remote — Skapsya100</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="remote" data-base="<?= BASE_URL ?>" data-csrf="<?= e(csrf_token()) ?>">
<header class="topbar">
  <a class="merk" href="<?= BASE_URL ?>/remote/">📱 Remote<span>100</span></a>
  <div class="topbar-kanan">
    <span class="pengguna">👤 <?= e($_SESSION['admin_nama'] ?? 'Guru') ?></span>
    <a class="btn btn-kecil" href="<?= BASE_URL ?>/admin/">Admin</a>
  </div>
</header>
<main class="bungkus">
  <!-- Layar pairing -->
  <div id="layar-pair" class="layar-pair">
    <h1>🔗 Hubungkan Remote</h1>
    <p>Masukkan kode 4 huruf yang tampil di layar proyektor.</p>
    <input id="input-kode" maxlength="4" placeholder="····" autocomplete="off">
    <button class="btn btn-primer btn-besar" id="btn-pair">Hubungkan</button>
  </div>
  <!-- Layar kontrol (diisi JS) -->
  <div id="layar-kontrol" class="remote-grid" style="display:none">
    <div id="pesan-remote" class="pesan" style="display:none"></div>
    <p>Memuat…</p>
  </div>
</main>
<script src="<?= BASE_URL ?>/assets/js/remote.js"></script>
</body>
</html>
