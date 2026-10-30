/* =====================================================
   Skapsya100 — Display proyektor (READ-ONLY saat kuis).
   Polling tiap 1 detik, mainkan tiap event TEPAT SATU KALI.
   ===================================================== */
(function () {
  'use strict';

  var BASE = document.body.getAttribute('data-base') || '';
  var token = localStorage.getItem('skapsya_token') || '';
  var lastSeq = 0;             // event terakhir yang sudah dimainkan
  var state = null;
  var mulaiDitekan = false;    // sudah klik MULAI (lokal)
  var langkahSetup = 0;        // 0 jumlah, 1 nama, 2 kategori
  var daftarKategori = [];
  var soalAktif = null;        // soal_id yang sedang tampil
  var animasiNomor = -1;       // nomor jawaban yang sedang dianimasikan
  var gerakKurangi = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var offsetWaktu = 0;         // selisih jam server - lokal (detik)

  function $(id) { return document.getElementById(id); }

  /* ---------- Bintang & awan latar ---------- */
  function hiasLatar() {
    var b = $('bintang-latar');
    if (b) {
      var h = '';
      for (var i = 0; i < 40; i++) {
        h += '<span style="left:' + (Math.random() * 100) + 'vw;top:' + (Math.random() * 100) +
             'vh;font-size:' + (1 + Math.random() * 2) + 'vmin;animation-delay:' +
             (-Math.random() * 3).toFixed(1) + 's">★</span>';
      }
      b.innerHTML = h;
    }
    var a = $('awan-latar');
    if (a) {
      var aw = '';
      for (var j = 0; j < 4; j++) {
        aw += '<span style="top:' + (5 + j * 22) + 'vh;animation-delay:' + (-j * 7) + 's">☁️</span>';
      }
      a.innerHTML = aw;
    }
  }

  /* ---------- API ---------- */
  function api(lempar, opsi, cb) {
    fetch(BASE + lempar, opsi).then(function (r) { return r.json(); })
      .then(function (j) { cb && cb(j); })
      .catch(function () { cb && cb({ ok: false, error: 'Jaringan?' }); });
  }

  function aksiSetup(action, data) {
    data = data || {};
    data.action = action;
    data.display_token = token;
    api('/api/action_setup.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    }, function (j) {
      if (j && j.ok && j.state) { state = j.state; render(); }
      else if (j && j.error) pesan(j.error);
    });
  }

  function pesan(t) {
    var el = $('pesan-display');
    if (!el) return;
    el.textContent = t;
    el.style.display = 'block';
    clearTimeout(el._t);
    el._t = setTimeout(function () { el.style.display = 'none'; }, 2600);
  }

  /* ---------- Polling ---------- */
  function poll() {
    if (!token) return;
    api('/api/state.php?token=' + encodeURIComponent(token) + '&t=' + Date.now(), {}, function (j) {
      if (!j || !j.ok) {
        // Reset token HANYA jika server menolak token (bukan saat jaringan putus).
        if (j && j.error === 'Token display tidak valid.') {
          token = '';
          localStorage.removeItem('skapsya_token');
          pasang();
        }
        return;
      }
      if (j.waktu_server) offsetWaktu = j.waktu_server - Math.floor(Date.now() / 1000);
      state = j.state;
      terapkanSuara();
      render();
      tanganiEvent();
    });
  }

  function terapkanSuara() {
    if (!state || !state.pengaturan || !window.SFX) return;
    SFX.setVolume(state.pengaturan.volume);
    if (state.pengaturan.musik) { if (SFX.siap()) SFX.mulaiMusik(); }
    else SFX.hentiMusik();
  }

  /* ---------- Pairing ---------- */
  function pasang() {
    api('/api/pair.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'display_init', token_lama: token })
    }, function (j) {
      if (j && j.ok) {
        token = j.token;
        localStorage.setItem('skapsya_token', token);
        $('kode-pair-teks').textContent = j.kode;
        poll();
      } else {
        $('kode-pair-teks').textContent = '····';
        setTimeout(pasang, 3000);
      }
    });
  }

  /* ---------- Tampil layar ---------- */
  var LAYAR = ['beranda', 'setup', 'kuis', 'selesai'];
  function tampil(nama) {
    LAYAR.forEach(function (l) {
      var el = $('layar-' + l);
      if (el) el.classList.toggle('tampil', l === nama);
    });
    // Reset tanda render agar layar baru selalu digambar penuh.
    sigSetup = ''; sigKategori = ''; sigBilah = ''; sigX = ''; sigSelesai = '';
  }

  function pintu(cb) {
    // Transisi pintu geser: tutup → ganti → buka
    var k1 = $('pintu-kiri'), k2 = $('pintu-kanan');
    k1.className = 'pintu kiri tutup-kiri';
    k2.className = 'pintu kanan tutup-kanan';
    setTimeout(function () {
      cb && cb();
      k1.className = 'pintu kiri buka-kiri';
      k2.className = 'pintu kanan buka-kanan';
      setTimeout(function () { k1.className = 'pintu kiri'; k2.className = 'pintu kanan'; }, 500);
    }, gerakKurangi ? 50 : 480);
  }

  /* ---------- Render utama ---------- */
  var faseTampil = '';
  function render() {
    if (!state) return;
    var fase = state.fase;
    if (!mulaiDitekan) { tampil('beranda'); faseTampil = 'beranda'; return; }

    if (fase === 'setup') {
      if (faseTampil !== 'setup') { tampil('setup'); faseTampil = 'setup'; }
      renderSetup();
    } else if (fase === 'kuis') {
      // Dari setup → kuis jangan langsung pindah; tunggu event ronde_baru (pintu geser).
      if (faseTampil === 'setup') return;
      if (faseTampil !== 'kuis') { tampil('kuis'); faseTampil = 'kuis'; soalAktif = null; }
      renderKuis();
    } else if (fase === 'selesai') {
      if (faseTampil !== 'selesai') { tampil('selesai'); faseTampil = 'selesai'; }
      renderSelesai();
    }
  }

  /* ---------- Setup ---------- */
  var sigSetup = '';
  function renderSetup(paksa) {
    var setup = state.setup || {};
    var jml = setup.jumlah_tim || 2;
    // Jangan bangun ulang tiap polling (agar fokus ketik & animasi tidak reset).
    var sig = langkahSetup + '|' + JSON.stringify(setup);
    if (!paksa && sig === sigSetup) return;
    sigSetup = sig;
    // Langkah 1: jumlah tim
    var wadahAngka = $('pilih-angka');
    wadahAngka.innerHTML = '';
    [2, 3, 4].forEach(function (n) {
      var b = document.createElement('button');
      b.className = 'kartu-angka' + (n === jml ? ' pilih' : '');
      b.textContent = n;
      b.onclick = function () { SFX.klik(); aksiSetup('set_jumlah_tim', { jumlah: n }); };
      wadahAngka.appendChild(b);
    });
    // Avatar pop-in
    var av = $('avatar-tim');
    av.innerHTML = '';
    (setup.tim || []).forEach(function (t, i) {
      var d = document.createElement('div');
      d.className = 'avatar anim-pop';
      d.style.background = t.warna;
      d.style.animationDelay = (i * 0.12) + 's';
      d.textContent = (t.nama || '?').trim().charAt(0).toUpperCase();
      av.appendChild(d);
    });
    // Langkah 2: nama
    var fn = $('form-nama');
    fn.innerHTML = '';
    (setup.tim || []).forEach(function (t, i) {
      var row = document.createElement('div');
      row.className = 'papan-nama anim-jatuh';
      row.style.animationDelay = (i * 0.15) + 's';
      row.innerHTML = '<span class="warna" style="background:' + t.warna + '"></span>';
      var inp = document.createElement('input');
      inp.maxLength = 20;
      inp.value = t.nama;
      inp.placeholder = 'Nama tim ' + (i + 1);
      var deb = null;
      inp.oninput = function () {
        clearTimeout(deb);
        deb = setTimeout(function () { aksiSetup('set_nama_tim', { index: i, nama: inp.value }); }, 500);
      };
      inp.onchange = function () { SFX.boing(); };
      row.appendChild(inp);
      fn.appendChild(row);
    });
    // Langkah 3: kategori
    renderKategori(setup.kategori_id);
    // Navigasi langkah
    [0, 1, 2].forEach(function (i) {
      $('setup-langkah-' + i).style.display = (i === langkahSetup) ? 'block' : 'none';
    });
    $('setup-info-langkah').textContent = 'Langkah ' + (langkahSetup + 1) + ' dari 3';
    $('btn-setup-kembali').style.visibility = langkahSetup === 0 ? 'hidden' : 'visible';
    $('btn-setup-lanjut').textContent = langkahSetup === 2 ? 'MULAI KUIS 🚀' : 'Lanjut →';
  }

  function muatKategori() {
    api('/api/state.php?action=kategori', {}, function (j) {
      if (j && j.ok) { daftarKategori = j.kategori; renderKategori(state && state.setup ? state.setup.kategori_id : null); }
    });
  }

  var sigKategori = '';
  function renderKategori(pilihId) {
    var g = $('grid-kategori');
    if (!g) return;
    var sig = pilihId + '|' + JSON.stringify(daftarKategori);
    if (sig === sigKategori) return;
    sigKategori = sig;
    g.innerHTML = '';
    daftarKategori.forEach(function (k) {
      var d = document.createElement('div');
      d.className = 'kartu-kategori' + (k.id === pilihId ? ' pilih' : '');
      d.innerHTML = '<div class="ikon">' + k.ikon + '</div><div class="nama">' +
        escapeHtml(k.nama) + '</div><div class="jml">' + k.jumlah_soal + ' soal</div>';
      d.onmouseenter = function () { if (SFX.siap()) SFX.hover(); };
      d.onclick = function (ev) {
        SFX.popBurst();
        aksiSetup('pilih_kategori', { kategori_id: k.id });
      };
      g.appendChild(d);
    });
    if (!daftarKategori.length) {
      g.innerHTML = '<p>Belum ada kategori aktif. Tambahkan di panel admin.</p>';
    }
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function setupLanjut() {
    SFX.klik();
    if (langkahSetup < 2) { langkahSetup++; renderSetup(); return; }
    // Mulai kuis!
    var katId = state.setup ? state.setup.kategori_id : null;
    if (!katId) { pesan('Pilih kategori dulu.'); return; }
    var kartu = document.querySelector('.kartu-kategori.pilih');
    if (kartu && !gerakKurangi) {
      var r = kartu.getBoundingClientRect();
      kartu.classList.add('meledak');
      Konfeti.ledakan(r.left + r.width / 2, r.top + r.height / 2, 120);
    }
    SFX.mulaiFanfare();
    setTimeout(function () {
      api('/api/action_setup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'mulai_kuis', display_token: token })
      }, function (j) {
        if (j && j.ok) { state = j.state; render(); tanganiEvent(); }
        else if (j) pesan(j.error || 'Gagal memulai.');
      });
    }, gerakKurangi ? 50 : 550);
  }

  /* ---------- Kuis ---------- */
  var sigBilah = '', sigX = '';
  function renderKuis() {
    // Tim + bank (bangun ulang hanya jika berubah, agar count-up tidak terpotong)
    var bilah = $('bilah-tim');
    var sb = JSON.stringify([state.tim, state.bank, state.pengali, state.tim_main]);
    if (sb !== sigBilah) {
      sigBilah = sb;
      bilah.innerHTML = '';
    (state.tim || []).forEach(function (t, i) {
      var d = document.createElement('div');
      d.className = 'kotak-tim' + (i === state.tim_main ? ' main' : '');
      d.id = 'kotak-tim-' + i;
      d.style.borderTopColor = t.warna;
      d.innerHTML = '<div class="panah">🔻</div><div class="nama">' + escapeHtml(t.nama) +
        '</div><div class="skor" id="skor-tim-' + i + '">' + t.skor + '</div>';
      bilah.appendChild(d);
    });
      var bank = document.createElement('div');
      bank.className = 'kotak-bank';
      bank.id = 'kotak-bank';
      bank.innerHTML = '<div class="label">BANK ×' + state.pengali + '</div><div class="nilai" id="nilai-bank">' +
        state.bank + '</div>';
      bilah.appendChild(bank);
    }

    // Label ronde
    var lr = $('label-ronde');
    lr.textContent = 'RONDE ' + state.ronde;

    // Pertanyaan (typewriter jika soal baru)
    var pq = $('teks-pertanyaan');
    if (soalAktif !== state.soal_id) {
      soalAktif = state.soal_id;
      ketik(pq, state.pertanyaan || '', function () {});
      var pp = $('papan-pertanyaan');
      pp.classList.remove('anim-turun');
      void pp.offsetWidth;
      pp.classList.add('anim-turun');
    } else if (pq.getAttribute('data-penuh') !== state.pertanyaan) {
      pq.textContent = state.pertanyaan || '';
      pq.setAttribute('data-penuh', state.pertanyaan || '');
    }

    // Papan jawaban
    var papan = $('papan-jawaban');
    // Bangun ulang hanya jika jumlah slot berubah
    if (papan.childElementCount !== (state.jawaban || []).length) {
      papan.innerHTML = '';
      (state.jawaban || []).forEach(function (jb, i) {
        papan.appendChild(buatSlot(i, jb));
      });
    }
    // Sinkron tiap slot (tanpa animasi; animasi lewat event)
    (state.jawaban || []).forEach(function (jb, i) {
      var slot = papan.children[i];
      if (!slot) return;
      if (jb.terbuka && i !== animasiNomor) bukaSlotStatis(slot, jb);
    });

    // Indikator X (bangun ulang hanya jika berubah)
    if (sigX !== 'x' + state.x + 's' + (state.steal ? state.steal.mode : '')) {
      sigX = 'x' + state.x + 's' + (state.steal ? state.steal.mode : '');
      var xi = $('indikator-x');
      xi.innerHTML = '';
      for (var k = 0; k < 3; k++) {
        var x = document.createElement('div');
        x.className = 'x-kecil' + (k < state.x ? ' nyala' : '');
        x.textContent = '✕';
        xi.appendChild(x);
      }
    }
  }

  function buatSlot(i, jb) {
    var s = document.createElement('div');
    s.className = 'slot-jawab' + (i === 0 ? ' emas' : '');
    s.id = 'slot-' + i;
    s.innerHTML =
      '<div class="kartu-jawab"><div class="muka depan"><span class="nomor">' + (i + 1) +
      '</span><span class="teks">• • •</span></div>' +
      '<div class="muka belakang kilau"><span class="nomor">' + (i + 1) +
      '</span><span class="teks"></span><span class="poin"></span></div></div>';
    if (jb.terbuka) bukaSlotStatis(s, jb);
    return s;
  }

  function bukaSlotStatis(slot, jb) {
    slot.classList.add('terbuka');
    var belakang = slot.querySelector('.belakang');
    belakang.querySelector('.teks').textContent = jb.teks || '';
    belakang.querySelector('.poin').textContent = jb.poin;
  }

  // Efek ketik huruf per huruf + bunyi tik.
  function ketik(el, teks, selesai) {
    el.setAttribute('data-penuh', teks);
    if (gerakKurangi) { el.textContent = teks; selesai && selesai(); return; }
    el.textContent = '';
    el.classList.add('kursor-ketik');
    var i = 0;
    var timer = setInterval(function () {
      i++;
      el.textContent = teks.slice(0, i);
      if (i % 2 === 0 && SFX.siap()) SFX.tikKetik();
      if (i >= teks.length) {
        clearInterval(timer);
        el.classList.remove('kursor-ketik');
        selesai && selesai();
      }
    }, 28);
  }

  // Count-up angka.
  function countUp(el, dari, ke, detik, tiapLangkah) {
    if (gerakKurangi || dari === ke) { el.textContent = ke; return; }
    var awal = performance.now();
    var dur = (detik || 0.8) * 1000;
    function fr(t) {
      var p = Math.min(1, (t - awal) / dur);
      var v = Math.round(dari + (ke - dari) * p);
      el.textContent = v;
      if (tiapLangkah && p < 1 && v !== dari) tiapLangkah();
      if (p < 1) requestAnimationFrame(fr);
      else el.textContent = ke;
    }
    requestAnimationFrame(fr);
  }

  /* ---------- Event (tepat satu kali) ---------- */
  var pollPertama = true;
  function tanganiEvent() {
    if (!state || !state.event) return;
    var ev = state.event;
    if (pollPertama) { // sinkron sunyi: jangan putar ulang event lama
      pollPertama = false;
      lastSeq = ev.seq;
      return;
    }
    if (ev.seq <= lastSeq) return;
    lastSeq = ev.seq;
    var tipe = ev.tipe, data = ev.data || {};
    if (tipe === 'ronde_baru') evRondeBaru(data);
    else if (tipe === 'benar') evBenar(data, false);
    else if (tipe === 'top') evBenar(data, true);
    else if (tipe === 'bank_masuk') evBankMasuk(data);
    else if (tipe === 'x') evX(data);
    else if (tipe === 'x3') evX3(data);
    else if (tipe === 'steal') evSteal(data);
    else if (tipe === 'steal_berhasil') evStealHasil(data, true);
    else if (tipe === 'steal_gagal') evStealHasil(data, false);
    else if (tipe === 'menang') evMenang(data);
    else if (tipe === 'ke_setup') { langkahSetup = 0; tampil('setup'); faseTampil = 'setup'; renderSetup(); }
  }

  function evRondeBaru(data) {
    SFX.rondeJingle();
    if (gerakKurangi) {
      tampil('kuis'); faseTampil = 'kuis'; soalAktif = null; renderKuis(); return;
    }
    pintu(function () {
      tampil('kuis'); faseTampil = 'kuis';
      soalAktif = null;
      renderKuis();
      var lr = $('label-ronde');
      lr.classList.remove('anim-ronde');
      void lr.offsetWidth;
      lr.classList.add('anim-ronde');
      SFX.dingDong();
    });
  }

  function evBenar(data, isTop) {
    var nomor = data.nomor;
    animasiNomor = nomor;
    var slot = $('slot-' + nomor);
    var jb = (state.jawaban || [])[nomor] || {};
    if (isTop) SFX.tingTop(); else SFX.tingBenar();
    // Flip 3D
    setTimeout(function () { if (slot) slot.classList.add('terbuka'); }, gerakKurangi ? 0 : 150);
    setTimeout(function () {
      if (!slot) { animasiNomor = -1; return; }
      var belakang = slot.querySelector('.belakang');
      var teksEl = belakang.querySelector('.teks');
      var poinEl = belakang.querySelector('.poin');
      // Kilau menyapu kartu
      belakang.classList.remove('sapu');
      void belakang.offsetWidth;
      belakang.classList.add('sapu');
      // Teks huruf per huruf + poin count-up
      ketik(teksEl, jb.teks || '', null);
      countUp(poinEl, 0, jb.poin || 0, 0.7, null);
      // Bank berdenyut + hitung naik
      var bankEl = $('nilai-bank');
      if (bankEl) {
        var bankBox = $('kotak-bank');
        bankBox.classList.remove('anim-bank');
        void bankBox.offsetWidth;
        bankBox.classList.add('anim-bank');
        countUp(bankEl, Math.max(0, state.bank - (data.poin || 0)), state.bank, 0.7, null);
      }
      // Partikel bintang dari kartu
      bintangDari(slot);
      if (isTop && !gerakKurangi) {
        // Jawaban #1: emas, goyang layar, konfeti kecil
        document.querySelector('.panggung').classList.add('anim-goyang');
        setTimeout(function () { document.querySelector('.panggung').classList.remove('anim-goyang'); }, 950);
        var r = slot.getBoundingClientRect();
        Konfeti.kecil(r.left + r.width / 2, r.top + r.height / 2);
      }
      animasiNomor = -1;
    }, gerakKurangi ? 0 : 600);
    renderKuis();
  }

  function bintangDari(slot) {
    if (gerakKurangi) return;
    var r = slot.getBoundingClientRect();
    for (var i = 0; i < 10; i++) {
      var s = document.createElement('div');
      s.className = 'bintang-efek';
      s.textContent = '★';
      s.style.color = ['#ffd166', '#ffffff', '#ffb300'][i % 3];
      s.style.left = (r.left + r.width / 2) + 'px';
      s.style.top = (r.top + r.height / 2) + 'px';
      s.style.setProperty('--dx', (Math.random() * 30 - 15) + 'vmin');
      s.style.setProperty('--dy', (Math.random() * -20 - 4) + 'vmin');
      document.body.appendChild(s);
      (function (el) { setTimeout(function () { el.remove(); }, 950); })(s);
    }
  }

  function evBankMasuk(data) {
    // Semua jawaban terbuka: animasikan kartu terakhir lalu terbangkan bank.
    var nomor = data.nomor;
    if (typeof nomor === 'number') {
      var slot = $('slot-' + nomor);
      if (slot) {
        animasiNomor = nomor;
        var jb = (state.jawaban || [])[nomor] || {};
        if (data.top) SFX.tingTop(); else SFX.tingBenar();
        setTimeout(function () { slot.classList.add('terbuka'); }, 150);
        setTimeout(function () {
          var belakang = slot.querySelector('.belakang');
          belakang.classList.add('sapu');
          ketik(belakang.querySelector('.teks'), jb.teks || '', null);
          countUp(belakang.querySelector('.poin'), 0, jb.poin || 0, 0.6, null);
          animasiNomor = -1;
          renderKuis();
          terbangBank(data.tim, data.bank);
        }, 650);
        return;
      }
    }
    terbangBank(data.tim, data.bank);
  }

  function terbangBank(timIdx, bank) {
    renderKuis();
    var dari = $('kotak-bank');
    var ke = $('kotak-tim-' + timIdx);
    if (!dari || !ke || gerakKurangi) {
      var sk = $('skor-tim-' + timIdx);
      if (sk) sk.textContent = state.tim[timIdx].skor;
      var nb = $('nilai-bank');
      if (nb) nb.textContent = state.bank;
      return;
    }
    var r1 = dari.getBoundingClientRect();
    var r2 = ke.querySelector('.skor').getBoundingClientRect();
    // Koin/angka terbang + bunyi koin berderet
    for (var i = 0; i < 6; i++) {
      (function (k) {
        setTimeout(function () {
          var koin = document.createElement('div');
          koin.className = 'koin-terbang';
          koin.textContent = '🪙+' + Math.round(bank / 6);
          koin.style.left = (r1.left + r1.width / 2) + 'px';
          koin.style.top = (r1.top + r1.height / 2) + 'px';
          document.body.appendChild(koin);
          SFX.koin();
          koin.animate([
            { transform: 'translate(0,0) scale(1)', opacity: 1 },
            { transform: 'translate(' + (r2.left + r2.width / 2 - r1.left - r1.width / 2) + 'px,' +
              (r2.top - r1.top) + 'px) scale(.6)', opacity: .9 }
          ], { duration: 700, easing: 'ease-in' }).onfinish = function () { koin.remove(); };
        }, k * 130);
      })(i);
    }
    setTimeout(function () {
      var sk = $('skor-tim-' + timIdx);
      if (sk) countUp(sk, Math.max(0, state.tim[timIdx].skor - bank), state.tim[timIdx].skor, 0.9, function () {});
      var nb = $('nilai-bank');
      if (nb) nb.textContent = state.bank;
    }, 900);
  }

  function evX(data) {
    SFX.buzzerX();
    var ov = $('overlay-x');
    var pg = document.querySelector('.panggung');
    if (!gerakKurangi) pg.classList.add('anim-goyang');
    ov.classList.add('tampil', 'kilat-merah');
    var xr = ov.querySelector('.x-raksasa');
    xr.classList.remove('anim-x-masuk', 'anim-x-keluar');
    void xr.offsetWidth;
    xr.classList.add('anim-x-masuk');
    setTimeout(function () {
      xr.classList.remove('anim-x-masuk');
      xr.classList.add('anim-x-keluar');
    }, gerakKurangi ? 50 : 1100);
    setTimeout(function () {
      ov.classList.remove('tampil', 'kilat-merah');
      pg.classList.remove('anim-goyang');
      renderKuis();
    }, gerakKurangi ? 100 : 1650);
    renderKuis();
  }

  function evX3(data) {
    SFX.buzzer3X();
    var ov = $('overlay-x3');
    var pg = document.querySelector('.panggung');
    pg.classList.add('anim-redup');
    ov.classList.add('tampil');
    setTimeout(function () {
      ov.classList.remove('tampil');
      pg.classList.remove('anim-redup');
      renderKuis(); // panah tim bergeser (tim_main tetap, steal oleh lawan)
    }, gerakKurangi ? 100 : 2200);
    renderKuis();
  }

  function evSteal(data) {
    SFX.steal();
    var ov = $('overlay-steal');
    ov.classList.add('tampil', 'gelap');
    // Sorot tim lawan: tandai sementara
    var timEl = $('kotak-tim-' + data.oleh);
    if (timEl) timEl.classList.add('main');
    setTimeout(function () {
      ov.classList.remove('tampil', 'gelap');
      renderKuis();
    }, gerakKurangi ? 100 : 2600);
  }

  function evStealHasil(data, berhasil) {
    if (berhasil) { SFX.tingTop(); Konfeti.hujan(2); }
    else SFX.buzzerX();
    terbangBank(data.tim, data.bank);
  }

  function evMenang(data) {
    tampil('selesai');
    faseTampil = 'selesai';
    renderSelesai();
    SFX.fanfareMenang();
    if (!gerakKurangi) Konfeti.hujan(6);
  }

  /* ---------- Layar selesai (podium) ---------- */
  var sigSelesai = '';
  function renderSelesai() {
    var sig = JSON.stringify(state.tim);
    if (sig === sigSelesai) return;
    sigSelesai = sig;
    var tim = (state.tim || []).map(function (t, i) {
      return { nama: t.nama, skor: t.skor, warna: t.warna, idx: i };
    }).sort(function (a, b) { return b.skor - a.skor; });
    var pod = $('podium');
    pod.innerHTML = '';
    // Urutan tampil: juara 2, juara 1, juara 3, juara 4
    var urutan = [];
    if (tim[1]) urutan.push({ t: tim[1], k: 'juara2' });
    if (tim[0]) urutan.push({ t: tim[0], k: 'juara1' });
    if (tim[2]) urutan.push({ t: tim[2], k: 'juara3' });
    if (tim[3]) urutan.push({ t: tim[3], k: 'juara4' });
    urutan.forEach(function (o, n) {
      var d = document.createElement('div');
      d.className = 'tingkat ' + o.k + (o.k === 'juara1' ? ' anim-juara' : ' anim-lesu');
      d.style.animationDelay = (n * 0.15) + 's';
      d.innerHTML = '<div style="font-size:3vmin">' + (o.k === 'juara1' ? '🏆' : '👏') + '</div>' +
        '<div class="t-nama">' + escapeHtml(o.t.nama) + '</div><div class="t-skor">' + o.t.skor + '</div>';
      pod.appendChild(d);
    });
    var menang = tim[0] ? tim[0].nama : '';
    $('judul-menang').textContent = '🏆 ' + menang + ' MENANG! 🏆';
  }

  /* ---------- Timer ---------- */
  var detikBunyi = -1;
  setInterval(function () {
    if (!state || state.fase !== 'kuis') return;
    var box = $('timer-box');
    var tm = state.timer || {};
    var sisa = tm.sisa || 0;
    if (tm.berjalan && tm.deadline) {
      var kini = Math.floor(Date.now() / 1000) + offsetWaktu;
      sisa = Math.max(0, tm.deadline - kini);
    }
    if (!tm.berjalan && sisa <= 0) { box.classList.remove('tampil'); detikBunyi = -1; return; }
    box.classList.add('tampil');
    var total = state.pengaturan.timer_detik || 30;
    var p = Math.max(0, sisa / total);
    var lingkar = 2 * Math.PI * 46;
    $('timer-arc').style.strokeDashoffset = lingkar * (1 - p);
    $('timer-angka').textContent = sisa;
    var bahaya = tm.berjalan && sisa <= 5 && sisa > 0;
    box.classList.toggle('timer-bahaya', bahaya);
    $('timer-arc').style.stroke = bahaya ? '#ff6b6b' : '#ffd166';
    if (bahaya && sisa !== detikBunyi && SFX.siap()) {
      detikBunyi = sisa;
      SFX.tikTimer(true);
    }
  }, 250);

  /* ---------- Tombol global ---------- */
  function init() {
    hiasLatar();
    muatKategori();
    $('btn-mulai').onclick = function () {
      SFX.buka();
      SFX.mulaiFanfare();
      mulaiDitekan = true;
      langkahSetup = 0;
      pintu(function () { tampil('setup'); faseTampil = 'setup'; renderSetup(); SFX.boing(); });
    };
    $('btn-suara').onclick = function () {
      var ok = SFX.buka();
      if (ok) {
        SFX.klik();
        SFX.mulaiMusik();
        this.textContent = '🔊 Suara Aktif';
        // Masuk layar penuh sekali
        var el = document.documentElement;
        if (el.requestFullscreen) el.requestFullscreen().catch(function () {});
      } else {
        pesan('Audio tidak didukung browser ini.');
      }
    };
    $('btn-setup-kembali').onclick = function () {
      SFX.klik();
      if (langkahSetup > 0) { langkahSetup--; renderSetup(); }
    };
    $('btn-setup-lanjut').onclick = setupLanjut;
    // Refresh kategori tiap poll setup (murah, hanya saat setup)
    setInterval(function () {
      if (faseTampil === 'setup' && mulaiDitekan) muatKategori();
    }, 5000);
    pasang();
    setInterval(poll, 1000);
  }

  document.addEventListener('DOMContentLoaded', init);
})();
