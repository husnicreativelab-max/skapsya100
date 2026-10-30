<?php
// Aksi KUIS bergiliran: HANYA admin login (remote guru). Display read-only.
// - Benar: poin × pengali LANGSUNG ke skor tim yang giliran, lalu giliran pindah.
// - Salah: skor tim dikurangi denda (boleh minus), lalu giliran pindah.
// - Tiap giliran dimulai, timer berjalan otomatis (tanpa tombol remote).
require_once __DIR__ . '/../includes/game.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

mulai_sesi();
if (!sudah_login()) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Harus login admin.']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$aksi = $input['action'] ?? '';
if (!csrf_valid($input['csrf'] ?? null)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'CSRF tidak valid.']);
  exit;
}

$st = ambil_state();
sinkron_timer($st);

$jawab_ok = function () {
  $st = ambil_state();
  echo json_encode(['ok' => true, 'state' => $st], JSON_UNESCAPED_UNICODE);
  exit;
};
$gagal = function (string $pesan) {
  echo json_encode(['ok' => false, 'error' => $pesan], JSON_UNESCAPED_UNICODE);
  exit;
};
$wajib_kuis = function () use ($st, $gagal) {
  if ($st['fase'] !== 'kuis') $gagal('Kuis belum/sudah tidak berjalan.');
};

$n_tim = count($st['tim'] ?? []);

// ---------- Jawaban BENAR: poin langsung ke tim yang giliran ----------
if ($aksi === 'buka_jawaban') {
  $wajib_kuis();
  if (!empty($st['ronde_selesai'])) $gagal('Ronde sudah selesai. Lanjut soal berikutnya.');
  $nomor = (int)($input['nomor'] ?? -1);
  if (!isset($st['jawaban'][$nomor])) $gagal('Nomor jawaban tidak valid.');
  if (!empty($st['jawaban'][$nomor]['terbuka'])) $gagal('Jawaban sudah terbuka.');
  $st['jawaban'][$nomor]['terbuka'] = true;
  $aktif = (int)$st['tim_aktif'];
  $poin = (int)$st['jawaban'][$nomor]['poin'] * (int)$st['pengali'];
  $st['tim'][$aktif]['skor'] += $poin;
  $is_top = ($nomor === 0);
  // Semua terbuka? Ronde selesai, timer berhenti.
  $semua = true;
  foreach ($st['jawaban'] as $j) if (empty($j['terbuka'])) $semua = false;
  if ($semua) {
    $st['ronde_selesai'] = true;
    timer_berhenti($st);
    simpan_state($st, $is_top ? 'top' : 'benar', [
      'nomor' => $nomor, 'poin' => $poin, 'tim' => $aktif,
      'skor' => (int)$st['tim'][$aktif]['skor'], 'ronde_selesai' => true,
    ]);
    $jawab_ok();
  }
  // Benar = giliran TETAP (lanjut menjawab), timer jalan lagi.
  timer_jalan($st);
  simpan_state($st, $is_top ? 'top' : 'benar', [
    'nomor' => $nomor, 'poin' => $poin, 'tim' => $aktif,
    'skor' => (int)$st['tim'][$aktif]['skor'], 'giliran' => $aktif,
  ]);
  $jawab_ok();
}

// ---------- Jawaban SALAH: skor dikurangi denda, giliran pindah ----------
if ($aksi === 'jawab_salah') {
  $wajib_kuis();
  if (!empty($st['ronde_selesai'])) $gagal('Ronde sudah selesai. Lanjut soal berikutnya.');
  $aktif = (int)$st['tim_aktif'];
  $denda = max(0, (int)($st['pengaturan']['denda_salah'] ?? 10));
  $st['tim'][$aktif]['skor'] -= $denda; // boleh minus
  giliran_berikut($st);
  simpan_state($st, 'x', [
    'tim' => $aktif, 'denda' => $denda,
    'skor' => (int)$st['tim'][$aktif]['skor'], 'giliran' => (int)$st['tim_aktif'],
  ]);
  $jawab_ok();
}

