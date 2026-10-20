<?php
/** Revisión de un documento: estado y retroalimentación para el participante. */
require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
$u = require_login();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$d = q('SELECT e.*, i.reto_id, i.participante_id, p.nombre, p.correo, r.codigo, r.titulo, re.titulo entregable, re.descripcion entregable_desc, us.nombre revisor
        FROM entregas e JOIN inscripciones i ON i.id = e.inscripcion_id JOIN participantes p ON p.id = i.participante_id
        LEFT JOIN retos r ON r.id = i.reto_id LEFT JOIN reto_entregables re ON re.id = e.entregable_id LEFT JOIN usuarios us ON us.id = e.revisado_por
        WHERE e.id = ?', [$id])->fetch();
if (!$d || !puede_ver_reto($d['reto_id'] !== null ? (int) $d['reto_id'] : null)) not_found('Documento no encontrado.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $estado = (string) ($_POST['estado'] ?? '');
    $retro = mb_substr(trim((string) ($_POST['retroalimentacion'] ?? '')), 0, 4000);
    if (!isset(ESTADOS_ENTREGA[$estado])) {
        flash('error', 'Selecciona un estado válido.');
    } elseif ($estado === 'requiere_ajustes' && $retro === '') {
        flash('error', 'Escribe la retroalimentación para que la persona sepa qué ajustar.');
    } else {
        q('UPDATE entregas SET estado = ?, retroalimentacion = ?, revisado_por = ?, revisado_at = NOW() WHERE id = ?', [$estado, $retro ?: null, $u['id'], $id]);
        flash('ok', 'Revisión guardada. La persona la verá en «Mis entregas».');
        // Al revisar, pasar al siguiente documento pendiente
        [$w, $p] = alcance('i.reto_id');
        $sig = q("SELECT e.id FROM entregas e JOIN inscripciones i ON i.id = e.inscripcion_id WHERE e.estado = 'recibido' AND e.id <> ? AND $w ORDER BY e.created_at LIMIT 1", array_merge([$id], $p))->fetchColumn();
        redirect(isset($_POST['siguiente']) && $sig ? 'entrega.php?id=' . $sig : 'entrega.php?id=' . $id);
    }
    redirect('entrega.php?id=' . $id);
}

$puedeVer = in_array($d['mime'], ['application/pdf', 'image/jpeg', 'image/png'], true);
admin_header('Revisar documento', 'entregas', ['back' => 'entregas.php']);
?>
<div class="grid-2">
  <section class="card">
    <div class="card-head">
      <div><h2 class="card-title"><?= e($d['nombre_original']) ?></h2><p class="card-sub"><?= e(bytes_legibles((int) $d['tamano'])) ?> · cargado el <?= e(fecha_corta($d['created_at'])) ?></p></div>
      <div class="actions">
        <?php if ($puedeVer): ?><a class="btn btn-outline btn-sm" href="archivo.php?id=<?= $id ?>&ver=1" target="_blank" rel="noopener"><?= ui_icon('eye') ?>Ver</a><?php endif; ?>
        <a class="btn btn-primary btn-sm" href="archivo.php?id=<?= $id ?>"><?= ui_icon('download') ?>Descargar</a>
      </div>
    </div>
    <dl class="dl">
      <div><dt>Persona</dt><dd><a href="inscrito.php?id=<?= (int) $d['participante_id'] ?>"><?= e($d['nombre']) ?></a> · <?= e($d['correo']) ?></dd></div>
      <div><dt>Reto</dt><dd><?= e($d['codigo'] . ' · ' . $d['titulo']) ?></dd></div>
      <div><dt>Entregable</dt><dd><?= e($d['entregable'] ?? 'Otros documentos') ?><?php if ($d['entregable_desc']): ?><span class="cell-sub"><?= e($d['entregable_desc']) ?></span><?php endif; ?></dd></div>
      <div><dt>Nota de la persona</dt><dd><?= $d['comentario'] !== '' ? e($d['comentario']) : '<span class="muted">Sin nota</span>' ?></dd></div>
      <?php if ($d['revisado_at']): ?><div><dt>Última revisión</dt><dd><?= e(($d['revisor'] ?? 'Usuario eliminado') . ' · ' . fecha_corta($d['revisado_at'])) ?></dd></div><?php endif; ?>
    </dl>
  </section>

  <form class="card" method="post">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <div class="card-head"><h2 class="card-title">Revisión</h2></div>
    <div class="form-grid">
      <div class="field span-all">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
          <?php foreach (ESTADOS_ENTREGA as $k => [$t]): ?><option value="<?= $k ?>"<?= $d['estado'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field span-all">
        <label for="retro">Retroalimentación para el participante <span class="opt">(obligatoria si requiere ajustes)</span></label>
        <textarea id="retro" name="retroalimentacion" rows="7" maxlength="4000"><?= e($d['retroalimentacion']) ?></textarea>
        <p class="hint">La persona verá este texto debajo de su documento en «Mis entregas».</p>
      </div>
    </div>
    <div class="actions" style="margin-top: 16px">
      <button class="btn btn-primary" type="submit">Guardar revisión</button>
      <button class="btn btn-outline" type="submit" name="siguiente" value="1">Guardar y revisar el siguiente</button>
    </div>
  </form>
</div>
<?php admin_footer();
