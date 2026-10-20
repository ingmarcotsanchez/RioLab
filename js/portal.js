/* RioLab 4.0 — portal de participantes: zona de arrastre, confirmaciones y estado de carga */
(() => {
  'use strict';

  document.querySelectorAll('.drop').forEach((drop) => {
    const input = drop.querySelector('input[type="file"]');
    const text = drop.querySelector('.drop-text');
    const original = text.innerHTML;
    const show = () => {
      const f = input.files[0];
      drop.classList.toggle('has-file', !!f);
      text.innerHTML = original;
      if (f) text.textContent = `${f.name} (${(f.size / 1048576).toFixed(1)} MB)`;
    };
    input.addEventListener('change', show);
    ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, () => drop.classList.add('is-over')));
    ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, () => drop.classList.remove('is-over')));
  });

  document.querySelectorAll('form.upload').forEach((form) => {
    form.addEventListener('submit', () => {
      const btn = form.querySelector('button[type="submit"]');
      btn.classList.add('is-loading');
      btn.querySelector('.btn-label').textContent = 'Cargando…';
    });
  });

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (!window.confirm(form.dataset.confirm)) e.preventDefault();
    });
  });
})();
