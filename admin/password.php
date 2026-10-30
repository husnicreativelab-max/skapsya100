<?php
// Ganti password admin yang login.
require_once __DIR__ . '/_layout.php';
wajib_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_valid($_POST['csrf'] ?? null)) {
    set_pesan('Sesi kedaluwarsa, coba lagi.', 'galat');
  } else {
    $lama = $_POST['lama'] ?? '';
    $baru = $_POST['baru'] ?? '';
    $ulang = $_POST['ulang'] ?? '';
    $q = db()->prepare('SELECT password_hash FROM admins WHERE id = ? LIMIT 1');
    $q->execute([admin_id()]);
    $hash = $q->fetchColumn();
    if (!password_verify($lama, (string)$hash)) {
      set_pesan('Password lama salah.', 'galat');
    } elseif (strlen($baru) < 6) {
      set_pesan('Password baru minimal 6 karakter.', 'galat');
    } elseif ($baru !== $ulang) {
      set_pesan('Ulangi password tidak sama.', 'galat');
    } else {
      db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($baru, PASSWORD_DEFAULT), admin_id()]);
      set_pesan('Password berhasil diganti.');
    }
  }
  header('Location: password.php');
  exit;
}

layout_awal('Ganti Password');
tampil_pesan();
?>
<form class="form" method="post">
  <?= csrf_field() ?>
  <label>Password lama<input type="password" name="lama" required></label>
  <label>Password baru (min 6)<input type="password" name="baru" required></label>
  <label>Ulangi password baru<input type="password" name="ulang" required></label>
  <button class="btn btn-primer btn-besar" type="submit">Ganti Password</button>
</form>
<?php layout_akhir(); ?>
