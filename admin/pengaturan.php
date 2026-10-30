<?php
// Pengaturan: pengali ronde, timer, denda salah, volume, musik, reset sesi.
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/game.php';
wajib_login();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_valid($_POST['csrf'] ?? null)) {
    set_pesan('Sesi kedaluwarsa, coba lagi.', 'galat');
  } else {
    $aksi = $_POST['aksi'] ?? 'simpan';
    if ($aksi === 'reset_sesi') {
      // Kembalikan sesi ke setup kosong (kode pairing dipertahankan).
      $st = state_bawaan();
      $pdo->prepare('UPDATE sessions SET phase = ?, state_json = ? WHERE id = 1')
          ->execute(['setup', json_encode($st, JSON_UNESCAPED_UNICODE)]);
      set_pesan('Sesi direset ke awal (setup).');
    } else {
      $nilai = [
        'pengali_r1' => max(1, min(9, (int)($_POST['pengali_r1'] ?? 1))),
        'pengali_r2' => max(1, min(9, (int)($_POST['pengali_r2'] ?? 1))),
        'pengali_r3' => max(1, min(9, (int)($_POST['pengali_r3'] ?? 2))),
        'pengali_r4' => max(1, min(9, (int)($_POST['pengali_r4'] ?? 3))),
        'timer_detik' => max(5, min(300, (int)($_POST['timer_detik'] ?? 30))),
        'denda_salah' => max(0, min(100, (int)($_POST['denda_salah'] ?? 10))),
        'volume' => max(0, min(100, (int)($_POST['volume'] ?? 80))),
        'musik' => isset($_POST['musik']) ? '1' : '0',
      ];
      $q = $pdo->prepare('INSERT INTO settings (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)');
      foreach ($nilai as $k => $v) $q->execute([$k, (string)$v]);
      // Terapkan juga ke sesi berjalan (pengali ronde aktif dihitung ulang).
      $st = ambil_state();
      $st['pengaturan']['pengali_r1'] = $nilai['pengali_r1'];
      $st['pengaturan']['pengali_r2'] = $nilai['pengali_r2'];
      $st['pengaturan']['pengali_r3'] = $nilai['pengali_r3'];
      $st['pengaturan']['pengali_r4'] = $nilai['pengali_r4'];
      $st['pengaturan']['timer_detik'] = $nilai['timer_detik'];
      $st['pengaturan']['denda_salah'] = $nilai['denda_salah'];
      $st['pengaturan']['volume'] = $nilai['volume'];
      $st['pengaturan']['musik'] = $nilai['musik'] === '1';
      if ($st['fase'] === 'kuis') {
        $st['pengali'] = pengali_ronde($st['pengaturan'], (int)$st['ronde']);
      }
      simpan_state($st);
      set_pesan('Pengaturan disimpan.');
    }
  }
  header('Location: pengaturan.php');
  exit;
}

$p = [
  'pengali_r1' => pengaturan('pengali_r1', '1'),
  'pengali_r2' => pengaturan('pengali_r2', '1'),
  'pengali_r3' => pengaturan('pengali_r3', '2'),
  'pengali_r4' => pengaturan('pengali_r4', '3'),
  'timer_detik' => pengaturan('timer_detik', '30'),
  'denda_salah' => pengaturan('denda_salah', '10'),
  'volume' => pengaturan('volume', '80'),
  'musik' => pengaturan('musik', '1'),
];

layout_awal('Pengaturan', 'atur');
tampil_pesan();
?>
<form class="form" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="simpan">
  <h2 class="subjudul">Pengali poin per ronde</h2>
  <div class="baris2">
    <label>Ronde 1 (×)<input type="number" name="pengali_r1" min="1" max="9" value="<?= e($p['pengali_r1']) ?>"></label>
    <label>Ronde 2 (×)<input type="number" name="pengali_r2" min="1" max="9" value="<?= e($p['pengali_r2']) ?>"></label>
    <label>Ronde 3 (×)<input type="number" name="pengali_r3" min="1" max="9" value="<?= e($p['pengali_r3']) ?>"></label>
    <label>Ronde 4+ (×)<input type="number" name="pengali_r4" min="1" max="9" value="<?= e($p['pengali_r4']) ?>"></label>
  </div>
  <h2 class="subjudul">Giliran & denda</h2>
  <div class="baris2">
    <label>Timer tiap giliran (detik, otomatis)<input type="number" name="timer_detik" min="5" max="300" value="<?= e($p['timer_detik']) ?>"></label>
    <label>Denda jawaban salah (poin)<input type="number" name="denda_salah" min="0" max="100" value="<?= e($p['denda_salah']) ?>"></label>
  </div>
  <h2 class="subjudul">Suara</h2>
  <label>Volume (0-100)<input type="number" name="volume" min="0" max="100" value="<?= e($p['volume']) ?>"></label>
  <label class="cek"><input type="checkbox" name="musik" value="1" <?= $p['musik'] === '1' ? 'checked' : '' ?>>
    Musik latar menyala</label>
  <button class="btn btn-primer btn-besar" type="submit">Simpan Pengaturan</button>
</form>

<form class="form" method="post" onsubmit="return confirm('Reset sesi ke awal? Skor hilang.')">
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="reset_sesi">
  <h2 class="subjudul">Zona bahaya</h2>
  <button class="btn btn-bahaya btn-besar" type="submit">🔄 Reset sesi ke awal</button>
</form>
<?php layout_akhir('atur'); ?>
