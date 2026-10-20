<?php
/** Dashboard: estadísticas de uso del sitio, inscripciones y entregas. */
require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
$u = require_login();

$dias = in_array((int) ($_GET['dias'] ?? 30), [7, 30, 90], true) ? (int) ($_GET['dias'] ?? 30) : 30;
$desde = date('Y-m-d 00:00:00', strtotime('-' . ($dias - 1) . ' days'));
$prevDesde = date('Y-m-d 00:00:00', strtotime('-' . (2 * $dias - 1) . ' days'));
[$wR, $pR] = alcance('reto_id');          // eventos / inscripciones
[$wI, $pI] = alcance('i.reto_id');        // consultas con alias i
$esMentor = retos_permitidos() !== null;

$scalar = fn(string $sql, array $p = []) => (float) q($sql, $p)->fetchColumn();

/* ---------- KPIs ---------- */
$visitas = $scalar("SELECT COUNT(*) FROM eventos WHERE tipo = 'visita' AND created_at >= ?", [$desde]);
$visitasPrev = $scalar("SELECT COUNT(*) FROM eventos WHERE tipo = 'visita' AND created_at >= ? AND created_at < ?", [$prevDesde, $desde]);
$visitantes = $scalar("SELECT COUNT(DISTINCT DATE(created_at), visitante) FROM eventos WHERE tipo = 'visita' AND created_at >= ?", [$desde]);
$ins = $scalar("SELECT COUNT(*) FROM inscripciones WHERE created_at >= ? AND $wR", array_merge([$desde], $pR));
$insPrev = $scalar("SELECT COUNT(*) FROM inscripciones WHERE created_at >= ? AND created_at < ? AND $wR", array_merge([$prevDesde, $desde], $pR));
$pdfs = $scalar("SELECT COUNT(*) FROM eventos WHERE tipo = 'pdf' AND created_at >= ? AND $wR", array_merge([$desde], $pR));
$docs = $scalar("SELECT COUNT(*) FROM entregas e JOIN inscripciones i ON i.id = e.inscripcion_id WHERE e.created_at >= ? AND $wI", array_merge([$desde], $pI));
$pendientes = $scalar("SELECT COUNT(*) FROM entregas e JOIN inscripciones i ON i.id = e.inscripcion_id WHERE e.estado = 'recibido' AND $wI", $pI);
$participantes = $scalar("SELECT COUNT(DISTINCT i.participante_id) FROM inscripciones i WHERE $wI", $pI);
$conversion = $visitantes > 0 ? $ins / $visitantes * 100 : 0;

function delta(float $now, float $prev): string
{
    if ($prev <= 0) return $now > 0 ? '<span class="delta delta--up">nuevo</span>' : '';
    $pct = ($now - $prev) / $prev * 100;
    $cls = $pct >= 0 ? 'up' : 'down';
    return '<span class="delta delta--' . $cls . '">' . ($pct >= 0 ? '▲ ' : '▼ ') . number_format(abs($pct), 0) . ' %</span> vs. periodo anterior';
}
$n = fn($v) => number_format($v, 0, ',', '.');

/* ---------- Series diarias ---------- */
$labels = [];
$serieVisitas = $serieVisitantes = $serieIns = [];
for ($d = $dias - 1; $d >= 0; $d--) {
    $k = date('Y-m-d', strtotime("-$d days"));
    $labels[$k] = date('j', strtotime($k)) . ' ' . substr(MESES[(int) date('n', strtotime($k)) - 1], 0, 3);
    $serieVisitas[$k] = $serieVisitantes[$k] = $serieIns[$k] = 0;
}
foreach (q("SELECT DATE(created_at) d, COUNT(*) v, COUNT(DISTINCT visitante) u FROM eventos WHERE tipo = 'visita' AND created_at >= ? GROUP BY d", [$desde]) as $r) {
    $serieVisitas[$r['d']] = (int) $r['v'];
    $serieVisitantes[$r['d']] = (int) $r['u'];
}
foreach (q("SELECT DATE(created_at) d, COUNT(*) c FROM inscripciones WHERE created_at >= ? AND $wR GROUP BY d", array_merge([$desde], $pR)) as $r) {
    $serieIns[$r['d']] = (int) $r['c'];
}

