/* RioLab 4.0 — página de inicio (videos, retos, formulario) */
(() => {
  'use strict';
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Hero: carrusel de videos ---------- */
  // Los nombres en disco usan la "í" descompuesta (NFD): i + U+0301.
  const VIDEOS = [1, 2, 3, 4, 5, 6].map((n) => 'videos/' + encodeURIComponent(`Ri\u0301o Lab ${n}.mp4`));
  const players = [...document.querySelectorAll('.hero-video')];
  const reel = document.querySelector('.reel');
  const reelToggle = document.querySelector('.reel-toggle');
  let current = 0;
  let activeIdx = 0; // índice del <video> visible
  let paused = reduceMotion;

  const buttons = VIDEOS.map((_, i) => {
    const li = document.createElement('li');
    const b = document.createElement('button');
    b.type = 'button';
    b.setAttribute('aria-label', `Ver video ${i + 1} de ${VIDEOS.length}`);
    b.innerHTML = `<span class="label">0${i + 1}</span><span class="bar"><i></i></span>`;
    b.addEventListener('click', () => show(i));
    li.appendChild(b);
    reel.appendChild(li);
    return b;
  });

  const active = () => players[activeIdx];

  const paintReel = () => {
    buttons.forEach((b, i) => {
      if (i === current) b.setAttribute('aria-current', 'true');
      else b.removeAttribute('aria-current');
      b.classList.toggle('is-done', i < current);
      if (i !== current) b.querySelector('i').style.width = i < current ? '' : '0';
    });
  };

  const setPaused = (value) => {
    paused = value;
    reelToggle.setAttribute('aria-pressed', String(paused));
    reelToggle.setAttribute('aria-label', paused ? 'Reproducir video' : 'Pausar video');
    const v = active();
    if (paused) v.pause();
    else v.play().catch(() => setPaused(true));
  };

  function show(index) {
    current = (index + VIDEOS.length) % VIDEOS.length;
    const nextIdx = 1 - activeIdx;
    const next = players[nextIdx];
    const prev = players[activeIdx];

    next.src = VIDEOS[current];
    next.preload = 'auto';
    next.load();
    next.addEventListener('loadeddata', function onReady() {
      next.removeEventListener('loadeddata', onReady);
      next.classList.add('is-active');
      prev.classList.remove('is-active');
      activeIdx = nextIdx;
      if (!paused) next.play().catch(() => setPaused(true));
      setTimeout(() => { prev.pause(); prev.removeAttribute('src'); prev.load(); }, 1300);
    });
    paintReel();
  }

  players.forEach((v) => {
    v.addEventListener('timeupdate', () => {
      if (v !== active() || !v.duration) return;
      buttons[current].querySelector('i').style.width = (v.currentTime / v.duration) * 100 + '%';
    });
    v.addEventListener('ended', () => { if (v === active()) show(current + 1); });
  });

  reelToggle.addEventListener('click', () => setPaused(!paused));

  // Primer video
  players.forEach((v) => { v.muted = true; });
  const first = players[0];
  first.src = VIDEOS[0];
  reelToggle.setAttribute('aria-pressed', String(paused));
  reelToggle.setAttribute('aria-label', paused ? 'Reproducir video' : 'Pausar video');
  if (!paused) {
    first.play().catch(() => {
      // Si el navegador bloquea el autoplay, reintentar con la primera interacción
      const retry = () => { if (!paused) active().play().catch(() => setPaused(true)); };
      ['pointerdown', 'keydown', 'scroll'].forEach((ev) => window.addEventListener(ev, retry, { once: true, passive: true }));
    });
  }
  paintReel();

  // Pausar cuando el hero no es visible (ahorra batería y datos)
  new IntersectionObserver(([entry]) => {
    const v = active();
    if (!entry.isIntersecting) v.pause();
    else if (!paused) v.play().catch(() => {});
  }, { threshold: 0.1 }).observe(document.querySelector('.hero'));
  /* ---------- Retos: brillo que sigue al cursor ---------- */
  document.querySelectorAll('.reto').forEach((card) => {
    card.addEventListener('pointermove', (e) => {
      const r = card.getBoundingClientRect();
      card.style.setProperty('--mx', `${e.clientX - r.left}px`);
      card.style.setProperty('--my', `${e.clientY - r.top}px`);
    });
  });

  /* ---------- Preselección del reto (viene de reto.html?id=…) ---------- */
  const retoSelect = document.getElementById('reto');
  const retoParam = new URLSearchParams(location.search).get('reto');
  const retoData = retoParam && window.RIOLAB_RETOS && window.RIOLAB_RETOS[retoParam];
  if (retoData) {
    retoSelect.value = retoData.titulo;
    const field = retoSelect.closest('.field');
    field.classList.add('is-flash');
  }

  /* ---------- Formulario ---------- */
  const form = document.getElementById('join-form');
  const submitBtn = form.querySelector('button[type="submit"]');
  const success = form.querySelector('.form-success');

  const rules = {
    nombre: (v) => v.trim().length >= 3 || 'Escribe tu nombre completo.',
    correo: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()) || 'Ingresa un correo válido (ej. nombre@correo.com).',
    telefono: (v) => v.replace(/\D/g, '').length >= 7 || 'Ingresa un número de contacto válido (mínimo 7 dígitos).',
    municipio: (v) => v.trim().length >= 2 || 'Indica tu municipio.',
    perfil: (v) => v !== '' || 'Selecciona cómo quieres participar.',
    acepto: (_, el) => el.checked || 'Necesitamos tu autorización para contactarte.',
  };

  const validate = (name) => {
    const el = form.elements[name];
    const result = rules[name](el.value, el);
    const msg = result === true ? '' : result;
    const field = el.closest('.field');
    const err = document.getElementById(`${name}-err`);
    field.classList.toggle('has-error', !!msg);
    el.setAttribute('aria-invalid', String(!!msg));
    if (msg) el.setAttribute('aria-describedby', err.id); else el.removeAttribute('aria-describedby');
    err.textContent = msg;
    return !msg;
  };

  Object.keys(rules).forEach((name) => {
    const el = form.elements[name];
    el.addEventListener('blur', () => validate(name));
    el.addEventListener(el.type === 'checkbox' || el.tagName === 'SELECT' ? 'change' : 'input', () => {
      if (el.closest('.field').classList.contains('has-error')) validate(name);
    });
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    success.hidden = true;
    const invalid = Object.keys(rules).filter((name) => !validate(name));
    if (invalid.length) {
      form.elements[invalid[0]].focus();
      return;
    }

    const data = Object.fromEntries(new FormData(form));
    submitBtn.classList.add('is-loading');
    submitBtn.querySelector('.btn-label').textContent = 'Enviando…';

    // TODO: conectar con el backend o servicio de formularios del proyecto.
    setTimeout(() => {
      console.info('Registro RioLab 4.0', data);
      submitBtn.classList.remove('is-loading');
      submitBtn.querySelector('.btn-label').textContent = 'Quiero participar';
      form.reset();
      success.hidden = false;
      success.focus();
    }, 900);
  });
})();
