<?php
/** Listado de inscritos a los retos: filtros, búsqueda, cambio de estado y exportación a CSV. */
require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
$u = require_login();

/* ---------- Cambio de estado de una inscripción ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'estado') {
    csrf_check();
    $ins = q('SELECT * FROM inscripciones WHERE id = ?', [(int) ($_POST['id'] ?? 0)])->fetch();
    $estado = (string) ($_POST['estado'] ?? '');
    if ($ins && isset(ESTADOS_INSCRIPCION[$estado]) && puede_ver_reto($ins['reto_id'] !== null ? (int) $ins['reto_id'] : null)) {
        q('UPDATE inscripciones SET estado = ? WHERE id = ?', [$estado, $ins['id']]);
        flash('ok', 'Estado actualizado a «' . ESTADOS_INSCRIPCION[$estado][0] . '».');
    } else {
        flash('error', 'No se pudo actualizar el estado.');
    }
    redirect('inscritos.php' . (($_POST['back'] ?? '') !== '' ? '?' . preg_replace('/[^\w=&%.\-+]/', '', (string) $_POST['back']) : ''));
}

/* ---------- Filtros ---------- */
$f = [
    'q'         => trim((string) ($_GET['q'] ?? '')),
    'reto'      => (string) ($_GET['reto'] ?? ''),
    'perfil'    => (string) ($_GET['perfil'] ?? ''),
    'estado'    => (string) ($_GET['estado'] ?? ''),
    'municipio' => trim((string) ($_GET['municipio'] ?? '')),
];
[$w, $params] = alcance('i.reto_id');
$where = [$w];
if ($f['q'] !== '') {
    $where[] = '(p.nombre LIKE ? OR p.correo LIKE ? OR p.telefono LIKE ?)';
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $f['q']) . '%';
    array_push($params, $like, $like, $like);
}
if ($f['reto'] === 'general') $where[] = 'i.reto_id IS NULL';
elseif ($f['reto'] !== '') { $where[] = 'i.reto_id = ?'; $params[] = (int) $f['reto']; }
if (in_array($f['perfil'], PERFILES, true)) { $where[] = 'p.perfil = ?'; $params[] = $f['perfil']; }
if (isset(ESTADOS_INSCRIPCION[$f['estado']])) { $where[] = 'i.estado = ?'; $params[] = $f['estado']; }
if ($f['municipio'] !== '') { $where[] = 'p.municipio = ?'; $params[] = $f['municipio']; }
$whereSql = implode(' AND ', $where);

$base = "FROM inscripciones i JOIN participantes p ON p.id = i.participante_id LEFT JOIN retos r ON r.id = i.reto_id WHERE $whereSql";
$select = "SELECT i.id, i.estado, i.created_at, i.mensaje, i.origen, p.id pid, p.nombre, p.correo, p.telefono, p.municipio, p.perfil,
           r.codigo, r.titulo, (SELECT COUNT(*) FROM entregas e WHERE e.inscripcion_id = i.id) docs $base ORDER BY i.created_at DESC";

/* ---------- Exportar CSV (respeta los filtros) ---------- */
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="riolab-inscritos-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM para que Excel reconozca las tildes
    fputcsv($out, ['Nombre', 'Correo', 'Teléfono', 'Municipio', 'Perfil', 'Código reto', 'Reto', 'Estado', 'Documentos', 'Mensaje', 'Origen', 'Fecha inscripción'], ';', '"', '');
    foreach (q($select, $params) as $r) {
        // Evita la inyección de fórmulas en hojas de cálculo
        $safe = fn($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v;
        fputcsv($out, array_map($safe, [$r['nombre'], $r['correo'], $r['telefono'], $r['municipio'], $r['perfil'], $r['codigo'] ?? '', $r['titulo'] ?? 'Interés general',
            ESTADOS_INSCRIPCION[$r['estado']][0], $r['docs'], $r['mensaje'], $r['origen'], $r['created_at']]), ';', '"', '');
    }
    exit;
}

$porPagina = 25;
$total = (int) q("SELECT COUNT(*) $base", $params)->fetchColumn();
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($paginas, max(1, (int) ($_GET['p'] ?? 1)));
$rows = q($select . ' LIMIT ' . $porPagina . ' OFFSET ' . (($pagina - 1) * $porPagina), $params)->fetchAll();

[$wr, $pr] = alcance('id');
$retos = q("SELECT id, codigo, titulo FROM retos WHERE $wr ORDER BY orden, id", $pr)->fetchAll();
$municipios = q('SELECT DISTINCT municipio FROM participantes ORDER BY municipio')->fetchAll(PDO::FETCH_COLUMN);
$qs = http_build_query(array_filter($f, fn($v) => $v !== ''));

