<?php
// Mesin state kuis di sisi server. SEMUA hitungan skor hanya di sini.
require_once __DIR__ . '/db.php';

// Palet warna tim (maks 4 tim).
const WARNA_TIM = ['#e63946', '#1d70c9', '#2a9d8f', '#f4a261'];
const NAMA_TIM_BAWAAN = ['Tim A', 'Tim B', 'Tim C', 'Tim D'];

// Ambil satu nilai pengaturan dari tabel settings.
function pengaturan(string $kunci, string $bawaan = ''): string {
  $st = db()->prepare('SELECT nilai FROM settings WHERE kunci = ? LIMIT 1');
  $st->execute([$kunci]);
  $v = $st->fetchColumn();
  return $v === false ? $bawaan : (string)$v;
}

// State awal sesi (fase setup).
function state_bawaan(): array {
  $tim = [];
  for ($i = 0; $i < 2; $i++) {
    $tim[] = ['nama' => NAMA_TIM_BAWAAN[$i], 'warna' => WARNA_TIM[$i]];
  }
  return [
    'fase' => 'setup',
    'setup' => ['jumlah_tim' => 2, 'tim' => $tim, 'kategori_id' => null],
    'tim' => [],
    'tim_aktif' => 0,
    'tim_main' => 0,
    'ronde' => 1,
    'soal_id' => null,
    'pertanyaan' => '',
    'jawaban' => [],          // [{id, teks, poin, terbuka}]
    'x' => 0,
    'bank' => 0,
    'pengali' => 1,
    'steal' => ['mode' => null, 'oleh' => null], // mode: null|siap|aktif|selesai
    'riwayat_soal' => [],
    'ronde_selesai' => false,
    'event_seq' => 0,
    'event' => null,          // {seq, tipe, data, waktu}
    'pengaturan' => [
      'pengali_r1' => (int)pengaturan('pengali_r1', '1'),
      'pengali_r2' => (int)pengaturan('pengali_r2', '1'),
      'pengali_r3' => (int)pengaturan('pengali_r3', '2'),
      'pengali_r4' => (int)pengaturan('pengali_r4', '3'),
      'timer_detik' => (int)pengaturan('timer_detik', '30'),
      'volume' => (int)pengaturan('volume', '80'),
      'musik' => pengaturan('musik', '1') === '1',
    ],
    'timer' => ['berjalan' => false, 'sisa' => (int)pengaturan('timer_detik', '30'), 'deadline' => null],
  ];
}

// Ambil baris sesi (buat jika belum ada).
function sesi_row(): array {
  $pdo = db();
  $row = $pdo->query('SELECT * FROM sessions WHERE id = 1 LIMIT 1')->fetch();
  if (!$row) {
    $pdo->exec("INSERT INTO sessions (id, kode_pairing, display_token, phase, state_json) VALUES (1, '', '', 'setup', NULL)");
    $row = $pdo->query('SELECT * FROM sessions WHERE id = 1 LIMIT 1')->fetch();
  }
  return $row;
}

// Ambil state (gabung dengan bawaan agar kunci lama tetap ada).
function ambil_state(): array {
  $row = sesi_row();
  $st = state_bawaan();
  if (!empty($row['state_json'])) {
    $d = json_decode($row['state_json'], true);
    if (is_array($d)) {
      foreach ($d as $k => $v) $st[$k] = $v;
      // Gabung dalam: pengaturan & timer (agar tidak hilang saat update)
      if (isset($d['pengaturan']) && is_array($d['pengaturan'])) {
        $st['pengaturan'] = array_merge(state_bawaan()['pengaturan'], $d['pengaturan']);
      }
    }
  }
  // Sinkron fase dari kolom phase
  if (in_array($row['phase'], ['setup', 'kuis', 'selesai'], true)) {
    $st['fase'] = $row['phase'];
  }
  return $st;
}

// Simpan state. Jika $event diisi, nomor urut event naik (display main 1x animasi).
function simpan_state(array $st, ?string $event = null, array $data = []): array {
  if ($event !== null) {
    $st['event_seq'] = (int)($st['event_seq'] ?? 0) + 1;
    $st['event'] = ['seq' => $st['event_seq'], 'tipe' => $event, 'data' => $data, 'waktu' => time()];
  }
  $json = json_encode($st, JSON_UNESCAPED_UNICODE);
  $pdo = db();
  $q = $pdo->prepare('UPDATE sessions SET phase = ?, state_json = ?, updated_at = NOW() WHERE id = 1');
  $q->execute([$st['fase'], $json]);
  return $st;
}

