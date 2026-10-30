<?php
// CRUD soal + jawaban (teks, poin, alias). 3-8 jawaban, poin diurut otomatis.
require_once __DIR__ . '/_layout.php';
wajib_login();

$pdo = db();
$kat_aktif = (int)($_GET['kat'] ?? 0);

// --- Simpan / hapus ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_valid($_POST['csrf'] ?? null)) {
    set_pesan('Sesi kedaluwarsa, coba lagi.', 'galat');
    header('Location: soal.php?kat=' . (int)($_POST['category_id'] ?? 0));
    exit;
  }
  $aksi = $_POST['aksi'] ?? '';
  if ($aksi === 'hapus_soal') {
    $pdo->prepare('DELETE FROM questions WHERE id = ?')->execute([(int)$_POST['id']]);
    set_pesan('Soal dihapus.');
    header('Location: soal.php?kat=' . (int)($_POST['category_id'] ?? 0));
    exit;
  }
  // Tambah / ubah soal
  $kat_id = (int)($_POST['category_id'] ?? 0);
  $tanya = trim(strip_tags($_POST['pertanyaan'] ?? ''));
  $aktif = isset($_POST['aktif']) ? 1 : 0;
  $teks = $_POST['teks'] ?? [];
  $poin = $_POST['poin'] ?? [];
  $alias = $_POST['alias'] ?? [];
  $jawab = [];
  for ($i = 0; $i < count($teks); $i++) {
    $t = trim(strip_tags($teks[$i] ?? ''));
    $p = (int)($poin[$i] ?? 0);
    if ($t === '') continue;
    $a = array_values(array_filter(array_map('trim', explode(',', (string)($alias[$i] ?? '')))));
    $jawab[] = ['teks' => mb_substr($t, 0, 120), 'poin' => $p, 'alias' => array_slice($a, 0, 10)];
  }
  // Validasi: 3-8 jawaban, poin 1-100
  $salah = '';
  if (mb_strlen($tanya) < 5) $salah = 'Pertanyaan minimal 5 karakter.';
  elseif (count($jawab) < 3 || count($jawab) > 8) $salah = 'Jawaban harus 3-8 buah (yang terisi: ' . count($jawab) . ').';
  else foreach ($jawab as $j) {
    if ($j['poin'] < 1 || $j['poin'] > 100) { $salah = 'Poin tiap jawaban 1-100.'; break; }
  }
  if ($salah !== '') {
    set_pesan($salah, 'galat');
  } else {
    // Urutkan poin terbesar dulu, lalu simpan.
    usort($jawab, fn($a, $b) => $b['poin'] <=> $a['poin']);
    if ($aksi === 'tambah') {
      $pdo->prepare('INSERT INTO questions (category_id, pertanyaan, aktif) VALUES (?, ?, ?)')
          ->execute([$kat_id, $tanya, $aktif]);
      $qid = (int)$pdo->lastInsertId();
    } else {
      $qid = (int)$_POST['id'];
      $pdo->prepare('UPDATE questions SET category_id = ?, pertanyaan = ?, aktif = ? WHERE id = ?')
          ->execute([$kat_id, $tanya, $aktif, $qid]);
      $pdo->prepare('DELETE FROM answers WHERE question_id = ?')->execute([$qid]);
    }
    $ins = $pdo->prepare('INSERT INTO answers (question_id, teks, poin, alias_json, urutan) VALUES (?, ?, ?, ?, ?)');
    foreach ($jawab as $n => $j) {
      $ins->execute([$qid, $j['teks'], $j['poin'], json_encode($j['alias'], JSON_UNESCAPED_UNICODE), $n + 1]);
    }
    set_pesan($aksi === 'tambah' ? 'Soal ditambahkan.' : 'Soal disimpan.');
  }
  header('Location: soal.php?kat=' . $kat_id);
  exit;
}

// --- Data ---
$kats = $pdo->query('SELECT * FROM categories ORDER BY urutan ASC, id ASC')->fetchAll();
if ($kat_aktif <= 0 && $kats) $kat_aktif = (int)$kats[0]['id'];

$ubah = null; $ubah_jawab = [];
if (isset($_GET['ubah'])) {
  $q = $pdo->prepare('SELECT * FROM questions WHERE id = ? LIMIT 1');
  $q->execute([(int)$_GET['ubah']]);
  $ubah = $q->fetch();
  if ($ubah) {
    $kat_aktif = (int)$ubah['category_id'];
    $a = $pdo->prepare('SELECT * FROM answers WHERE question_id = ? ORDER BY urutan ASC');
    $a->execute([(int)$ubah['id']]);
    foreach ($a->fetchAll() as $r) {
      $al = json_decode($r['alias_json'] ?? '[]', true);
      $ubah_jawab[] = ['teks' => $r['teks'], 'poin' => $r['poin'], 'alias' => is_array($al) ? implode(', ', $al) : ''];
    }
  }
}
$daftar = [];
if ($kat_aktif > 0) {
  $q = $pdo->prepare(
    "SELECT q.*, (SELECT COUNT(*) FROM answers a WHERE a.question_id = q.id) AS jml,
     (SELECT GROUP_CONCAT(a.teks ORDER BY a.poin DESC SEPARATOR ' • ') FROM answers a WHERE a.question_id = q.id) AS ringkas
     FROM questions q WHERE q.category_id = ? ORDER BY q.id DESC"
  );
  $q->execute([$kat_aktif]);
  $daftar = $q->fetchAll();
}
// Baris form: data ubah, atau 6 baris kosong
$baris = $ubah_jawab ?: array_fill(0, 6, ['teks' => '', 'poin' => '', 'alias' => '']);