admin_header('Inscritos', 'inscritos', ['actions' => '<a class="btn btn-outline" href="?' . e($qs . ($qs ? '&' : '') . 'export=csv') . '">' . ui_icon('download') . 'Exportar CSV</a>']);
?>
<form class="card toolbar" method="get" role="search">
  <div class="field field--search">
    <label for="q">Buscar</label>
    <div class="search"><?= ui_icon('search') ?><input id="q" name="q" type="search" placeholder="Nombre, correo o teléfono" value="<?= e($f['q']) ?>"></div>
  </div>
  <div class="field">
    <label for="reto">Reto</label>
    <select id="reto" name="reto">
      <option value="">Todos</option>
      <?php foreach ($retos as $r): ?><option value="<?= (int) $r['id'] ?>"<?= $f['reto'] === (string) $r['id'] ? ' selected' : '' ?>><?= e($r['codigo'] . ' · ' . $r['titulo']) ?></option><?php endforeach; ?>
      <?php if (es_admin()): ?><option value="general"<?= $f['reto'] === 'general' ? ' selected' : '' ?>>Interés general (sin reto)</option><?php endif; ?>
    </select>
  </div>
  <div class="field">
    <label for="perfil">Perfil</label>
    <select id="perfil" name="perfil"><option value="">Todos</option>
      <?php foreach (PERFILES as $p): ?><option<?= $f['perfil'] === $p ? ' selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="municipio">Municipio</label>
    <select id="municipio" name="municipio"><option value="">Todos</option>
      <?php foreach ($municipios as $m): ?><option<?= $f['municipio'] === $m ? ' selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="estado">Estado</label>
    <select id="estado" name="estado"><option value="">Todos</option>
      <?php foreach (ESTADOS_INSCRIPCION as $k => [$t]): ?><option value="<?= $k ?>"<?= $f['estado'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="btns">
    <button class="btn btn-primary" type="submit">Filtrar</button>
    <?php if ($qs): ?><a class="btn btn-ghost" href="inscritos.php">Limpiar</a><?php endif; ?>
  </div>
</form>

<section class="card card--flush">
  <div class="card-head"><div><h2 class="card-title"><?= number_format($total, 0, ',', '.') ?> inscripciones</h2><p class="card-sub">Una persona aparece una vez por cada reto en el que se inscribió.</p></div></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Persona</th><th>Contacto</th><th>Perfil</th><th>Reto</th><th>Estado</th><th class="num">Docs</th><th>Fecha</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): $tel = preg_replace('/\D/', '', $r['telefono']); ?>
          <tr>
            <td><a class="cell-main" href="inscrito.php?id=<?= (int) $r['pid'] ?>"><?= e($r['nombre']) ?></a><span class="cell-sub"><?= e($r['correo']) ?></span></td>
            <td class="nowrap"><?= e($r['telefono']) ?><?php if (strlen($tel) >= 10): ?><span class="cell-sub"><a href="https://wa.me/<?= strlen($tel) === 10 ? '57' . $tel : $tel ?>" target="_blank" rel="noopener">WhatsApp</a></span><?php endif; ?></td>
            <td><?= e($r['perfil']) ?><span class="cell-sub"><?= e($r['municipio']) ?></span></td>
            <td><?= $r['codigo'] ? '<span class="cell-main">' . e($r['codigo']) . '</span><span class="cell-sub">' . e($r['titulo']) . '</span>' : '<span class="muted">Interés general</span>' ?></td>
            <td>
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="back" value="<?= e($qs . ($pagina > 1 ? ($qs ? '&' : '') . 'p=' . $pagina : '')) ?>">
                <label class="sr-only" for="st-<?= (int) $r['id'] ?>">Estado de <?= e($r['nombre']) ?></label>
                <select id="st-<?= (int) $r['id'] ?>" name="estado" data-autosubmit>
                  <?php foreach (ESTADOS_INSCRIPCION as $k => [$t]): ?><option value="<?= $k ?>"<?= $r['estado'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
                </select>
                <noscript><button class="btn btn-sm btn-outline">Guardar</button></noscript>
              </form>
            </td>
            <td class="num"><?= (int) $r['docs'] ? '<a href="entregas.php?q=' . urlencode($r['correo']) . '">' . (int) $r['docs'] . '</a>' : '0' ?></td>
            <td class="nowrap"><?= e(fecha_corta($r['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">No hay inscripciones con estos filtros.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($paginas > 1): ?>
    <nav class="pagination" aria-label="Paginación">
      <span class="muted small">Página <?= $pagina ?> de <?= $paginas ?></span>
      <div class="pages">
        <?php for ($i = max(1, $pagina - 2); $i <= min($paginas, $pagina + 2); $i++): ?>
          <?php if ($i === $pagina): ?><span class="cur" aria-current="page"><?= $i ?></span>
          <?php else: ?><a href="?<?= e($qs . ($qs ? '&' : '') . 'p=' . $i) ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
      </div>
    </nav>
  <?php endif; ?>
</section>
<?php admin_footer();
