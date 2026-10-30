/* =====================================================
   Skapsya100 — konfeti kanvas buatan sendiri (tanpa library)
   Pakai: Konfeti.ledakan(x, y, jumlah), Konfeti.hujan(detik),
          Konfeti.henti()
   ===================================================== */
(function (global) {
  'use strict';

  var kanvas = null, ctx = null;
  var partikel = [];
  var jalan = false;
  var raf = null;
  var WARNA = ['#ffd166', '#ef476f', '#06d6a0', '#118ab2', '#f78c6b', '#c8b6ff', '#ffffff'];

  function siap() {
    if (kanvas) return true;
    kanvas = document.getElementById('kanvas-konfeti');
    if (!kanvas) return false;
    ctx = kanvas.getContext('2d');
    function ukur() {
      kanvas.width = window.innerWidth;
      kanvas.height = window.innerHeight;
    }
    window.addEventListener('resize', ukur);
    ukur();
    return true;
  }

  function acak(a, b) { return a + Math.random() * (b - a); }

  function tambah(x, y, jumlah, sebar, naik) {
    for (var i = 0; i < jumlah; i++) {
      partikel.push({
        x: x, y: y,
        vx: acak(-sebar, sebar),
        vy: acak(-naik, sebar * 0.4),
        g: acak(0.12, 0.3),
        w: acak(6, 12),
        h: acak(8, 16),
        putar: acak(0, Math.PI * 2),
        vputar: acak(-0.25, 0.25),
        warna: WARNA[(Math.random() * WARNA.length) | 0],
        hidup: acak(60, 160)
      });
    }
    // Batasi agar ringan di laptop sekolah
    if (partikel.length > 900) partikel.splice(0, partikel.length - 900);
    putar();
  }

  function bingkai() {
    if (!jalan) return;
    ctx.clearRect(0, 0, kanvas.width, kanvas.height);
    var masih = [];
    for (var i = 0; i < partikel.length; i++) {
      var p = partikel[i];
      p.vy += p.g;
      p.vx *= 0.99;
      p.vy *= 0.995;
      p.x += p.vx;
      p.y += p.vy;
      p.putar += p.vputar;
      p.hidup--;
      if (p.hidup > 0 && p.y < kanvas.height + 30) {
        masih.push(p);
        ctx.save();
        ctx.translate(p.x, p.y);
        ctx.rotate(p.putar);
        ctx.fillStyle = p.warna;
        ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h * Math.abs(Math.cos(p.putar * 1.3)) + 2);
        ctx.restore();
      }
    }
    partikel = masih;
    if (partikel.length === 0) {
      jalan = false;
      ctx.clearRect(0, 0, kanvas.width, kanvas.height);
      return;
    }
    raf = requestAnimationFrame(bingkai);
  }

  function putar() {
    if (jalan || !siap()) return;
    jalan = true;
    raf = requestAnimationFrame(bingkai);
  }

  var hujanTimer = null;

  global.Konfeti = {
    // Ledakan dari satu titik (x, y piksel).
    ledakan: function (x, y, jumlah) {
      if (!siap()) return;
      tambah(x, y, jumlah || 80, 9, 13);
    },
    // Ledakan kecil (jawaban top).
    kecil: function (x, y) {
      if (!siap()) return;
      tambah(x, y, 40, 6, 9);
    },
    // Hujan dari atas selama `detik`.
    hujan: function (detik) {
      if (!siap()) return;
      var selesai = Date.now() + (detik || 3) * 1000;
      if (hujanTimer) clearInterval(hujanTimer);
      hujanTimer = setInterval(function () {
        tambah(acak(0, kanvas.width), -20, 12, 2, 1);
        if (Date.now() > selesai) { clearInterval(hujanTimer); hujanTimer = null; }
      }, 120);
    },
    henti: function () {
      jalan = false;
      partikel = [];
      if (raf) cancelAnimationFrame(raf);
      if (hujanTimer) { clearInterval(hujanTimer); hujanTimer = null; }
      if (ctx && kanvas) ctx.clearRect(0, 0, kanvas.width, kanvas.height);
    }
  };
})(window);
