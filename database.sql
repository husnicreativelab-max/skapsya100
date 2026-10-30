-- =====================================================
-- SKAPSYA100 — Kuis interaktif ala Family 100
-- Database: MySQL / MariaDB, utf8mb4
-- Cara pakai: impor file ini via phpMyAdmin (cPanel)
-- Admin bawaan: username `admin`, password `admin123`
-- (SEGERA ganti password setelah login pertama!)
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- Admin ----------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Kategori ----------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(100) NOT NULL,
  `ikon` VARCHAR(20) NOT NULL DEFAULT '❓',
  `urutan` INT NOT NULL DEFAULT 0,
  `aktif` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Soal ----------
CREATE TABLE IF NOT EXISTS `questions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `pertanyaan` VARCHAR(255) NOT NULL,
  `aktif` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_q_cat` (`category_id`),
  CONSTRAINT `fk_q_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Jawaban ----------
CREATE TABLE IF NOT EXISTS `answers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` INT UNSIGNED NOT NULL,
  `teks` VARCHAR(120) NOT NULL,
  `poin` INT NOT NULL DEFAULT 0,
  `alias_json` TEXT NULL,
  `urutan` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_a_q` (`question_id`),
  CONSTRAINT `fk_a_q` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Sesi permainan (1 baris aktif: id = 1) ----------
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` INT UNSIGNED NOT NULL,
  `kode_pairing` VARCHAR(10) NOT NULL DEFAULT '',
  `display_token` VARCHAR(64) NOT NULL DEFAULT '',
  `phase` ENUM('setup','kuis','selesai') NOT NULL DEFAULT 'setup',
  `state_json` MEDIUMTEXT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Pengaturan default ----------
CREATE TABLE IF NOT EXISTS `settings` (
  `kunci` VARCHAR(50) NOT NULL,
  `nilai` VARCHAR(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`kunci`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------- Data awal: admin ----------
-- password: admin123 (hash bcrypt, dibuat dengan password_hash)
INSERT INTO `admins` (`username`, `password_hash`) VALUES
('admin', '$2b$12$bycLAQMoJWaqZv42g5IESuj/ZlBTkkX.j71YVINAxqHdcvp8nxuj2')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- ---------- Data awal: pengaturan ----------
INSERT INTO `settings` (`kunci`, `nilai`) VALUES
('pengali_r1', '1'),
('pengali_r2', '1'),
('pengali_r3', '2'),
('pengali_r4', '3'),
('timer_detik', '30'),
('volume', '80'),
('musik', '1')
ON DUPLICATE KEY UPDATE `nilai` = VALUES(`nilai`);

-- ---------- Data awal: sesi kosong ----------
INSERT INTO `sessions` (`id`, `kode_pairing`, `display_token`, `phase`, `state_json`) VALUES
(1, '', '', 'setup', NULL)
ON DUPLICATE KEY UPDATE `id` = 1;

-- =====================================================
-- DATA CONTOH: 3 kategori x 3 soal
-- =====================================================

INSERT INTO `categories` (`id`, `nama`, `ikon`, `urutan`, `aktif`) VALUES
(1, 'Makanan Indonesia', '🍜', 1, 1),
(2, 'Hewan', '🐘', 2, 1),
(3, 'Sekolah', '📚', 3, 1);

-- ----- Kategori 1: Makanan Indonesia -----
INSERT INTO `questions` (`id`, `category_id`, `pertanyaan`, `aktif`) VALUES
(1, 1, 'Makanan khas Indonesia yang paling terkenal di dunia?', 1),
(2, 1, 'Jajanan yang sering dijual di depan sekolah?', 1),
(3, 1, 'Minuman segar pelepas dahaga saat siang hari?', 1);

INSERT INTO `answers` (`question_id`, `teks`, `poin`, `alias_json`, `urutan`) VALUES
(1, 'Rendang', 38, '["rendang"]', 1),
(1, 'Nasi Goreng', 27, '["nasi goreng", "nasgor"]', 2),
(1, 'Sate', 17, '["sate", "satai"]', 3),
(1, 'Gado-gado', 8, '["gado gado", "gadogado"]', 4),
(1, 'Soto', 6, '["soto"]', 5),
(1, 'Bakso', 4, '["bakso", "baso"]', 6),
(2, 'Gorengan', 30, '["gorengan"]', 1),
(2, 'Cilok', 22, '["cilok"]', 2),
(2, 'Cireng', 16, '["cireng"]', 3),
(2, 'Batagor', 14, '["batagor"]', 4),
(2, 'Siomay', 10, '["siomay", "siomai"]', 5),
(2, 'Leker', 8, '["leker"]', 6),
(3, 'Es Teh', 34, '["es teh", "esteh"]', 1),
(3, 'Es Jeruk', 24, '["es jeruk"]', 2),
(3, 'Es Kelapa', 18, '["es kelapa", "es degan"]', 3),
(3, 'Es Cendol', 12, '["es cendol", "cendol"]', 4),
(3, 'Jus Alpukat', 7, '["jus alpukat", "jus apokat"]', 5),
(3, 'Es Campur', 5, '["es campur"]', 6);

-- ----- Kategori 2: Hewan -----
INSERT INTO `questions` (`id`, `category_id`, `pertanyaan`, `aktif`) VALUES
(4, 2, 'Hewan yang hidup di air?', 1),
(5, 2, 'Hewan peliharaan favorit keluarga?', 1),
(6, 2, 'Hewan yang bisa terbang?', 1);

INSERT INTO `answers` (`question_id`, `teks`, `poin`, `alias_json`, `urutan`) VALUES
(4, 'Ikan', 36, '["ikan"]', 1),
(4, 'Paus', 20, '["paus", "ikan paus"]', 2),
(4, 'Hiu', 15, '["hiu", "ikan hiu"]', 3),
(4, 'Gurita', 12, '["gurita"]', 4),
(4, 'Lumba-lumba', 10, '["lumba lumba", "lumba-lumba"]', 5),
(4, 'Kura-kura', 7, '["kura kura", "kura-kura", "penyu"]', 6),
(5, 'Kucing', 40, '["kucing"]', 1),
(5, 'Anjing', 28, '["anjing"]', 2),
(5, 'Burung', 14, '["burung"]', 3),
(5, 'Ikan Hias', 9, '["ikan hias"]', 4),
(5, 'Kelinci', 6, '["kelinci"]', 5),
(5, 'Hamster', 3, '["hamster"]', 6),
(6, 'Burung', 42, '["burung"]', 1),
(6, 'Kelelawar', 22, '["kelelawar", "kalong"]', 2),
(6, 'Kupu-kupu', 15, '["kupu kupu", "kupu-kupu"]', 3),
(6, 'Lebah', 10, '["lebah"]', 4),
(6, 'Capung', 7, '["capung"]', 5),
(6, 'Lalat', 4, '["lalat"]', 6);

-- ----- Kategori 3: Sekolah -----
INSERT INTO `questions` (`id`, `category_id`, `pertanyaan`, `aktif`) VALUES
(7, 3, 'Pelajaran yang paling disukai siswa?', 1),
(8, 3, 'Benda yang selalu ada di dalam tas sekolah?', 1),
(9, 3, 'Kegiatan ekstrakurikuler di sekolah?', 1);

INSERT INTO `answers` (`question_id`, `teks`, `poin`, `alias_json`, `urutan`) VALUES
(7, 'Olahraga', 30, '["olahraga", "penjas", "pjok"]', 1),
(7, 'Matematika', 22, '["matematika", "mtk"]', 2),
(7, 'IPA', 16, '["ipa"]', 3),
(7, 'Bahasa Inggris', 13, '["bahasa inggris", "b inggris"]', 4),
(7, 'Seni', 11, '["seni", "seni budaya"]', 5),
(7, 'IPS', 8, '["ips"]', 6),
(8, 'Buku', 32, '["buku"]', 1),
(8, 'Pensil', 24, '["pensil"]', 2),
(8, 'Penghapus', 15, '["penghapus", "setip"]', 3),
(8, 'Penggaris', 12, '["penggaris", "mistas"]', 4),
(8, 'Pulpen', 10, '["pulpen", "pena", "bolpoin"]', 5),
(8, 'Tempat Pensil', 7, '["tempat pensil", "kotak pensil"]', 6),
(9, 'Pramuka', 30, '["pramuka"]', 1),
(9, 'Futsal', 24, '["futsal"]', 2),
(9, 'Basket', 16, '["basket", "bola basket"]', 3),
(9, 'Paskibra', 12, '["paskibra"]', 4),
(9, 'PMR', 10, '["pmr"]', 5),
(9, 'Tari', 8, '["tari", "menari"]', 6);
