<?php
// Login admin (dipakai juga oleh remote guru).
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

mulai_sesi();
if (sudah_login()) {
  header('Location: ' . BASE_PATH . '/admin/index.php');
  exit;
}

$galat = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_valid($_POST['csrf'] ?? null)) {
    $galat = 'Sesi kedaluwarsa, coba lagi.';
  } elseif (proses_login($_POST['username'] ?? '', $_POST['password'] ?? '')) {
    $lanjut = $_GET['lanjut'] ?? '';
    // Hanya izinkan redirect internal
    if ($lanjut !== '' && str_starts_with($lanjut, '/')) {
      header('Location: ' . $lanjut);
    } else {
      header('Location: ' . BASE_PATH . '/admin/index.php');
    }
    exit;
  } else {
    $galat = 'Username atau password salah.';
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk — Admin Skapsya100</title>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
</head>
<body class="admin login-latar">
<main class="kotak-login">
  <div class="login-logo">🎯</div>
  <h1>Skapsya<span>100</span></h1>
  <p class="sub">Masuk sebagai guru / admin</p>
  <?php if ($galat): ?><div class="pesan galat"><?= e($galat) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label>Username
      <input type="text" name="username" required autofocus maxlength="50" placeholder="admin">
    </label>
    <label>Password
      <input type="password" name="password" required placeholder="••••••">
    </label>
    <button class="btn btn-primer btn-besar" type="submit">Masuk</button>
  </form>
  <p class="kembali"><a href="<?= BASE_PATH ?>/display/">← Kembali ke layar display</a></p>
</main>
</body>
</html>
