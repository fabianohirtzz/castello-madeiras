/* Castello Casas de Madeira — main.js */
(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  // O scrub por currentTime roda igual no desktop e no mobile. O vídeo é
  // codificado all-intra (todo frame é keyframe), então o seek é O(1) e o
  // iOS Safari renderiza cada frame sem precisar de play() — mesmo padrão
  // usado no projeto Quarezemin.

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
    // No celular o scrub usa uma versao menor do video (2,5 MB em vez de 8,9).
    // O preload nasce em none e vira auto so aqui, entao a troca acontece antes de
    // qualquer requisicao do video: um arquivo so e baixado.
    var heroSource = heroVideo.querySelector('source');
    if (heroSource && heroSource.getAttribute('data-src-mobile') && window.matchMedia('(max-width: 760px)').matches) {
      heroSource.setAttribute('src', heroSource.getAttribute('data-src-mobile'));
    }
    heroVideo.preload = 'auto';
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
      // Não dispara um novo seek enquanto o anterior não terminou. Em load
      // frio (vídeo não decodificado), um seek por frame empilha e trava o
      // vídeo em seeking=true — o frame congela. Esperar o seek concluir
      // faz o scrub coalescer sempre para a posição de scroll mais recente.
      if (heroVideo.seeking) return;
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
  var activeStepIdx = -1;
  function setActiveStep(idx) {
    if (idx === activeStepIdx) return;
    activeStepIdx = idx;
    steps.forEach(function (s) { s.classList.toggle('is-active', s.getAttribute('data-step') == idx); });
    figs.forEach(function (f) { f.classList.toggle('is-active', f.getAttribute('data-step') == idx); });
    if (counter) counter.innerHTML = '<span>0' + (Number(idx) + 1) + '</span> / 05';
  }
  if (steps.length && !reduce) {
    // Dirigido pelo scroll (não por IntersectionObserver): a cada frame escolhe
    // o passo cujo centro está mais perto do centro da viewport. No iOS o scroll
    // por inércia pula centenas de px entre amostras do IO e a banda fina de
    // detecção era ignorada, travando a imagem no passo 01. Amostrar por scroll
    // é à prova de pulos e funciona igual em todos os dispositivos.
    var stepArr = Array.prototype.slice.call(steps);
    var stepTick = false;
    function pickStep() {
      stepTick = false;
      var mid = window.innerHeight / 2;
      var best = stepArr[0].getAttribute('data-step'), bestDist = Infinity;
      stepArr.forEach(function (s) {
        var r = s.getBoundingClientRect();
        var d = Math.abs((r.top + r.bottom) / 2 - mid);
        if (d < bestDist) { bestDist = d; best = s.getAttribute('data-step'); }
      });
      setActiveStep(best);
    }
    function onStepScroll() { if (!stepTick) { stepTick = true; requestAnimationFrame(pickStep); } }
    window.addEventListener('scroll', onStepScroll, { passive: true });
    window.addEventListener('resize', onStepScroll);
    pickStep();
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

  /* ---------- Carrossel de vantagens (um card por vez) ---------- */
  var whyCar = document.getElementById('whyCarousel');
  if (whyCar) {
    var whySlides = Array.prototype.slice.call(whyCar.querySelectorAll('.why__slide'));
    var whyPrev = document.getElementById('whyPrev');
    var whyNext = document.getElementById('whyNext');
    var whyDots = document.getElementById('whyDots');
    var whyIdx = 0;
    var whyN = whySlides.length;
    var whyTimer = null, whyAuto = !reduce, whyInView = false, whyHover = false;

    // monta os indicadores (dots)
    var dotEls = whySlides.map(function (s, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'why__dot' + (i === 0 ? ' is-active' : '');
      b.setAttribute('role', 'tab');
      b.setAttribute('aria-label', 'Vantagem ' + (i + 1) + ' de ' + whyN);
      b.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
      b.addEventListener('click', function () { stopWhyAuto(); goWhy(i); });
      whyDots.appendChild(b);
      return b;
    });

    function renderWhy() {
      whySlides.forEach(function (s, i) { s.classList.toggle('is-active', i === whyIdx); });
      dotEls.forEach(function (d, i) {
        var on = i === whyIdx;
        d.classList.toggle('is-active', on);
        d.setAttribute('aria-selected', on ? 'true' : 'false');
      });
    }
    function goWhy(i) { whyIdx = (i + whyN) % whyN; renderWhy(); }
    function nextWhy() { goWhy(whyIdx + 1); }
    function prevWhy() { goWhy(whyIdx - 1); }

    function whyTick() { goWhy(whyIdx + 1); }
    function startWhyAuto() { if (whyAuto && whyInView && !whyHover && !whyTimer) whyTimer = setInterval(whyTick, 5200); }
    function pauseWhyAuto() { if (whyTimer) { clearInterval(whyTimer); whyTimer = null; } }
    function stopWhyAuto() { whyAuto = false; pauseWhyAuto(); }   // usuário assumiu o controle

    whyNext.addEventListener('click', function () { stopWhyAuto(); nextWhy(); });
    whyPrev.addEventListener('click', function () { stopWhyAuto(); prevWhy(); });

    // teclado: setas quando o carrossel está focado
    whyCar.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { stopWhyAuto(); nextWhy(); }
      else if (e.key === 'ArrowLeft') { stopWhyAuto(); prevWhy(); }
    });

    // swipe / arrastar
    var wDown = false, wX = 0, wMoved = false;
    whyCar.addEventListener('pointerdown', function (e) {
      if (e.pointerType === 'mouse' && e.button !== 0) return;
      wDown = true; wX = e.clientX; wMoved = false;
    });
    whyCar.addEventListener('pointermove', function (e) {
      if (wDown && Math.abs(e.clientX - wX) > 8) wMoved = true;
    });
    function whyEnd(e) {
      if (!wDown) return; wDown = false;
      var dx = e.clientX - wX;
      if (Math.abs(dx) > 45) { stopWhyAuto(); dx < 0 ? nextWhy() : prevWhy(); }
    }
    whyCar.addEventListener('pointerup', whyEnd);
    whyCar.addEventListener('pointercancel', function () { wDown = false; });

    // pausa em hover/foco
    whyCar.addEventListener('mouseenter', function () { whyHover = true; pauseWhyAuto(); });
    whyCar.addEventListener('mouseleave', function () { whyHover = false; startWhyAuto(); });
    whyCar.addEventListener('focusin', pauseWhyAuto);
    whyCar.addEventListener('focusout', function () { if (!whyHover) startWhyAuto(); });

    if ('IntersectionObserver' in window) {
      var whyObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) { whyInView = e.isIntersecting; if (whyInView) startWhyAuto(); else pauseWhyAuto(); });
      }, { threshold: 0.4 });
      whyObs.observe(whyCar);
    } else { whyInView = true; startWhyAuto(); }

    renderWhy();
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

  /* ---------- Galeria do portfólio: grade + foto em tela cheia ---------- */
  var galeria = document.getElementById('galeria');
  var fotobox = document.getElementById('fotobox');
  if (galeria && fotobox) {
    var fotos = [].slice.call(galeria.querySelectorAll('.galeria__item'));
    var fbImg = document.getElementById('fotoboxImg');
    var fbCat = document.getElementById('fotoboxCat');
    var fbTitle = document.getElementById('fotoboxTitle');
    var fbCount = document.getElementById('fotoboxCount');
    var fbIdx = 0, fbLast = null;

    function showFoto(i) {
      fbIdx = (i + fotos.length) % fotos.length;
      var item = fotos[fbIdx];
      var img = item.querySelector('img');
      fbImg.src = img.getAttribute('src');
      fbImg.alt = img.getAttribute('alt') || '';
      fbCat.textContent = item.getAttribute('data-categoria') || '';
      fbTitle.textContent = item.getAttribute('data-titulo') || '';
      fbCount.textContent = (fbIdx + 1) + ' / ' + fotos.length;
    }
    function openFoto(i, trigger) {
      fbLast = trigger || null;
      fotobox.hidden = false;
      document.body.classList.add('modal-open');
      requestAnimationFrame(function () { fotobox.classList.add('is-open'); });
      showFoto(i);
      setTimeout(function () { document.getElementById('fotoboxClose').focus({ preventScroll: true }); }, 60);
    }
    function closeFoto() {
      if (fotobox.hidden) return;
      fotobox.classList.remove('is-open');
      document.body.classList.remove('modal-open');
      setTimeout(function () { fotobox.hidden = true; fbImg.removeAttribute('src'); }, 300);
      if (fbLast) fbLast.focus({ preventScroll: true });
    }

    fotos.forEach(function (item, i) {
      item.addEventListener('click', function () { openFoto(i, item); });
    });
    document.getElementById('fotoboxClose').addEventListener('click', closeFoto);
    document.getElementById('fotoboxPrev').addEventListener('click', function () { showFoto(fbIdx - 1); });
    document.getElementById('fotoboxNext').addEventListener('click', function () { showFoto(fbIdx + 1); });
    fotobox.addEventListener('click', function (e) { if (e.target === fotobox || e.target.classList.contains('fotobox__stage')) closeFoto(); });
    document.addEventListener('keydown', function (e) {
      if (fotobox.hidden) return;
      if (e.key === 'Escape') closeFoto();
      else if (e.key === 'ArrowRight') showFoto(fbIdx + 1);
      else if (e.key === 'ArrowLeft') showFoto(fbIdx - 1);
    });
    // swipe horizontal
    var fx = 0, fy = 0, fTracking = false;
    fotobox.addEventListener('touchstart', function (e) {
      if (e.touches.length !== 1) return;
      fTracking = true; fx = e.touches[0].clientX; fy = e.touches[0].clientY;
    }, { passive: true });
    fotobox.addEventListener('touchend', function (e) {
      if (!fTracking) return;
      fTracking = false;
      var t = e.changedTouches[0];
      var dx = t.clientX - fx, dy = t.clientY - fy;
      if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.4) showFoto(fbIdx + (dx < 0 ? 1 : -1));
    }, { passive: true });
  }

  /* ---------- FAQ: tabs verticais (uma aba ativa por vez) ---------- */
  var faqTabs = document.getElementById('faqTabs');
  if (faqTabs) {
    var tabs = [].slice.call(faqTabs.querySelectorAll('.faq__tab'));
    var panels = [].slice.call(faqTabs.querySelectorAll('.faq__content'));

    function selectTab(idx, focus) {
      tabs.forEach(function (tab, i) {
        var on = i === idx;
        tab.classList.toggle('is-active', on);
        tab.setAttribute('aria-selected', on ? 'true' : 'false');
        tab.setAttribute('tabindex', on ? '0' : '-1');
        if (panels[i]) {
          panels[i].classList.toggle('is-active', on);
          if (on) { panels[i].removeAttribute('hidden'); }
          else { panels[i].setAttribute('hidden', ''); }
        }
      });
      if (focus && tabs[idx]) tabs[idx].focus();
    }

    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () { selectTab(i, false); });
      tab.addEventListener('keydown', function (e) {
        var n = tabs.length, next = null;
        if (e.key === 'ArrowDown' || e.key === 'ArrowRight') next = (i + 1) % n;
        else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') next = (i - 1 + n) % n;
        else if (e.key === 'Home') next = 0;
        else if (e.key === 'End') next = n - 1;
        if (next !== null) { e.preventDefault(); selectTab(next, true); }
      });
    });
  }

  /* ---------- Galeria de vídeos do Instagram ---------- */
  var instaRail = document.getElementById('instaRail');
  if (instaRail) {
    var ICO_PLAY  = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>';
    var ICO_PAUSE = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z"/></svg>';
    var ICO_VOL   = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9H4zm12.5 3a3.5 3.5 0 0 0-2-3.16v6.32A3.5 3.5 0 0 0 16.5 12zM14 3.23v2.06a6 6 0 0 1 0 13.42v2.06a8 8 0 0 0 0-17.54z"/></svg>';
    var ICO_MUTE  = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9H4zm17.3.3-1.4-1.4L17.5 10.3 15.1 7.9l-1.4 1.4L16.1 11.7l-2.4 2.4 1.4 1.4 2.4-2.4 2.4 2.4 1.4-1.4-2.4-2.4z"/></svg>';

    var ICO_FULL  = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v2H6v4H4V4Zm10 0h6v6h-2V6h-4V4ZM4 14h2v4h4v2H4v-6Zm14 0h2v6h-6v-2h4v-4Z"/></svg>';

    var cards = Array.prototype.slice.call(instaRail.querySelectorAll('.ivid'));
    var videos = [];

    /* ---- tela cheia (lightbox) com navegação entre os reels ---- */
    var rbox = document.getElementById('reelbox');
    var rboxVideo = document.getElementById('reelboxVideo');
    var rboxCount = document.getElementById('reelboxCount');
    var rboxIdx = 0, rboxLast = null;

    function srcOf(i) {
      var s = cards[i] && cards[i].querySelector('source');
      return s ? s.getAttribute('src') : '';
    }
    function posterOf(i) {
      var v = cards[i] && cards[i].querySelector('.ivid__video');
      return v ? v.getAttribute('poster') : '';
    }
    function loadReel(i) {
      rboxIdx = (i + cards.length) % cards.length;
      rboxVideo.setAttribute('poster', posterOf(rboxIdx));
      rboxVideo.src = srcOf(rboxIdx);
      rboxVideo.muted = false;
      rboxVideo.volume = 1;
      if (rboxCount) rboxCount.textContent = (rboxIdx + 1) + ' / ' + cards.length;
      var p = rboxVideo.play();
      if (p && p.catch) p.catch(function () {});
    }
    function openReel(i, trigger) {
      if (!rbox) return;
      rboxLast = trigger || null;
      videos.forEach(function (v) { if (!v.paused) v.pause(); });   // pausa o rail
      rbox.hidden = false;
      document.body.classList.add('modal-open');
      requestAnimationFrame(function () { rbox.classList.add('is-open'); });
      loadReel(i);
    }
    function closeReel() {
      if (!rbox || rbox.hidden) return;
      rbox.classList.remove('is-open');
      rboxVideo.pause();
      document.body.classList.remove('modal-open');
      setTimeout(function () {
        rbox.hidden = true;
        rboxVideo.removeAttribute('src');
        rboxVideo.load();
      }, 300);
      if (rboxLast) rboxLast.focus();
    }

    if (rbox) {
      document.getElementById('reelboxClose').addEventListener('click', closeReel);
      document.getElementById('reelboxPrev').addEventListener('click', function () { loadReel(rboxIdx - 1); });
      document.getElementById('reelboxNext').addEventListener('click', function () { loadReel(rboxIdx + 1); });
      rbox.addEventListener('click', function (e) { if (e.target === rbox) closeReel(); });
      document.addEventListener('keydown', function (e) {
        if (rbox.hidden) return;
        if (e.key === 'Escape') closeReel();
        else if (e.key === 'ArrowRight') loadReel(rboxIdx + 1);
        else if (e.key === 'ArrowLeft') loadReel(rboxIdx - 1);
      });
      // swipe horizontal no palco
      var sx = 0, sy = 0, sTracking = false;
      var stage = rbox.querySelector('.reelbox__stage');
      stage.addEventListener('touchstart', function (e) {
        if (e.touches.length !== 1) return;
        sTracking = true; sx = e.touches[0].clientX; sy = e.touches[0].clientY;
      }, { passive: true });
      stage.addEventListener('touchend', function (e) {
        if (!sTracking) return;
        sTracking = false;
        var t = e.changedTouches[0];
        var dx = t.clientX - sx, dy = t.clientY - sy;
        if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.4) loadReel(rboxIdx + (dx < 0 ? 1 : -1));
      }, { passive: true });
    }

    cards.forEach(function (card, cardIdx) {
      var frame = card.querySelector('.ivid__frame');
      var video = card.querySelector('.ivid__video');
      if (!frame || !video) return;
      videos.push(video);
      video.volume = 1;

      // botão central
      var big = document.createElement('button');
      big.type = 'button';
      big.className = 'ivid__big';
      big.setAttribute('aria-label', 'Reproduzir vídeo');
      big.innerHTML = '<span class="ivid__big-ico">' + ICO_PLAY + '</span>';

      // barra de controles
      var bar = document.createElement('div');
      bar.className = 'ivid__bar';
      var toggle = document.createElement('button');
      toggle.type = 'button'; toggle.className = 'ivid__ctrl ivid__toggle';
      toggle.setAttribute('aria-label', 'Reproduzir'); toggle.innerHTML = ICO_PLAY;
      var mute = document.createElement('button');
      mute.type = 'button'; mute.className = 'ivid__ctrl ivid__mute';
      mute.setAttribute('aria-label', 'Desativar som'); mute.innerHTML = ICO_VOL;
      var vol = document.createElement('input');
      vol.type = 'range'; vol.className = 'ivid__vol';
      vol.min = '0'; vol.max = '1'; vol.step = '0.05'; vol.value = '1';
      vol.setAttribute('aria-label', 'Volume');
      bar.appendChild(toggle); bar.appendChild(mute); bar.appendChild(vol);

      // botão de tela cheia (abre o lightbox no vídeo deste card)
      var expand = document.createElement('button');
      expand.type = 'button';
      expand.className = 'ivid__expand';
      expand.setAttribute('aria-label', 'Ver em tela cheia');
      expand.innerHTML = ICO_FULL;
      expand.addEventListener('click', function (e) {
        e.stopPropagation();
        openReel(cardIdx, expand);
      });

      frame.appendChild(big);
      frame.appendChild(bar);
      if (rbox) frame.appendChild(expand);

      function playThis() {
        videos.forEach(function (v) { if (v !== video && !v.paused) v.pause(); });
        var p = video.play();
        if (p && p.catch) p.catch(function () {});
      }
      function refresh() {
        var playing = !video.paused && !video.ended;
        card.classList.toggle('is-playing', playing);
        toggle.innerHTML = playing ? ICO_PAUSE : ICO_PLAY;
        toggle.setAttribute('aria-label', playing ? 'Pausar' : 'Reproduzir');
        big.querySelector('.ivid__big-ico').innerHTML = playing ? ICO_PAUSE : ICO_PLAY;
        big.setAttribute('aria-label', playing ? 'Pausar vídeo' : 'Reproduzir vídeo');
      }
      function refreshMute() {
        var muted = video.muted || video.volume === 0;
        mute.innerHTML = muted ? ICO_MUTE : ICO_VOL;
        mute.setAttribute('aria-label', muted ? 'Ativar som' : 'Desativar som');
        if (!muted) vol.value = String(video.volume);
      }

      function togglePlay() { if (video.paused) playThis(); else video.pause(); }
      big.addEventListener('click', togglePlay);
      toggle.addEventListener('click', togglePlay);

      mute.addEventListener('click', function () {
        if (video.muted || video.volume === 0) {
          video.muted = false;
          if (video.volume === 0) { video.volume = 1; }
        } else {
          video.muted = true;
        }
        refreshMute();
      });
      vol.addEventListener('input', function () {
        var v = parseFloat(vol.value);
        video.volume = v;
        video.muted = v === 0;
        refreshMute();
      });

      video.addEventListener('play', refresh);
      video.addEventListener('pause', refresh);
      video.addEventListener('ended', refresh);
      refresh();
      refreshMute();
    });

    // pausa vídeos que saem da viewport (e nunca toca por trás do lightbox)
    if ('IntersectionObserver' in window) {
      var ivo = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (!e.isIntersecting) {
            var v = e.target.querySelector('.ivid__video');
            if (v && !v.paused) v.pause();
          }
        });
      }, { threshold: 0.2 });
      cards.forEach(function (c) { ivo.observe(c); });
    }
  }

  /* ---------- Modal de orçamento (só interface) ----------
     Abrir e fechar, foco preso, máscara do WhatsApp e o campo condicional de
     modelo. O envio, o honeypot, o time-trap e a tela de sucesso vivem em
     js/formulario.js, carregado depois deste arquivo. */
  var qModal = document.getElementById('quoteModal');
  if (qModal) {
    var qForm = document.getElementById('quoteForm');
    var qPanelForm = qForm;
    var qDone = document.getElementById('quoteDone');
    var qWppLink = document.getElementById('quoteWppLink');
    var qStatus = document.getElementById('quoteStatus');
    var qSubmit = document.getElementById('quoteSubmit');
    var qBusca = document.getElementById('q-busca');
    var qModeloGroup = document.getElementById('qGroupModelo');
    var qModelo = document.getElementById('q-modelo');
    var qWpp = document.getElementById('q-whatsapp');
    var qLastFocus = null;

    function qSetStatus(msg) { if (qStatus) qStatus.textContent = msg || ''; }

    function qToggleModelo() {
      var on = qBusca.value === 'Modelo pronto do catálogo';
      qModeloGroup.hidden = !on;
    }
    qBusca.addEventListener('change', function () { qToggleModelo(); qBusca.classList.remove('is-error'); qBusca.removeAttribute('aria-invalid'); });
    document.getElementById('q-nome').addEventListener('input', function () { this.classList.remove('is-error'); this.removeAttribute('aria-invalid'); });

    // máscara de telefone: (48) 99999-9999
    qWpp.addEventListener('input', function () {
      var d = qWpp.value.replace(/\D/g, '').slice(0, 11);
      var out = d;
      if (d.length > 2) out = '(' + d.slice(0, 2) + ') ' + d.slice(2);
      if (d.length > 7) out = '(' + d.slice(0, 2) + ') ' + d.slice(2, d.length > 10 ? 7 : 6) + '-' + d.slice(d.length > 10 ? 7 : 6);
      qWpp.value = out;
      qWpp.classList.remove('is-error');
      qWpp.removeAttribute('aria-invalid');
    });

    function qOpen(trigger) {
      qLastFocus = trigger || null;
      if (drawer && drawer.classList.contains('is-open')) toggleDrawer(false);
      // reabriu depois de enviar: volta pro formulário (mantendo o que foi digitado)
      if (!qDone.hidden) {
        qDone.hidden = true; qPanelForm.hidden = false;
        qSetStatus(''); qSubmit.disabled = false;
      }
      // pré-seleção vinda do card de modelo
      if (trigger) {
        var m = trigger.getAttribute('data-modelo');
        if (m) { qBusca.value = 'Modelo pronto do catálogo'; qToggleModelo(); qModelo.value = m; }
      }
      qModal.hidden = false;
      document.body.classList.add('modal-open');
      requestAnimationFrame(function () { qModal.classList.add('is-open'); });
      setTimeout(function () {
        var first = qDone.hidden ? document.getElementById('q-nome') : qWppLink;
        if (first) first.focus({ preventScroll: true });
      }, 320);
    }
    function qClose() {
      if (qModal.hidden) return;
      qModal.classList.remove('is-open');
      document.body.classList.remove('modal-open');
      setTimeout(function () { qModal.hidden = true; }, 340);
      if (qLastFocus) { try { qLastFocus.focus({ preventScroll: true }); } catch (e) {} }
    }

    document.addEventListener('click', function (e) {
      var opener = e.target.closest('[data-quote-open]');
      if (opener) { e.preventDefault(); qOpen(opener); return; }
      if (e.target.closest('[data-quote-close]')) { e.preventDefault(); qClose(); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !qModal.hidden) qClose();
    });

    // foco preso dentro do painel enquanto o modal está aberto
    qModal.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') return;
      var f = qModal.querySelectorAll('button, input, select, textarea, a[href]');
      var vis = Array.prototype.filter.call(f, function (el) { return el.offsetParent !== null; });
      if (!vis.length) return;
      var first = vis[0], last = vis[vis.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    document.getElementById('quoteBack').addEventListener('click', function () {
      qDone.hidden = true;
      qPanelForm.hidden = false;
      qSetStatus('');
      qSubmit.disabled = false;
      document.getElementById('q-nome').focus({ preventScroll: true });
    });
  }

})();

