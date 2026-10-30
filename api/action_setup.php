<?php
// Aksi SETUP: boleh dari display_token ATAU admin login.
// Kuis yang sudah berjalan tidak bisa diubah dari sini.
require_once __DIR__ . '/../includes/game.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$aksi = $input['action'] ?? '';

// --- Hak akses: display_token valid didahulukan, lalu admin login (wajib CSRF) ---
mulai_sesi();
$is_admin = sudah_login();
$row = sesi_row();
$token = $input['display_token'] ?? '';
$is_display = ($token !== '' && hash_equals((string)$row['display_token'], (string)$token));

if ($is_display) {
  $is_admin = false; // display selalu lewat jalur token (anti-bentrok sesi admin se-browser)
} elseif ($is_admin) {
  if (!csrf_valid($input['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF tidak valid.']);
    exit;
  }
} else {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Tidak punya hak akses setup.']);
  exit;
}

$st = ambil_state();
if ($st['fase'] !== 'setup') {
  echo json_encode(['ok' => false, 'error' => 'Kuis sudah dimulai. Setup dikunci.']);
  exit;
}

$jawab_ok = function () use ($st, $is_admin) {
  $st = ambil_state(); // baca ulang terbaru
  echo json_encode(['ok' => true, 'state' => $is_admin ? $st : state_display($st)], JSON_UNESCAPED_UNICODE);
  exit;
};
$gagal = function (string $pesan) {
  echo json_encode(['ok' => false, 'error' => $pesan], JSON_UNESCAPED_UNICODE);
  exit;
};

// --- Jumlah tim: 2/3/4 ---
if ($aksi === 'set_jumlah_tim') {
  $j = (int)($input['jumlah'] ?? 0);
  if ($j < 2 || $j > 4) $gagal('Jumlah tim harus 2, 3, atau 4.');
  $tim = $st['setup']['tim'] ?? [];
  $baru = [];
  for ($i = 0; $i < $j; $i++) {
    $baru[] = [
      'nama' => $tim[$i]['nama'] ?? NAMA_TIM_BAWAAN[$i],
      'warna' => WARNA_TIM[$i],
    ];
  }
  $st['setup']['jumlah_tim'] = $j;
  $st['setup']['tim'] = $baru;
  simpan_state($st);
  $jawab_ok();
}

// --- Nama tim ---
if ($aksi === 'set_nama_tim') {
  $i = (int)($input['index'] ?? -1);
  $j = (int)($st['setup']['jumlah_tim'] ?? 2);
  if ($i < 0 || $i >= $j) $gagal('Indeks tim tidak valid.');
  $st['setup']['tim'][$i]['nama'] = bersih_nama($input['nama'] ?? '', NAMA_TIM_BAWAAN[$i]);
  simpan_state($st);
  $jawab_ok();
}

// --- Pilih kategori ---
if ($aksi === 'pilih_kategori') {
  $id = (int)($input['kategori_id'] ?? 0);
  $q = db()->prepare('SELECT id FROM categories WHERE id = ? AND aktif = 1 LIMIT 1');
  $q->execute([$id]);
  if (!$q->fetch()) $gagal('Kategori tidak valid.');
  $c = db()->prepare('SELECT COUNT(*) FROM questions WHERE category_id = ? AND aktif = 1');
  $c->execute([$id]);
  if ((int)$c->fetchColumn() < 1) $gagal('Kategori ini belum punya soal aktif.');
  $st['setup']['kategori_id'] = $id;
  simpan_state($st);
  $jawab_ok();
}

// --- Mulai kuis: kunci setup, susun tim, ambil soal ronde 1 ---
if ($aksi === 'mulai_kuis') {
  $j = (int)($st['setup']['jumlah_tim'] ?? 2);
  $kat = (int)($st['setup']['kategori_id'] ?? 0);
  if ($j < 2 || $j > 4) $gagal('Jumlah tim belum dipilih.');
  if ($kat <= 0) $gagal('Kategori belum dipilih.');
  // Nama tim wajib terisi & berbeda
  $nama = [];
  $tim = [];
  for ($i = 0; $i < $j; $i++) {
    $n = bersih_nama($st['setup']['tim'][$i]['nama'] ?? '', NAMA_TIM_BAWAAN[$i]);
    $nama[] = mb_strtolower($n);
    $tim[] = ['nama' => $n, 'warna' => WARNA_TIM[$i], 'skor' => 0];
  }
  if (count(array_unique($nama)) !== $j) $gagal('Nama tiap tim harus berbeda.');
  $soal = soal_acak($kat, []);
  if (!$soal || count($soal['jawaban']) < 1) $gagal('Soal kategori ini belum lengkap.');
  // Susun state kuis ronde 1
  $st['fase'] = 'kuis';
  $st['tim'] = $tim;
  $st['tim_aktif'] = 0;
  $st['tim_main'] = 0;
  $st['ronde'] = 1;
  $st['soal_id'] = (int)$soal['id'];
  $st['pertanyaan'] = $soal['pertanyaan'];
  $st['jawaban'] = array_map(fn($a) => [
    'id' => (int)$a['id'], 'teks' => $a['teks'], 'poin' => (int)$a['poin'], 'terbuka' => false,
  ], $soal['jawaban']);
  $st['x'] = 0;
  $st['bank'] = 0;
  $st['pengali'] = pengali_ronde($st['pengaturan'], 1);
  $st['steal'] = ['mode' => null, 'oleh' => null];
  $st['riwayat_soal'] = [(int)$soal['id']];
  $st['ronde_selesai'] = false;
  $st['timer'] = ['berjalan' => false, 'sisa' => (int)$st['pengaturan']['timer_detik'], 'deadline' => null];
  simpan_state($st, 'ronde_baru', ['ronde' => 1, 'tim_main' => 0]);
  $jawab_ok();
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Aksi setup tidak dikenal.']);
