<?php
/** Portal de participantes: acceso con correo + código y carga de documentos por entregable. */
require __DIR__ . '/../app/bootstrap.php';
start_session();

$loginError = '';
$correoIn = '';

/* ---------- Inicio de sesión ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'login') {
    csrf_check();
    $correoIn = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
    $codigo = codigo_normalizar((string) ($_POST['codigo'] ?? ''));

    if (too_many_attempts('portal', $correoIn)) {
        $loginError = 'Demasiados intentos fallidos. Espera 15 minutos e inténtalo de nuevo.';
    } else {
        $p = q('SELECT id, codigo_hash FROM participantes WHERE correo = ?', [$correoIn])->fetch();
        if ($p && $codigo !== '' && password_verify($codigo, $p['codigo_hash'])) {
            session_regenerate_id(true);
            $_SESSION['participante_id'] = (int) $p['id'];
            clear_attempts('portal', $correoIn);
            q('UPDATE participantes SET ultimo_acceso = NOW() WHERE id = ?', [$p['id']]);
            redirect('./');
        }
        register_attempt('portal', $correoIn);
        $loginError = 'El correo o el código de acceso no coinciden. Revisa que el código tenga el formato RL-XXXX-XXXX.';
    }
}

$yo = participante_actual();
if ($yo) track('visita', 'portal');

$title = 'Mis entregas · RioLab 4.0';
$bodyClass = 'page-portal';
$extraCss = ['css/portal.css'];
$navActive = 'portal';
$scripts = ['js/portal.js'];
require ROOT . '/app/views/public_header.php';
$flashes = take_flashes();
?>
  <main id="main" class="portal">
    <section class="portal-hero">
      <div class="container">
        <p class="kicker kicker--light">Mis entregas</p>
        <?php if ($yo): ?>
          <h1 class="portal-title">Hola, <?= e(explode(' ', $yo['nombre'])[0]) ?></h1>
          <p class="portal-lead">Aquí encuentras tus retos, los entregables de cada uno y el estado de revisión de tus documentos.</p>
          <form method="post" action="salir.php" class="portal-logout"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Cerrar sesión</button></form>
        <?php else: ?>
          <h1 class="portal-title">Carga los documentos de tu reto</h1>
          <p class="portal-lead">Ingresa con el correo que usaste al inscribirte y el código de acceso que recibiste.</p>
        <?php endif; ?>
      </div>
    </section>

    <div class="container portal-body">
      <?php foreach ($flashes as $f): ?>
        <div class="notice notice--<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>

<?php if (!$yo): ?>
      <!-- ACCESO -->
      <div class="portal-login">
        <form class="pcard" method="post" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="accion" value="login">
          <h2 class="pcard-title">Ingresar</h2>
          <?php if ($loginError): ?><div class="notice notice--error" role="alert"><?= e($loginError) ?></div><?php endif; ?>
          <div class="field">
            <label for="correo">Correo electrónico</label>
            <input id="correo" name="correo" type="email" autocomplete="email" required value="<?= e($correoIn) ?>">
          </div>
          <div class="field">
            <label for="codigo">Código de acceso</label>
            <input id="codigo" name="codigo" type="text" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" placeholder="RL-XXXX-XXXX" required class="code-input">
            <p class="hint">Lo recibiste al enviar el formulario «Quiero participar».</p>
          </div>
          <button class="btn btn-sun btn-lg btn-block" type="submit">Ingresar a Mis entregas</button>
        </form>
        <aside class="portal-help">
          <h2>¿Aún no tienes código?</h2>
          <p>El código se genera al inscribirte en un reto. Elige un reto y completa el formulario «Quiero participar».</p>
          <a class="btn btn-line" href="<?= url('index.php#retos') ?>">Ver retos disponibles</a>
          <h2>¿Perdiste tu código?</h2>
          <p>Escríbele al equipo RioLab o a tu mentor: pueden generarte un código nuevo desde el panel administrativo.</p>
        </aside>
      </div>

<?php else:
    $inscripciones = q('SELECT i.*, r.slug, r.codigo, r.titulo, r.icono, r.tag
                        FROM inscripciones i LEFT JOIN retos r ON r.id = i.reto_id
                        WHERE i.participante_id = ? ORDER BY i.reto_id IS NULL, i.created_at DESC', [$yo['id']])->fetchAll();
    $maxMb = (int) config('uploads.max_mb');
    $exts = implode(', ', array_map('strtoupper', config('uploads.extensions')));
?>
      <!-- PANEL -->
      <?php if (!$inscripciones): ?>
        <div class="pcard empty">
          <p>Aún no tienes inscripciones. <a href="<?= url('index.php#retos') ?>">Elige un reto</a> para empezar.</p>
        </div>
      <?php endif; ?>

      <?php foreach ($inscripciones as $ins):
          [$estadoTxt, $estadoTone] = ESTADOS_INSCRIPCION[$ins['estado']];
          if (!$ins['reto_id']): ?>
        <section class="pcard">
          <div class="ins-head">
            <div><span class="ins-code">Registro general</span><h2 class="pcard-title">Aún no has elegido un reto</h2></div>
          </div>
          <p>Nos dejaste tus datos sin elegir un reto. Cuando quieras, inscríbete en uno y aparecerá aquí con sus entregables.</p>
          <a class="btn btn-line" href="<?= url('index.php#retos') ?>">Explorar retos</a>
        </section>
      <?php continue; endif;

          $entregables = q('SELECT * FROM reto_entregables WHERE reto_id = ? ORDER BY orden, id', [$ins['reto_id']])->fetchAll();
          $docs = q('SELECT * FROM entregas WHERE inscripcion_id = ? ORDER BY created_at DESC', [$ins['id']])->fetchAll();
          $porEntregable = [];
          foreach ($docs as $d) $porEntregable[$d['entregable_id'] ?? 0][] = $d;
          $puedeSubir = $ins['estado'] !== 'rechazado';
          $hechos = count(array_filter($entregables, fn($en) => !empty($porEntregable[$en['id']])));
      ?>
        <section class="pcard ins" id="ins-<?= (int) $ins['id'] ?>">
          <div class="ins-head">
            <span class="ins-icon" aria-hidden="true"><?= icon_svg($ins['icono']) ?></span>
            <div class="ins-titles">
              <span class="ins-code"><?= e($ins['codigo'] . ' · ' . $ins['tag']) ?></span>
              <h2 class="pcard-title"><a href="<?= url('reto.php?r=' . urlencode($ins['slug'])) ?>"><?= e($ins['titulo']) ?></a></h2>
            </div>
            <span class="badge badge--<?= $estadoTone ?>">Inscripción: <?= e($estadoTxt) ?></span>
          </div>

          <div class="ins-progress">
            <div class="ins-progress-row"><span>Entregables con documentos</span><strong><?= $hechos ?> de <?= count($entregables) ?></strong></div>
            <span class="ficha-bar"><i style="width: <?= $entregables ? round($hechos / count($entregables) * 100) : 0 ?>%"></i></span>
          </div>

          <?php if (!$puedeSubir): ?>
            <p class="notice notice--warn">Tu inscripción en este reto no fue admitida, por eso no puedes cargar documentos. Si crees que es un error, contacta al equipo RioLab.</p>
          <?php endif; ?>

          <ol class="slots">
            <?php foreach (array_merge($entregables, [['id' => 0, 'titulo' => 'Otros documentos', 'descripcion' => 'Actas, fotografías, bitácoras u otros soportes del desarrollo del reto.', 'semana' => '']]) as $i => $en):
                $lista = $porEntregable[$en['id']] ?? []; ?>
              <li class="slot">
                <div class="slot-head">
                  <span class="slot-n"><?= $en['id'] ? 'E' . ($i + 1) : '+' ?></span>
                  <div>
                    <h3><?= e($en['titulo']) ?></h3>
                    <p><?= e($en['descripcion']) ?><?= $en['semana'] ? ' · <strong>' . e($en['semana']) . '</strong>' : '' ?></p>
                  </div>
                </div>

                <?php if ($lista): ?>
                  <ul class="files">
                    <?php foreach ($lista as $d): [$et, $etone] = ESTADOS_ENTREGA[$d['estado']]; ?>
                      <li class="file">
                        <div class="file-main">
                          <a href="archivo.php?id=<?= (int) $d['id'] ?>" class="file-name"><?= e($d['nombre_original']) ?></a>
                          <span class="file-meta"><?= e(fecha_corta($d['created_at'])) ?> · <?= e(bytes_legibles((int) $d['tamano'])) ?></span>
                          <?php if ($d['comentario']): ?><p class="file-note">Tu nota: <?= e($d['comentario']) ?></p><?php endif; ?>
                          <?php if ($d['retroalimentacion']): ?><p class="file-feedback"><strong>Retroalimentación del mentor:</strong> <?= nl2br(e($d['retroalimentacion'])) ?></p><?php endif; ?>
                        </div>
                        <span class="badge badge--<?= $etone ?>"><?= e($et) ?></span>
                        <?php if ($d['estado'] === 'recibido'): ?>
                          <form method="post" action="eliminar.php" class="file-del" data-confirm="¿Eliminar «<?= e($d['nombre_original']) ?>»?">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                            <button type="submit" class="icon-btn" aria-label="Eliminar <?= e($d['nombre_original']) ?>">
                              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>
                            </button>
                          </form>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>

                <?php if ($puedeSubir): ?>
                  <form class="upload" method="post" action="subir.php" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="inscripcion_id" value="<?= (int) $ins['id'] ?>">
                    <input type="hidden" name="entregable_id" value="<?= (int) $en['id'] ?>">
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?= $maxMb * 1048576 ?>">
                    <label class="drop">
                      <input type="file" name="archivo" required accept="<?= e('.' . implode(',.', config('uploads.extensions'))) ?>">
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m-5 5 5-5 5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
                      <span class="drop-text"><strong>Elige un archivo</strong> o arrástralo aquí</span>
                      <span class="drop-hint"><?= e($exts) ?> · máx. <?= $maxMb ?> MB</span>
                    </label>
                    <div class="upload-row">
                      <label class="sr-only" for="c-<?= (int) $ins['id'] ?>-<?= (int) $en['id'] ?>">Nota para el mentor (opcional)</label>
                      <input id="c-<?= (int) $ins['id'] ?>-<?= (int) $en['id'] ?>" type="text" name="comentario" maxlength="500" placeholder="Nota para el mentor (opcional)">
                      <button type="submit" class="btn btn-sun"><span class="btn-label">Cargar</span><span class="spinner" aria-hidden="true"></span></button>
                    </div>
                  </form>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
        </section>
      <?php endforeach; ?>
<?php endif; ?>
    </div>
  </main>
<?php require ROOT . '/app/views/public_footer.php'; ?>
