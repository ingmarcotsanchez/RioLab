<?php
/** Documentos cargados por los participantes. */
require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
$u = require_login();

$f = ['q' => trim((string) ($_GET['q'] ?? '')), 'reto' => (string) ($_GET['reto'] ?? ''), 'estado' => (string) ($_GET['estado'] ?? '')];
[$w, $params] = alcance('i.reto_id');
$where = [$w];
if ($f['q'] !== '') {
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $f['q']) . '%';
    $where[] = '(p.nombre LIKE ? OR p.correo LIKE ? OR e.nombre_original LIKE ?)';
    array_push($params, $like, $like, $like);
}
if ($f['reto'] !== '') { $where[] = 'i.reto_id = ?'; $params[] = (int) $f['reto']; }
if (isset(ESTADOS_ENTREGA[$f['estado']])) { $where[] = 'e.estado = ?'; $params[] = $f['estado']; }
$base = 'FROM entregas e JOIN inscripciones i ON i.id = e.inscripcion_id JOIN participantes p ON p.id = i.participante_id
         LEFT JOIN retos r ON r.id = i.reto_id LEFT JOIN reto_entregables re ON re.id = e.entregable_id WHERE ' . implode(' AND ', $where);

$porPagina = 25;
$total = (int) q("SELECT COUNT(*) $base", $params)->fetchColumn();
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($paginas, max(1, (int) ($_GET['p'] ?? 1)));
$rows = q("SELECT e.*, p.id pid, p.nombre, p.correo, r.codigo, r.titulo, re.titulo entregable $base
           ORDER BY e.estado = 'recibido' DESC, e.created_at DESC LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina), $params)->fetchAll();

[$wr, $pr] = alcance('id');
$retos = q("SELECT id, codigo, titulo FROM retos WHERE $wr ORDER BY orden, id", $pr)->fetchAll();
$qs = http_build_query(array_filter($f, fn($v) => $v !== ''));

admin_header('Entregas', 'entregas');
?>
<form class="card toolbar" method="get" role="search">
  <div class="field field--search">
    <label for="q">Buscar</label>
    <div class="search"><?= ui_icon('search') ?><input id="q" name="q" type="search" placeholder="Persona, correo o nombre del archivo" value="<?= e($f['q']) ?>"></div>
  </div>
  <div class="field">
    <label for="reto">Reto</label>
    <select id="reto" name="reto"><option value="">Todos</option>
      <?php foreach ($retos as $r): ?><option value="<?= (int) $r['id'] ?>"<?= $f['reto'] === (string) $r['id'] ? ' selected' : '' ?>><?= e($r['codigo'] . ' · ' . $r['titulo']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="estado">Estado</label>
    <select id="estado" name="estado"><option value="">Todos</option>
      <?php foreach (ESTADOS_ENTREGA as $k => [$t]): ?><option value="<?= $k ?>"<?= $f['estado'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="btns">
    <button class="btn btn-primary" type="submit">Filtrar</button>
    <?php if ($qs): ?><a class="btn btn-ghost" href="entregas.php">Limpiar</a><?php endif; ?>
  </div>
</form>

<section class="card card--flush">
  <div class="card-head"><div><h2 class="card-title"><?= $total ?> documentos</h2><p class="card-sub">Los documentos sin revisar aparecen primero.</p></div></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Documento</th><th>Persona</th><th>Reto / entregable</th><th>Estado</th><th>Cargado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rows as $d): [$et, $tone] = ESTADOS_ENTREGA[$d['estado']]; ?>
          <tr>
            <td><a class="cell-main" href="entrega.php?id=<?= (int) $d['id'] ?>"><?= e($d['nombre_original']) ?></a><span class="cell-sub"><?= e(bytes_legibles((int) $d['tamano'])) ?></span></td>
            <td><a class="cell-main" href="inscrito.php?id=<?= (int) $d['pid'] ?>"><?= e($d['nombre']) ?></a><span class="cell-sub"><?= e($d['correo']) ?></span></td>
            <td><span class="cell-main"><?= e($d['codigo']) ?></span><span class="cell-sub"><?= e($d['entregable'] ?? 'Otros documentos') ?></span></td>
            <td><?= badge($et, $tone) ?></td>
            <td class="nowrap"><?= e(fecha_corta($d['created_at'])) ?></td>
            <td class="actions">
              <a class="btn btn-outline btn-sm" href="entrega.php?id=<?= (int) $d['id'] ?>">Revisar</a>
              <a class="icon-btn" href="archivo.php?id=<?= (int) $d['id'] ?>" aria-label="Descargar <?= e($d['nombre_original']) ?>"><?= ui_icon('download') ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="empty">No hay documentos con estos filtros.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($paginas > 1): ?>
    <nav class="pagination" aria-label="Paginación">
      <span class="muted small">Página <?= $pagina ?> de <?= $paginas ?></span>
      <div class="pages">
        <?php for ($i = max(1, $pagina - 2); $i <= min($paginas, $pagina + 2); $i++): ?>
          <?php if ($i === $pagina): ?><span class="cur" aria-current="page"><?= $i ?></span><?php else: ?><a href="?<?= e($qs . ($qs ? '&' : '') . 'p=' . $i) ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
      </div>
    </nav>
  <?php endif; ?>
</section>
<?php admin_footer();