// ---------- Lewati giliran (misal waktu habis): tanpa denda ----------
if ($aksi === 'lewat_giliran') {
  $wajib_kuis();
  if (!empty($st['ronde_selesai'])) $gagal('Ronde sudah selesai. Lanjut soal berikutnya.');
  $dari = (int)$st['tim_aktif'];
  giliran_berikut($st);
  simpan_state($st, 'giliran', ['dari' => $dari, 'tim' => (int)$st['tim_aktif']]);
  $jawab_ok();
}

// ---------- Tim yang mulai duluan di ronde ini (awal ronde saja) ----------
if ($aksi === 'set_tim_main') {
  $wajib_kuis();
  $t = (int)($input['tim'] ?? -1);
  if ($t < 0 || $t >= $n_tim) $gagal('Tim tidak valid.');
  if (!empty($st['ronde_selesai'])) $gagal('Ronde sudah selesai.');
  foreach ($st['jawaban'] as $j) if (!empty($j['terbuka'])) $gagal('Ronde sudah berjalan.');
  $st['tim_main'] = $t;
  $st['tim_aktif'] = $t;
  timer_jalan($st); // timer starter ronde berjalan otomatis
  simpan_state($st);
  $jawab_ok();
}

// ---------- Soal berikutnya / ronde baru ----------
if ($aksi === 'soal_berikutnya') {
  $wajib_kuis();
  $paksa = !empty($input['paksa']);
  if (empty($st['ronde_selesai']) && !$paksa) {
    $gagal('Ronde belum selesai. Selesaikan dulu atau paksa lanjut.');
  }
  $kat = (int)($st['setup']['kategori_id'] ?? 0);
  $soal = soal_acak($kat, $st['riwayat_soal'] ?? []);
  // Soal habis -> permainan selesai, tentukan pemenang.
  if (!$soal) {
    $st['fase'] = 'selesai';
    timer_berhenti($st);
    $menang = 0;
    foreach ($st['tim'] as $i => $t) {
      if ((int)$t['skor'] > (int)$st['tim'][$menang]['skor']) $menang = $i;
    }
    simpan_state($st, 'menang', ['tim' => $menang]);
    $jawab_ok();
  }
  $ronde = (int)$st['ronde'] + 1;
  $st['ronde'] = $ronde;
  $st['soal_id'] = (int)$soal['id'];
  $st['pertanyaan'] = $soal['pertanyaan'];
  $st['jawaban'] = array_map(fn($a) => [
    'id' => (int)$a['id'], 'teks' => $a['teks'], 'poin' => (int)$a['poin'], 'terbuka' => false,
  ], $soal['jawaban']);
  $st['x'] = 0;
  $st['bank'] = 0;
  $st['pengali'] = pengali_ronde($st['pengaturan'], $ronde);
  $st['steal'] = ['mode' => null, 'oleh' => null];
  $st['riwayat_soal'][] = (int)$soal['id'];
  $st['ronde_selesai'] = false;
  // Starter ronde bergiliran (bisa diubah lagi sebelum jawaban dibuka).
  $tm = $n_tim > 0 ? ($ronde - 1) % $n_tim : 0;
  $st['tim_main'] = $tm;
  $st['tim_aktif'] = $tm;
  timer_jalan($st); // timer ronde baru langsung berjalan
  simpan_state($st, 'ronde_baru', ['ronde' => $ronde, 'tim_main' => $tm]);
  $jawab_ok();
}

// ---------- Koreksi skor (boleh minus) & nama tim ----------
if ($aksi === 'koreksi_skor') {
  $t = (int)($input['tim'] ?? -1);
  $skor = (int)($input['skor'] ?? -9999999);
  if ($t < 0 || $t >= $n_tim || $skor < -100000 || $skor > 100000) $gagal('Skor tidak valid.');
  $st['tim'][$t]['skor'] = $skor;
  simpan_state($st);
  $jawab_ok();
}
if ($aksi === 'edit_nama_tim') {
  $t = (int)($input['tim'] ?? -1);
  if ($t < 0 || $t >= $n_tim) $gagal('Tim tidak valid.');
  $st['tim'][$t]['nama'] = bersih_nama($input['nama'] ?? '', NAMA_TIM_BAWAAN[$t] ?? 'Tim');
  simpan_state($st);
  $jawab_ok();
}

