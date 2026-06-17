/* Castello Casas de Madeira — main.js */
(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function clamp(v, lo, hi) { return v < lo ? lo : v > hi ? hi : v; }

  /* ---------- Nav: rolado + esconde ao descer ---------- */
  var nav = document.getElementById('nav');
  var hero = document.getElementById('topo');
  var heroTrack = document.getElementById('heroTrack');
  var lastY = window.scrollY;

  // A nav fica clara/transparente durante todo o scrub do hero e só
  // solidifica depois que a trilha do hero é ultrapassada.
  function navThreshold() {
    return heroTrack ? heroTrack.offsetHeight - window.innerHeight - 80 : 40;
  }

  function onScroll() {
    var y = window.scrollY;
    var th = navThreshold();
    if (y > th) nav.classList.add('is-scrolled');
    else nav.classList.remove('is-scrolled');

    // esconde ao descer (depois do hero), mostra ao subir — nunca com drawer aberto
    if (!document.body.classList.contains('drawer-open')) {
      if (y > th + 60 && y > lastY + 6) nav.classList.add('is-hidden');
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

  /* ---------- Hero: scrub de vídeo dirigido pelo scroll ----------
     A trilha (.hero__track) tem ~280vh; o pin fica fixo. O progresso
     do scroll através da trilha dirige video.currentTime, e dois beats
     de texto cruzam no playhead (split em ~50% = ~3s de 6s).
     Implementação vanilla (sem libs): progresso pelo bounding rect +
     lerp em rAF para suavizar o seek do vídeo. */
  var heroVideo = document.getElementById('heroVideo');
  var beatOne = document.getElementById('heroBeatOne');
  var beatTwo = document.getElementById('heroBeatTwo');
  var statusFill = document.getElementById('heroStatusFill');
  var statusCurrent = document.getElementById('heroStatusCurrent');
  var SPLIT = 0.5;   // fração do scrub onde o texto troca
  var HALF = 0.07;   // meia janela do crossfade

  function applyBeats(p) {
    // beat 1 já visível no carregamento (p=0) e cruza para o beat 2 no split
    var cross = clamp((p - (SPLIT - HALF)) / (2 * HALF), 0, 1); // 0 antes do split, 1 depois
    var oneOp = 1 - cross;
    var twoOp = cross;
    if (beatOne) {
      beatOne.style.opacity = oneOp.toFixed(3);
      beatOne.classList.toggle('is-active', oneOp > 0.5);
    }
    if (beatTwo) {
      beatTwo.style.opacity = twoOp.toFixed(3);
      beatTwo.classList.toggle('is-active', twoOp > 0.5);
    }
    if (statusFill) statusFill.style.width = (p * 100).toFixed(1) + '%';
    if (statusCurrent) statusCurrent.textContent = p < SPLIT ? '01' : '02';
    if (p > 0.02) hero.classList.add('is-scrolled');
    else hero.classList.remove('is-scrolled');
  }

  if (heroTrack && heroVideo && !reduce) {
    var vReady = false;
    var vDur = 6;
    var targetT = 0, curT = 0, lastSeek = -1;

    heroVideo.addEventListener('loadedmetadata', function () { vDur = heroVideo.duration || 6; });
    heroVideo.addEventListener('loadeddata', function () {
      vReady = true;
      try { heroVideo.currentTime = 0.001; } catch (e) {}
      applyBeats(scrubProgress());
    });
    heroVideo.load();

    function scrubProgress() {
      var scrollable = heroTrack.offsetHeight - window.innerHeight;
      if (scrollable <= 0) return 0;
      var top = heroTrack.getBoundingClientRect().top;
      return clamp(-top / scrollable, 0, 1);
    }

    function seek(t) {
      if (Math.abs(t - lastSeek) < 0.012) return;
      try { heroVideo.currentTime = t; } catch (e) {}
      lastSeek = t;
    }

    function tick() {
      var p = scrubProgress();
      targetT = p * vDur;
      applyBeats(p);
      if (vReady) {
        var delta = targetT - curT;
        if (Math.abs(delta) > 0.001) {
          curT += delta * 0.35;            // lerp: alcança o alvo em ~3 frames
          seek(clamp(curT, 0, vDur - 0.001));
        }
      }
      requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
    applyBeats(scrubProgress());
  } else if (heroTrack) {
    // Reduced motion: ambos os beats visíveis, sem scrub.
    if (beatOne) { beatOne.style.opacity = 1; beatOne.classList.add('is-active'); }
    if (beatTwo) { beatTwo.style.opacity = 1; beatTwo.classList.add('is-active'); }
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

  /* ---------- Carrossel de avaliações ---------- */
  var revTrack = document.getElementById('reviewsTrack');
  var revPrev = document.getElementById('revPrev');
  var revNext = document.getElementById('revNext');
  if (revTrack && revPrev && revNext) {
    var autoTimer = null, autoEnabled = !reduce, inView = false, hovering = false;

    function cardStep() {
      var card = revTrack.querySelector('.review');
      if (!card) return revTrack.clientWidth;
      var gap = parseFloat(getComputedStyle(revTrack).columnGap) || 20;
      return card.getBoundingClientRect().width + gap;
    }
    function updateArrows() {
      var max = revTrack.scrollWidth - revTrack.clientWidth - 2;
      revPrev.disabled = revTrack.scrollLeft <= 2;
      revNext.disabled = revTrack.scrollLeft >= max;
    }
    function pauseAuto() { if (autoTimer) { clearInterval(autoTimer); autoTimer = null; } }
    function maybeStart() { if (autoEnabled && inView && !hovering && !autoTimer) autoTimer = setInterval(autoTick, 4800); }
    function killAuto() { autoEnabled = false; pauseAuto(); }   // usuário assumiu o controle
    function autoTick() {
      var max = revTrack.scrollWidth - revTrack.clientWidth - 2;
      if (revTrack.scrollLeft >= max) revTrack.scrollTo({ left: 0, behavior: 'smooth' });
      else revTrack.scrollBy({ left: cardStep(), behavior: 'smooth' });
    }

    revPrev.addEventListener('click', function () { killAuto(); revTrack.scrollBy({ left: -cardStep(), behavior: 'smooth' }); });
    revNext.addEventListener('click', function () { killAuto(); revTrack.scrollBy({ left: cardStep(), behavior: 'smooth' }); });
    revTrack.addEventListener('scroll', updateArrows, { passive: true });
    window.addEventListener('resize', updateArrows);
    updateArrows();

    // arrastar para rolar (pointer)
    var down = false, startX = 0, startScroll = 0;
    revTrack.addEventListener('pointerdown', function (e) {
      if (e.pointerType === 'mouse' && e.button !== 0) return;
      down = true; startX = e.clientX; startScroll = revTrack.scrollLeft;
      revTrack.classList.add('is-dragging');
    });
    revTrack.addEventListener('pointermove', function (e) {
      if (!down) return;
      var dx = e.clientX - startX;
      if (Math.abs(dx) > 4) killAuto();
      revTrack.scrollLeft = startScroll - dx;
    });
    function endDrag() { if (!down) return; down = false; revTrack.classList.remove('is-dragging'); }
    revTrack.addEventListener('pointerup', endDrag);
    revTrack.addEventListener('pointercancel', endDrag);
    revTrack.addEventListener('pointerleave', endDrag);

    // parar autoplay em interação direta; pausar em hover/foco
    ['wheel', 'touchstart', 'keydown'].forEach(function (ev) {
      revTrack.addEventListener(ev, killAuto, { passive: true });
    });
    revTrack.addEventListener('mouseenter', function () { hovering = true; pauseAuto(); });
    revTrack.addEventListener('mouseleave', function () { hovering = false; maybeStart(); });
    revTrack.addEventListener('focusin', pauseAuto);

    if ('IntersectionObserver' in window) {
      var rvo = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) { inView = e.isIntersecting; if (inView) maybeStart(); else pauseAuto(); });
      }, { threshold: 0.35 });
      rvo.observe(revTrack);
    } else { inView = true; maybeStart(); }
  }

  /* ---------- Scroll-spy: destaca o link da seção atual ---------- */
  var spyLinks = Array.prototype.slice.call(document.querySelectorAll('.nav__links a[href^="#"]'));
  var spyMap = {};
  var spyTargets = [];
  spyLinks.forEach(function (a) {
    var id = a.getAttribute('href').slice(1);
    var sec = document.getElementById(id);
    if (sec) { spyMap[id] = a; spyTargets.push(sec); }
  });
  if (spyTargets.length && 'IntersectionObserver' in window) {
    var current = null;
    function setCurrent(id) {
      if (id === current) return;
      current = id;
      spyLinks.forEach(function (a) {
        a.classList.toggle('is-current', a.getAttribute('href') === '#' + id);
      });
    }
    var spyObs = new IntersectionObserver(function (entries) {
      // escolhe a seção mais visível no centro da viewport
      var best = null, bestRatio = 0;
      entries.forEach(function (e) {
        if (e.isIntersecting && e.intersectionRatio > bestRatio) {
          bestRatio = e.intersectionRatio; best = e.target;
        }
      });
      if (best) setCurrent(best.id);
    }, { rootMargin: '-45% 0px -45% 0px', threshold: [0, 0.25, 0.5, 1] });
    spyTargets.forEach(function (s) { spyObs.observe(s); });
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

})();
