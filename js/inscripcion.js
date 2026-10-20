/* RioLab 4.0 — formulario "Quiero participar" (inicio y página de reto) */
(() => {
  'use strict';

  const rules = {
    nombre: (v) => v.trim().length >= 3 || 'Escribe tu nombre completo.',
    correo: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()) || 'Ingresa un correo válido (ej. nombre@correo.com).',
    telefono: (v) => v.replace(/\D/g, '').length >= 7 || 'Ingresa un número de contacto válido (mínimo 7 dígitos).',
    municipio: (v) => v.trim().length >= 2 || 'Indica tu municipio.',
    perfil: (v) => v !== '' || 'Selecciona cómo quieres participar.',
    acepto: (_, el) => el.checked || 'Necesitamos tu autorización para contactarte.',
  };

  document.querySelectorAll('.js-inscripcion').forEach((form) => {
    const submitBtn = form.querySelector('button[type="submit"]');
    const label = submitBtn.querySelector('.btn-label');
    const success = form.querySelector('.form-success');
    const alertBox = form.querySelector('.form-alert');

    const setError = (name, msg) => {
      const el = form.elements[name];
      const err = form.querySelector(`[data-err="${name}"]`);
      if (!el || !err) return;
      if (!err.id) err.id = `${el.id}-err`;
      el.closest('.field').classList.toggle('has-error', !!msg);
      el.setAttribute('aria-invalid', String(!!msg));
      if (msg) el.setAttribute('aria-describedby', err.id); else el.removeAttribute('aria-describedby');
      err.textContent = msg || '';
    };

    const validate = (name) => {
      const el = form.elements[name];
      const result = rules[name](el.value, el);
      setError(name, result === true ? '' : result);
      return result === true;
    };

    Object.keys(rules).forEach((name) => {
      const el = form.elements[name];
      el.addEventListener('blur', () => validate(name));
      el.addEventListener(el.type === 'checkbox' || el.tagName === 'SELECT' ? 'change' : 'input', () => {
        if (el.closest('.field').classList.contains('has-error')) validate(name);
      });
    });

    form.querySelector('.fs-copy')?.addEventListener('click', async (e) => {
      try {
        await navigator.clipboard.writeText(form.querySelector('.fs-code code').textContent);
        e.currentTarget.textContent = 'Código copiado';
      } catch { /* el usuario puede copiarlo manualmente */ }
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      success.hidden = true;
      alertBox.hidden = true;
      const invalid = Object.keys(rules).filter((name) => !validate(name));
      if (invalid.length) { form.elements[invalid[0]].focus(); return; }

      submitBtn.classList.add('is-loading');
      label.textContent = 'Enviando…';
      try {
        const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
        const data = await res.json();
        if (!data.ok) {
          if (data.errors) {
            Object.entries(data.errors).forEach(([k, v]) => setError(k, v));
            const first = Object.keys(data.errors)[0];
            form.elements[first]?.focus();
          }
          if (data.error) { alertBox.textContent = data.error; alertBox.hidden = false; }
          return;
        }
        form.querySelector('.fs-title').textContent = data.titulo;
        form.querySelector('.fs-msg').textContent = data.mensaje;
        const codeBox = form.querySelector('.fs-code');
        codeBox.hidden = !data.codigo;
        if (data.codigo) codeBox.querySelector('code').textContent = data.codigo;
        form.querySelectorAll('.form-grid, .form-reto, button[type="submit"]').forEach((el) => { el.hidden = true; });
        success.hidden = false;
        success.focus();
      } catch {
        alertBox.textContent = 'No pudimos enviar tu registro. Revisa tu conexión e inténtalo de nuevo.';
        alertBox.hidden = false;
      } finally {
        submitBtn.classList.remove('is-loading');
        label.textContent = 'Quiero participar';
      }
    });
  });
})();
