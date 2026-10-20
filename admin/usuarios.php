<?php
/** Usuarios del panel: administradores y mentores (con sus retos asignados). */
require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
$u = require_admin();

$editId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$nuevo = isset($_GET['nuevo']);
$errors = [];
$v = ['nombre' => '', 'correo' => '', 'rol' => 'mentor', 'activo' => 1, 'retos' => []];
if ($editId) {
    $row = q('SELECT * FROM usuarios WHERE id = ?', [$editId])->fetch();
    if (!$row) not_found('Usuario no encontrado.');
    $v = $row + ['retos' => array_map('intval', q('SELECT reto_id FROM mentor_retos WHERE usuario_id = ?', [$editId])->fetchAll(PDO::FETCH_COLUMN))];
}
$retos = q('SELECT id, codigo, titulo FROM retos ORDER BY orden, id')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v['nombre'] = mb_substr(trim((string) ($_POST['nombre'] ?? '')), 0, 120);
    $v['correo'] = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
    $v['rol'] = ($_POST['rol'] ?? '') === 'admin' ? 'admin' : 'mentor';
    $v['activo'] = isset($_POST['activo']) ? 1 : 0;
    $v['retos'] = array_map('intval', (array) ($_POST['retos'] ?? []));
    $pass = (string) ($_POST['password'] ?? '');

    if ($v['nombre'] === '') $errors['nombre'] = 'Escribe el nombre.';
    if (!filter_var($v['correo'], FILTER_VALIDATE_EMAIL)) $errors['correo'] = 'Correo inválido.';
    elseif (q('SELECT 1 FROM usuarios WHERE correo = ? AND id <> ?', [$v['correo'], $editId])->fetch()) $errors['correo'] = 'Ya existe un usuario con este correo.';
    if ((!$editId || $pass !== '') && mb_strlen($pass) < 10) $errors['password'] = 'La contraseña debe tener al menos 10 caracteres.';
    if ($editId === (int) $u['id'] && ($v['rol'] !== 'admin' || !$v['activo'])) $errors['rol'] = 'No puedes quitarte el rol de administrador ni desactivar tu propia cuenta.';

    if (!$errors) {
        if ($editId) {
            q('UPDATE usuarios SET nombre = ?, correo = ?, rol = ?, activo = ? WHERE id = ?', [$v['nombre'], $v['correo'], $v['rol'], $v['activo'], $editId]);
            if ($pass !== '') q('UPDATE usuarios SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $editId]);
        } else {
            q('INSERT INTO usuarios (nombre, correo, password_hash, rol, activo) VALUES (?,?,?,?,?)', [$v['nombre'], $v['correo'], password_hash($pass, PASSWORD_DEFAULT), $v['rol'], $v['activo']]);
            $editId = (int) db()->lastInsertId();
        }
        q('DELETE FROM mentor_retos WHERE usuario_id = ?', [$editId]);
        if ($v['rol'] === 'mentor') {
            foreach (array_intersect($v['retos'], array_map('intval', array_column($retos, 'id'))) as $r) q('INSERT INTO mentor_retos (usuario_id, reto_id) VALUES (?, ?)', [$editId, $r]);
        }
        flash('ok', 'Usuario guardado.' . ($pass !== '' ? ' Comparte la contraseña con la persona por un canal privado.' : ''));
        redirect('usuarios.php');
    }
    $nuevo = !$editId;
}