// ---------- Kembali ke setup (nama tim & kategori dipertahankan) ----------
if ($aksi === 'kembali_setup') {
  // Sinkronkan nama terakhir (termasuk koreksi saat kuis) ke setup.
  $setup_tim = [];
  $sumber = $st['tim'] ?: ($st['setup']['tim'] ?? []);
  foreach ($sumber as $i => $t) {
    if ($i > 3) break;
    $setup_tim[] = [
      'nama' => bersih_nama($t['nama'] ?? '', NAMA_TIM_BAWAAN[$i] ?? 'Tim'),
      'warna' => WARNA_TIM[$i] ?? '#888',
    ];
  }
  $st['fase'] = 'setup';
  $st['tim'] = [];
  $st['tim_aktif'] = 0;
  $st['tim_main'] = 0;
  $st['ronde'] = 1;
  $st['soal_id'] = null;
  $st['pertanyaan'] = '';
  $st['jawaban'] = [];
  $st['x'] = 0;
  $st['bank'] = 0;
  $st['pengali'] = 1;
  $st['steal'] = ['mode' => null, 'oleh' => null];
  $st['riwayat_soal'] = [];
  $st['ronde_selesai'] = false;
  if ($setup_tim) {
    $st['setup']['tim'] = $setup_tim;
    $st['setup']['jumlah_tim'] = count($setup_tim);
  }
  timer_berhenti($st);
  simpan_state($st, 'ke_setup', []);
  $jawab_ok();
}

// ---------- Akhiri kuis sekarang (langsung ke podium) ----------
if ($aksi === 'akhiri_kuis') {
  if ($st['fase'] !== 'kuis') $gagal('Kuis tidak sedang berjalan.');
  $st['fase'] = 'selesai';
  timer_berhenti($st);
  $menang = 0;
  foreach ($st['tim'] as $i => $t) {
    if ((int)$t['skor'] > (int)$st['tim'][$menang]['skor']) $menang = $i;
  }
  simpan_state($st, 'menang', ['tim' => $menang]);
  $jawab_ok();
}

// ---------- Volume & musik latar ----------
if ($aksi === 'set_volume') {
  $v = (int)($input['volume'] ?? -1);
  if ($v < 0 || $v > 100) $gagal('Volume 0-100.');
  $st['pengaturan']['volume'] = $v;
  simpan_state($st);
  $jawab_ok();
}
if ($aksi === 'set_musik') {
  $st['pengaturan']['musik'] = !empty($input['musik']);
  simpan_state($st);
  $jawab_ok();
}

// ---------- Timer cadangan (umumnya otomatis; tak dipakai remote) ----------
if ($aksi === 'timer_start') {
  $wajib_kuis();
  $sisa = (int)($st['timer']['sisa'] ?? 0);
  if ($sisa <= 0) $sisa = (int)$st['pengaturan']['timer_detik'];
  $st['timer'] = ['berjalan' => true, 'sisa' => $sisa, 'deadline' => time() + $sisa];
  simpan_state($st);
  $jawab_ok();
}
if ($aksi === 'timer_stop') {
  timer_berhenti($st);
  simpan_state($st);
  $jawab_ok();
}
if ($aksi === 'timer_reset') {
  $d = (int)($input['detik'] ?? $st['pengaturan']['timer_detik']);
  if ($d < 5 || $d > 300) $gagal('Timer 5-300 detik.');
  $st['timer'] = ['berjalan' => false, 'sisa' => $d, 'deadline' => null];
  simpan_state($st);
  $jawab_ok();
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Aksi kuis tidak dikenal.']);
