/* =====================================================
   Skapsya100 — Remote guru (HP). Kuis BERGILIRAN.
   Wajib login + pairing. Polling tiap 1 detik.
   Timer berjalan otomatis tiap giliran (tanpa tombol).
   ===================================================== */
(function () {
  'use strict';

  var BASE = document.body.getAttribute('data-base') || '';
  var CSRF = document.body.getAttribute('data-csrf') || '';
  var state = null;
  var paired = false;
  var daftarKategori = [];

  function $(id) { return document.getElementById(id); }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function api(url, data, cb) {
    data = data || {};
    data.csrf = CSRF;
    fetch(BASE + url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    }).then(function (r) { return r.json(); })
      .then(function (j) { cb && cb(j); })
      .catch(function () { cb && cb({ ok: false, error: 'Jaringan bermasalah.' }); });
  }

  var pesanTeks = '', pesanGalat = false, pesanWaktu = 0;
  function pesan(t, galat) {
    pesanTeks = t; pesanGalat = !!galat; pesanWaktu = Date.now();
    var el = $('pesan-remote');
    if (!el) return;
    el.textContent = t;
    el.className = 'pesan ' + (galat ? 'galat' : '');
    el.style.display = 'block';
    clearTimeout(el._t);
    el._t = setTimeout(function () { el.style.display = 'none'; }, 2800);
  }

  function aksiKuis(action, data, konfirmasi) {
    if (konfirmasi && !confirm(konfirmasi)) return;
    data = data || {};
    data.action = action;
    api('/api/action_quiz.php', data, function (j) {
      if (j && j.ok) { state = j.state; render(); }
      else if (j) pesan(j.error || 'Gagal.', true);
    });
  }

  function aksiSetup(action, data) {
    data = data || {};
    data.action = action;
    api('/api/action_setup.php', data, function (j) {
      if (j && j.ok) { state = j.state; render(); }
      else if (j) pesan(j.error || 'Gagal.', true);
    });
  }

  /* ---------- Pairing ---------- */
  function cekStatus() {
    api('/api/pair.php', { action: 'status' }, function (j) {
      if (j && j.ok && j.paired) {
        paired = true;
        $('layar-pair').style.display = 'none';
        $('layar-kontrol').style.display = 'block';
        poll();
      }
    });
  }

  function pasang() {
    var kode = $('input-kode').value.trim().toUpperCase();
    if (!kode) { pesan('Masukkan kode dulu.', true); return; }
    api('/api/pair.php', { action: 'remote_pair', kode: kode }, function (j) {
      if (j && j.ok) {
        paired = true;
        $('layar-pair').style.display = 'none';
        $('layar-kontrol').style.display = 'block';
        poll();
      } else pesan((j && j.error) || 'Gagal pairing.', true);
    });
  }

  /* ---------- Polling state lengkap ---------- */
  function poll() {
    if (!paired) return;
    fetch(BASE + '/api/state.php?full=1&t=' + Date.now())
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.ok) { state = j.state; render(); }
      })
      .catch(function () {});
  }

  /* ---------- Render ---------- */
  var sigRender = '';
  function render(paksa) {
    if (!state) return;
    // Jangan gambar ulang jika tidak ada perubahan (hemat + anti-kedip).
    var sig = JSON.stringify(state) + '|' + JSON.stringify(daftarKategori);
    if (!paksa && sig === sigRender) return;
    // Jangan ganggu pengguna yang sedang mengetik/menggeser.
    var fokus = document.activeElement;
    if (!paksa && fokus && /INPUT|TEXTAREA|SELECT/.test(fokus.tagName) &&
        $('layar-kontrol').contains(fokus)) return;
    sigRender = sig;
    var h = '';
    h += kartuStatus();
    if (state.fase === 'setup') h += kartuSetup();
    else if (state.fase === 'kuis') h += kartuKuis();
    else h += kartuSelesai();
    h += kartuSuara();
    // Pertahankan pesan yang masih segar.
    var pj = '';
    if (pesanTeks && Date.now() - pesanWaktu < 2800) {
      pj = '<div id="pesan-remote" class="pesan ' + (pesanGalat ? 'galat' : '') + '">' + esc(pesanTeks) + '</div>';
    } else {
      pj = '<div id="pesan-remote" class="pesan" style="display:none"></div>';
    }
    $('layar-kontrol').innerHTML = pj + h;
    kait();
  }

  function namaAktif() {
    var t = (state.tim || [])[state.tim_aktif];
    return t ? t.nama : '-';
  }

  function kartuStatus() {
    var fase = { setup: '⚙️ Persiapan', kuis: '🎮 Kuis', selesai: '🏁 Selesai' }[state.fase] || state.fase;
    var h = '<div class="kartu"><h2>' + fase + '</h2><div class="status-ronde">';
    if (state.fase === 'kuis') {
      h += '<span>Ronde <b>' + state.ronde + '</b> (×' + state.pengali + ')</span>';
      h += '<span>🎯 Giliran <b>' + esc(namaAktif()) + '</b></span>';
      if (state.ronde_selesai) h += '<span>✅ <b>Ronde selesai</b></span>';
      h += '<span>⏱️ <b>' + timerTeks() + '</b> (otomatis)</span>';
    }
    h += '</div>';
    (state.tim || []).forEach(function (t, i) {
      var tandai = (state.fase === 'kuis' && i === state.tim_aktif) ? ' 🎯' : '';
      h += '<div>• ' + esc(t.nama) + tandai + ': <b>' + t.skor + '</b></div>';
    });
    h += '</div>';
    return h;
  }

  function timerTeks() {
    var tm = state.timer || {};
    return (tm.berjalan ? '▶ ' : '⏸ ') + (tm.sisa || 0) + ' dtk';
  }

  function kartuSetup() {
    var s = state.setup || {};
    var h = '<div class="kartu"><h2>Setup (cadangan)</h2>';
    h += '<p>Jumlah tim:</p><div class="pilih-tim">';
    [2, 3, 4].forEach(function (n) {
      h += '<button class="btn' + (s.jumlah_tim === n ? ' btn-primer' : '') +
        '" data-setup-jml="' + n + '">' + n + ' Tim</button>';
    });
    h += '</div><p>Nama tim:</p>';
    (s.tim || []).forEach(function (t, i) {
      h += '<div class="edit-nama"><input id="setup-nama-' + i + '" maxlength="20" value="' +
        esc(t.nama) + '"><button class="btn" data-setup-nama="' + i + '">OK</button></div>';
    });
    h += '<p>Kategori:</p><div class="pilih-tim" id="kat-remote">';
    daftarKategori.forEach(function (k) {
      h += '<button class="btn' + (s.kategori_id === k.id ? ' btn-emas' : '') +
        '" data-setup-kat="' + k.id + '">' + k.ikon + ' ' + esc(k.nama) + ' (' + k.jumlah_soal + ')</button>';
    });
    h += '</div><br><button class="btn btn-primer btn-besar" data-aksi="mulai_kuis_setup">🚀 MULAI KUIS</button></div>';
    return h;
  }

  function awalRonde() {
    if (state.ronde_selesai) return false;
    for (var i = 0; i < (state.jawaban || []).length; i++) {
      if (state.jawaban[i].terbuka) return false;
    }
    return true;
  }

  function kartuKuis() {
    var h = '';
    var denda = (state.pengaturan && state.pengaturan.denda_salah) || 0;

    // Giliran + papan jawaban (host mengetuk jawaban yang disebut tim)
    h += '<div class="kartu"><h2>🎯 Giliran: ' + esc(namaAktif()) + '</h2>';
    h += '<p>📋 ' + esc(state.pertanyaan || '') + '</p><div class="remote-grid">';
    (state.jawaban || []).forEach(function (jb, i) {
      var sudah = !!jb.terbuka;
      h += '<button class="btn tombol-jawab' + (sudah ? ' sudah' : '') + '" data-kuis-buka="' + i + '"' +
        (sudah || state.ronde_selesai ? ' disabled' : '') + '><span>' + (i + 1) + '. ' +
        esc(jb.teks) + ' <small>(+' + (jb.poin * state.pengali) + ')</small></span>' +
        '<span class="poin">' + (sudah ? '✓' : 'BENAR') + '</span></button>';
    });
    h += '</div></div>';

    // Salah / lewati
    h += '<div class="kartu"><h2>❌ / ⏭️ ' + esc(namaAktif()) + '</h2><div class="remote-grid">';
    h += '<button class="btn btn-bahaya btn-besar" data-aksi="jawab_salah"' +
      (state.ronde_selesai ? ' disabled' : '') + '>❌ SALAH (−' + denda + ' poin)</button>';
    h += '<button class="btn" data-aksi="lewat_giliran"' +
      (state.ronde_selesai ? ' disabled' : '') + '>⏭ Lewati giliran (tanpa denda)</button>';
    h += '</div></div>';

    // Tim mulai duluan (awal ronde)
    h += '<div class="kartu"><h2>🏁 Tim mulai duluan</h2><div class="pilih-tim">';
    (state.tim || []).forEach(function (t, i) {
      h += '<button class="btn' + (state.tim_main === i ? ' btn-emas' : '') +
        '" data-kuis-main="' + i + '"' + (awalRonde() ? '' : ' disabled') + '>' +
        esc(t.nama) + '</button>';
    });
    h += '</div>' + (awalRonde() ? '<small>Bisa diubah sebelum jawaban pertama dibuka.</small>' : '<small>Ronde berjalan — terkunci.</small>') + '</div>';

    // Ronde
    h += '<div class="kartu"><h2>⏭️ Ronde</h2><div class="remote-grid">';
    h += '<button class="btn btn-primer btn-besar" data-aksi="soal_berikutnya">Soal Berikutnya →</button>';
    h += '<button class="btn" data-aksi="akhiri_kuis">🏁 Akhiri & tampilkan podium</button>';
    h += '</div></div>';

    // Koreksi
    h += '<div class="kartu"><h2>✏️ Koreksi</h2>';
    (state.tim || []).forEach(function (t, i) {
      h += '<div class="koreksi"><span style="flex:1">' + esc(t.nama) +
        '</span><input type="number" id="skor-' + i + '" value="' + t.skor + '">' +
        '<button class="btn" data-kuis-skor="' + i + '">Set</button></div>';
      h += '<div class="edit-nama"><input id="nama-' + i + '" maxlength="20" value="' +
        esc(t.nama) + '"><button class="btn" data-kuis-nama="' + i + '">Nama OK</button></div>';
    });
    h += '<button class="btn" data-aksi="kembali_setup">↩ Kembali ke setup</button></div>';
    return h;
  }

  function kartuSelesai() {
    var tim = (state.tim || []).slice().sort(function (a, b) { return b.skor - a.skor; });
    var h = '<div class="kartu"><h2>🏆 Hasil akhir</h2>';
    tim.forEach(function (t, i) {
      h += '<div>' + ['🥇', '🥈', '🥉', '4️⃣'][i] + ' ' + esc(t.nama) + ': <b>' + t.skor + '</b></div>';
    });
    h += '<br><button class="btn btn-primer btn-besar" data-aksi="kembali_setup">↩ Main lagi (ke setup)</button></div>';
    return h;
  }

  function kartuSuara() {
    var p = state.pengaturan || {};
    var h = '<div class="kartu"><h2>🔊 Suara display</h2>';
    h += '<label>Volume: <b id="vol-label">' + (p.volume || 0) + '</b></label>';
    h += '<input type="range" class="volume" id="vol-range" min="0" max="100" value="' + (p.volume || 0) + '">';
    h += '<div class="pilih-tim"><button class="btn' + (p.musik ? ' btn-ok' : '') +
      '" data-aksi="musik_toggle">' + (p.musik ? '🎵 Musik: ON' : '🔇 Musik: OFF') + '</button></div></div>';
    return h;
  }

  /* ---------- Kait tombol ---------- */
  function kait() {
    function semua(sel, fn) {
      document.querySelectorAll(sel).forEach(fn);
    }
    semua('[data-aksi]', function (b) {
      b.onclick = function () {
        var a = b.getAttribute('data-aksi');
        if (a === 'mulai_kuis_setup') aksiSetup('mulai_kuis', {});
        else if (a === 'jawab_salah') aksiKuis('jawab_salah', {});
        else if (a === 'lewat_giliran') aksiKuis('lewat_giliran', {});
        else if (a === 'soal_berikutnya') {
          if (state.ronde_selesai) aksiKuis('soal_berikutnya', {});
          else aksiKuis('soal_berikutnya', { paksa: 1 }, 'Ronde belum selesai. Paksa lanjut ke soal berikutnya?');
        }
        else if (a === 'akhiri_kuis') aksiKuis('akhiri_kuis', {}, 'Akhiri kuis & tampilkan podium?');
        else if (a === 'kembali_setup') aksiKuis('kembali_setup', {}, 'Kembali ke setup?');
        else if (a === 'musik_toggle') aksiKuis('set_musik', { musik: !(state.pengaturan && state.pengaturan.musik) });
      };
    });
    semua('[data-kuis-buka]', function (b) {
      b.onclick = function () { aksiKuis('buka_jawaban', { nomor: parseInt(b.getAttribute('data-kuis-buka'), 10) }); };
    });
    semua('[data-kuis-main]', function (b) {
      b.onclick = function () { aksiKuis('set_tim_main', { tim: parseInt(b.getAttribute('data-kuis-main'), 10) }); };
    });
    semua('[data-kuis-skor]', function (b) {
      b.onclick = function () {
        var i = parseInt(b.getAttribute('data-kuis-skor'), 10);
        aksiKuis('koreksi_skor', { tim: i, skor: parseInt($('skor-' + i).value, 10) });
      };
    });
    semua('[data-kuis-nama]', function (b) {
      b.onclick = function () {
        var i = parseInt(b.getAttribute('data-kuis-nama'), 10);
        aksiKuis('edit_nama_tim', { tim: i, nama: $('nama-' + i).value });
      };
    });
    semua('[data-setup-jml]', function (b) {
      b.onclick = function () { aksiSetup('set_jumlah_tim', { jumlah: parseInt(b.getAttribute('data-setup-jml'), 10) }); };
    });
    semua('[data-setup-nama]', function (b) {
      b.onclick = function () {
        var i = parseInt(b.getAttribute('data-setup-nama'), 10);
        aksiSetup('set_nama_tim', { index: i, nama: $('setup-nama-' + i).value });
      };
    });
    semua('[data-setup-kat]', function (b) {
      b.onclick = function () { aksiSetup('pilih_kategori', { kategori_id: parseInt(b.getAttribute('data-setup-kat'), 10) }); };
    });
    var vr = $('vol-range');
    if (vr) {
      var deb = null;
      vr.oninput = function () {
        $('vol-label').textContent = vr.value;
        clearTimeout(deb);
        deb = setTimeout(function () { aksiKuis('set_volume', { volume: parseInt(vr.value, 10) }); }, 400);
      };
    }
  }

  function muatKategori() {
    fetch(BASE + '/api/state.php?action=kategori')
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j && j.ok) daftarKategori = j.kategori; });
  }

  document.addEventListener('DOMContentLoaded', function () {
    $('btn-pair').onclick = pasang;
    $('input-kode').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') pasang();
    });
    muatKategori();
    cekStatus();
    setInterval(poll, 1000);
    setInterval(muatKategori, 10000);
  });
})();
