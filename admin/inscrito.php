<?php
/** Ficha de una persona inscrita: datos, inscripciones, documentos y código de acceso. */
require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
$u = require_login();

$p = q('SELECT * FROM participantes WHERE id = ?', [(int) ($_GET['id'] ?? $_POST['id'] ?? 0)])->fetch();
if (!$p) not_found('Persona no encontrada.');

[$w, $wp] = alcance('reto_id');
$inscripciones = q("SELECT i.*, r.codigo, r.titulo, r.slug FROM inscripciones i LEFT JOIN retos r ON r.id = i.reto_id
                    WHERE i.participante_id = ? AND " . str_replace('reto_id', 'i.reto_id', $w) . ' ORDER BY i.created_at DESC', array_merge([$p['id']], $wp))->fetchAll();
if (!$inscripciones && !es_admin()) {
    flash('error', 'Esta persona no está inscrita en tus retos.');
    redirect('inscritos.php');
}

$nuevoCodigo = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'codigo') {
        $nuevoCodigo = codigo_generar();
        q('UPDATE participantes SET codigo_hash = ? WHERE id = ?', [password_hash(codigo_normalizar($nuevoCodigo), PASSWORD_DEFAULT), $p['id']]);
    } elseif ($accion === 'eliminar' && es_admin()) {
        $archivos = q('SELECT e.archivo FROM entregas e JOIN inscripciones i ON i.id = e.inscripcion_id WHERE i.participante_id = ?', [$p['id']])->fetchAll(PDO::FETCH_COLUMN);
        q('DELETE FROM participantes WHERE id = ?', [$p['id']]);
        foreach ($archivos as $a) upload_borrar($a);
        flash('ok', 'Se eliminaron los datos de ' . $p['nombre'] . ' y sus ' . count($archivos) . ' documentos.');
        redirect('inscritos.php');
    } elseif ($accion === 'estado') {
        $ins = q('SELECT * FROM inscripciones WHERE id = ? AND participante_id = ?', [(int) ($_POST['ins'] ?? 0), $p['id']])->fetch();
        $estado = (string) ($_POST['estado'] ?? '');
        if ($ins && isset(ESTADOS_INSCRIPCION[$estado]) && puede_ver_reto($ins['reto_id'] !== null ? (int) $ins['reto_id'] : null)) {
            q('UPDATE inscripciones SET estado = ? WHERE id = ?', [$estado, $ins['id']]);
            flash('ok', 'Estado actualizado.');
        }
        redirect('inscrito.php?id=' . $p['id']);
    }
}

