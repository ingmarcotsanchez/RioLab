<?php
require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';

if (admin_actual()) redirect('index.php');
$error = '';
$correo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $correo = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
    $pass = (string) ($_POST['password'] ?? '');
    if (too_many_attempts('admin', $correo, 5, 15)) {
        $error = 'Demasiados intentos fallidos. Espera 15 minutos e inténtalo de nuevo.';
    } else {
        $u = q('SELECT * FROM usuarios WHERE correo = ? AND activo = 1', [$correo])->fetch();
        if ($u && password_verify($pass, $u['password_hash'])) {
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE usuarios SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
            }
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $u['id'];
            $_SESSION['admin_seen'] = time();
            clear_attempts('admin', $correo);
            q('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?', [$u['id']]);
            $next = $_SESSION['admin_next'] ?? '';
            unset($_SESSION['admin_next']);
            // Solo redirecciones internas al panel
            redirect(preg_match('#^/[^/]#', $next) && str_contains($next, '/admin/') ? $next : 'index.php');
        }
        register_attempt('admin', $correo);
        $error = 'Correo o contraseña incorrectos.';
    }
}

admin_header('Iniciar sesión');
?>
<div class="login">
  <div class="login-art" aria-hidden="true">
    <svg viewBox="0 0 400 400" preserveAspectRatio="none"><path d="M0 250c70-40 130-40 200 0s130 40 200 0"/><path d="M0 290c70-40 130-40 200 0s130 40 200 0"/><path d="M0 330c70-40 130-40 200 0s130 40 200 0"/></svg>
  </div>
  <form class="card login-card" method="post" novalidate>
    <?= csrf_field() ?>
    <img src="../image/logo_riolab-nav.png" alt="RioLab 4.0" width="72" height="72" class="login-logo">
    <h1>Panel RioLab 4.0</h1>
    <p class="muted">Ingresa con tu cuenta de administrador o mentor.</p>
    <?php if ($error): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <div class="field">
      <label for="correo">Correo</label>
      <input id="correo" name="correo" type="email" autocomplete="username" required value="<?= e($correo) ?>" autofocus>
    </div>
    <div class="field">
      <label for="password">Contraseña</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Ingresar</button>
    <a class="muted small center" href="../index.php">← Volver al sitio</a>
  </form>
</div>
<?php admin_footer();
