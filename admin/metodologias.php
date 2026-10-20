<?php
/** Metodologías de aprendizaje (ABR, ABP, IAP…) con sus fases. */
require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
$u = require_admin();

$editId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$nuevo = isset($_GET['nuevo']);
$errors = [];
$v = ['clave' => '', 'nombre' => '', 'sigla' => '', 'descripcion' => '', 'fases' => [['nombre' => '', 'desc' => '']]];
if ($editId) {
    $row = q('SELECT * FROM metodologias WHERE id = ?', [$editId])->fetch();
    if (!$row) not_found('Metodología no encontrada.');
    $v = $row;
    $v['fases'] = jdecode($row['fases']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['accion'] ?? '') === 'eliminar') {
        $usos = (int) q('SELECT COUNT(*) FROM retos WHERE metodologia_id = ?', [$editId])->fetchColumn();
        if ($usos) flash('error', "No se puede eliminar: la usan {$usos} retos.");
        else { q('DELETE FROM metodologias WHERE id = ?', [$editId]); flash('ok', 'Metodología eliminada.'); }
        redirect('metodologias.php');
    }
    $v['nombre'] = mb_substr(trim((string) ($_POST['nombre'] ?? '')), 0, 120);
    $v['sigla'] = mb_substr(trim((string) ($_POST['sigla'] ?? '')), 0, 120);
    $v['descripcion'] = mb_substr(trim((string) ($_POST['descripcion'] ?? '')), 0, 3000);
    $v['fases'] = array_values(array_filter(array_map(fn($f) => ['nombre' => mb_substr(trim((string) ($f['nombre'] ?? '')), 0, 120), 'desc' => mb_substr(trim((string) ($f['desc'] ?? '')), 0, 500)],
        (array) ($_POST['fases'] ?? [])), fn($f) => $f['nombre'] !== ''));
    if ($v['nombre'] === '') $errors['nombre'] = 'Escribe el nombre.';
    if ($v['descripcion'] === '') $errors['descripcion'] = 'Escribe una descripción.';
    if (count($v['fases']) < 2) $errors['fases'] = 'Agrega al menos dos fases.';
    if (!$errors) {
        $json = json_encode($v['fases'], JSON_UNESCAPED_UNICODE);
        if ($editId) {
            q('UPDATE metodologias SET nombre = ?, sigla = ?, descripcion = ?, fases = ? WHERE id = ?', [$v['nombre'], $v['sigla'], $v['descripcion'], $json, $editId]);
        } else {
            $clave = substr(slugify($v['sigla'] ?: $v['nombre']), 0, 14) . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
            q('INSERT INTO metodologias (clave, nombre, sigla, descripcion, fases) VALUES (?,?,?,?,?)', [$clave, $v['nombre'], $v['sigla'], $v['descripcion'], $json]);
        }
        flash('ok', 'Metodología guardada.');
        redirect('metodologias.php');
    }
    $nuevo = !$editId;
}

$lista = q('SELECT m.*, (SELECT COUNT(*) FROM retos r WHERE r.metodologia_id = m.id) usos FROM metodologias m ORDER BY m.nombre')->fetchAll();
admin_header('Metodologías', 'metodologias', ['actions' => '<a class="btn btn-primary" href="?nuevo=1#form">' . ui_icon('plus') . 'Nueva metodología</a>']);
?>
<div class="grid-2">
  <section class="card card--flush">
    <div class="card-head"><div><h2 class="card-title">Metodologías disponibles</h2><p class="card-sub">Se eligen en el formulario de cada reto.</p></div></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Metodología</th><th class="num">Fases</th><th class="num">Retos</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($lista as $m): ?>
            <tr>
              <td><a class="cell-main" href="?id=<?= (int) $m['id'] ?>#form"><?= e($m['nombre']) ?></a><span class="cell-sub"><?= e($m['sigla']) ?></span></td>
              <td class="num"><?= count(jdecode($m['fases'])) ?></td>
              <td class="num"><?= (int) $m['usos'] ?></td>
              <td><a class="icon-btn" href="?id=<?= (int) $m['id'] ?>#form" aria-label="Editar <?= e($m['nombre']) ?>"><?= ui_icon('edit') ?></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php if ($editId || $nuevo): ?>
  <form class="card" id="form" method="post" novalidate>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $editId ?>">
    <div class="card-head"><h2 class="card-title"><?= $editId ? 'Editar metodología' : 'Nueva metodología' ?></h2></div>
    <div class="form-grid">
      <div class="field span-all<?= isset($errors['nombre']) ? ' has-error' : '' ?>"><label for="nombre">Nombre</label><input id="nombre" name="nombre" type="text" value="<?= e($v['nombre']) ?>"><?php if (isset($errors['nombre'])): ?><p class="error"><?= e($errors['nombre']) ?></p><?php endif; ?></div>
      <div class="field span-all"><label for="sigla">Sigla o subtítulo</label><input id="sigla" name="sigla" type="text" placeholder="Ej. ABR · Challenge Based Learning" value="<?= e($v['sigla']) ?>"></div>
      <div class="field span-all<?= isset($errors['descripcion']) ? ' has-error' : '' ?>"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" rows="4"><?= e($v['descripcion']) ?></textarea><?php if (isset($errors['descripcion'])): ?><p class="error"><?= e($errors['descripcion']) ?></p><?php endif; ?></div>
    </div>
    <div data-repeater style="margin-top: 16px">
      <p class="label" style="margin-bottom:8px">Fases</p>
      <?php if (isset($errors['fases'])): ?><p class="error"><?= e($errors['fases']) ?></p><?php endif; ?>
      <div class="repeater">
        <?php foreach ($v['fases'] ?: [['nombre' => '', 'desc' => '']] as $i => $f): ?>
          <div class="rep-row">
            <div class="field"><label>Fase</label><input type="text" name="fases[<?= $i ?>][nombre]" value="<?= e($f['nombre']) ?>"></div>
            <div class="field wide"><label>Qué se hace</label><input type="text" name="fases[<?= $i ?>][desc]" value="<?= e($f['desc']) ?>"></div>
            <button type="button" class="icon-btn rep-del" aria-label="Quitar fase"><?= ui_icon('x') ?></button>
          </div>
        <?php endforeach; ?>
      </div>
      <template><div class="rep-row">
        <div class="field"><label>Fase</label><input type="text" name="fases[0][nombre]"></div>
        <div class="field wide"><label>Qué se hace</label><input type="text" name="fases[0][desc]"></div>
        <button type="button" class="icon-btn rep-del" aria-label="Quitar fase"><?= ui_icon('x') ?></button>
      </div></template>
      <button type="button" class="btn btn-outline btn-sm rep-add" style="margin-top:10px"><?= ui_icon('plus') ?>Agregar fase</button>
    </div>
    <div class="actions" style="margin-top: 20px">
      <button class="btn btn-primary" type="submit">Guardar</button>
      <a class="btn btn-ghost" href="metodologias.php">Cancelar</a>
    </div>
  </form>
  <?php if ($editId): ?>
    <form method="post" data-confirm="¿Eliminar esta metodología?" style="grid-column: 2">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $editId ?>"><input type="hidden" name="accion" value="eliminar">
      <button class="btn btn-danger btn-sm" type="submit"><?= ui_icon('trash') ?>Eliminar metodología</button>
    </form>
  <?php endif; ?>
  <?php else: ?>
  <section class="card"><p class="muted">Selecciona una metodología para editarla o crea una nueva.</p></section>
  <?php endif; ?>
</div>
<?php admin_footer();