// State aman untuk DISPLAY: jawaban tertutup disembunyikan (tanpa teks/poin).
function state_display(array $st): array {
  $jawab = [];
  foreach (($st['jawaban'] ?? []) as $i => $j) {
    if (!empty($j['terbuka'])) {
      $jawab[] = ['teks' => $j['teks'], 'poin' => (int)$j['poin'], 'terbuka' => true];
    } else {
      $jawab[] = ['teks' => null, 'poin' => null, 'terbuka' => false];
    }
  }
  $st['jawaban'] = $jawab;
  unset($st['riwayat_soal']);
  return $st;
}

// Pengali ronde berjalan (ronde 4+ pakai pengali_r4).
function pengali_ronde(array $peng, int $ronde): int {
  if ($ronde <= 1) return max(1, (int)($peng['pengali_r1'] ?? 1));
  if ($ronde == 2) return max(1, (int)($peng['pengali_r2'] ?? 1));
  if ($ronde == 3) return max(1, (int)($peng['pengali_r3'] ?? 2));
  return max(1, (int)($peng['pengali_r4'] ?? 3));
}

// Ambil soal + jawaban (terurut poin terbesar dulu).
function ambil_soal(int $qid): ?array {
  $pdo = db();
  $q = $pdo->prepare('SELECT id, pertanyaan FROM questions WHERE id = ? AND aktif = 1 LIMIT 1');
  $q->execute([$qid]);
  $soal = $q->fetch();
  if (!$soal) return null;
  $a = $pdo->prepare('SELECT id, teks, poin FROM answers WHERE question_id = ? ORDER BY poin DESC, urutan ASC, id ASC');
  $a->execute([$qid]);
  $soal['jawaban'] = $a->fetchAll();
  return $soal;
}

// Pilih soal acak dari kategori yang belum dipakai ronde ini.
function soal_acak(int $kat_id, array $kecuali): ?array {
  $pdo = db();
  $sql = 'SELECT id FROM questions WHERE category_id = ? AND aktif = 1';
  $param = [$kat_id];
  if ($kecuali) {
    $tanda = implode(',', array_fill(0, count($kecuali), '?'));
    $sql .= " AND id NOT IN ($tanda)";
    foreach ($kecuali as $x) $param[] = (int)$x;
  }
  $q = $pdo->prepare($sql);
  $q->execute($param);
  $ids = $q->fetchAll(PDO::FETCH_COLUMN);
  if (!$ids) return null;
  return ambil_soal((int)$ids[array_rand($ids)]);
}

// Daftar kategori aktif + jumlah soal (aman untuk display).
function daftar_kategori(): array {
  $sql = "SELECT c.id, c.nama, c.ikon,
          (SELECT COUNT(*) FROM questions q WHERE q.category_id = c.id AND q.aktif = 1) AS jumlah_soal
          FROM categories c WHERE c.aktif = 1 ORDER BY c.urutan ASC, c.id ASC";
  return db()->query($sql)->fetchAll();
}

// Hitung mundur timer di server (dipanggil saat baca state).
function sinkron_timer(array &$st): void {
  if (!empty($st['timer']['berjalan']) && !empty($st['timer']['deadline'])) {
    $sisa = $st['timer']['deadline'] - time();
    if ($sisa <= 0) {
      $st['timer']['sisa'] = 0;
      $st['timer']['berjalan'] = false;
      $st['timer']['deadline'] = null;
    } else {
      $st['timer']['sisa'] = (int)$sisa;
    }
  }
}

// Kode pairing pendek (4 karakter mudah dibaca).
function kode_pairing_baru(): string {
  $huruf = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
  $kode = '';
  for ($i = 0; $i < 4; $i++) $kode .= $huruf[random_int(0, strlen($huruf) - 1)];
  return $kode;
}

// Token acak heksadesimal.
function token_baru(int $byte = 32): string {
  return bin2hex(random_bytes($byte));
}

// Bersihkan nama tim: potong 20 karakter, buang tag.
function bersih_nama(string $nama, string $bawaan): string {
  $n = trim(strip_tags($nama));
  $n = mb_substr($n, 0, 20);
  return $n === '' ? $bawaan : $n;
}
