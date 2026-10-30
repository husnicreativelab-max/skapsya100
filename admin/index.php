<?php
// Dashboard admin: ringkasan + status sesi + jalan pintas.
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/game.php';
wajib_login();

$nkat = (int)db()->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$nsoal = (int)db()->query('SELECT COUNT(*) FROM questions')->fetchColumn();
$st = ambil_state();
$kode = sesi_row()['kode_pairing'] ?? '';

$fase_label = ['setup' => '⚙️ Persiapan', 'kuis' => '🎮 Kuis berjalan', 'selesai' => '🏁 Selesai'];

layout_awal('Beranda Admin', 'dash');
tampil_pesan();
?>
<div class="grid-kartu">
  <div class="kartu info-sesi">
    <h2>Status sesi</h2>
    <p class="fase"><?= e($fase_label[$st['fase']] ?? $st['fase']) ?></p>
    <?php if ($st['fase'] === 'kuis'): ?>
      <p>Ronde <?= (int)$st['ronde'] ?> (×<?= (int)$st['pengali'] ?>) • Giliran:
        <b><?= e($st['tim'][(int)$st['tim_aktif']]['nama'] ?? '-') ?></b></p>
      <?php foreach (($st['tim'] ?? []) as $t): ?>
        <p>• <?= e($t['nama']) ?>: <b><?= (int)$t['skor'] ?></b></p>
      <?php endforeach; ?>
    <?php endif; ?>
    <p>Kode pairing display: <b class="kode"><?= e($kode ?: '—') ?></b></p>
  </div>
  <div class="kartu">
    <h2>Data</h2>
    <p class="angka-besar"><?= $nkat ?> <span>kategori</span></p>
    <p class="angka-besar"><?= $nsoal ?> <span>soal</span></p>
  </div>
</div>

<div class="grid-tombol">
  <a class="btn btn-primer btn-besar" href="<?= BASE_PATH ?>/remote/">📱 Buka Remote</a>
  <a class="btn btn-besar" href="<?= BASE_PATH ?>/display/" target="_blank">🖥️ Buka Display</a>
  <a class="btn btn-besar" href="kategori.php">🗂️ Kelola Kategori</a>
  <a class="btn btn-besar" href="soal.php">❓ Kelola Soal</a>
</div>
<?php layout_akhir('dash'); ?>
