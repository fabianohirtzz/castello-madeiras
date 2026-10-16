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
    if (!nav) return; // paginas sem menu, como a /link
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
  if (burger && drawer && backdrop) {
    burger.addEventListener('click', function () { toggleDrawer(); });
    backdrop.addEventListener('click', function () { toggleDrawer(false); });
    drawer.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () { toggleDrawer(false); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && drawer.classList.contains('is-open')) toggleDrawer(false);
    });
  }

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
     do scroll através da trilha dirige video.currentTime. O título fica
     parado a rolagem inteira; a segunda linha entra no meio do vídeo e a
     terceira, com os botões, no fim, e as três terminam juntas.
     Implementação vanilla (sem libs): progresso pelo bounding rect +
     lerp em rAF para suavizar o seek do vídeo. */
  var heroVideo = document.getElementById('heroVideo');
  var heroEtapas = Array.prototype.slice.call(document.querySelectorAll('[data-hero-etapa]'));
  var statusFill = document.getElementById('heroStatusFill');
  var statusCurrent = document.getElementById('heroStatusCurrent');
  // onde cada etapa termina de entrar, em fração da rolagem
  var ENTRA = { 2: [0.36, 0.48], 3: [0.78, 0.9] };

  function applyBeats(p) {
    heroEtapas.forEach(function (el) {
      var faixa = ENTRA[el.getAttribute('data-hero-etapa')];
      var op = clamp((p - faixa[0]) / (faixa[1] - faixa[0]), 0, 1);
      el.style.opacity = op.toFixed(3);
      el.style.transform = 'translateY(' + ((1 - op) * 18).toFixed(1) + 'px)';
      el.classList.toggle('is-in', op > 0.5);
    });
    if (statusFill) statusFill.style.width = (p * 100).toFixed(1) + '%';
    if (statusCurrent) statusCurrent.textContent = p < ENTRA[2][1] ? '01' : (p < ENTRA[3][1] ? '02' : '03');
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
    // Reduced motion: o texto inteiro visível, sem scrub.
    heroEtapas.forEach(function (el) { el.style.opacity = 1; el.classList.add('is-in'); });
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

  /* ---------- Etapas da obra: rolagem horizontal travada (Casa Pronta e Flex) ----------
     A trilha (.hscroll__track) ganha a altura do pin mais a distância que os
     cards precisam andar; o pin fica sticky e a fração rolada vira o
     translateX da trilha. Terminada a trilha, a página segue descendo. Com
     movimento reduzido nada trava: a trilha rola com o dedo. */
  document.querySelectorAll('[data-hscroll]').forEach(function (sec) {
    if (reduce) return;
    var track = sec.querySelector('.hscroll__track');
    var pin = sec.querySelector('.hscroll__pin');
    var janela = sec.querySelector('.hscroll__janela');
    var trilho = sec.querySelector('[data-hscroll-trilho]');
    var fill = sec.querySelector('[data-hscroll-fill]');
    if (!track || !pin || !janela || !trilho) return;

    var FATOR = 0.85;   // px de rolagem vertical por px de trilha
    var FOLGA = 24;     // a ultima foto para a 24px da borda direita da tela
    var dist = 0, agendado = false, ultimaAltura = -1;
    sec.classList.add('is-pinned');

    // A distancia sai da ultima foto, nao da largura da trilha: assim que ela
    // encosta no fim da tela a secao destrava e a pagina volta a descer.
    function medir() {
      trilho.style.transform = 'translateX(0)';
      var ultimo = trilho.lastElementChild;
      var fimTela = document.documentElement.clientWidth;
      dist = ultimo ? Math.max(0, ultimo.getBoundingClientRect().right - fimTela + FOLGA) : 0;
      var altura = Math.round(pin.offsetHeight + dist * FATOR);
      if (altura !== ultimaAltura) { ultimaAltura = altura; track.style.height = altura + 'px'; }
      mover();
    }
    function mover() {
      agendado = false;
      var rolavel = track.offsetHeight - pin.offsetHeight;
      var p = rolavel > 0 ? clamp(-track.getBoundingClientRect().top / rolavel, 0, 1) : 0;
      trilho.style.transform = 'translateX(' + (-p * dist).toFixed(1) + 'px)';
      if (fill) fill.style.width = (p * 100).toFixed(1) + '%';
    }
    window.addEventListener('scroll', function () {
      if (!agendado) { agendado = true; requestAnimationFrame(mover); }
    }, { passive: true });
    window.addEventListener('resize', medir);
    // mede de novo sempre que o layout muda depois da carga (fotos, fontes,
    // barra de rolagem, zoom): uma medida velha deixava rolagem sobrando
    trilho.querySelectorAll('img').forEach(function (img) {
      if (!img.complete) img.addEventListener('load', medir, { once: true });
    });
    window.addEventListener('load', medir);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(medir);
    if ('ResizeObserver' in window) {
      var ro = new ResizeObserver(function () { requestAnimationFrame(medir); });
      ro.observe(pin);
      ro.observe(trilho);
      ro.observe(document.documentElement);
    }
    medir();
  });

  /* ---------- Ver mais do modelo (galeria + descrição) ----------
     Cada card traz um <template> com o conteúdo do popup; abrir clona o
     template para dentro de #modelbox, então as fotos grandes só baixam quando
     alguém pede. O botão de orçamento de dentro fecha o popup antes de o
     modal de orçamento abrir (o listener do documento vem depois deste). */
  var modelbox = document.getElementById('modelbox');
  if (modelbox) {
    var mbConteudo = document.getElementById('modelboxConteudo');
    var mbVoltar = null;
    var mbIdx = 0;

    function mbIr(i) {
      var fotos = mbConteudo.querySelectorAll('.modelbox__foto');
      var minis = mbConteudo.querySelectorAll('.modelbox__mini');
      if (!fotos.length) return;
      mbIdx = (i + fotos.length) % fotos.length;
      fotos.forEach(function (f, k) { f.classList.toggle('is-active', k === mbIdx); });
      minis.forEach(function (m, k) {
        m.classList.toggle('is-active', k === mbIdx);
        m.setAttribute('aria-current', k === mbIdx ? 'true' : 'false');
      });
    }
    function mbAbrir(botao) {
      var tpl = document.getElementById(botao.getAttribute('data-modelo-ver'));
      if (!tpl) return;
      mbVoltar = botao;
      mbConteudo.innerHTML = '';
      mbConteudo.appendChild(tpl.content.cloneNode(true));
      var nome = mbConteudo.querySelector('.modelbox__nome');
      modelbox.setAttribute('aria-label', nome ? nome.textContent : 'Detalhes do modelo');
      mbIr(0);
      modelbox.hidden = false;
      document.body.classList.add('modal-open');
      requestAnimationFrame(function () { modelbox.classList.add('is-open'); });
      setTimeout(function () { modelbox.querySelector('.modelbox__fechar').focus({ preventScroll: true }); }, 60);
    }
    function mbFechar(devolverFoco) {
      if (modelbox.hidden) return;
      modelbox.classList.remove('is-open');
      document.body.classList.remove('modal-open');
      setTimeout(function () { modelbox.hidden = true; mbConteudo.innerHTML = ''; }, 300);
      if (devolverFoco && mbVoltar) { try { mbVoltar.focus({ preventScroll: true }); } catch (e) {} }
    }

    document.addEventListener('click', function (e) {
      var ver = e.target.closest('[data-modelo-ver]');
      if (ver) { e.preventDefault(); mbAbrir(ver); }
    });
    modelbox.addEventListener('click', function (e) {
      if (e.target.closest('[data-modelbox-fechar]')) { mbFechar(true); return; }
      if (e.target.closest('[data-galeria-prev]')) { mbIr(mbIdx - 1); return; }
      if (e.target.closest('[data-galeria-next]')) { mbIr(mbIdx + 1); return; }
      var ir = e.target.closest('[data-galeria-ir]');
      if (ir) { mbIr(parseInt(ir.getAttribute('data-galeria-ir'), 10) || 0); return; }
      if (e.target.closest('[data-quote-open]')) mbFechar(false);
    });
    document.addEventListener('keydown', function (e) {
      if (modelbox.hidden) return;
      if (e.key === 'Escape') mbFechar(true);
      else if (e.key === 'ArrowLeft') mbIr(mbIdx - 1);
      else if (e.key === 'ArrowRight') mbIr(mbIdx + 1);
      else if (e.key === 'Tab') {
        var f = modelbox.querySelectorAll('button, a[href]');
        var vis = Array.prototype.filter.call(f, function (el) { return el.offsetParent !== null; });
        if (!vis.length) return;
        var first = vis[0], last = vis[vis.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
    var mbToque = 0;
    modelbox.addEventListener('touchstart', function (e) { mbToque = e.changedTouches[0].clientX; }, { passive: true });
    modelbox.addEventListener('touchend', function (e) {
      if (!e.target.closest('.modelbox__palco')) return;
      var dx = e.changedTouches[0].clientX - mbToque;
      if (Math.abs(dx) > 45) mbIr(mbIdx + (dx < 0 ? 1 : -1));
    }, { passive: true });
  }

  /* ---------- Carrossel infinito dos modelos (Castello Flex) ----------
     Três por vez no desktop, dois no tablet, um no celular; anda de um em um.
     O "infinito" vem de cópias dos cards nas duas pontas: quando a trilha
     chega numa cópia, salta sem animação para o card original equivalente.
     As cópias ficam inert, fora do foco e do leitor de tela. */
  document.querySelectorAll('[data-carrossel]').forEach(function (car) {
    var viewport = car.querySelector('.mcarrossel__viewport');
    var track = car.querySelector('.mcarrossel__track');
    var originais = Array.prototype.slice.call(track.children);
    var total = originais.length;
    if (total < 2) { car.classList.add('is-estatico'); return; }

    function marcarCopia(el) {
      el.setAttribute('aria-hidden', 'true');
      el.setAttribute('inert', '');
      el.classList.add('is-copia');
      el.querySelectorAll('[id]').forEach(function (n) { n.removeAttribute('id'); });
      el.querySelectorAll('template').forEach(function (t) { t.remove(); });
      return el;
    }
    originais.slice().reverse().forEach(function (c) { track.insertBefore(marcarCopia(c.cloneNode(true)), track.firstChild); });
    originais.forEach(function (c) { track.appendChild(marcarCopia(c.cloneNode(true))); });

    var idx = total;
    var andando = false;

    function porVez() {
      var cs = getComputedStyle(viewport);
      var w = viewport.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
      return w >= 940 ? 3 : w >= 600 ? 2 : 1;
    }
    function passo() {
      var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
      return track.children[0].getBoundingClientRect().width + gap;
    }
    // parado, os cards fora da vista somem: o vizinho encosta na borda do
    // viewport (que tem folga para a sombra) e apareceria como um risco
    function marcarFora(parado) {
      var n = porVez();
      Array.prototype.forEach.call(track.children, function (c, k) {
        c.classList.toggle('is-fora', parado && (k < idx || k >= idx + n));
      });
    }
    function posicionar(animar) {
      track.style.transition = animar && !reduce ? '' : 'none';
      track.style.transform = 'translateX(' + (-idx * passo()) + 'px)';
      marcarFora(!animar);
      if (!animar) { void track.offsetWidth; track.style.transition = ''; }
    }
    function medir() {
      car.style.setProperty('--por-vez', porVez());
      posicionar(false);
    }
    function normalizar() {
      andando = false;
      if (idx >= 2 * total) idx -= total;
      else if (idx < total) idx += total;
      posicionar(false);
    }
    function ir(delta) {
      if (andando) return;
      idx += delta;
      if (reduce) { normalizar(); return; }
      andando = true;
      posicionar(true);
    }
    track.addEventListener('transitionend', function (e) { if (e.target === track) normalizar(); });

    var timer = null, naTela = false, pausado = false;
    function tocar() { if (!reduce && naTela && !pausado && !timer) timer = setInterval(function () { ir(1); }, 4800); }
    function parar() { if (timer) { clearInterval(timer); timer = null; } }

    car.querySelector('[data-carrossel-prev]').addEventListener('click', function () { ir(-1); });
    car.querySelector('[data-carrossel-next]').addEventListener('click', function () { ir(1); });

    var toque = 0;
    viewport.addEventListener('touchstart', function (e) { toque = e.changedTouches[0].clientX; pausado = true; parar(); }, { passive: true });
    viewport.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - toque;
      if (Math.abs(dx) > 40) ir(dx < 0 ? 1 : -1);
    }, { passive: true });

    // passa sozinho enquanto está na tela; para no hover, no foco e depois de um toque
    car.addEventListener('mouseenter', function () { pausado = true; parar(); });
    car.addEventListener('mouseleave', function () { pausado = false; tocar(); });
    car.addEventListener('focusin', function () { pausado = true; parar(); });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (en) {
        naTela = en[0].isIntersecting;
        if (naTela) tocar(); else parar();
      }, { threshold: 0.3 }).observe(car);
    }

    medir();
    window.addEventListener('resize', medir);
  });

  /* ---------- Vídeos da Castello Flex (um por vez, com transição) ----------
     O vídeo que sai desliza e gira de leve para o lado da navegação enquanto
     o próximo entra pelo lado oposto; atrás, dois cards empilhados (CSS)
     dão a ideia de que há mais vídeos. No celular dá para arrastar. Com
     movimento reduzido a troca é direta. */
  document.querySelectorAll('[data-vcarrossel]').forEach(function (vc) {
    var slides = Array.prototype.slice.call(vc.querySelectorAll('.vcarrossel__slide'));
    var palco = vc.querySelector('.vcarrossel__palco');
    var pontos = vc.querySelector('.vcarrossel__pontos');
    var cont = vc.querySelector('.vcarrossel__cont');
    if (slides.length < 2) return;
    var atual = 0, animando = false;
    var CLASSES = ['vc-entra-prox', 'vc-entra-ant', 'vc-sai-prox', 'vc-sai-ant'];
    var botoes = slides.map(function (s, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'vcarrossel__ponto';
      b.setAttribute('aria-label', 'Vídeo ' + (i + 1) + ' de ' + slides.length);
      b.addEventListener('click', function () { mostrar(i, i > atual ? 1 : -1); });
      pontos.appendChild(b);
      return b;
    });
    function marcar() {
      botoes.forEach(function (b, k) {
        b.classList.toggle('is-active', k === atual);
        b.setAttribute('aria-current', k === atual ? 'true' : 'false');
      });
      if (cont) cont.textContent = (atual + 1) + ' / ' + slides.length;
    }
    function mostrar(i, dir) {
      var novo = (i + slides.length) % slides.length;
      if (novo === atual || animando) return;
      var sai = slides[atual], entra = slides[novo];
      var video = sai.querySelector('video');
      if (video && !video.paused) video.pause();
      atual = novo;
      marcar();
      if (reduce) {
        sai.hidden = true;
        entra.hidden = false;
        return;
      }
      animando = true;
      var lado = dir > 0 ? 'prox' : 'ant';
      sai.classList.add('vc-sai-' + lado);
      entra.hidden = false;
      entra.classList.add('vc-entra-' + lado);
      palco.classList.add('is-trocando');
      setTimeout(function () {
        sai.hidden = true;
        sai.classList.remove.apply(sai.classList, CLASSES);
        entra.classList.remove.apply(entra.classList, CLASSES);
        palco.classList.remove('is-trocando');
        animando = false;
      }, 560);
    }
    vc.querySelector('[data-vcarrossel-prev]').addEventListener('click', function () { mostrar(atual - 1, -1); });
    vc.querySelector('[data-vcarrossel-next]').addEventListener('click', function () { mostrar(atual + 1, 1); });

    // arrastar no celular (fora dos controles do vídeo, que ficam embaixo)
    var x0 = null, y0 = 0;
    palco.addEventListener('touchstart', function (e) {
      var t = e.changedTouches[0];
      x0 = t.clientX; y0 = t.clientY;
    }, { passive: true });
    palco.addEventListener('touchend', function (e) {
      if (x0 === null) return;
      var t = e.changedTouches[0];
      var dx = t.clientX - x0, dy = t.clientY - y0;
      x0 = null;
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.4) mostrar(atual + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1);
    }, { passive: true });

    marcar();
  });

  /* ---------- Formulário de orçamento (só interface) ----------
     Máscara do WhatsApp, campo condicional de modelo e o botão de voltar da
     tela de sucesso valem para o formulário onde ele estiver: dentro do modal
     (#quoteModal) ou embutido na página de contato. Abrir e fechar o modal e o
     foco preso existem só quando há modal; sem ele, todo [data-quote-open]
     rola até o formulário. O envio, o honeypot, o time-trap e a tela de
     sucesso vivem em js/formulario.js, carregado depois deste arquivo. */
  var qModal = document.getElementById('quoteModal');
  var qForm = document.getElementById('quoteForm');
  if (qForm) {
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

    // volta da tela de sucesso para o formulário, mantendo o que foi digitado
    function qVoltar() {
      if (qDone.hidden) return;
      qDone.hidden = true;
      qPanelForm.hidden = false;
      qSetStatus('');
      qSubmit.disabled = false;
    }
    // pré-seleção vinda do card de modelo
    function qPreSelecionar(trigger) {
      var m = trigger ? trigger.getAttribute('data-modelo') : '';
      if (m) { qBusca.value = 'Modelo pronto do catálogo'; qToggleModelo(); qModelo.value = m; }
    }

    document.getElementById('quoteBack').addEventListener('click', function () {
      qVoltar();
      document.getElementById('q-nome').focus({ preventScroll: true });
    });

    if (qModal) {
      function qOpen(trigger) {
        qLastFocus = trigger || null;
        if (drawer && drawer.classList.contains('is-open')) toggleDrawer(false);
        qVoltar();
        qPreSelecionar(trigger);
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
    } else {
      // formulário embutido na página: o CTA rola até ele e foca o primeiro campo
      var qAlvo = qForm.closest('.contato__card') || qForm;
      document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-quote-open]');
        if (!opener) return;
        e.preventDefault();
        if (drawer && drawer.classList.contains('is-open')) toggleDrawer(false);
        qVoltar();
        qPreSelecionar(opener);
        qAlvo.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
        setTimeout(function () { document.getElementById('q-nome').focus({ preventScroll: true }); }, reduce ? 0 : 500);
      });
    }
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
