<?php
/** Layout del panel: barra lateral + barra superior. */

function admin_header(string $title, string $active = '', array $opts = []): void
{
    $u = admin_actual();
    $pendientes = 0;
    if ($u) {
        [$w, $p] = alcance('i.reto_id');
        $pendientes = (int) q("SELECT COUNT(*) FROM entregas e JOIN inscripciones i ON i.id = e.inscripcion_id WHERE e.estado = 'recibido' AND $w", $p)->fetchColumn();
    }
    $nav = [
        ['index.php', 'dashboard', 'Dashboard', 'dashboard', false],
        ['inscritos.php', 'inscritos', 'Inscritos', 'users', false],
        ['entregas.php', 'entregas', 'Entregas', 'files', false],
        ['retos.php', 'retos', 'Retos', 'flag', true],
        ['metodologias.php', 'metodologias', 'Metodologías', 'book', true],
        ['usuarios.php', 'usuarios', 'Usuarios del panel', 'shield', true],
    ];
    ?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title) ?> · Panel RioLab 4.0</title>
  <link rel="icon" type="image/png" href="../image/logo_riolab-nav.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="adm<?= $u ? '' : ' adm--guest' ?>">
  <a class="skip-link" href="#contenido">Saltar al contenido</a>
<?php if ($u): ?>
  <aside class="sidebar" id="sidebar" aria-label="Menú del panel">
    <a class="sb-brand" href="index.php">
      <img src="../image/logo_riolab-nav.png" alt="" width="40" height="40">
      <span><strong>RioLab 4.0</strong><small>Panel administrativo</small></span>
    </a>
    <nav class="sb-nav">
      <?php foreach ($nav as [$href, $key, $label, $icon, $soloAdmin]):
          if ($soloAdmin && $u['rol'] !== 'admin') continue; ?>
        <a href="<?= $href ?>"<?= $active === $key ? ' class="is-active" aria-current="page"' : '' ?>>
          <?= ui_icon($icon) ?><span><?= e($label) ?></span>
          <?php if ($key === 'entregas' && $pendientes): ?><span class="sb-count" aria-label="<?= $pendientes ?> sin revisar"><?= $pendientes ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sb-foot">
      <a href="../index.php" target="_blank" rel="noopener"><?= ui_icon('external') ?><span>Ver sitio público</span></a>
      <a href="cuenta.php"<?= $active === 'cuenta' ? ' class="is-active" aria-current="page"' : '' ?>><?= ui_icon('user') ?><span>Mi cuenta</span></a>
      <form method="post" action="logout.php"><?= csrf_field() ?><button type="submit"><?= ui_icon('logout') ?><span>Cerrar sesión</span></button></form>
    </div>
  </aside>
  <div class="sb-backdrop" data-close-sidebar></div>
<?php endif; ?>

  <div class="main">
<?php if ($u): ?>
    <header class="topbar">
      <button class="icon-btn sb-toggle" type="button" aria-controls="sidebar" aria-expanded="false" aria-label="Abrir menú"><?= ui_icon('menu') ?></button>
      <div class="topbar-title">
        <?php if (!empty($opts['back'])): ?><a class="back" href="<?= e($opts['back']) ?>"><?= ui_icon('arrow-left') ?><span>Volver</span></a><?php endif; ?>
        <h1><?= e($title) ?></h1>
      </div>
      <div class="topbar-actions"><?= $opts['actions'] ?? '' ?></div>
      <div class="who">
        <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($u['nombre'], 0, 1))) ?></span>
        <span class="who-text"><strong><?= e($u['nombre']) ?></strong><small><?= $u['rol'] === 'admin' ? 'Administrador' : 'Mentor' ?></small></span>
      </div>
    </header>
<?php endif; ?>
    <main id="contenido" class="content">
<?php foreach (take_flashes() as $f): ?>
      <div class="alert alert--<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['msg']) ?></div>
<?php endforeach;
}

function admin_footer(array $scripts = []): void
{
    ?>
    </main>
  </div>
  <script src="assets/admin.js" defer></script>
<?php foreach ($scripts as $s): ?>
  <script src="<?= e($s) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
