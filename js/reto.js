/* RioLab 4.0 — página de detalle de un reto (reto.html?id=r01) */
(() => {
  'use strict';

  const RETOS = window.RIOLAB_RETOS;
  const METS = window.RIOLAB_METODOLOGIAS;
  const FAQ = window.RIOLAB_FAQ;
  const ids = Object.keys(RETOS);

  const params = new URLSearchParams(location.search);
  const id = params.get('id') || ids[0];
  const reto = RETOS[id];

  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const arrow = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

  /* ---------- Reto inexistente ---------- */
  if (!reto) {
    document.title = 'Reto no encontrado · RioLab 4.0';
    document.querySelector('.rh-title').textContent = 'No encontramos este reto';
    document.querySelector('.rh-lead').textContent = 'Es posible que el enlace esté incompleto o que el reto ya no esté disponible.';
    document.querySelectorAll('.rh-facts, .rh-chips, .rh-icon, .subnav, .rb, .final-cta').forEach((el) => el.remove());
    const actions = document.querySelector('.rh-actions');
    actions.innerHTML = `<a href="index.html#retos" class="btn btn-sun btn-lg">Ver todos los retos ${arrow}</a>`;
    renderOthers(null);
    return;
  }

  const met = METS[reto.metodologia];
  const data = { ...reto, cuposTxt: `${reto.cupos} participantes` };

  /* ---------- Campos simples ---------- */
  document.title = `${reto.titulo} · RioLab 4.0`;
  document.querySelector('meta[name="description"]').setAttribute('content', reto.resumen);
  document.querySelectorAll('[data-field]').forEach((el) => {
    const v = data[el.dataset.field];
    if (v != null) el.textContent = v;
  });
  document.querySelectorAll('[data-met]').forEach((el) => { el.textContent = met[el.dataset.met]; });
  document.querySelector('[data-icon]').innerHTML = reto.icon;

  // Botones de inscripción: llevan al formulario del inicio con el reto preseleccionado
  document.querySelectorAll('[data-inscribir]').forEach((a) => { a.href = `index.html?reto=${id}#unete`; });

  /* ---------- Listas ---------- */
  const lists = {
    contexto: () => reto.contexto.map((p) => `<p>${esc(p)}</p>`).join(''),

    ods: () => reto.ods.map((o) => `
      <li class="ods-item" style="--ods: var(--ods-${o.n})">
        <span class="ods-n">ODS ${o.n}</span>
        <span>${esc(o.nombre)}</span>
      </li>`).join(''),

    fases: () => met.fases.map((f, i) => `
      <li class="phase">
        <span class="phase-n">${String(i + 1).padStart(2, '0')}</span>
        <h3>${esc(f.nombre)}</h3>
        <p>${esc(f.desc)}</p>
      </li>`).join(''),

    otrasMet: () => Object.entries(METS)
      .filter(([k]) => k !== reto.metodologia)
      .map(([, m]) => esc(m.nombre)).join(' · '),

    cronograma: () => reto.cronograma.map((c) => `
      <li class="tl-item">
        <span class="tl-week">Semana${c.semanas.includes('–') ? 's' : ''} ${esc(c.semanas)}</span>
        <div class="tl-body">
          <h3>${esc(c.titulo)}</h3>
          <p>${esc(c.desc)}</p>
        </div>
      </li>`).join(''),

    entregables: () => reto.entregables.map((e, i) => `
      <li class="deliv">
        <div class="deliv-top">
          <span class="deliv-n">E${i + 1}</span>
          <span class="deliv-when">${esc(e.semana)}</span>
        </div>
        <h3>${esc(e.titulo)}</h3>
        <p>${esc(e.desc)}</p>
      </li>`).join(''),

    resultados: () => reto.resultados.map((r, i) => `
      <li class="outcome">
        <span class="outcome-n">RA${i + 1}</span>
        <p>${esc(r)}</p>
      </li>`).join(''),

    competencias: () => reto.competencias.map((c) => `<li>${esc(c)}</li>`).join(''),

    evaluacion: () => reto.evaluacion.map((e) => `
      <li class="rubric-item">
        <div class="rubric-row"><span>${esc(e.criterio)}</span><strong>${e.peso}%</strong></div>
        <span class="rubric-bar" aria-hidden="true"><i style="--w:${e.peso * 2.5}%"></i></span>
      </li>`).join(''),

    dirigido: () => reto.dirigido.map((d) => `<li>${esc(d)}</li>`).join(''),
    requisitos: () => reto.requisitos.map((d) => `<li>${esc(d)}</li>`).join(''),
    aliados: () => reto.aliados.map((d) => `<li>${esc(d)}</li>`).join(''),

    faq: () => FAQ.map((f) => `
      <details class="faq-item">
        <summary>${esc(f.q)}<span class="faq-icon" aria-hidden="true"></span></summary>
        <p>${esc(f.a)}</p>
      </details>`).join(''),
  };

  document.querySelectorAll('[data-list]').forEach((el) => {
    const fn = lists[el.dataset.list];
    if (fn) el.innerHTML = fn();
  });

  renderOthers(id);

  /* ---------- Otros retos ---------- */
  function renderOthers(currentId) {
    const el = document.querySelector('[data-list="otros"]');
    el.innerHTML = ids.filter((k) => k !== currentId).slice(0, currentId ? 3 : 6).map((k) => {
      const r = RETOS[k];
      return `
        <li>
          <a class="more-card" href="reto.html?id=${k}">
            <span class="more-icon" aria-hidden="true"><svg viewBox="0 0 24 24">${r.icon}</svg></span>
            <span class="more-meta">${esc(r.codigo)} · ${esc(r.tag)}</span>
            <span class="more-title">${esc(r.titulo)}</span>
            <span class="more-foot">${esc(METS[r.metodologia].nombre)} ${arrow}</span>
          </a>
        </li>`;
    }).join('');
  }

  /* ---------- Subnav: sección activa ---------- */
  const subLinks = [...document.querySelectorAll('.subnav a')];
  const subList = document.querySelector('.subnav ul');
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

  /* ---------- Copiar enlace ---------- */
  const shareBtn = document.getElementById('share');
  const shareMsg = document.querySelector('.share-msg');
  shareBtn.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(location.href);
      shareMsg.textContent = 'Enlace copiado. ¡Compártelo con tu equipo!';
    } catch {
      shareMsg.textContent = 'No se pudo copiar. Copia la dirección desde la barra del navegador.';
    }
    setTimeout(() => { shareMsg.textContent = ''; }, 4000);
  });
})();