layout_awal($ubah ? 'Ubah Soal' : 'Soal', 'soal');
tampil_pesan();
?>
<form class="form" method="get" style="margin-bottom:12px">
  <label>Pilih kategori
    <select name="kat" onchange="this.form.submit()">
      <?php foreach ($kats as $k): ?>
        <option value="<?= (int)$k['id'] ?>" <?= $k['id'] == $kat_aktif ? 'selected' : '' ?>>
          <?= e($k['ikon'] . ' ' . $k['nama']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
</form>

<form class="form" method="post" id="form-soal">
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="<?= $ubah ? 'ubah' : 'tambah' ?>">
  <?php if ($ubah): ?><input type="hidden" name="id" value="<?= (int)$ubah['id'] ?>"><?php endif; ?>
  <input type="hidden" name="category_id" value="<?= $kat_aktif ?>">
  <label>Pertanyaan survei
    <textarea name="pertanyaan" required rows="2" maxlength="255"
      placeholder="cth: Makanan khas Indonesia yang paling terkenal?"><?= e($ubah['pertanyaan'] ?? '') ?></textarea>
  </label>
  <label class="cek"><input type="checkbox" name="aktif" value="1"
    <?= ($ubah ? $ubah['aktif'] : 1) ? 'checked' : '' ?>> Aktif</label>

  <h2 class="subjudul">Jawaban (3-8, poin terbesar di atas)</h2>
  <div id="daftar-jawaban">
    <?php foreach ($baris as $n => $b): ?>
      <div class="jawab-row">
        <span class="jawab-no"><?= $n + 1 ?></span>
        <input type="text" name="teks[]" maxlength="120" placeholder="Teks jawaban"
          value="<?= e($b['teks']) ?>">
        <input type="number" name="poin[]" min="1" max="100" placeholder="Poin"
          value="<?= e($b['poin']) ?>" class="kecil">
        <input type="text" name="alias[]" maxlength="200" placeholder="Alias, pisah koma (opsional)"
          value="<?= e($b['alias']) ?>" class="alias">
      </div>
    <?php endforeach; ?>
  </div>
  <div class="baris2">
    <button type="button" class="btn" id="tambah-baris">＋ Baris</button>
    <button type="button" class="btn" id="kurang-baris">－ Baris</button>
  </div>
  <div class="baris2">
    <button class="btn btn-primer btn-besar" type="submit"><?= $ubah ? 'Simpan' : 'Tambah' ?></button>
    <?php if ($ubah): ?><a class="btn btn-besar" href="soal.php?kat=<?= $kat_aktif ?>">Batal</a><?php endif; ?>
  </div>
</form>

<h2 class="subjudul">Daftar soal</h2>
<div class="daftar-kartu">
  <?php foreach ($daftar as $s): ?>
    <div class="kartu-baris kolom">
      <b><?= e($s['pertanyaan']) ?></b>
      <small><?= (int)$s['jml'] ?> jawaban • <?= $s['aktif'] ? '✅' : '⛔' ?></small>
      <small class="ringkas"><?= e($s['ringkas'] ?? '') ?></small>
      <div class="aksi-baris">
        <a class="btn btn-kecil" href="soal.php?kat=<?= $kat_aktif ?>&ubah=<?= (int)$s['id'] ?>">Ubah</a>
        <form method="post" onsubmit="return confirm('Hapus soal ini?')">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="hapus_soal">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <input type="hidden" name="category_id" value="<?= $kat_aktif ?>">
          <button class="btn btn-kecil btn-bahaya" type="submit">Hapus</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$daftar): ?><p class="kosong">Belum ada soal di kategori ini.</p><?php endif; ?>
</div>

<script>
// Tambah/kurang baris jawaban (3-8).
(function () {
  var wadah = document.getElementById('daftar-jawaban');
  function renomor() {
    wadah.querySelectorAll('.jawab-row').forEach(function (r, i) {
      r.querySelector('.jawab-no').textContent = i + 1;
    });
  }
  document.getElementById('tambah-baris').onclick = function () {
    var n = wadah.querySelectorAll('.jawab-row').length;
    if (n >= 8) { alert('Maksimal 8 jawaban.'); return; }
    var d = document.createElement('div');
    d.className = 'jawab-row';
    d.innerHTML = '<span class="jawab-no"></span>' +
      '<input type="text" name="teks[]" maxlength="120" placeholder="Teks jawaban">' +
      '<input type="number" name="poin[]" min="1" max="100" placeholder="Poin" class="kecil">' +
      '<input type="text" name="alias[]" maxlength="200" placeholder="Alias, pisah koma (opsional)" class="alias">';
    wadah.appendChild(d);
    renomor();
  };
  document.getElementById('kurang-baris').onclick = function () {
    var baris = wadah.querySelectorAll('.jawab-row');
    if (baris.length <= 3) { alert('Minimal 3 jawaban.'); return; }
    baris[baris.length - 1].remove();
    renomor();
  };
})();
</script>
<?php layout_akhir('soal'); ?>