/* ---------- Por reto: ocupación y embudo ---------- */
[$wRt, $pRt] = alcance('r.id');
$porReto = q("SELECT r.id, r.codigo, r.titulo, r.cupos, r.estado,
        (SELECT COUNT(*) FROM inscripciones i WHERE i.reto_id = r.id AND i.estado <> 'rechazado') ocupados,
        (SELECT COUNT(*) FROM inscripciones i WHERE i.reto_id = r.id AND i.created_at >= ?) ins_rango,
        (SELECT COUNT(*) FROM eventos e WHERE e.reto_id = r.id AND e.tipo = 'visita' AND e.created_at >= ?) visitas,
        (SELECT COUNT(*) FROM eventos e WHERE e.reto_id = r.id AND e.tipo = 'pdf' AND e.created_at >= ?) pdfs,
        (SELECT COUNT(*) FROM entregas en JOIN inscripciones i ON i.id = en.inscripcion_id WHERE i.reto_id = r.id) docs
    FROM retos r WHERE r.estado <> 'borrador' AND $wRt ORDER BY r.orden, r.id", array_merge([$desde, $desde, $desde], $pRt))->fetchAll();
$cuposTot = array_sum(array_column($porReto, 'cupos'));
$ocupTot = array_sum(array_column($porReto, 'ocupados'));

/* ---------- Perfil, municipio, dispositivos y fuentes ---------- */
$perfiles = q("SELECT p.perfil k, COUNT(DISTINCT p.id) v FROM participantes p JOIN inscripciones i ON i.participante_id = p.id WHERE $wI GROUP BY p.perfil ORDER BY v DESC", $pI)->fetchAll();
$municipios = q("SELECT p.municipio k, COUNT(DISTINCT p.id) v FROM participantes p JOIN inscripciones i ON i.participante_id = p.id WHERE $wI GROUP BY p.municipio ORDER BY v DESC LIMIT 8", $pI)->fetchAll();
$disp = q("SELECT dispositivo k, COUNT(*) v FROM eventos WHERE tipo = 'visita' AND created_at >= ? GROUP BY dispositivo", [$desde])->fetchAll(PDO::FETCH_KEY_PAIR);
$dispTot = max(1, array_sum($disp));
$fuentes = q("SELECT IF(referer_host = '', 'Directo / sin referencia', referer_host) k, COUNT(*) v FROM eventos WHERE tipo = 'visita' AND pagina = 'inicio' AND created_at >= ? GROUP BY k ORDER BY v DESC LIMIT 6", [$desde])->fetchAll();
$fuentesMax = max(1, ...array_map('intval', array_column($fuentes, 'v') ?: [1]));
$estadosEnt = q("SELECT e.estado, COUNT(*) c FROM entregas e JOIN inscripciones i ON i.id = e.inscripcion_id WHERE $wI GROUP BY e.estado", $pI)->fetchAll(PDO::FETCH_KEY_PAIR);
$recientes = q("SELECT i.id, i.created_at, i.estado, p.id pid, p.nombre, p.perfil, p.municipio, r.codigo, r.titulo
    FROM inscripciones i JOIN participantes p ON p.id = i.participante_id LEFT JOIN retos r ON r.id = i.reto_id
    WHERE $wI ORDER BY i.created_at DESC LIMIT 6", $pI)->fetchAll();

$chartData = [
    'labels' => array_values($labels),
    'visitas' => array_values($serieVisitas),
    'visitantes' => array_values($serieVisitantes),
    'inscripciones' => array_values($serieIns),
    'perfiles' => ['labels' => array_column($perfiles, 'k'), 'values' => array_map('intval', array_column($perfiles, 'v'))],
    'municipios' => ['labels' => array_column($municipios, 'k'), 'values' => array_map('intval', array_column($municipios, 'v'))],
];

$seg = '<nav class="seg" aria-label="Periodo">';
foreach ([7 => '7 días', 30 => '30 días', 90 => '90 días'] as $k => $t) $seg .= '<a href="?dias=' . $k . '"' . ($k === $dias ? ' aria-current="true"' : '') . '>' . $t . '</a>';
$seg .= '</nav>';

admin_header('Dashboard', 'dashboard', ['actions' => $seg]);
?>
<?php if ($esMentor): ?>
  <div class="alert alert--info">Ves las estadísticas de los retos que tienes asignados. Las visitas y dispositivos corresponden a todo el sitio.</div>
<?php endif; ?>

<section class="kpis" aria-label="Indicadores principales">
  <div class="kpi">
    <p class="kpi-label">Visitas al sitio</p>
    <p class="kpi-value"><?= $n($visitas) ?></p>
    <p class="kpi-foot"><?= delta($visitas, $visitasPrev) ?></p>
  </div>
  <div class="kpi">
    <p class="kpi-label">Visitantes únicos</p>
    <p class="kpi-value"><?= $n($visitantes) ?></p>
    <p class="kpi-foot">Personas distintas por día</p>
  </div>
  <div class="kpi kpi--dark">
    <p class="kpi-label">Inscripciones</p>
    <p class="kpi-value"><?= $n($ins) ?></p>
    <p class="kpi-foot"><?= delta($ins, $insPrev) ?></p>
  </div>
  <div class="kpi">
    <p class="kpi-label">Conversión</p>
    <p class="kpi-value"><?= number_format($conversion, 1, ',', '.') ?> %</p>
    <p class="kpi-foot">Inscripciones por cada 100 visitantes</p>
  </div>
  <div class="kpi">
    <p class="kpi-label">Fichas PDF descargadas</p>
    <p class="kpi-value"><?= $n($pdfs) ?></p>
    <p class="kpi-foot">En los últimos <?= $dias ?> días</p>
  </div>
  <div class="kpi">
    <p class="kpi-label">Documentos cargados</p>
    <p class="kpi-value"><?= $n($docs) ?></p>
    <p class="kpi-foot"><?php if ($pendientes): ?><a href="entregas.php?estado=recibido"><?= $n($pendientes) ?> sin revisar</a><?php else: ?>Todo revisado<?php endif; ?></p>
  </div>
</section>

<div class="grid-2">
  <section class="card">
    <div class="card-head">
      <div><h2 class="card-title">Tráfico del sitio</h2><p class="card-sub">Visitas a páginas y visitantes únicos por día</p></div>
      <div class="legend"><span><i style="--c: var(--series-1)"></i>Visitas</span><span><i style="--c: var(--series-2)"></i>Visitantes únicos</span></div>
    </div>
    <div class="chart-box"><canvas id="ch-trafico" role="img" aria-label="Gráfica de visitas y visitantes únicos por día en los últimos <?= $dias ?> días"></canvas></div>
  </section>
  <section class="card">
    <div class="card-head">
      <div><h2 class="card-title">Inscripciones por día</h2><p class="card-sub"><?= $n($ins) ?> en los últimos <?= $dias ?> días · <?= $n($participantes) ?> participantes en total</p></div>
    </div>
    <div class="chart-box"><canvas id="ch-inscripciones" role="img" aria-label="Gráfica de inscripciones por día"></canvas></div>
  </section>
</div>

<div class="grid-2">
  <section class="card">
    <div class="card-head">
      <div><h2 class="card-title">Ocupación de cupos por reto</h2><p class="card-sub"><?= $n($ocupTot) ?> de <?= $n($cuposTot) ?> cupos ocupados (<?= $cuposTot ? round($ocupTot / $cuposTot * 100) : 0 ?> %)</p></div>
    </div>
    <div class="bars">
      <?php foreach ($porReto as $r): $pct = $r['cupos'] ? min(100, round($r['ocupados'] / $r['cupos'] * 100)) : 0; ?>
        <div class="bar-row">
          <div class="bar-top"><span><?= e($r['codigo'] . ' · ' . $r['titulo']) ?></span><strong><?= (int) $r['ocupados'] ?> / <?= (int) $r['cupos'] ?></strong></div>
          <div class="bar" role="img" aria-label="<?= $pct ?> % de cupos ocupados"><i class="<?= $pct >= 100 ? 'full' : '' ?>" style="width: <?= $pct ?>%"></i></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$porReto): ?><p class="empty">No hay retos publicados<?= $esMentor ? ' asignados a tu cuenta' : '' ?>.</p><?php endif; ?>
    </div>
  </section>
  <section class="card">
    <div class="card-head"><div><h2 class="card-title">Perfil de los participantes</h2><p class="card-sub">Personas inscritas según cómo participan</p></div></div>
    <div class="chart-box chart-box--sm"><canvas id="ch-perfiles" role="img" aria-label="Participantes por perfil"></canvas></div>
    <details class="data-table"><summary>Ver datos en tabla</summary>
      <table class="table"><thead><tr><th>Perfil</th><th class="num">Personas</th></tr></thead><tbody>
        <?php foreach ($perfiles as $p): ?><tr><td><?= e($p['k']) ?></td><td class="num"><?= (int) $p['v'] ?></td></tr><?php endforeach; ?>
      </tbody></table>
    </details>
  </section>
</div>

<section class="card card--flush">
  <div class="card-head"><div><h2 class="card-title">Embudo por reto</h2><p class="card-sub">De la visita a la inscripción en los últimos <?= $dias ?> días</p></div></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th class="w-main">Reto</th><th class="num">Visitas a la página</th><th class="num">Fichas PDF</th><th class="num">Inscripciones</th><th class="num">Conversión</th><th class="num">Documentos (total)</th></tr></thead>
      <tbody>
        <?php foreach ($porReto as $r): ?>
          <tr>
            <td><span class="cell-main"><?= e($r['codigo']) ?></span><span class="cell-sub"><?= e($r['titulo']) ?></span></td>
            <td class="num"><?= $n($r['visitas']) ?></td>
            <td class="num"><?= $n($r['pdfs']) ?></td>
            <td class="num"><?= $n($r['ins_rango']) ?></td>
            <td class="num"><?= $r['visitas'] ? number_format($r['ins_rango'] / $r['visitas'] * 100, 1, ',', '.') . ' %' : '—' ?></td>
            <td class="num"><?= $n($r['docs']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="grid-3">
  <section class="card">
    <div class="card-head"><div><h2 class="card-title">Municipios</h2><p class="card-sub">Lugar de origen de los inscritos</p></div></div>
    <div class="chart-box chart-box--sm"><canvas id="ch-municipios" role="img" aria-label="Participantes por municipio"></canvas></div>
    <details class="data-table"><summary>Ver datos en tabla</summary>
      <table class="table"><thead><tr><th>Municipio</th><th class="num">Personas</th></tr></thead><tbody>
        <?php foreach ($municipios as $m): ?><tr><td><?= e($m['k']) ?></td><td class="num"><?= (int) $m['v'] ?></td></tr><?php endforeach; ?>
      </tbody></table>
    </details>
  </section>

  <section class="card">
    <div class="card-head"><div><h2 class="card-title">Dispositivos</h2><p class="card-sub">Desde dónde visitan el sitio</p></div></div>
    <?php $dcfg = ['movil' => ['Móvil', 'var(--series-1)'], 'desktop' => ['Computador', 'var(--series-2)'], 'tablet' => ['Tableta', 'var(--series-3)']]; ?>
    <div class="stack" role="img" aria-label="<?= e(implode(', ', array_map(fn($k) => $dcfg[$k][0] . ' ' . round(($disp[$k] ?? 0) / $dispTot * 100) . ' %', array_keys($dcfg)))) ?>">
      <?php foreach ($dcfg as $k => [$t, $c]): if (empty($disp[$k])) continue; ?>
        <i style="width: <?= $disp[$k] / $dispTot * 100 ?>%; background: <?= $c ?>" title="<?= e($t) ?>: <?= round($disp[$k] / $dispTot * 100) ?> %"></i>
      <?php endforeach; ?>
    </div>
    <div class="bars" style="margin-top: 16px">
      <?php foreach ($dcfg as $k => [$t, $c]): ?>
        <div class="bar-top"><span class="legend"><span><i class="dot" style="--c: <?= $c ?>"></i><?= e($t) ?></span></span><strong><?= round(($disp[$k] ?? 0) / $dispTot * 100) ?> % · <?= $n($disp[$k] ?? 0) ?></strong></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><div><h2 class="card-title">Fuentes de tráfico</h2><p class="card-sub">De dónde llegan a la página de inicio</p></div></div>
    <div class="bars">
      <?php foreach ($fuentes as $f): ?>
        <div class="bar-row">
          <div class="bar-top"><span><?= e($f['k']) ?></span><strong><?= $n($f['v']) ?></strong></div>
          <div class="bar"><i style="width: <?= $f['v'] / $fuentesMax * 100 ?>%"></i></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$fuentes): ?><p class="empty">Aún no hay visitas registradas.</p><?php endif; ?>
    </div>
  </section>
</div>

<div class="grid-2">
  <section class="card card--flush">
    <div class="card-head">
      <div><h2 class="card-title">Últimas inscripciones</h2></div>
      <a class="btn btn-outline btn-sm" href="inscritos.php">Ver todas</a>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Persona</th><th>Reto</th><th>Estado</th><th>Fecha</th></tr></thead>
        <tbody>
          <?php foreach ($recientes as $r): [$et, $tone] = ESTADOS_INSCRIPCION[$r['estado']]; ?>
            <tr>
              <td><a class="cell-main" href="inscrito.php?id=<?= (int) $r['pid'] ?>"><?= e($r['nombre']) ?></a><span class="cell-sub"><?= e($r['perfil'] . ' · ' . $r['municipio']) ?></span></td>
              <td><?= $r['codigo'] ? e($r['codigo']) : '<span class="muted">General</span>' ?></td>
              <td><?= badge($et, $tone) ?></td>
              <td class="nowrap"><?= e(fecha_corta($r['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recientes): ?><tr><td colspan="4" class="empty">Aún no hay inscripciones.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card">
    <div class="card-head"><div><h2 class="card-title">Estado de las entregas</h2><p class="card-sub">Documentos cargados por los participantes</p></div><a class="btn btn-outline btn-sm" href="entregas.php">Revisar</a></div>
    <div class="bars">
      <?php $totEnt = max(1, array_sum($estadosEnt)); foreach (ESTADOS_ENTREGA as $k => [$t, $tone]): ?>
        <div class="bar-row">
          <div class="bar-top"><a href="entregas.php?estado=<?= $k ?>" style="text-decoration:none"><?= badge($t, $tone) ?></a><strong><?= $n($estadosEnt[$k] ?? 0) ?></strong></div>
          <div class="bar"><i style="width: <?= ($estadosEnt[$k] ?? 0) / $totEnt * 100 ?>%"></i></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<script type="application/json" id="dash-data"><?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php admin_footer(['https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js', 'assets/dashboard.js']);