$insIds = array_column($inscripciones, 'id') ?: [0];
$docs = q('SELECT e.*, re.titulo AS entregable, r.codigo FROM entregas e JOIN inscripciones i ON i.id = e.inscripcion_id
           LEFT JOIN reto_entregables re ON re.id = e.entregable_id LEFT JOIN retos r ON r.id = i.reto_id
           WHERE e.inscripcion_id IN (' . implode(',', array_map('intval', $insIds)) . ') ORDER BY e.created_at DESC')->fetchAll();
$tel = preg_replace('/\D/', '', $p['telefono']);

admin_header($p['nombre'], 'inscritos', ['back' => 'inscritos.php']);
?>
<?php if ($nuevoCodigo): ?>
  <div class="code-box" role="status">
    <span>Nuevo código de acceso de <?= e($p['nombre']) ?>:</span>
    <code><?= e($nuevoCodigo) ?></code>
    <button class="btn btn-outline btn-sm" type="button" data-copy="<?= e($nuevoCodigo) ?>">Copiar</button>
    <span class="muted small">Compártelo con la persona por un canal privado. No se volverá a mostrar; el código anterior dejó de funcionar.</span>
  </div>
<?php endif; ?>

<div class="grid-2">
  <section class="card">
    <div class="card-head"><h2 class="card-title">Datos de contacto</h2></div>
    <dl class="dl">
      <div><dt>Correo</dt><dd><a href="mailto:<?= e($p['correo']) ?>"><?= e($p['correo']) ?></a></dd></div>
      <div><dt>Teléfono</dt><dd><?= e($p['telefono']) ?><?php if (strlen($tel) >= 10): ?> · <a href="https://wa.me/<?= strlen($tel) === 10 ? '57' . $tel : $tel ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></dd></div>
      <div><dt>Municipio</dt><dd><?= e($p['municipio']) ?></dd></div>
      <div><dt>Perfil</dt><dd><?= e($p['perfil']) ?></dd></div>
      <div><dt>Registro</dt><dd><?= e(fecha_corta($p['created_at'])) ?></dd></div>
      <div><dt>Autorización de datos</dt><dd><?= e(fecha_corta($p['acepto_datos_at'])) ?> (Ley 1581 de 2012)</dd></div>
      <div><dt>Último ingreso a Mis entregas</dt><dd><?= e(fecha_corta($p['ultimo_acceso'])) ?></dd></div>
    </dl>
  </section>

  <section class="card">
    <div class="card-head"><h2 class="card-title">Código de acceso</h2></div>
    <p class="muted">Por seguridad el código no se guarda en texto plano, así que no se puede consultar. Si la persona lo perdió, genera uno nuevo y compártelo con ella.</p>
    <form method="post" style="margin-top: 14px" data-confirm="¿Generar un código nuevo? El código actual dejará de funcionar.">
      <?= csrf_field() ?><input type="hidden" name="accion" value="codigo"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <button class="btn btn-outline" type="submit"><?= ui_icon('key') ?>Generar nuevo código</button>
    </form>
    <?php if (es_admin()): ?>
      <hr style="border: 0; border-top: 1px solid var(--border); margin: 20px 0">
      <h3 class="card-title" style="font-size: .95rem">Eliminar datos personales</h3>
      <p class="muted small" style="margin: 6px 0 12px">Borra a la persona, sus inscripciones y sus documentos (derecho de supresión, Ley 1581). No se puede deshacer.</p>
      <form method="post" data-confirm="¿Eliminar definitivamente a <?= e($p['nombre']) ?> y todos sus documentos?">
        <?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <button class="btn btn-danger" type="submit"><?= ui_icon('trash') ?>Eliminar persona</button>
      </form>
    <?php endif; ?>
  </section>
</div>

<section class="card card--flush">
  <div class="card-head"><h2 class="card-title">Inscripciones</h2></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Reto</th><th>Mensaje</th><th>Origen</th><th>Estado</th><th>Fecha</th></tr></thead>
      <tbody>
        <?php foreach ($inscripciones as $i): ?>
          <tr>
            <td><?= $i['codigo'] ? '<span class="cell-main">' . e($i['codigo']) . '</span><span class="cell-sub">' . e($i['titulo']) . '</span>' : '<span class="muted">Interés general</span>' ?></td>
            <td><?= $i['mensaje'] !== '' ? e($i['mensaje']) : '<span class="muted">—</span>' ?></td>
            <td><?= $i['origen'] === 'reto' ? 'Página del reto' : 'Inicio' ?></td>
            <td>
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="ins" value="<?= (int) $i['id'] ?>">
                <label class="sr-only" for="st-<?= (int) $i['id'] ?>">Estado</label>
                <select id="st-<?= (int) $i['id'] ?>" name="estado" data-autosubmit>
                  <?php foreach (ESTADOS_INSCRIPCION as $k => [$t]): ?><option value="<?= $k ?>"<?= $i['estado'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
                </select>
              </form>
            </td>
            <td class="nowrap"><?= e(fecha_corta($i['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card">
  <div class="card-head"><h2 class="card-title">Documentos cargados (<?= count($docs) ?>)</h2></div>
  <?php foreach ($docs as $d): [$et, $tone] = ESTADOS_ENTREGA[$d['estado']]; ?>
    <div class="doc">
      <span class="doc-icon"><?= ui_icon('files') ?></span>
      <div class="doc-main">
        <a class="cell-main" href="entrega.php?id=<?= (int) $d['id'] ?>"><?= e($d['nombre_original']) ?></a>
        <span class="cell-sub"><?= e(($d['codigo'] ?? '') . ' · ' . ($d['entregable'] ?? 'Otros documentos') . ' · ' . fecha_corta($d['created_at']) . ' · ' . bytes_legibles((int) $d['tamano'])) ?></span>
      </div>
      <?= badge($et, $tone) ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$docs): ?><p class="empty">Aún no ha cargado documentos.</p><?php endif; ?>
</section>
<?php admin_footer();
