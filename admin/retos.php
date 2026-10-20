<?php
/** Listado de retos: estado, cupos, acciones de edición, duplicado y eliminación. */
require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
$u = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $reto = q('SELECT * FROM retos WHERE id = ?', [$id])->fetch();
    $accion = $_POST['accion'] ?? '';
    if (!$reto) {
        flash('error', 'El reto no existe.');
    } elseif ($accion === 'estado' && in_array($_POST['estado'] ?? '', ['borrador', 'publicado', 'cerrado'], true)) {
        q('UPDATE retos SET estado = ? WHERE id = ?', [$_POST['estado'], $id]);
        flash('ok', "«{$reto['titulo']}» ahora está " . ['borrador' => 'en borrador', 'publicado' => 'publicado', 'cerrado' => 'cerrado'][$_POST['estado']] . '.');
    } elseif ($accion === 'duplicar') {
        $pdo = db();
        $pdo->beginTransaction();
        $cols = array_diff(array_keys($reto), ['id', 'slug', 'codigo', 'titulo', 'estado', 'created_at', 'updated_at']);
        $slug = $reto['slug'] . '-copia';
        for ($n = 2; q('SELECT 1 FROM retos WHERE slug = ?', [$slug])->fetch(); $n++) $slug = $reto['slug'] . '-copia-' . $n;
        q('INSERT INTO retos (slug, codigo, titulo, estado, ' . implode(', ', $cols) . ') VALUES (?, ?, ?, \'borrador\', ' . implode(', ', array_fill(0, count($cols), '?')) . ')',
            array_merge([$slug, $reto['codigo'] . '-C', $reto['titulo'] . ' (copia)'], array_map(fn($c) => $reto[$c], $cols)));
        $nuevo = (int) $pdo->lastInsertId();
        q('INSERT INTO reto_entregables (reto_id, orden, titulo, descripcion, semana) SELECT ?, orden, titulo, descripcion, semana FROM reto_entregables WHERE reto_id = ?', [$nuevo, $id]);
        $pdo->commit();
        flash('ok', 'Se creó una copia en borrador. Ajusta el título y el código antes de publicarla.');
        redirect('reto-form.php?id=' . $nuevo);
    } elseif ($accion === 'eliminar') {
        $ins = (int) q('SELECT COUNT(*) FROM inscripciones WHERE reto_id = ?', [$id])->fetchColumn();
        if ($ins > 0) {
            flash('error', "«{$reto['titulo']}» tiene {$ins} inscripciones: no se puede eliminar. Puedes cerrarlo para que no reciba más inscritos.");
        } else {
            q('DELETE FROM retos WHERE id = ?', [$id]);
            flash('ok', "Se eliminó «{$reto['titulo']}».");
        }
    }
    redirect('retos.php');
}

$retos = q("SELECT r.*, m.nombre metodologia,
              (SELECT COUNT(*) FROM inscripciones i WHERE i.reto_id = r.id AND i.estado <> 'rechazado') ocupados,
              (SELECT COUNT(*) FROM entregas e JOIN inscripciones i ON i.id = e.inscripcion_id WHERE i.reto_id = r.id) docs,
              (SELECT GROUP_CONCAT(us.nombre SEPARATOR ', ') FROM mentor_retos mr JOIN usuarios us ON us.id = mr.usuario_id WHERE mr.reto_id = r.id) mentores
            FROM retos r JOIN metodologias m ON m.id = r.metodologia_id ORDER BY r.orden, r.id")->fetchAll();
$tonos = ['borrador' => ['Borrador', 'draft'], 'publicado' => ['Publicado', 'ok'], 'cerrado' => ['Cerrado', 'neutral']];

admin_header('Retos', 'retos', ['actions' => '<a class="btn btn-primary" href="reto-form.php">' . ui_icon('plus') . 'Crear reto</a>']);
?>
<section class="card card--flush">
  <div class="card-head"><div><h2 class="card-title"><?= count($retos) ?> retos</h2><p class="card-sub">Solo los retos publicados reciben inscripciones. Los cerrados siguen visibles en el sitio.</p></div></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th class="w-main">Reto</th><th>Metodología</th><th>Cupos</th><th class="num">Docs</th><th>Mentores</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach ($retos as $r): $pct = $r['cupos'] ? min(100, round($r['ocupados'] / $r['cupos'] * 100)) : 0; ?>
          <tr>
            <td><a class="cell-main" href="reto-form.php?id=<?= (int) $r['id'] ?>"><?= e($r['codigo'] . ' · ' . $r['titulo']) ?></a><span class="cell-sub"><?= e($r['tag']) ?> · cierre <?= e(fecha_corta($r['fecha_cierre'])) ?></span></td>
            <td><?= e($r['metodologia']) ?></td>
            <td style="min-width: 130px">
              <div class="bar-top small"><span><?= (int) $r['ocupados'] ?> / <?= (int) $r['cupos'] ?></span><strong><?= $pct ?> %</strong></div>
              <div class="bar"><i class="<?= $pct >= 100 ? 'full' : '' ?>" style="width: <?= $pct ?>%"></i></div>
            </td>
            <td class="num"><?= (int) $r['docs'] ?></td>
            <td><?= $r['mentores'] ? e($r['mentores']) : '<span class="muted">Sin asignar</span>' ?></td>
            <td>
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <label class="sr-only" for="es-<?= (int) $r['id'] ?>">Estado</label>
                <select id="es-<?= (int) $r['id'] ?>" name="estado" data-autosubmit>
                  <?php foreach ($tonos as $k => [$t]): ?><option value="<?= $k ?>"<?= $r['estado'] === $k ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
                </select>
              </form>
            </td>
            <td class="actions">
              <a class="icon-btn" href="reto-form.php?id=<?= (int) $r['id'] ?>" aria-label="Editar <?= e($r['titulo']) ?>" title="Editar"><?= ui_icon('edit') ?></a>
              <?php if ($r['estado'] !== 'borrador'): ?>
                <a class="icon-btn" href="../reto.php?r=<?= e(urlencode($r['slug'])) ?>" target="_blank" rel="noopener" aria-label="Ver en el sitio" title="Ver en el sitio"><?= ui_icon('external') ?></a>
                <a class="icon-btn" href="../reto-pdf.php?r=<?= e(urlencode($r['slug'])) ?>" aria-label="Descargar PDF" title="Descargar PDF"><?= ui_icon('pdf') ?></a>
              <?php endif; ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="duplicar"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit">Duplicar</button></form>
              <form method="post" data-confirm="¿Eliminar «<?= e($r['titulo']) ?>»? Esta acción no se puede deshacer.">
                <?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="icon-btn" type="submit" aria-label="Eliminar <?= e($r['titulo']) ?>" title="Eliminar" style="color:#b91c1c"><?= ui_icon('trash') ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$retos): ?><tr><td colspan="7" class="empty">Aún no hay retos. <a href="reto-form.php">Crea el primero</a>.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_footer();
