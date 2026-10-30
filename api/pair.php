<?php
// Pairing display <-> remote dalam satu sesi.
// - display_init (tanpa login): display minta kode + token baru.
// - kode (display_token): display membaca ulang kodenya.
// - remote_pair (admin login + CSRF): remote memasukkan kode display.
// - status (admin login): admin melihat kode aktif.
require_once __DIR__ . '/../includes/game.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$aksi = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

// Display pertama kali dibuka: buatkan kode + token.
if ($aksi === 'display_init') {
  $kode = kode_pairing_baru();
  $token = token_baru();
  // Pakai ulang sesi bila token lama masih dikirim (refresh halaman).
  $lama = $input['token_lama'] ?? '';
  $row = sesi_row();
  if ($lama !== '' && hash_equals((string)$row['display_token'], (string)$lama)) {
    echo json_encode(['ok' => true, 'kode' => $row['kode_pairing'], 'token' => $row['display_token']]);
    exit;
  }
  $q = db()->prepare('UPDATE sessions SET kode_pairing = ?, display_token = ? WHERE id = 1');
  $q->execute([$kode, $token]);
  echo json_encode(['ok' => true, 'kode' => $kode, 'token' => $token]);
  exit;
}

// Display baca kodenya sendiri.
if ($aksi === 'kode') {
  $token = $input['token'] ?? $_GET['token'] ?? '';
  $row = sesi_row();
  if ($token === '' || !hash_equals((string)$row['display_token'], (string)$token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token tidak valid.']);
    exit;
  }
  echo json_encode(['ok' => true, 'kode' => $row['kode_pairing']]);
  exit;
}

// Remote (guru, wajib login): cocokkan kode yang tampil di proyektor.
if ($aksi === 'remote_pair') {
  mulai_sesi();
  if (!sudah_login()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Harus login admin.']);
    exit;
  }
  if (!csrf_valid($input['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF tidak valid.']);
    exit;
  }
  $kode = strtoupper(trim($input['kode'] ?? ''));
  $row = sesi_row();
  if ($kode === '' || $kode !== strtoupper($row['kode_pairing'])) {
    echo json_encode(['ok' => false, 'error' => 'Kode salah. Lihat kode di layar proyektor.']);
    exit;
  }
  $_SESSION['paired'] = true;
  echo json_encode(['ok' => true]);
  exit;
}

// Status pairing untuk remote.
if ($aksi === 'status') {
  mulai_sesi();
  if (!sudah_login()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Harus login admin.']);
    exit;
  }
  echo json_encode(['ok' => true, 'paired' => !empty($_SESSION['paired']), 'kode' => sesi_row()['kode_pairing'] ?? '']);
  exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Aksi tidak dikenal.']);
