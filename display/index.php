<?php
// Layar proyektor: beranda, setup, papan kuis, podium.
// Kuis BERGILIRAN, display READ-ONLY (hanya polling + animasi + suara).
require_once __DIR__ . '/../includes/db.php'; // db.php sudah memuat config.php + fungsi e()
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Skapsya100 — Layar Kuis</title>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css?v=20261031d">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/anim.css?v=20261031b">
</head>
<body class="display" data-base="<?= BASE_PATH ?>">
<canvas id="kanvas-konfeti"></canvas>
<div class="bintang-latar" id="bintang-latar"></div>
<div class="awan-latar" id="awan-latar"></div>

<!-- Panggung game-show: sorot lampu, lengkung bohlam, lantai -->
<div class="panggung-latar" aria-hidden="true">
  <div class="sorot sorot-kiri"></div>
  <div class="sorot sorot-tengah"></div>
  <div class="sorot sorot-kanan"></div>
  <div class="lengkung lengkung-kiri"></div>
  <div class="lengkung lengkung-kanan"></div>
  <div class="lantai-latar"></div>
</div>
<div class="konfeti-latar" id="konfeti-latar" aria-hidden="true"></div>

<!-- Pintu geser -->
<div class="pintu kiri" id="pintu-kiri"></div>
<div class="pintu kanan" id="pintu-kanan"></div>

<main class="panggung">
  <!-- ===== BERANDA ===== -->
  <section class="layar tampil" id="layar-beranda">
    <img class="logo-gambar anim-logo" src="<?= BASE_PATH ?>/assets/img/logo.png?v=20261031b" alt="Skapsya 100">
    <button class="btn-mulai anim-denyut" id="btn-mulai"><span class="segitiga">▶</span> MULAI</button>
    <div class="kode-remot">
      <span class="ikon-remot">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="2" width="10" height="20" rx="4.5" fill="#fff"/><circle cx="12" cy="7" r="2" fill="#0a2472"/><rect x="10" y="10.6" width="4" height="1.8" rx=".9" fill="#0a2472"/><rect x="10" y="13.8" width="4" height="1.8" rx=".9" fill="#0a2472"/><rect x="10" y="17" width="4" height="1.8" rx=".9" fill="#0a2472"/></svg>
      </span>
      <span class="label-remot">KODE<br>REMOT</span>
      <span class="kotak-kode" id="kode-0">·</span>
      <span class="kotak-kode" id="kode-1">·</span>
      <span class="kotak-kode" id="kode-2">·</span>
      <span class="kotak-kode" id="kode-3">·</span>
    </div>
    <div class="tagline"><span class="bintang-emas">★</span> Kuis Survei Seru ala Family 100 <span class="bintang-emas">★</span></div>
    <div class="petunjuk-remot">Remote guru: buka <b><?= e($_SERVER['HTTP_HOST'] ?? '') ?>/remote</b> lalu masukkan kode di atas</div>
  </section>

  <!-- ===== SETUP ===== -->
  <section class="layar" id="layar-setup">
    <div class="kartu-setup">
      <h2 class="judul-setup">⚙️ Persiapan Permainan</h2>
      <p class="langkah" id="setup-info-langkah">Langkah 1 dari 3</p>

      <div id="setup-langkah-0">
        <p>Pilih jumlah tim:</p>
        <div class="pilih-angka" id="pilih-angka"></div>
        <div class="avatar-tim" id="avatar-tim"></div>
      </div>

      <div id="setup-langkah-1" style="display:none">
        <p>Tulis nama tiap tim:</p>
        <div class="form-nama" id="form-nama"></div>
      </div>

      <div id="setup-langkah-2" style="display:none">
        <p>Pilih kategori soal:</p>
        <div class="grid-kategori" id="grid-kategori"></div>
      </div>

      <div class="nav-setup">
        <button class="btn" id="btn-setup-kembali">← Kembali</button>
        <button class="btn btn-emas" id="btn-setup-lanjut">Lanjut →</button>
      </div>
    </div>
  </section>

  <!-- ===== KUIS ===== -->
  <section class="layar" id="layar-kuis">
    <img class="logo-kuis" src="<?= BASE_PATH ?>/assets/img/logo.png?v=20261031b" alt="Skapsya 100">
    <div class="bilah-atas" id="bilah-tim"></div>
    <div><span class="label-ronde" id="label-ronde">RONDE 1</span></div>
    <div class="papan-pertanyaan" id="papan-pertanyaan">
      <span id="teks-pertanyaan" data-penuh=""></span>
    </div>
    <div class="papan-jawaban" id="papan-jawaban"></div>
  </section>

  <!-- ===== SELESAI / PODIUM ===== -->
  <section class="layar" id="layar-selesai">
    <div class="podium-wrap">
      <h2 class="judul-menang" id="judul-menang">🏆 Pemenang 🏆</h2>
      <div class="podium" id="podium"></div>
      <p style="margin-top:3vmin;color:#9fb4d8">Tekan "Main lagi" di remote untuk permainan baru.</p>
    </div>
  </section>
</main>

<!-- Timer otomatis tiap giliran -->
<div class="timer-lingkaran" id="timer-box">
  <svg viewBox="0 0 100 100">
    <circle cx="50" cy="50" r="46" fill="rgba(0,0,0,.4)" stroke="#ffffff33" stroke-width="8"/>
    <circle id="timer-arc" cx="50" cy="50" r="46" fill="none" stroke="#ffd166" stroke-width="8"
      stroke-linecap="round" stroke-dasharray="289" stroke-dashoffset="0"/>
  </svg>
  <b id="timer-angka">30</b>
</div>
<div class="waktu-habis" id="waktu-habis" style="display:none">⏰ WAKTU HABIS!</div>

<!-- Overlay efek -->
<div class="overlay" id="overlay-x">
  <div style="text-align:center">
    <div class="x-raksasa">✕</div>
    <div class="x-sub" id="x-sub"></div>
  </div>
</div>

<!-- Pesan tengah (papan habis, giliran, dll) -->
<div class="toast-tengah" id="toast"></div>

<div id="pesan-display" class="pesan galat" style="display:none;position:fixed;bottom:8vmin;left:50%;transform:translateX(-50%);z-index:400"></div>

<!-- Nama sekolah + tombol sudut -->
<div class="sekolah">
  <svg viewBox="0 0 40 48" aria-hidden="true"><path d="M20 2 L36 8 V22 C36 34 28 42 20 46 C12 42 4 34 4 22 V8 Z" fill="#0a7d2c" stroke="#ffd166" stroke-width="3"/><path d="M20 10 L29 13.4 V22 C29 29 25 34 20 37 C15 34 11 29 11 22 V13.4 Z" fill="none" stroke="#ffffff" stroke-width="1.6" opacity=".8"/><text x="20" y="28" text-anchor="middle" font-size="12" font-weight="900" fill="#ffd166" font-family="Arial, sans-serif">S</text></svg>
  <span>SMK As-Syafi'iyah</span>
</div>
<div class="tombol-sudut">
  <button class="tombol-bulat" id="btn-suara"><span class="ikon" id="ikon-suara">🔈</span><span class="label">SUARA</span></button>
  <button class="tombol-bulat" id="btn-full"><span class="ikon">⛶</span><span class="label">LAYAR</span></button>
</div>

<script src="<?= BASE_PATH ?>/assets/js/sfx.js?v=20261031a"></script>
<script src="<?= BASE_PATH ?>/assets/js/confetti.js?v=20261031a"></script>
<script src="<?= BASE_PATH ?>/assets/js/display.js?v=20261031a"></script>
</body>
</html>
