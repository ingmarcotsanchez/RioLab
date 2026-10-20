/* RioLab 4.0 — gráficas del dashboard (Chart.js) */
(() => {
  'use strict';
  if (!window.Chart) return;
  const data = JSON.parse(document.getElementById('dash-data').textContent);
  const css = getComputedStyle(document.documentElement);
  const v = (name) => css.getPropertyValue(name).trim();
  const S1 = v('--series-1');
  const S2 = v('--series-2');
  const INK = v('--muted');
  const GRID = '#e6eeec';

  Chart.defaults.font.family = '"DM Sans", system-ui, sans-serif';
  Chart.defaults.font.size = 12;
  Chart.defaults.color = INK;
  Chart.defaults.animation.duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 500;

  const tooltip = {
    backgroundColor: '#04161f', titleColor: '#fff', bodyColor: '#d7e6e3', padding: 10, cornerRadius: 8,
    boxPadding: 4, usePointStyle: true, titleFont: { weight: '600' },
  };
  const axes = (horizontal = false) => ({
    x: { grid: { display: horizontal, color: GRID, drawTicks: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 14, precision: 0 }, beginAtZero: true },
    y: { grid: { display: !horizontal, color: GRID, drawTicks: false }, border: { display: false }, ticks: { padding: 8, precision: 0 }, beginAtZero: true },
  });

  /* Tráfico: dos series en la misma escala → una sola gráfica de líneas */
  new Chart(document.getElementById('ch-trafico'), {
    type: 'line',
    data: {
      labels: data.labels,
      datasets: [
        { label: 'Visitas', data: data.visitas, borderColor: S1, backgroundColor: S1 + '1f', fill: true, tension: .3, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 },
        { label: 'Visitantes únicos', data: data.visitantes, borderColor: S2, backgroundColor: S2, tension: .3, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 },
      ],
    },
    options: {
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: false }, tooltip },
      scales: axes(),
    },
  });

  /* Inscripciones por día */
  new Chart(document.getElementById('ch-inscripciones'), {
    type: 'bar',
    data: { labels: data.labels, datasets: [{ label: 'Inscripciones', data: data.inscripciones, backgroundColor: S1, hoverBackgroundColor: '#0f766e', borderRadius: 4, borderSkipped: 'start', maxBarThickness: 22 }] },
    options: {
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: false }, tooltip },
      scales: axes(),
    },
  });

  /* Barras horizontales de una sola serie (perfil y municipio) */
  const hbar = (id, set, label) => new Chart(document.getElementById(id), {
    type: 'bar',
    data: { labels: set.labels, datasets: [{ label, data: set.values, backgroundColor: S1, hoverBackgroundColor: '#0f766e', borderRadius: 4, borderSkipped: 'start', barPercentage: .75, categoryPercentage: .85, maxBarThickness: 20 }] },
    options: {
      indexAxis: 'y',
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip },
      scales: { ...axes(true), y: { grid: { display: false }, border: { display: false }, ticks: { padding: 6, autoSkip: false } } },
    },
  });
  hbar('ch-perfiles', data.perfiles, 'Personas');
  hbar('ch-municipios', data.municipios, 'Personas');
})();
