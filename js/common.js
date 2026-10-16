/* RioLab 4.0 — comportamiento compartido (nav, footer, animaciones) */
(() => {
  'use strict';
  document.documentElement.classList.add('js');

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Año del footer ---------- */
  const year = document.getElementById('year');
  if (year) year.textContent = new Date().getFullYear();

  /* ---------- Nav flotante ---------- */
  const navWrap = document.querySelector('.nav-wrap');
  const toggle = document.querySelector('.nav-toggle');
  const links = document.getElementById('nav-links');
  let lastY = window.scrollY;

  const closeMenu = () => {
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Abrir menú');
    links.classList.remove('is-open');
  };

  toggle.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    links.classList.toggle('is-open', open);
  });
  links.addEventListener('click', (e) => { if (e.target.closest('a')) closeMenu(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });

  window.addEventListener('scroll', () => {
    const y = window.scrollY;
    navWrap.classList.toggle('is-scrolled', y > 40);
    const menuOpen = links.classList.contains('is-open');
    navWrap.classList.toggle('is-hidden', !menuOpen && y > lastY && y > 600);
    lastY = y;
  }, { passive: true });

  // Enlace activo según la sección visible
  const navAnchors = [...links.querySelectorAll('a')];
  const spy = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      navAnchors.forEach((a) => a.classList.toggle('is-active', a.hash === '#' + entry.target.id));
    });
  }, { rootMargin: '-45% 0px -50% 0px' });
  document.querySelectorAll('main section[id]').forEach((s) => spy.observe(s));

  /* ---------- Revelado al hacer scroll ---------- */
  const reveals = document.querySelectorAll('.reveal');
  if (reduceMotion || !('IntersectionObserver' in window)) {
    reveals.forEach((el) => el.classList.add('is-in'));
  } else {
    // Escalonar elementos hermanos dentro de un mismo grupo
    document.querySelectorAll('[data-stagger]').forEach((group) => {
      group.querySelectorAll(':scope > .reveal').forEach((el, i) => el.style.setProperty('--d', `${i * 90}ms`));
    });
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) { entry.target.classList.add('is-in'); io.unobserve(entry.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px' });
    reveals.forEach((el) => io.observe(el));
  }

})();
