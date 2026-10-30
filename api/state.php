<?php
// GET state permainan (polling tiap 1 detik).
// - Admin login (+full=1): state lengkap termasuk jawaban tersembunyi (untuk remote).
// - Display (token valid): state tersaring, jawaban tertutup TANPA teks/poin.
// - ?action=kategori: daftar kategori (nama, ikon, jumlah soal) — boleh publik.
require_once __DIR__ . '/../includes/game.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$aksi = $_GET['action'] ?? 'state';

// Daftar kategori: hanya nama, ikon, jumlah soal (tanpa isi soal).
if ($aksi === 'kategori') {
  echo json_encode(['ok' => true, 'kategori' => daftar_kategori()], JSON_UNESCAPED_UNICODE);
  exit;
}

$st = ambil_state();
sinkron_timer($st);

// Admin yang login boleh minta state lengkap untuk remote.
mulai_sesi();
if (!empty($_GET['full']) && sudah_login()) {
  echo json_encode([
    'ok' => true, 'state' => $st,
    'waktu_server' => time(),
    'kode_pairing' => sesi_row()['kode_pairing'] ?? '',
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// Display: wajib display_token valid.
$token = $_GET['token'] ?? '';
$row = sesi_row();
if ($token === '' || !hash_equals((string)$row['display_token'], (string)$token)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Token display tidak valid.']);
  exit;
}

echo json_encode([
  'ok' => true,
  'state' => state_display($st),
  'waktu_server' => time(),
], JSON_UNESCAPED_UNICODE);
