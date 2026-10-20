/* RioLab 4.0 — página de detalle de un reto: subnav activo y copiar enlace */
(() => {
  'use strict';

  /* ---------- Subnav: sección activa ---------- */
  const subLinks = [...document.querySelectorAll('.subnav a')];
  const subList = document.querySelector('.subnav ul');
  if (subList) {
    const spy = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        subLinks.forEach((a) => {
          const on = a.hash === '#' + entry.target.id;
          a.classList.toggle('is-active', on);
          if (on) {
            a.setAttribute('aria-current', 'true');
            // Mantener visible el enlace activo en móvil (scroll horizontal del subnav)
            subList.scrollTo({ left: a.offsetLeft - 16, behavior: 'smooth' });
          } else a.removeAttribute('aria-current');
        });
      });
    }, { rootMargin: '-35% 0px -60% 0px' });
    document.querySelectorAll('.rb-main > section').forEach((s) => spy.observe(s));
  }

  /* ---------- Copiar enlace ---------- */
  const shareBtn = document.getElementById('share');
  const shareMsg = document.querySelector('.share-msg');
  shareBtn?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(location.href.split('#')[0]);
      shareMsg.textContent = 'Enlace copiado. ¡Compártelo con tu equipo!';
    } catch {
      shareMsg.textContent = 'No se pudo copiar. Copia la dirección desde la barra del navegador.';
    }
    setTimeout(() => { shareMsg.textContent = ''; }, 4000);
  });
})();
