/* =====================================================
   Skapsya100 — SFX sintesis Web Audio API (tanpa file audio)
   Pakai: SFX.klik(), SFX.tingBenar(), ... ; SFX.mulaiMusik()
   Catatan: browser memblokir autoplay → panggil SFX.buka()
   dari gestur pengguna (tombol "Aktifkan Suara").
   ===================================================== */
(function (global) {
  'use strict';

  var ctx = null;        // AudioContext (dibuat saat buka())
  var master = null;     // Gain utama (volume)
  var musikGain = null;  // Gain musik latar
  var terbuka = false;
  var volume = 0.8;      // 0..1
  var musikNyala = true;
  var musikTimer = null;
  var noiseBuf = null;

  function pastikan() {
    if (ctx) return true;
    try {
      var AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return false;
      ctx = new AC();
      master = ctx.createGain();
      master.gain.value = volume;
      master.connect(ctx.destination);
      musikGain = ctx.createGain();
      musikGain.gain.value = 0.16; // musik pelan di latar
      musikGain.connect(master);
      // Buffer noise putih (untuk tepuk/sorak/drum)
      var len = ctx.sampleRate * 1;
      noiseBuf = ctx.createBuffer(1, len, ctx.sampleRate);
      var d = noiseBuf.getChannelData(0);
      for (var i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
      terbuka = true;
      return true;
    } catch (e) { return false; }
  }

  // Nada osilator sederhana dengan amplop.
  function nada(freq, mulai, dur, tipe, vol, tujuan, slideKe) {
    if (!terbuka) return;
    var t = ctx.currentTime + (mulai || 0);
    var o = ctx.createOscillator();
    var g = ctx.createGain();
    o.type = tipe || 'sine';
    o.frequency.setValueAtTime(freq, t);
    if (slideKe) o.frequency.exponentialRampToValueAtTime(slideKe, t + dur);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(vol || 0.5, t + 0.015);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    o.connect(g);
    g.connect(tujuan || master);
    o.start(t);
    o.stop(t + dur + 0.05);
  }

  // Semburan noise (klik/drum/tepuk).
  function noise(mulai, dur, vol, filterFreq, tipeFilter, tujuan) {
    if (!terbuka) return;
    var t = ctx.currentTime + (mulai || 0);
    var s = ctx.createBufferSource();
    s.buffer = noiseBuf;
    s.loop = true;
    var f = ctx.createBiquadFilter();
    f.type = tipeFilter || 'bandpass';
    f.frequency.value = filterFreq || 2000;
    f.Q.value = 1;
    var g = ctx.createGain();
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(vol || 0.4, t + 0.01);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    s.connect(f); f.connect(g); g.connect(tujuan || master);
    s.start(t); s.stop(t + dur + 0.05);
  }

  // Deret nada (jingle).
  function deret(daftar, tipe, vol, tujuan) {
    daftar.forEach(function (n) { // [freq, mulai, dur]
      nada(n[0], n[1], n[2], tipe, vol, tujuan);
    });
  }

  var SFX = {
    // Wajib dipanggil dari klik pengguna. Kembalikan true jika audio siap.
    buka: function () {
      var ok = pastikan();
      if (ok && ctx.state === 'suspended') ctx.resume();
      return ok;
    },
    siap: function () { return terbuka; },

    setVolume: function (v) { // v: 0..100
      volume = Math.max(0, Math.min(100, v)) / 100;
      if (master) master.gain.value = volume;
    },
    setMusik: function (nyala) {
      musikNyala = !!nyala;
      if (musikNyala) this.mulaiMusik();
      else this.hentiMusik();
    },

    // --- Efek per kejadian ---
    klik: function () { // pop pendek tombol
      nada(520, 0, 0.09, 'square', 0.25);
      nada(780, 0.03, 0.08, 'sine', 0.3);
    },
    hover: function () { // tick halus
      nada(1200, 0, 0.04, 'sine', 0.12);
    },
    mulaiFanfare: function () { // whoosh + fanfare pendek
      noise(0, 0.5, 0.25, 900, 'highpass');
      deret([[392, 0.25, 0.18], [523, 0.43, 0.18], [659, 0.61, 0.3]], 'triangle', 0.5);
    },
    boing: function () { // nama tim masuk
      nada(160, 0, 0.35, 'sine', 0.5, null, 520);
    },
    popBurst: function () { // kategori dipilih
      noise(0, 0.12, 0.4, 3000, 'highpass');
      deret([[660, 0.02, 0.1], [880, 0.1, 0.12], [1320, 0.18, 0.16]], 'sine', 0.4);
    },
    dingDong: function () { // pertanyaan muncul
      nada(784, 0, 0.35, 'sine', 0.45);
      nada(587, 0.32, 0.5, 'sine', 0.45);
    },
    tikKetik: function () { // tick typewriter
      noise(0, 0.03, 0.12, 4000, 'highpass');
    },
    tingBenar: function () { // "Ting!" khas
      nada(1319, 0, 0.6, 'sine', 0.5);
      nada(1976, 0.02, 0.5, 'sine', 0.22);
    },
    tingTop: function () { // ting + sparkle naik
      this.tingBenar();
      deret([[1568, 0.25, 0.15], [2093, 0.38, 0.15], [2637, 0.51, 0.3]], 'sine', 0.3);
    },
    buzzerX: function () { // "eeeh" rendah
      nada(160, 0, 0.55, 'sawtooth', 0.4, null, 120);
      nada(110, 0, 0.55, 'square', 0.18, null, 90);
    },
    buzzer3X: function () { // buzzer panjang + thud
      nada(150, 0, 1.1, 'sawtooth', 0.45, null, 100);
      nada(70, 1.0, 0.5, 'sine', 0.7, null, 40);
      noise(1.0, 0.25, 0.4, 200, 'lowpass');
    },
    steal: function () { // drum roll + tegang
      for (var i = 0; i < 10; i++) noise(i * 0.09, 0.06, 0.3, 800 + i * 120, 'bandpass');
      nada(98, 0.9, 0.8, 'sawtooth', 0.3);
      nada(104, 0.9, 0.8, 'sawtooth', 0.3);
    },
    koin: function () { // 1 keping (dipanggil berderet saat count-up)
      nada(988, 0, 0.09, 'square', 0.18);
      nada(1319, 0.06, 0.18, 'square', 0.18);
    },
    tikTimer: function (cepat) { // tik-tik timer
      nada(cepat ? 1500 : 1000, 0, 0.05, 'square', 0.22);
    },
    rondeJingle: function () { // swoosh + jingle ronde baru
      noise(0, 0.4, 0.22, 1200, 'highpass');
      deret([[523, 0.3, 0.15], [659, 0.45, 0.15], [784, 0.6, 0.15], [1047, 0.75, 0.35]], 'triangle', 0.45);
    },
    fanfareMenang: function () { // fanfare + sorak & tepuk (noise)
      deret([
        [523, 0, 0.2], [523, 0.22, 0.2], [523, 0.44, 0.2], [659, 0.66, 0.35],
        [784, 1.1, 0.2], [659, 1.32, 0.2], [523, 1.54, 0.5]
      ], 'triangle', 0.5);
      // Sorak: noise lahat + tepuk ritmis
      noise(1.0, 1.6, 0.12, 1500, 'highpass');
      for (var i = 0; i < 8; i++) noise(1.0 + i * 0.28, 0.05, 0.35, 2500, 'bandpass');
    },

    // --- Musik latar: loop ceria (waltz sederhana C-F-G) ---
    mulaiMusik: function () {
      if (!terbuka || !musikNyala || musikTimer) return;
      var melodi = [523, 587, 659, 784, 659, 587, 523, 440, 523, 659, 523, 392];
      var langkah = 0;
      var main = function () {
        if (!musikNyala || !terbuka) return;
        var f = melodi[langkah % melodi.length];
        nada(f, 0, 0.32, 'triangle', 0.5, musikGain);
        if (langkah % 4 === 0) nada(f / 2, 0, 0.5, 'sine', 0.4, musikGain);
        langkah++;
      };
      main();
      musikTimer = setInterval(main, 360);
    },
    hentiMusik: function () {
      if (musikTimer) { clearInterval(musikTimer); musikTimer = null; }
    }
  };

  global.SFX = SFX;
})(window);
