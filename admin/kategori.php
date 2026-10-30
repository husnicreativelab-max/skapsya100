<?php
// CRUD kategori (nama, ikon/emoji, urutan, aktif).
require_once __DIR__ . '/_layout.php';
wajib_login();

$pdo = db();

// --- Aksi simpan / hapus ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_valid($_POST['csrf'] ?? null)) {
    set_pesan('Sesi kedaluwarsa, coba lagi.', 'galat');
  } else {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'hapus') {
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
      set_pesan('Kategori dihapus (soalnya ikut terhapus).');
    } else {
      $nama = trim(strip_tags($_POST['nama'] ?? ''));
      $ikon = trim($_POST['ikon'] ?? '❓');
      $urutan = (int)($_POST['urutan'] ?? 0);
      $aktif = isset($_POST['aktif']) ? 1 : 0;
      if (mb_strlen($nama) < 2 || mb_strlen($nama) > 100) {
        set_pesan('Nama kategori 2-100 karakter.', 'galat');
      } else {
        if ($aksi === 'tambah') {
          $pdo->prepare('INSERT INTO categories (nama, ikon, urutan, aktif) VALUES (?, ?, ?, ?)')
              ->execute([$nama, $ikon ?: '❓', $urutan, $aktif]);
          set_pesan('Kategori ditambahkan.');
        } elseif ($aksi === 'ubah') {
          $id = (int)($_POST['id'] ?? 0);
          $pdo->prepare('UPDATE categories SET nama = ?, ikon = ?, urutan = ?, aktif = ? WHERE id = ?')
              ->execute([$nama, $ikon ?: '❓', $urutan, $aktif, $id]);
          set_pesan('Kategori disimpan.');
        }
      }
    }
  }
  header('Location: kategori.php');
  exit;
}

// --- Data untuk daftar + form edit ---
$ubah = null;
if (isset($_GET['ubah'])) {
  $q = $pdo->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
  $q->execute([(int)$_GET['ubah']]);
  $ubah = $q->fetch();
}
$daftar = $pdo->query(
  "SELECT c.*, (SELECT COUNT(*) FROM questions q WHERE q.category_id = c.id) AS jml
   FROM categories c ORDER BY c.urutan ASC, c.id ASC"
)->fetchAll();

layout_awal($ubah ? 'Ubah Kategori' : 'Kategori', 'kat');
tampil_pesan();
?>
<form class="form" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="<?= $ubah ? 'ubah' : 'tambah' ?>">
  <?php if ($ubah): ?><input type="hidden" name="id" value="<?= (int)$ubah['id'] ?>"><?php endif; ?>
  <label>Nama kategori
    <input type="text" name="nama" required maxlength="100" placeholder="cth: Makanan Indonesia"
      value="<?= e($ubah['nama'] ?? '') ?>">
  </label>
  <div class="baris2">
    <label>Ikon / emoji
      <input type="text" name="ikon" maxlength="20" placeholder="🍜" value="<?= e($ubah['ikon'] ?? '') ?>">
    </label>
    <label>Urutan
      <input type="number" name="urutan" min="0" max="999" value="<?= e($ubah['urutan'] ?? 0) ?>">
    </label>
  </div>
  <label class="cek"><input type="checkbox" name="aktif" value="1"
    <?= ($ubah ? $ubah['aktif'] : 1) ? 'checked' : '' ?>> Aktif (tampil di display)</label>
  <div class="baris2">
    <button class="btn btn-primer btn-besar" type="submit"><?= $ubah ? 'Simpan' : 'Tambah' ?></button>
    <?php if ($ubah): ?><a class="btn btn-besar" href="kategori.php">Batal</a><?php endif; ?>
  </div>
</form>

<h2 class="subjudul">Daftar kategori</h2>
<div class="daftar-kartu">
  <?php foreach ($daftar as $k): ?>
    <div class="kartu-baris">
      <div class="ikon-besar"><?= e($k['ikon']) ?></div>
      <div class="isi">
        <b><?= e($k['nama']) ?></b>
        <small><?= (int)$k['jml'] ?> soal • urutan <?= (int)$k['urutan'] ?> •
          <?= $k['aktif'] ? '✅ aktif' : '⛔ nonaktif' ?></small>
      </div>
      <div class="aksi-baris">
        <a class="btn btn-kecil" href="soal.php?kat=<?= (int)$k['id'] ?>">Soal</a>
        <a class="btn btn-kecil" href="kategori.php?ubah=<?= (int)$k['id'] ?>">Ubah</a>
        <form method="post" onsubmit="return confirm('Hapus kategori ini beserta soalnya?')">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="hapus">
          <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
          <button class="btn btn-kecil btn-bahaya" type="submit">Hapus</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$daftar): ?><p class="kosong">Belum ada kategori.</p><?php endif; ?>
</div>
<?php layout_akhir('kat'); ?>
