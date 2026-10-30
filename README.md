# Skapsya100 — Kuis Interaktif ala Family 100

Game kuis untuk kelas/acara sekolah. Layar proyektor menampilkan permainan,
guru mengendalikan dari HP (remote), dan admin mengelola soal dari HP/PC.

- **Display** (`/display/`): beranda → setup → papan kuis → podium. Read-only saat kuis.
- **Remote** (`/remote/`): kontrol guru (wajib login + kode pairing).
- **Admin** (`/admin/`): CRUD kategori & soal, pengaturan, ganti password. Mobile-first.
- **Realtime**: polling ringan 1 detik (tanpa WebSocket, cocok shared hosting).
- **Animasi**: CSS `@keyframes` + JS ringan + kanvas konfeti sendiri (tanpa library).
- **Suara**: sintesis Web Audio API (`assets/js/sfx.js`, tanpa file audio).

## Struktur

```
index.php            → arahan ke /display/
display/             → layar proyektor
remote/              → kontrol HP guru
admin/               → login, dashboard, kategori, soal, pengaturan
api/                 → state, pair, action_setup, action_quiz
includes/            → config, db, auth, csrf, game (mesin skor server)
assets/css/          → style.css, anim.css
assets/js/           → display.js, remote.js, sfx.js, confetti.js
assets/sfx/          → kosong (untuk MP3 nanti)
database.sql         → skema + admin bawaan + data contoh
```

## Instalasi cPanel — LIHAT `INSTALL.md`