$lista = q("SELECT us.*, (SELECT GROUP_CONCAT(r.codigo ORDER BY r.orden SEPARATOR ', ') FROM mentor_retos mr JOIN retos r ON r.id = mr.reto_id WHERE mr.usuario_id = us.id) retos_txt
            FROM usuarios us ORDER BY us.rol, us.nombre")->fetchAll();
admin_header('Usuarios del panel', 'usuarios', ['actions' => '<a class="btn btn-primary" href="?nuevo=1#form">' . ui_icon('plus') . 'Nuevo usuario</a>']);
?>
<div class="grid-2">
  <section class="card card--flush">
    <div class="card-head"><div><h2 class="card-title"><?= count($lista) ?> usuarios</h2><p class="card-sub">Los mentores solo ven los inscritos y entregas de sus retos.</p></div></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Usuario</th><th>Rol</th><th>Retos</th><th>Último acceso</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($lista as $x): ?>
            <tr>
              <td><a class="cell-main" href="?id=<?= (int) $x['id'] ?>#form"><?= e($x['nombre']) ?></a><span class="cell-sub"><?= e($x['correo']) ?></span></td>
              <td><?= $x['rol'] === 'admin' ? badge('Administrador', 'info') : badge('Mentor', 'neutral') ?><?= $x['activo'] ? '' : ' ' . badge('Inactivo', 'danger') ?></td>
              <td><?= $x['rol'] === 'admin' ? '<span class="muted">Todos</span>' : ($x['retos_txt'] ? e($x['retos_txt']) : '<span class="muted">Ninguno</span>') ?></td>
              <td class="nowrap"><?= e(fecha_corta($x['ultimo_acceso'])) ?></td>
              <td><a class="icon-btn" href="?id=<?= (int) $x['id'] ?>#form" aria-label="Editar <?= e($x['nombre']) ?>"><?= ui_icon('edit') ?></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php if ($editId || $nuevo): ?>
  <form class="card" id="form" method="post" novalidate>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $editId ?>">
    <div class="card-head"><h2 class="card-title"><?= $editId ? 'Editar usuario' : 'Nuevo usuario' ?></h2></div>
    <div class="form-grid">
      <?php foreach (['nombre' => ['Nombre', 'text', 'name'], 'correo' => ['Correo', 'email', 'off']] as $k => [$label, $type, $ac]): ?>
        <div class="field span-all<?= isset($errors[$k]) ? ' has-error' : '' ?>">
          <label for="<?= $k ?>"><?= $label ?></label>
          <input id="<?= $k ?>" name="<?= $k ?>" type="<?= $type ?>" autocomplete="<?= $ac ?>" value="<?= e($v[$k]) ?>">
          <?php if (isset($errors[$k])): ?><p class="error"><?= e($errors[$k]) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="field span-all<?= isset($errors['password']) ? ' has-error' : '' ?>">
        <label for="password"><?= $editId ? 'Nueva contraseña <span class="opt">(déjala vacía para no cambiarla)</span>' : 'Contraseña inicial' ?></label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="10">
        <p class="hint">Mínimo 10 caracteres. La persona puede cambiarla en «Mi cuenta».</p>
        <?php if (isset($errors['password'])): ?><p class="error"><?= e($errors['password']) ?></p><?php endif; ?>
      </div>
      <div class="field<?= isset($errors['rol']) ? ' has-error' : '' ?>">
        <label for="rol">Rol</label>
        <select id="rol" name="rol">
          <option value="mentor"<?= $v['rol'] === 'mentor' ? ' selected' : '' ?>>Mentor / Docente</option>
          <option value="admin"<?= $v['rol'] === 'admin' ? ' selected' : '' ?>>Administrador</option>
        </select>
        <?php if (isset($errors['rol'])): ?><p class="error"><?= e($errors['rol']) ?></p><?php endif; ?>
      </div>
      <div class="field" style="align-self:end"><label class="check"><input type="checkbox" name="activo" value="1"<?= $v['activo'] ? ' checked' : '' ?>>Cuenta activa</label></div>
      <fieldset class="field span-all" style="border:0;padding:0;margin:0">
        <legend class="label" style="margin-bottom:6px">Retos asignados <span class="opt">(solo para mentores)</span></legend>
        <div class="ods-picker">
          <?php foreach ($retos as $r): ?>
            <label><input type="checkbox" name="retos[]" value="<?= (int) $r['id'] ?>"<?= in_array((int) $r['id'], $v['retos'], true) ? ' checked' : '' ?>><?= e($r['codigo'] . ' · ' . $r['titulo']) ?></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
    </div>
    <div class="actions" style="margin-top: 20px">
      <button class="btn btn-primary" type="submit">Guardar</button>
      <a class="btn btn-ghost" href="usuarios.php">Cancelar</a>
    </div>
  </form>
  <?php else: ?>
  <section class="card"><p class="muted">Selecciona un usuario para editarlo o crea uno nuevo.</p></section>
  <?php endif; ?>
</div>
<?php admin_footer();
