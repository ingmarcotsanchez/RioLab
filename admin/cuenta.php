<?php
require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
$u = require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $actual = (string) ($_POST['actual'] ?? '');
    $nueva = (string) ($_POST['nueva'] ?? '');
    $hash = q('SELECT password_hash FROM usuarios WHERE id = ?', [$u['id']])->fetchColumn();
    if (!password_verify($actual, $hash)) $errors['actual'] = 'La contraseña actual no es correcta.';
    if (mb_strlen($nueva) < 10) $errors['nueva'] = 'Usa al menos 10 caracteres.';
    if ($nueva !== ($_POST['confirmar'] ?? '')) $errors['confirmar'] = 'Las contraseñas no coinciden.';
    if (!$errors) {
        q('UPDATE usuarios SET password_hash = ? WHERE id = ?', [password_hash($nueva, PASSWORD_DEFAULT), $u['id']]);
        session_regenerate_id(true);
        flash('ok', 'Tu contraseña se actualizó.');
        redirect('cuenta.php');
    }
}

admin_header('Mi cuenta', 'cuenta');
?>
<div class="grid-2">
  <section class="card">
    <h2 class="card-title">Datos de la cuenta</h2>
    <dl class="dl">
      <div><dt>Nombre</dt><dd><?= e($u['nombre']) ?></dd></div>
      <div><dt>Correo</dt><dd><?= e($u['correo']) ?></dd></div>
      <div><dt>Rol</dt><dd><?= $u['rol'] === 'admin' ? 'Administrador' : 'Mentor' ?></dd></div>
    </dl>
  </section>
  <form class="card" method="post" novalidate>
    <?= csrf_field() ?>
    <h2 class="card-title">Cambiar contraseña</h2>
    <?php foreach (['actual' => ['Contraseña actual', 'current-password'], 'nueva' => ['Nueva contraseña', 'new-password'], 'confirmar' => ['Confirmar nueva contraseña', 'new-password']] as $k => [$label, $ac]): ?>
      <div class="field<?= isset($errors[$k]) ? ' has-error' : '' ?>">
        <label for="<?= $k ?>"><?= $label ?></label>
        <input id="<?= $k ?>" name="<?= $k ?>" type="password" autocomplete="<?= $ac ?>" required<?= isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="' . $k . '-err"' : '' ?>>
        <?php if (isset($errors[$k])): ?><p class="error" id="<?= $k ?>-err"><?= e($errors[$k]) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <button class="btn btn-primary" type="submit">Guardar contraseña</button>
  </form>
</div>
<?php admin_footer();
