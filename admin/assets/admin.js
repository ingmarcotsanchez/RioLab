/* RioLab 4.0 — panel: menú lateral, confirmaciones, repetidores y ayudas de formulario */
(() => {
  'use strict';

  /* ---------- Menú lateral en móvil ---------- */
  const sidebar = document.getElementById('sidebar');
  const toggle = document.querySelector('.sb-toggle');
  const backdrop = document.querySelector('.sb-backdrop');
  const setOpen = (open) => {
    sidebar?.classList.toggle('is-open', open);
    backdrop?.classList.toggle('is-open', open);
    toggle?.setAttribute('aria-expanded', String(open));
  };
  toggle?.addEventListener('click', () => setOpen(!sidebar.classList.contains('is-open')));
  backdrop?.addEventListener('click', () => setOpen(false));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });

  /* ---------- Confirmaciones ---------- */
  document.addEventListener('submit', (e) => {
    const msg = e.target.dataset.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  /* ---------- Envío automático (selects de estado en tablas) ---------- */
  document.querySelectorAll('[data-autosubmit]').forEach((el) => {
    el.addEventListener('change', () => el.form.requestSubmit());
  });

  /* ---------- Copiar al portapapeles ---------- */
  document.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try { await navigator.clipboard.writeText(btn.dataset.copy); btn.textContent = 'Copiado'; } catch { /* sin permiso */ }
    });
  });

  /* ---------- Repetidores (cronograma, entregables, evaluación, fases) ---------- */
  document.querySelectorAll('[data-repeater]').forEach((rep) => {
    const list = rep.querySelector('.repeater');
    const tpl = rep.querySelector('template');
    const total = rep.querySelector('.rep-total');

    const renumber = () => {
      // Reindexa los name="campo[i][sub]" para que el servidor reciba el orden visible
      list.querySelectorAll('.rep-row').forEach((row, i) => {
        row.querySelectorAll('[name]').forEach((el) => { el.name = el.name.replace(/\[\d+\]/, `[${i}]`); });
        row.querySelectorAll('[data-rownum]').forEach((el) => { el.textContent = i + 1; });
      });
      if (total) {
        const sum = [...list.querySelectorAll('[data-peso]')].reduce((a, el) => a + (parseInt(el.value, 10) || 0), 0);
        total.textContent = `Total: ${sum} %${sum === 100 ? '' : ' (debe sumar 100 %)'}`;
        total.classList.toggle('is-bad', sum !== 100);
        total.classList.toggle('is-ok', sum === 100);
      }
    };

    rep.querySelector('.rep-add').addEventListener('click', () => {
      const node = tpl.content.firstElementChild.cloneNode(true);
      list.appendChild(node);
      renumber();
      node.querySelector('input, textarea')?.focus();
    });
    list.addEventListener('click', (e) => {
      const btn = e.target.closest('.rep-del');
      if (!btn) return;
      if (list.children.length <= 1) { btn.closest('.rep-row').querySelectorAll('input, textarea').forEach((el) => { el.value = ''; }); renumber(); return; }
      btn.closest('.rep-row').remove();
      renumber();
    });
    list.addEventListener('input', (e) => { if (e.target.matches('[data-peso]')) renumber(); });
    renumber();
  });

  /* ---------- Slug automático a partir del título ---------- */
  const titulo = document.querySelector('[data-slug-source]');
  const slug = document.querySelector('[data-slug-target]');
  if (titulo && slug) {
    let touched = slug.value !== '';
    slug.addEventListener('input', () => { touched = true; });
    titulo.addEventListener('input', () => {
      if (touched) return;
      slug.value = titulo.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 80);
    });
  }

  /* ---------- Aviso de cambios sin guardar ---------- */
  document.querySelectorAll('form[data-dirty-guard]').forEach((form) => {
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  });
})();
