# Petunjuk Instalasi di cPanel (Shared Hosting)

Kebutuhan: **PHP 8+** dan **MySQL/MariaDB**. Tanpa Node, tanpa WebSocket, tanpa composer.

## Langkah 1 — Unggah file

1. Zip semua isi repo ini di komputer, lalu unggah ke cPanel via **File Manager**
   (atau FTP) ke `public_html/kuis/` (boleh nama folder lain).
2. Ekstrak sehingga strukturnya: `public_html/kuis/index.php`,
   `public_html/kuis/display/`, `public_html/kuis/admin/`, dst.

## Langkah 2 — Buat database

1. Buka **MySQL Databases** → buat database, misal `sekolah_kuis`.
2. Buat user database + password, lalu **Add User to Database** (centang ALL PRIVILEGES).
3. Catat: nama database, username, password (di cPanel biasanya berawalan akun,
   misal `akunck_sekolah_kuis`).

## Langkah 3 — Impor SQL

1. Buka **phpMyAdmin** → pilih database baru → tab **Import**.
2. Pilih file `database.sql` dari repo ini → **Go**.
3. Pastikan tabel terbentuk: `admins`, `categories`, `questions`,
   `answers`, `sessions`, `settings`.

## Langkah 4 — Edit config

Buka file `includes/config.php`, sesuaikan 4 baris ini:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'akunck_sekolah_kuis');   // nama database cPanel
define('DB_USER', 'akunck_userkuis');       // user database cPanel
define('DB_PASS', 'password-kuat-di-sini'); // password user database
```

Simpan. Tidak ada pengaturan lain yang wajib diubah
(alamat situs terdeteksi otomatis).

## Langkah 5 — Login pertama (buat admin)

Admin bawaan sudah dibuat oleh `database.sql`:

- URL: `https://domain-sekolah.sch.id/kuis/admin/`
- Username: `admin`
- Password: `admin123`

**WAJIB**: setelah masuk, buka ikon 🔑 (Ganti Password) dan ganti password.
Jika ingin admin tambahan, jalankan di phpMyAdmin → SQL:

```sql
INSERT INTO admins (username, password_hash)
VALUES ('guru2', '$2y$10$gantidengan-hash-baru');
```

> Untuk membuat hash baru tanpa coding: login → ganti password akun `admin`
> sementara ke password yang diinginkan → salin `password_hash`-nya dari tabel
> `admins` → pakai untuk user baru → lalu kembalikan password `admin`.

## Langkah 6 — Uji coba

1. **Display** (laptop proyektor): buka `/kuis/display/`
   → klik **Aktifkan Suara** → klik **MULAI** → catat **kode pairing**.
2. **Remote** (HP guru, Chrome/Safari): buka `/kuis/remote/`
   → login admin → masukkan kode pairing → kontrol tampil.
3. **Main**: setup di display (jumlah tim, nama, kategori) →
   mulai kuis → kendalikan dari remote (buka jawaban, X, steal, ronde).

## Catatan produksi

- Hapus/acak ulang kode pairing tiap acara baru (otomatis tiap display dibuka).
- Satu sesi aktif dalam satu waktu (sesuai desain untuk 1 kelas).
- Jika pindah domain/folder, tidak perlu ubah config (BASE_URL otomatis).
- Backup: unduh database via phpMyAdmin → Export, sesekali saja.
- File `assets/sfx/` sengaja kosong — suara dibuat di browser (Web Audio API).
