/* Castello Casas de Madeira — main.js */
(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Nav: rolado + esconde ao descer ---------- */
  var nav = document.getElementById('nav');
  var hero = document.getElementById('topo');
  var lastY = window.scrollY;

  function onScroll() {
    var y = window.scrollY;
    if (y > 40) nav.classList.add('is-scrolled');
    else nav.classList.remove('is-scrolled');

    // esconde ao descer (depois do hero), mostra ao subir — nunca com drawer aberto
    if (!document.body.classList.contains('drawer-open')) {
      if (y > 640 && y > lastY + 6) nav.classList.add('is-hidden');
      else if (y < lastY - 6) nav.classList.remove('is-hidden');
    }
    lastY = y;
  }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  /* ---------- Drawer mobile ---------- */
  var burger = document.getElementById('burger');
  var drawer = document.getElementById('drawer');
  var backdrop = document.getElementById('drawerBackdrop');
  function toggleDrawer(open) {
    var willOpen = open !== undefined ? open : !drawer.classList.contains('is-open');
    drawer.classList.toggle('is-open', willOpen);
    burger.classList.toggle('is-active', willOpen);
    document.body.classList.toggle('drawer-open', willOpen);
    burger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    burger.setAttribute('aria-label', willOpen ? 'Fechar menu' : 'Abrir menu');
    drawer.setAttribute('aria-hidden', willOpen ? 'false' : 'true');
    if (willOpen) { nav.classList.remove('is-hidden'); backdrop.hidden = false; requestAnimationFrame(function () { backdrop.classList.add('is-open'); }); }
    else { backdrop.classList.remove('is-open'); setTimeout(function () { backdrop.hidden = true; }, 300); }
  }
  burger.addEventListener('click', function () { toggleDrawer(); });
  backdrop.addEventListener('click', function () { toggleDrawer(false); });
  drawer.querySelectorAll('a').forEach(function (a) {
    a.addEventListener('click', function () { toggleDrawer(false); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && drawer.classList.contains('is-open')) toggleDrawer(false);
  });

  /* ---------- Reveal on scroll ---------- */
  var reveals = document.querySelectorAll('.reveal');
  if (reduce || !('IntersectionObserver' in window)) {
    reveals.forEach(function (el) { el.classList.add('is-in'); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var el = entry.target;
          var sibs = Array.prototype.slice.call(el.parentNode.children).filter(function (c) {
            return c.classList.contains('reveal');
          });
          var idx = sibs.indexOf(el);
          el.style.transitionDelay = Math.min(idx, 6) * 75 + 'ms';
          el.classList.add('is-in');
          io.unobserve(el);
        }
      });
    }, { threshold: 0.14, rootMargin: '0px 0px -8% 0px' });
    reveals.forEach(function (el) { io.observe(el); });
  }

  /* ---------- Hero: parallax + fade no scroll ---------- */
  var heroMedia = document.getElementById('heroMedia');
  var heroContent = document.getElementById('heroContent');
  if (!reduce && heroMedia) {
    var ticking = false;
    window.addEventListener('scroll', function () {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(function () {
        var h = hero.offsetHeight;
        var p = Math.min(Math.max(window.scrollY / h, 0), 1);
        heroMedia.style.transform = 'translateY(' + (p * 60) + 'px)';
        if (heroContent) {
          heroContent.style.opacity = String(1 - p * 1.1);
          heroContent.style.transform = 'translateY(' + (p * -30) + 'px)';
        }
        ticking = false;
      });
    }, { passive: true });
  }

  /* ---------- Contadores ---------- */
  function animateCount(el) {
    var raw = el.getAttribute('data-count');
    var target = parseFloat(raw);
    var decimals = (raw.split('.')[1] || '').length;
    var suffix = el.getAttribute('data-suffix') || '';
    var prefix = el.getAttribute('data-prefix') || '';
    if (reduce) {
      el.textContent = prefix + target.toLocaleString('pt-BR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + suffix;
      return;
    }
    var start = null, dur = 1300;
    function step(ts) {
      if (!start) start = ts;
      var prog = Math.min((ts - start) / dur, 1);
      var eased = 1 - Math.pow(1 - prog, 3);
      var val = target * eased;
      el.textContent = prefix + val.toLocaleString('pt-BR', {
        minimumFractionDigits: decimals, maximumFractionDigits: decimals
      }) + suffix;
      if (prog < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  var counters = document.querySelectorAll('[data-count]');
  if (counters.length) {
    if (!('IntersectionObserver' in window)) {
      counters.forEach(animateCount);
    } else {
      var co = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) { animateCount(e.target); co.unobserve(e.target); }
        });
      }, { threshold: 0.6 });
      counters.forEach(function (el) { co.observe(el); });
    }
  }

  /* ---------- Scrollytelling: do terreno à chave ---------- */
  var steps = document.querySelectorAll('.process__step');
  var figs = document.querySelectorAll('.process__media-fig');
  var counter = document.getElementById('processCounter');
  function setActiveStep(idx) {
    steps.forEach(function (s) { s.classList.toggle('is-active', s.getAttribute('data-step') == idx); });
    figs.forEach(function (f) { f.classList.toggle('is-active', f.getAttribute('data-step') == idx); });
    if (counter) counter.innerHTML = '<span>0' + (Number(idx) + 1) + '</span> / 05';
  }
  if (steps.length && !reduce && 'IntersectionObserver' in window) {
    var so = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) setActiveStep(e.target.getAttribute('data-step'));
      });
    }, { threshold: 0, rootMargin: '-45% 0px -45% 0px' });
    steps.forEach(function (s) { so.observe(s); });
  }

  /* ---------- Float WhatsApp: aparece após o hero ---------- */
  var wpp = document.getElementById('wppFloat');
  if (wpp && 'IntersectionObserver' in window) {
    var wo = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        wpp.classList.toggle('is-visible', !e.isIntersecting);
      });
    }, { threshold: 0.15 });
    wo.observe(hero);
  } else if (wpp) {
    wpp.classList.add('is-visible');
  }

  /* ---------- Pausar vídeo do hero fora da viewport (quando houver) ---------- */
  var heroVideo = heroMedia ? heroMedia.querySelector('video') : null;
  if (heroVideo && 'IntersectionObserver' in window) {
    var vo = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) heroVideo.play().catch(function () {});
        else heroVideo.pause();
      });
    }, { threshold: 0.1 });
    vo.observe(hero);
  }
})();