/* ---------- Fundo floresta (parallax) da seção de avaliações ----------
   Gera fileiras de coníferas escalonadas (formato de pinheiro com "galhos").
   O viewBox de cada faixa é montado em PIXELS reais (largura x altura medidas),
   então preserveAspectRatio="none" não distorce nada: os pinheiros mantêm a
   mesma proporção em qualquer tela (no mobile ficavam esticados pra cima).
   Determinístico: as duas cópias de cada faixa ficam idênticas -> loop sem emenda. */
(function () {
  var scene = document.querySelector('.reviews__scene');
  if (!scene) return;

  function r(v) { return Math.round(v); }

  function conifer(cx, By, Ty, HW) {
    var H = By - Ty, t1 = Ty + 0.32 * H, t2 = Ty + 0.60 * H;
    var w1 = 0.40 * HW, w2 = 0.68 * HW, n1 = 0.22 * HW, n2 = 0.42 * HW;
    return 'M' + r(cx) + ',' + r(Ty)
      + 'L' + r(cx + w1) + ',' + r(t1) + 'L' + r(cx + n1) + ',' + r(t1)
      + 'L' + r(cx + w2) + ',' + r(t2) + 'L' + r(cx + n2) + ',' + r(t2)
      + 'L' + r(cx + HW) + ',' + r(By)
      + 'L' + r(cx - HW) + ',' + r(By)
      + 'L' + r(cx - n2) + ',' + r(t2) + 'L' + r(cx - w2) + ',' + r(t2)
      + 'L' + r(cx - n1) + ',' + r(t1) + 'L' + r(cx - w1) + ',' + r(t1) + 'Z';
  }

  // hFrac: altura do pinheiro sobre a altura da faixa. k: altura / meia-largura
  // (proporção do pinheiro, ~3,5 a 3,9). jFrac: degrau de altura entre vizinhos.
  var layers = [
    { sel: '.tline--2', hFrac: 0.67,  k: 3.88, jFrac: 0.040 },
    { sel: '.tline--3', hFrac: 0.75,  k: 3.75, jFrac: 0.055 },
    { sel: '.tline--4', hFrac: 0.835, k: 3.51, jFrac: 0.065 }
  ];

  function build() {
    // faixa distante (massa enevoada): ondas com amplitude proporcional ao vão
    var far = scene.querySelector('.tline--1 svg');
    if (far) {
      var fw = far.getBoundingClientRect().width;
      var fh = far.getBoundingClientRect().height;
      if (fw > 0 && fh > 0) {
        var nb = Math.max(3, Math.round(fw / 120));
        var bs = fw / nb, base = fh * 0.625, d1 = 'M0,' + r(fh) + 'L0,' + r(base);
        for (var b = 0; b < nb; b++) {
          d1 += 'Q' + r(bs * (b + 0.5)) + ',' + r(base - bs * 0.45) + ',' + r(bs * (b + 1)) + ',' + r(base);
        }
        d1 += 'L' + r(fw) + ',' + r(fh) + 'Z';
        scene.querySelectorAll('.tline--1 svg').forEach(function (s) {
          s.setAttribute('viewBox', '0 0 ' + r(fw) + ' ' + r(fh));
          s.querySelector('path').setAttribute('d', d1);
        });
      }
    }

    layers.forEach(function (L) {
      var svgs = scene.querySelectorAll(L.sel + ' svg');
      if (!svgs.length) return;
      var box = svgs[0].getBoundingClientRect();
      var W = box.width, H = box.height;
      if (W <= 0 || H <= 0) return;

      var treeH = L.hFrac * H;
      var hw = treeH / L.k;                                  // meia-largura proporcional
      var n = Math.max(2, Math.round(W / (2.5 * hw)));        // quantos cabem sem distorcer
      var span = W / n, jitter = L.jFrac * H, d = '';
      for (var i = 0; i < n; i++) {
        d += conifer(span * (i + 0.5), H, H - treeH + (i % 3) * jitter, hw);
      }
      svgs.forEach(function (s) {
        s.setAttribute('viewBox', '0 0 ' + r(W) + ' ' + r(H));
        s.querySelector('path').setAttribute('d', d);
      });
    });
  }

  build();
  var rebuildT = null, lastW = window.innerWidth;
  window.addEventListener('resize', function () {
    // no mobile a barra de endereço muda só a altura: rebuild apenas em mudança real
    if (Math.abs(window.innerWidth - lastW) < 2) return;
    lastW = window.innerWidth;
    clearTimeout(rebuildT);
    rebuildT = setTimeout(build, 180);
  });
  window.addEventListener('orientationchange', function () { setTimeout(build, 250); });
})();
